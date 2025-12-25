<?php
require_once __DIR__ . '/../includes/db_config.php';

$message = "";

// --- 一括保存処理（ここを最新版に統一） ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_law_info'])) {
    $lawId = (int)$_POST['law_id'];
    $dropbox = $_POST['dropbox_url'];
    $source = $_POST['source_url'];
    $tags = $_POST['tags'];
    $u_date = $_POST['updated_date'];
    
    try {
        $stmt = $pdo->prepare("UPDATE laws SET dropbox_url = ?, source_url = ?, tags = ?, updated_date = ? WHERE id = ?");
        $stmt->execute([$dropbox, $source, $tags, $u_date, $lawId]);
        $message = "法律情報を更新しました！";
    } catch (Exception $e) {
        $message = "更新エラー: " . $e->getMessage();
    }
}

try {
    // SQLに updated_date, source_url, tags を追加
    $sql = "SELECT 
                l.id, 
                l.law_title, 
                l.law_num, 
                l.created_at, 
                l.updated_date,
                l.source_url,
                l.tags,
                l.dropbox_url,
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
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .main-content { padding: 40px; margin-left: 250px; transition: margin-left 0.3s; }
        body.menu-closed .main-content { margin-left: 0; }
        
        h1 { border-left: 6px solid #0056b3; padding-left: 15px; margin-bottom: 30px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #0056b3; }
        
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background: #0056b3; color: white; font-weight: normal; }
        tr:hover { background: #f1f8ff; }
        
        .nav-link { margin-bottom: 20px; display: block; color: #666; text-decoration: none; }
        .success-msg { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <a href="index.php" class="nav-link">← 新しいXMLを登録する</a>
    <h1>法令サマリーボード</h1>

    <?php if($message): ?><div class="success-msg"><?php echo $message; ?></div><?php endif; ?>

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
                <th>ID</th>
                <th>法令名 / 番号</th>
                <th>詳細管理</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($laws as $law): ?>
            <tr>
                <td><?php echo $law['id']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($law['law_title']); ?></strong><br>
                    <small style="color: #666;"><?php echo htmlspecialchars($law['law_num']); ?></small><br>
                    <small>登録: <?php echo date('Y/m/d', strtotime($law['created_at'])); ?></small>
                </td>
                <td>
                    <form action="" method="post" style="font-size: 11px; display: grid; gap: 4px; min-width: 250px; background: #fdfdfd; padding: 8px; border: 1px solid #eee; border-radius: 4px;">
                        <input type="hidden" name="law_id" value="<?php echo $law['id']; ?>">
                        
                        <label>📅 更新日</label>
                        <input type="date" name="updated_date" value="<?php echo htmlspecialchars($law['updated_date'] ?? ''); ?>">
                        
                        <label>🏷️ キーワード (カンマ区切り)</label>
                        <input type="text" name="tags" value="<?php echo htmlspecialchars($law['tags'] ?? ''); ?>" placeholder="例: 賃金, 残業">
                        
                        <label>🌐 出典元URL</label>
                        <input type="text" name="source_url" value="<?php echo htmlspecialchars($law['source_url'] ?? ''); ?>" placeholder="e-Govなど">
                        
                        <label>📄 Dropbox (PDF)</label>
                        <input type="text" name="dropbox_url" value="<?php echo htmlspecialchars($law['dropbox_url'] ?? ''); ?>" placeholder="共有リンク">
                        
                        <button type="submit" name="update_law_info" style="margin-top: 5px; background: #28a745; color: white; border: none; padding: 6px; cursor: pointer; border-radius: 3px; font-weight: bold;">一括保存</button>
                    </form>
                    
                    <?php if(!empty($law['dropbox_url'])): ?>
                        <div style="margin-top: 5px;"><a href="<?php echo htmlspecialchars($law['dropbox_url']); ?>" target="_blank" style="font-size: 11px; color: #007bff;">🔗 Dropbox資料を開く</a></div>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="xml-view.php?id=<?php echo $law['id']; ?>" class="btn-view" style="text-decoration: none; padding: 5px 10px; border: 1px solid #0056b3; border-radius: 4px; font-size: 12px; color: #0056b3;">詳細表示</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>