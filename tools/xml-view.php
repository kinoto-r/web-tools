<?php
require_once __DIR__ . '/../includes/db_config.php';

// URLの「?id=123」からIDを取得
$lawId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // 1. 法令の基本情報を取得
    $stmt = $pdo->prepare("SELECT * FROM laws WHERE id = ?");
    $stmt->execute([$lawId]);
    $law = $stmt->fetch();

    if (!$law) {
        die("指定された法令が見つかりません。");
    }

    // 2. その法令に紐づく全条文を取得
    $stmtContent = $pdo->prepare("SELECT * FROM law_contents WHERE law_id = ? ORDER BY id ASC");
    $stmtContent->execute([$lawId]);
    $contents = $stmtContent->fetchAll();

} catch (Exception $e) {
    die("エラー: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($law['law_title']); ?></title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; background: #f9f9f9; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .nav { margin-bottom: 20px; }
        h1 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        .chapter { background: #eee; padding: 5px 10px; margin-top: 30px; font-weight: bold; }
        .article { margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .art-title { font-weight: bold; color: #0056b3; }
        .content { margin-top: 5px; white-space: pre-wrap; }
        .main-content {
        padding: 40px;
        margin-left: 250px; /* ここが重要！ */
        transition: margin-left 0.3s;
    }
    body.menu-closed .main-content {
        margin-left: 0;
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
    <div class="nav"><a href="dashboard.php">← サマリーボードに戻る</a></div>
    
    <h1><?php echo htmlspecialchars($law['law_title']); ?></h1>
    <p>法令番号：<?php echo htmlspecialchars($law['law_num']); ?></p>

    <?php 
    $currentChapter = "";
    foreach ($contents as $item): 
        // 章が変わった時だけ章タイトルを表示
        if ($currentChapter !== $item['chapter_title']):
            $currentChapter = $item['chapter_title'];
            echo "<div class='chapter'>" . htmlspecialchars($currentChapter) . "</div>";
        endif;
    ?>
        <div class="article">
            <span class="art-title"><?php echo htmlspecialchars($item['article_title']); ?></span>
            <div class="content"><?php echo htmlspecialchars($item['content_text']); ?></div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>