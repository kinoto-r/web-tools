<?php

$message = "";
$isError = false;
$debugLogs = [];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $action = $_POST['action'] ?? 'save_db';
    $xmlFile = $_FILES['xml_file']['tmp_name'];

    if (is_uploaded_file($xmlFile)) {
        try {
            if ($action === 'download_csv') {
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
            }

            require_once __DIR__ . '/../includes/db_config.php';

            $xml = load_xml_file($xmlFile, $debugLogs);

            // XMLから法令名と法令番号を取得
            $lawTitle = (string)$xml->LawBody->LawTitle;
            $lawNum = (string)$xml->attributes()->LawNum;
            log_debug($debugLogs, 'DB登録処理を開始しました。', ['lawTitle' => $lawTitle, 'lawNum' => $lawNum]);

            // 1. 既存の同じ法令番号の「最新版」を探す
            $stmtCheck = $pdo->prepare("SELECT id FROM laws WHERE law_num = ? AND is_latest = 1 LIMIT 1");
            $stmtCheck->execute([$lawNum]);
            $oldVersion = $stmtCheck->fetch();

            $pdo->beginTransaction();

            // 2. もし既存データがあれば、その最新フラグをOFFにする
            $parentId = null;
            if ($oldVersion) {
                $parentId = $oldVersion['id'];
                $updateOld = $pdo->prepare("UPDATE laws SET is_latest = 0 WHERE id = ?");
                $updateOld->execute([$parentId]);
            }

            // 3. 新しいバージョンを登録
            // version_label にはアップロード日時などを自動付与（後で編集可能）
            $versionLabel = date('Ymd') . " アップロード分";

            $stmtLaw = $pdo->prepare("INSERT INTO laws (law_title, law_num, version_label, is_latest, parent_id, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
            $stmtLaw->execute([$lawTitle, $lawNum, $versionLabel, $parentId]);
            $lawId = $pdo->lastInsertId();

            // 4. 条文（law_contents）の登録
            $stmtContent = $pdo->prepare("INSERT INTO law_contents (law_id, chapter_title, article_title, content_text) VALUES (?, ?, ?, ?)");

            // 条文データの解析（e-Gov XML構造: LawBody -> MainProvision）
            foreach ($xml->LawBody->MainProvision->xpath('.//Article') as $article) {
                // 章タイトルの取得（親ノードを遡ってChapterTitleを探す）
                $chapterTitle = "";
                $chapter = $article->xpath('ancestor::Chapter/ChapterTitle');
                if ($chapter) {
                    $chapterTitle = (string)$chapter[0];
                } else {
                    // 節の場合も考慮
                    $section = $article->xpath('ancestor::Section/SectionTitle');
                    if ($section) {
                        $chapterTitle = (string)$section[0];
                    }
                }

                $articleTitle = (string)$article->ArticleTitle;

                // 本文（Paragraphが複数ある場合を結合）
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
    } else {
        $isError = true;
        $message = "アップロードに失敗しました。ファイルを再選択してください。";
        log_debug($debugLogs, 'ファイルが正しくアップロードされませんでした。');
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
            <button type="submit" name="action" value="save_db">データベースに最新版として登録</button>
            <button type="submit" name="action" value="download_csv">CSVをダウンロード（DB登録しない）</button>
            <div class="note">CSVダウンロードを選ぶと、SQLには登録せずにCSVのみ生成します。</div>
        </form>
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
