<?php

$message = "";
$isError = false;
$debugLogs = [];
$previewData = null;
$xmlPayload = '';

function log_debug(array &$debugLogs, string $message, array $context = []): void
{
    $entry = $message;
    if (!empty($context)) {
        $entry .= " | " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $debugLogs[] = $entry;
    error_log($entry);
}

function load_xml_file(string $xmlFile, array &$debugLogs): SimpleXMLElement
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xmlFile);
    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = trim($error->message) . " (line: {$error->line})";
        }
        libxml_clear_errors();
        log_debug($debugLogs, 'XMLの解析に失敗しました。', ['errors' => $errorMessages]);
        throw new Exception('XMLの解析に失敗しました。XMLファイルの形式を確認してください。');
    }

    log_debug($debugLogs, 'XMLの読み込みに成功しました。');
    return $xml;
}

function build_csv_rows_from_xml(SimpleXMLElement $xml, array &$debugLogs): array
{
    $rows = [];

    $lawTitle = (string)$xml->LawBody->LawTitle;
    $lawNum = (string)$xml->attributes()->LawNum;
    log_debug($debugLogs, '法令情報を取得しました。', ['lawTitle' => $lawTitle, 'lawNum' => $lawNum]);

    foreach ($xml->LawBody->MainProvision->xpath('.//Article') as $article) {
        $chapterTitle = '';
        $sectionTitle = '';

        $chapter = $article->xpath('ancestor::Chapter/ChapterTitle');
        if ($chapter) {
            $chapterTitle = (string)$chapter[0];
        }

        $section = $article->xpath('ancestor::Section/SectionTitle');
        if ($section) {
            $sectionTitle = (string)$section[0];
        }

        $articleTitle = (string)$article->ArticleTitle;

        $paragraphs = [];
        foreach ($article->Paragraph as $para) {
            $sentences = [];
            foreach ($para->xpath('.//Sentence') as $sentence) {
                $sentences[] = (string)$sentence;
            }
            $paragraphText = trim(implode('', $sentences));
            if ($paragraphText !== '') {
                $paragraphs[] = $paragraphText;
            }
        }

        if (empty($paragraphs)) {
            $paragraphs[] = '';
        }

        foreach ($paragraphs as $index => $para) {
            $kou = ($index === 0 && !preg_match('/^[０-９2-9]/u', $para)) ? '' : ($index + 1);

            $rows[] = [
                'law_name' => $lawTitle,
                'chapter' => $chapterTitle,
                'section' => $sectionTitle,
                'article' => $articleTitle,
                'article_heading' => '',
                'paragraph' => $kou,
                'content' => $para,
            ];
        }
    }

    log_debug($debugLogs, '条文をCSV行に変換しました。', ['rowCount' => count($rows)]);

    return $rows;
}
function load_xml_string(string $xmlContent, array &$debugLogs): SimpleXMLElement
{
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = trim($error->message) . " (line: {$error->line})";
        }
        libxml_clear_errors();
        log_debug($debugLogs, 'XMLの解析に失敗しました。', ['errors' => $errorMessages]);
        throw new Exception('XMLの解析に失敗しました。XMLファイルの形式を確認してください。');
    }
log_debug($debugLogs, 'XMLの読み込みに成功しました。');
    return $xml;
}

function has_column(PDO $pdo, string $table, string $column, array &$debugLogs): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        $exists = (bool)$stmt->fetch();
        log_debug($debugLogs, 'カラムの存在確認をしました。', ['table' => $table, 'column' => $column, 'exists' => $exists]);
        return $exists;
    } catch (Exception $e) {
        log_debug($debugLogs, 'カラム確認に失敗しました。', ['table' => $table, 'column' => $column, 'error' => $e->getMessage()]);
        return false;
    }
}

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'preview';

    if ($action === 'confirm_register') {
        $xmlPayload = $_POST['xml_payload'] ?? '';
        if ($xmlPayload === '') {
            $isError = true;
            $message = 'XMLデータが見つかりませんでした。もう一度アップロードしてください。';
            log_debug($debugLogs, 'XMLペイロードが空です。');
        } else {
            try {
                require_once __DIR__ . '/../includes/db_config.php';

                $xmlContent = base64_decode($xmlPayload, true);
                if ($xmlContent === false) {
                    throw new Exception('XMLデータのデコードに失敗しました。');
                }

                $xml = load_xml_string($xmlContent, $debugLogs);
 $lawTitle = (string)$xml->LawBody->LawTitle;
                $lawNum = (string)$xml->attributes()->LawNum;
                log_debug($debugLogs, 'DB登録処理を開始しました。', ['lawTitle' => $lawTitle, 'lawNum' => $lawNum]);

                $updatedDate = trim($_POST['updated_date'] ?? '');
                $effectiveDate = trim($_POST['effective_date'] ?? '');
                $tags = trim($_POST['tags'] ?? '');
                $sourceUrl = trim($_POST['source_url'] ?? '');
                $dropboxUrl = trim($_POST['dropbox_url'] ?? '');

                $stmtCheck = $pdo->prepare("SELECT id FROM laws WHERE law_num = ? AND is_latest = 1 LIMIT 1");
                $stmtCheck->execute([$lawNum]);
                $oldVersion = $stmtCheck->fetch();

                $pdo->beginTransaction();

                $parentId = null;
                if ($oldVersion) {
                    $parentId = $oldVersion['id'];
                    $updateOld = $pdo->prepare("UPDATE laws SET is_latest = 0 WHERE id = ?");
                    $updateOld->execute([$parentId]);
                }
            

           $versionLabel = date('Ymd') . " アップロード分";

                $stmtLaw = $pdo->prepare("INSERT INTO laws (law_title, law_num, version_label, is_latest, parent_id, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
                $stmtLaw->execute([$lawTitle, $lawNum, $versionLabel, $parentId]);
                $lawId = $pdo->lastInsertId();

                $optionalFields = [
                    'updated_date' => $updatedDate,
                    'effective_date' => $effectiveDate,
                    'tags' => $tags,
                    'source_url' => $sourceUrl,
                    'dropbox_url' => $dropboxUrl,
                ];
                $updateColumns = [];
                $updateValues = [];
                $updatedColumnNames = [];
                foreach ($optionalFields as $column => $value) {
                    if (has_column($pdo, 'laws', $column, $debugLogs)) {
                        $updateColumns[] = "{$column} = ?";
                        $updateValues[] = $value;
                        $updatedColumnNames[] = $column;
                    }
                }
                if (!empty($updateColumns)) {
                    $updateValues[] = $lawId;
                    $stmtOptional = $pdo->prepare("UPDATE laws SET " . implode(', ', $updateColumns) . " WHERE id = ?");
                    $stmtOptional->execute($updateValues);
                    log_debug($debugLogs, '任意項目を更新しました。', ['columns' => $updatedColumnNames]);
                } else {
                    log_debug($debugLogs, '任意項目の更新をスキップしました。', ['reason' => '該当カラムが存在しません。']);
                }

$stmtContent = $pdo->prepare("INSERT INTO law_contents (law_id, chapter_title, article_title, content_text) VALUES (?, ?, ?, ?)");

                foreach ($xml->LawBody->MainProvision->xpath('.//Article') as $article) {
                    $chapterTitle = "";
                    $chapter = $article->xpath('ancestor::Chapter/ChapterTitle');
                    if ($chapter) {
                        $chapterTitle = (string)$chapter[0];
                    } else {
                        $section = $article->xpath('ancestor::Section/SectionTitle');
                        if ($section) {
                            $chapterTitle = (string)$section[0];
                        }
                    }
 $articleTitle = (string)$article->ArticleTitle;

                    $paragraphs = [];
                    foreach ($article->Paragraph as $para) {
                        $paragraphs[] = (string)$para->ParagraphSentence->Sentence;
            }
                    $contentText = implode("\n", $paragraphs);

                    $stmtContent->execute([$lawId, $chapterTitle, $articleTitle, $contentText]);
                }

                $pdo->commit();
                $message = "「" . htmlspecialchars($lawTitle) . "」を最新バージョンとして登録しました。";
                if ($parentId) {
                    $message .= "（旧バージョンからの履歴を継承しました）";
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $isError = true;
                $message = "エラーが発生しました: " . $e->getMessage();
                log_debug($debugLogs, '例外が発生しました。', ['error' => $e->getMessage()]);
            }
        }
    } elseif ($action === 'download_csv') {
        if (isset($_FILES['xml_file'])) {
            $xmlFile = $_FILES['xml_file']['tmp_name'];
            if (is_uploaded_file($xmlFile)) {
                try {
                    $xml = load_xml_file($xmlFile, $debugLogs);

                    $lawTitle = (string)$xml->LawBody->LawTitle;
                    $rows = build_csv_rows_from_xml($xml, $debugLogs);
                    $filename = $lawTitle . "_structured_" . date('Ymd') . ".csv";

                    header('Content-Type: text/csv; charset=shift_jis');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');

                    $output = fopen('php://output', 'w');

                    $head = ['法規名', '章', '節', '条', '条（節）見出し', '項', '内容'];
                    mb_convert_variables('SJIS-win', 'UTF-8', $head);
                    fputcsv($output, $head);

                    foreach ($rows as $row) {
                        $line = [
                            $row['law_name'],
                            $row['chapter'],
                            $row['section'],
                            $row['article'],
                            $row['article_heading'],
                            $row['paragraph'],
                            $row['content'],
                        ];

                        mb_convert_variables('SJIS-win', 'UTF-8', $line);
                        fputcsv($output, $line);
                    }

            fclose($output);
                    exit;
                } catch (Exception $e) {
                    $isError = true;
                    $message = "エラーが発生しました: " . $e->getMessage();
                    log_debug($debugLogs, 'CSV出力で例外が発生しました。', ['error' => $e->getMessage()]);
                }
            } else {
                $isError = true;
                $message = "アップロードに失敗しました。ファイルを再選択してください。";
                log_debug($debugLogs, 'ファイルが正しくアップロードされませんでした。');
            }
        }
    } elseif ($action === 'preview') {
        if (isset($_FILES['xml_file'])) {
            $xmlFile = $_FILES['xml_file']['tmp_name'];
            if (is_uploaded_file($xmlFile)) {
                try {
                    $xml = load_xml_file($xmlFile, $debugLogs);
                    $lawTitle = (string)$xml->LawBody->LawTitle;
                    $lawNum = (string)$xml->attributes()->LawNum;
                    $xmlPayload = base64_encode(file_get_contents($xmlFile));

                    $previewData = [
                        'id' => '自動採番',
                        'law_title' => $lawTitle,
                        'law_num' => $lawNum,
                        'created_at' => date('Y-m-d H:i'),
                        'updated_date' => '',
                        'effective_date' => '',
                        'tags' => '',
                        'source_url' => '',
                        'dropbox_url' => '',
                    ];
$message = 'プレビューを表示しました。内容を確認して登録してください。';
                    log_debug($debugLogs, 'プレビュー用データを作成しました。', ['lawTitle' => $lawTitle, 'lawNum' => $lawNum]);
                } catch (Exception $e) {
                    $isError = true;
                    $message = "エラーが発生しました: " . $e->getMessage();
                    log_debug($debugLogs, 'プレビュー生成で例外が発生しました。', ['error' => $e->getMessage()]);
                }
            } else {
                $isError = true;
                $message = "アップロードに失敗しました。ファイルを再選択してください。";
                log_debug($debugLogs, 'ファイルが正しくアップロードされませんでした。');
            }
        }
                    
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>法令XML データベース登録</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
        .main-content { padding: 40px; margin-left: 250px; transition: margin-left 0.3s; }
        body.menu-closed .main-content { margin-left: 0; }

        .box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; }
        h1 { color: #0056b3; margin-top: 0; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 4px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        input[type="file"] { margin: 20px 0; display: block; }
        button { background: #0056b3; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 8px; }
        button:hover { background: #004494; }
        .preview-card { margin-top: 24px; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fafafa; }
        .preview-grid { display: grid; grid-template-columns: 160px 1fr; gap: 10px 16px; align-items: center; }
        .preview-grid label { font-weight: bold; color: #333; }
        .preview-grid input[type="text"],
        .preview-grid input[type="month"],
        .preview-grid input[type="date"] {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .preview-grid input[disabled] { background: #eee; color: #666; }
        .preview-actions { margin-top: 16px; }
        .note { color: #555; font-size: 13px; margin-top: 12px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="box">
        <h1>XML履歴登録 / CSVダウンロード</h1>
        <p>e-Govからダウンロードした法令XMLを選択してください。<br>
        既に同じ法令番号がある場合は、自動的に履歴として紐付けます。</p>

        <?php if ($message): ?>
            <div class="msg <?php echo $isError ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="xml_file" accept=".xml" required>
            <button type="submit" name="action" value="preview">内容を確認して登録</button>
            <button type="submit" name="action" value="download_csv">CSVをダウンロード（DB登録しない）</button>
            <div class="note">CSVダウンロードを選ぶと、SQLには登録せずにCSVのみ生成します。</div>
        </form>
        <?php if ($previewData): ?>
            <div class="preview-card">
                <h2>登録内容の確認</h2>
                <p class="note">IDなどの自動採番項目は編集できません。</p>
                <form action="" method="post">
                    <input type="hidden" name="action" value="confirm_register">
                    <input type="hidden" name="xml_payload" value="<?php echo htmlspecialchars($xmlPayload); ?>">

                    <div class="preview-grid">
                        <label>ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($previewData['id']); ?>" disabled>

                        <label>法令名</label>
                        <input type="text" value="<?php echo htmlspecialchars($previewData['law_title']); ?>" disabled>

                        <label>法令番号</label>
                        <input type="text" value="<?php echo htmlspecialchars($previewData['law_num']); ?>" disabled>

                        <label>登録日時</label>
                        <input type="text" value="<?php echo htmlspecialchars($previewData['created_at']); ?>" disabled>

                        <label>最終更新日</label>
                        <input type="date" name="updated_date" value="<?php echo htmlspecialchars($previewData['updated_date']); ?>">

                        <label>施行年月</label>
                        <input type="month" name="effective_date" value="<?php echo htmlspecialchars($previewData['effective_date']); ?>">

                        <label>タグ</label>
                        <input type="text" name="tags" value="<?php echo htmlspecialchars($previewData['tags']); ?>" placeholder="例: 賃金, 残業">

                        <label>参照URL</label>
                        <input type="text" name="source_url" value="<?php echo htmlspecialchars($previewData['source_url']); ?>" placeholder="e-Govなど">

                        <label>Dropbox URL</label>
                        <input type="text" name="dropbox_url" value="<?php echo htmlspecialchars($previewData['dropbox_url']); ?>" placeholder="共有リンク">
                    </div>

                    <div class="preview-actions">
                        <button type="submit">登録を確定する</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($debugLogs)): ?>
    <script>
        console.group('XML Upload Debug');
        <?php foreach ($debugLogs as $log): ?>
        console.log(<?php echo json_encode($log, JSON_UNESCAPED_UNICODE); ?>);
        <?php endforeach; ?>
        <?php if ($isError): ?>
        console.error('XML処理でエラーが発生しました。');
        <?php endif; ?>
        console.groupEnd();
    </script>
<?php endif; ?>

</body>
</html>