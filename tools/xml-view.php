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
        body { font-family: "Helvetica Neue", Arial, sans-serif; line-height: 1.6; background: #f4f7f6; margin: 0; padding: 0; }
        
        /* サイドバー対応のメインコンテンツ枠 */
        .main-content {
            padding: 40px;
            margin-left: 250px;
            transition: margin-left 0.3s;
            max-width: 1000px; /* 読みやすい幅に制限 */
        }
        body.menu-closed .main-content { margin-left: 0; }

        /* 法令情報のボックス */
        .law-header-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        h1 { border-bottom: 3px solid #0056b3; padding-bottom: 10px; margin-top: 0; color: #1a1a1a; }
        
        .meta-info { font-size: 0.9em; color: #666; margin-bottom: 20px; }
        .tag { background: #e2e8f0; padding: 2px 10px; border-radius: 12px; font-size: 0.8em; margin-right: 5px; color: #4a5568; }

        /* 条文のスタイル */
        .chapter { background: #f8f9fa; padding: 10px 15px; margin-top: 40px; font-weight: bold; border-left: 5px solid #0056b3; }
        .article { margin-top: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .art-title { font-weight: bold; color: #0056b3; display: block; margin-bottom: 5px; }
        .content { margin-left: 10px; white-space: pre-wrap; color: #333; }

        /* ボタン類 */
        .btn-group { display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .btn-dropbox { background: #0061ff; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .btn-source { background: #f1f8ff; color: #0056b3; padding: 10px 20px; border-radius: 6px; text-decoration: none; border: 1px solid #0056b3; }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="law-header-card">
        <h1><?php echo htmlspecialchars($law['law_title']); ?></h1>
        
        <div class="meta-info">
            <div>法令番号：<?php echo htmlspecialchars($law['law_num']); ?></div>
            <div>最終更新日：<?php echo htmlspecialchars($law['updated_date'] ?: '未設定'); ?></div>
            
            <?php if (!empty($law['tags'])): ?>
                <div style="margin-top: 10px;">
                    <?php 
                    $tags = explode(',', $law['tags']);
                    foreach ($tags as $tag) {
                        echo '<span class="tag">#' . htmlspecialchars(trim($tag)) . '</span>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="btn-group">
            <?php if (!empty($law['dropbox_url'])): ?>
                <a href="<?php echo htmlspecialchars($law['dropbox_url']); ?>" target="_blank" class="btn-dropbox">
                   📄 原本資料 (Dropbox)
                </a>
            <?php endif; ?>
<div class="btn-group">
    <?php if (!empty($law['dropbox_url'])): ?>
        <a href="<?php echo htmlspecialchars($law['dropbox_url']); ?>" target="_blank" class="btn-dropbox">📄 原本資料 (Dropbox)</a>
    <?php endif; ?>

    <a href="csv-export.php?id=<?php echo $law['id']; ?>" class="btn-csv" style="background: #28a745; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; border: none; display: inline-block;">
        📊 Excel形式(CSV)で書き出し
    </a>
</div>
            <?php if (!empty($law['source_url'])): ?>
                <a href="<?php echo htmlspecialchars($law['source_url']); ?>" target="_blank" class="btn-source">
                   🌐 出典元サイト
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <?php 
        $currentChapter = "";
        foreach ($contents as $item): 
            if ($currentChapter !== $item['chapter_title'] && !empty($item['chapter_title'])):
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
</div>

</body>
</html>