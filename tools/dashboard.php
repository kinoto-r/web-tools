<?php
require_once __DIR__ . '/../includes/db_config.php';

// --- 追加：URLが送信された時の保存処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_url'])) {
    $lawId = (int)$_POST['law_id'];
    $url = $_POST['dropbox_url'];
    
    $stmt = $pdo->prepare("UPDATE laws SET dropbox_url = ? WHERE id = ?");
    $stmt->execute([$url, $lawId]);
    $message = "URLを更新しました。";
}
// ------------------------------------
try {
    // 登録されている法令の一覧と、それぞれの条文（law_contents）の数を取得するSQL
    $sql = "SELECT 
                l.id, 
                l.law_title, 
                l.law_num, 
                l.created_at, 
                COUNT(c.id) as total_articles 
            FROM laws l
            LEFT JOIN law_contents c ON l.id = c.law_id
            GROUP BY l.id
            ORDER BY l.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $laws = $stmt->fetchAll();
} catch (Exception $e) {
    die("データ取得エラー: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>法令サマリーボード</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { border-left: 6px solid #0056b3; padding-left: 15px; margin-bottom: 30px; }
        
        /* カードスタイルの統計表示 */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #0056b3; }
        
        /* テーブルスタイル */
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #0056b3; color: white; font-weight: normal; }
        tr:hover { background: #f1f8ff; }
        
        .btn { display: inline-block; padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; transition: 0.3s; }
        .btn-view { background: #e7f1ff; color: #0056b3; border: 1px solid #0056b3; }
        .btn-view:hover { background: #0056b3; color: white; }
        .nav-link { margin-bottom: 20px; display: block; color: #666; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
    <a href="index.php" class="nav-link">← 新しいXMLを登録する</a>
    <h1>法令サマリーボード</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <div>登録法令数</div>
            <div class="stat-number"><?php echo count($laws); ?></div>
        </div>
        <div class="stat-card">
            <div>総蓄積条文数</div>
            <div class="stat-number">
                <?php 
                    $total = 0;
                    foreach($laws as $l) $total += $l['total_articles'];
                    echo $total;
                ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>登録日</th>
                <th>法令名</th>
                <th>法令番号</th>
                <th>条文数</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($laws as $law): ?>
            <tr>
                <td><?php echo date('Y/m/d', strtotime($law['created_at'])); ?></td>
                <td><strong><?php echo htmlspecialchars($law['law_title']); ?></strong></td>
                <td><?php echo htmlspecialchars($law['law_num']); ?></td>
                <td><?php echo $law['total_articles']; ?> 件</td>
                <td>
                    <a href="xml-view.php?id=<?php echo $law['id']; ?>" class="btn btn-view">詳細表示</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($laws)): ?>
            <tr>
                <td colspan="5" style="text-align:center;">まだ登録されている法令はありません。</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>