<?php
// 「__DIR__」は現在のファイルがある場所（toolフォルダ）
// 「/..」は一つ上の階層に戻るという意味
require_once __DIR__ . '/../includes/db_config.php';

$message = "";

// フォームから送信された時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $xml = simplexml_load_file($_FILES['xml_file']['tmp_name']);
    
    if ($xml) {
        try {
            // 1. 法令の基本情報をlawsテーブルに保存
            $stmt = $pdo->prepare("INSERT INTO laws (law_title, law_num) VALUES (?, ?)");
            $stmt->execute([ (string)$xml->LawBody->LawTitle, (string)$xml->LawNum ]);
            $lawId = $pdo->lastInsertId(); // 今作った法律のIDを取得

            // 2. 条文を解析して保存（本則のみの簡易版）
            if (isset($xml->LawBody->MainProvision)) {
                $stmtContent = $pdo->prepare("INSERT INTO law_contents (law_id, chapter_title, article_title, content_text) VALUES (?, ?, ?, ?)");
                
                foreach ($xml->LawBody->MainProvision->Chapter as $chapter) {
                    $chapTitle = (string)$chapter->ChapterTitle;
                    foreach ($chapter->Article as $article) {
                        $artTitle = (string)$article->ArticleTitle;
                        $text = (string)$article->Paragraph->ParagraphSentence->Sentence;
                        
                        $stmtContent->execute([$lawId, $chapTitle, $artTitle, $text]);
                    }
                }
            }
            $message = "データベースに正常に保存されました！";
        } catch (Exception $e) {
            $message = "保存エラー: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>法令DB登録ツール</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f9f9f9; }
        .box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        <style>
    body { font-family: sans-serif; background: #f9f9f9; margin: 0; }
    
    .main-content {
        padding: 40px;
        margin-left: 250px; /* ここが重要！ */
        transition: margin-left 0.3s;
    }
    
    body.menu-closed .main-content {
        margin-left: 0;
    }

    .box { 
        background: white; 
        padding: 30px; 
        border-radius: 8px; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        max-width: 600px; /* 登録画面は横長になりすぎない方が綺麗です */
    }
</style>
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <div class="box">
        <h1>法令XML データベース登録</h1>
        <?php if($message): ?> <p class="success"><?php echo $message; ?></p> <?php endif; ?>
        
        <form action="" method="post" enctype="multipart/form-data">
            <input type="file" name="xml_file" required>
            <button type="submit">XMLを読み込んでDBに保存</button>
        </form>
        </div>
    </div>
</body>
</html>