<?php
require_once __DIR__ . '/../includes/db_config.php';

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$results = [];

if ($keyword !== '') {
    try {
        // キーワードを含む条文を、法律名と一緒に検索するSQL（LIKE演算子を使用）
        $sql = "SELECT 
                    l.law_title, 
                    c.law_id, 
                    c.chapter_title, 
                    c.article_title, 
                    c.content_text 
                FROM law_contents c
                JOIN laws l ON c.law_id = l.id
                WHERE c.content_text LIKE :keyword 
                   OR c.article_title LIKE :keyword
                ORDER BY l.id ASC, c.id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        $results = $stmt->fetchAll();
    } catch (Exception $e) {
        die("検索エラー: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>法令単語検索</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .search-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        input[type="text"] { width: 70%; padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; font-size: 16px; background: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        .result-item { background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; border-left: 5px solid #0056b3; }
        .law-tag { font-size: 0.8em; color: #666; background: #eee; padding: 2px 8px; border-radius: 10px; }
        .res-title { font-weight: bold; margin: 10px 0; display: block; color: #0056b3; }
        .res-text { font-size: 0.95em; color: #333; line-height: 1.6; }
        .highlight { background: yellow; font-weight: bold; }
        .nav-link { margin-bottom: 20px; display: block; color: #666; text-decoration: none; }
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
    <h1>法令単語検索</h1>

    <div class="search-box">
        <form action="" method="get">
            <input type="text" name="keyword" placeholder="検索したい単語（例：解雇、賃金、休憩）" value="<?php echo htmlspecialchars($keyword); ?>">
            <button type="submit">検索</button>
        </form>
    </div>

    <?php if ($keyword !== ''): ?>
        <p><?php echo count($results); ?> 件見つかりました。</p>
        <?php foreach ($results as $row): ?>
            <div class="result-item">
                <span class="law-tag"><?php echo htmlspecialchars($row['law_title']); ?></span>
                <a href="xml-view.php?id=<?php echo $row['law_id']; ?>" class="res-title">
                    <?php echo htmlspecialchars($row['chapter_title'] . " " . $row['article_title']); ?>
                </a>
                <div class="res-text">
                    <?php 
                        // 検索単語をハイライト表示
                        $text = htmlspecialchars($row['content_text']);
                        echo str_replace(htmlspecialchars($keyword), "<span class='highlight'>".htmlspecialchars($keyword)."</span>", $text);
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>