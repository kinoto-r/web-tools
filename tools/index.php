<?php
require_once __DIR__ . '/../includes/db_config.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $xmlFile = $_FILES['xml_file']['tmp_name'];

    if (is_uploaded_file($xmlFile)) {
        try {
            $xml = simplexml_load_file($xmlFile);
            
            // XMLから法令名と法令番号を取得
            $lawTitle = (string)$xml->LawBody->LawTitle;
            $lawNum = (string)$xml->attributes()->LawNum;
            
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
                    if ($section) $chapterTitle = (string)$section[0];
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
            if ($parentId) $message .= "（旧バージョンからの履歴を継承しました）";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "エラーが発生しました: " . $e->getMessage();
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
        button { background: #0056b3; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #004494; }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="box">
        <h1>XML履歴登録</h1>
        <p>e-Govからダウンロードした法令XMLを選択してください。<br>
        既に同じ法令番号がある場合は、自動的に履歴として紐付けます。</p>

        <?php if ($message): ?>
            <div class="msg <?php echo (strpos($message, 'エラー') !== false) ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="xml_file" accept=".xml" required>
            <button type="submit">データベースに最新版として登録</button>
        </form>
    </div>
</div>

</body>
</html>