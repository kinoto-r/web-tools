<?php
require_once __DIR__ . '/../includes/db_config.php';

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

function has_column(PDO $pdo, string $table, string $column, array &$debugLogs): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $exists = (int)$stmt->fetchColumn() > 0;
        log_debug($debugLogs, 'カラム存在チェック', ['table' => $table, 'column' => $column, 'exists' => $exists]);
        return $exists;
    } catch (Exception $e) {
        log_debug($debugLogs, 'カラム存在チェックに失敗しました。', ['error' => $e->getMessage()]);
        return false;
    }
}

$hasEffectiveDate = has_column($pdo, 'laws', 'effective_date', $debugLogs);

// --- 一括保存処理（ここを最新版に統一） ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_law_info'])) {
    $lawId = (int)$_POST['law_id'];
    $dropbox = $_POST['dropbox_url'];
    $source = $_POST['source_url'];
    $tags = $_POST['tags'];
    $u_date = $_POST['updated_date'];
    $effectiveDate = $_POST['effective_date'] ?? '';

    try {
        if ($hasEffectiveDate) {
            $stmt = $pdo->prepare("UPDATE laws SET dropbox_url = ?, source_url = ?, tags = ?, updated_date = ?, effective_date = ? WHERE id = ?");
            $stmt->execute([$dropbox, $source, $tags, $u_date, $effectiveDate, $lawId]);
        } else {
            $stmt = $pdo->prepare("UPDATE laws SET dropbox_url = ?, source_url = ?, tags = ?, updated_date = ? WHERE id = ?");
            $stmt->execute([$dropbox, $source, $tags, $u_date, $lawId]);
        }
        $message = "法律情報を更新しました！";
        log_debug($debugLogs, '法律情報を更新しました。', ['lawId' => $lawId]);
    } catch (Exception $e) {
        $isError = true;
        $message = "更新エラー: " . $e->getMessage();
        log_debug($debugLogs, '更新エラーが発生しました。', ['error' => $e->getMessage()]);
    }
}

try {
    // SQLに updated_date, source_url, tags を追加
    $selectEffectiveDate = $hasEffectiveDate ? "l.effective_date," : "NULL AS effective_date,";
    $sql = "SELECT 
                l.id, 
                l.law_title, 
                l.law_num, 
                l.created_at, 
                l.updated_date,
                {$selectEffectiveDate}
                l.source_url,
                l.tags,
                l.dropbox_url,
                l.parent_id,
                COUNT(c.id) as total_articles 
            FROM laws l
            LEFT JOIN law_contents c ON l.id = c.law_id
            GROUP BY l.id
            ORDER BY l.created_at DESC";

    $stmt = $pdo->query($sql);
    $laws = $stmt->fetchAll();
    log_debug($debugLogs, '法令一覧を取得しました。', ['count' => count($laws)]);
    $compareAvailableByTitle = [];
    if ($hasEffectiveDate) {
        $stmtCompare = $pdo->query("SELECT law_title, COUNT(DISTINCT effective_date) AS date_count FROM laws WHERE effective_date IS NOT NULL AND effective_date <> '' GROUP BY law_title");
        $compareRows = $stmtCompare->fetchAll(PDO::FETCH_ASSOC);
        foreach ($compareRows as $row) {
            $compareAvailableByTitle[$row['law_title']] = (int)$row['date_count'];
        }
        log_debug($debugLogs, '改正比較の対象件数を取得しました。', ['titles' => count($compareAvailableByTitle)]);
    }
} catch (Exception $e) {
    log_debug($debugLogs, 'データ取得エラーが発生しました。', ['error' => $e->getMessage()]);
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
        th { background: #0056b3; color: white; font-weight: normal; }
        tr:hover { background: #f1f8ff; }

        .nav-link { margin-bottom: 20px; display: none; color: #666; text-decoration: none; }
        .success-msg { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .error-msg { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .info-msg { color: #0c5460; background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }

        .column-id { width: 60px; }
        .column-law { width: 220px; }
        .column-view { width: 120px; }
        .column-updated { width: 120px; }
        .column-effective { width: 120px; }
        .column-tags { width: 160px; }
        .column-source { width: 180px; }
        .column-dropbox { width: 160px; }
        .column-edit { width: 80px; }

        .detail-toggle { color: #fff; text-decoration: underline; font-weight: bold; }
        .detail-placeholder { font-size: 12px; color: #666; padding: 6px 0; }
        .detail-body { display: none; }
        .detail-row { display: none; background: #fdfdfd; }
        .detail-row.active { display: table-row; }
        .edit-link { color: #0056b3; font-size: 12px; text-decoration: underline; cursor: pointer; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <a href="index.php" class="nav-link">← 新しいXMLを登録する</a>
    <h1>法令サマリーボード</h1>

    <?php if (!$hasEffectiveDate): ?>
        <div class="info-msg">※ 法令施行年月（effective_date）カラムが未追加のため、入力欄は保存されません。SQLでカラム追加後に反映されます。</div>
    <?php endif; ?>

    <?php if($message): ?>
        <div class="<?php echo $isError ? 'error-msg' : 'success-msg'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

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
                <th class="column-id">ID</th>
                <th class="column-law">法令名 / 番号</th>
                <th class="column-view">法令表示</th>
                <th class="column-updated">更新日（登録日）</th>
                <th class="column-effective">法令施行年月</th>
                <th class="column-tags">キーワード</th>
                <th class="column-source">出典URL</th>
                <th class="column-dropbox">Dropbox URL</th>
                <th class="column-edit"><a href="#" id="toggle-details" class="detail-toggle">編集</a></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($laws as $law): ?>
            <tr>
                <td class="column-id"><?php echo $law['id']; ?></td>
                <td class="column-law">
                    <strong><?php echo htmlspecialchars($law['law_title']); ?></strong><br>
                    <small style="color: #666;"><?php echo htmlspecialchars($law['law_num']); ?></small><br>
                    <small>登録: <?php echo date('Y/m/d', strtotime($law['created_at'])); ?></small>
                </td>
                <td class="column-view">
                    <a href="xml-view.php?id=<?php echo $law['id']; ?>" class="btn-view" style="text-decoration: none; padding: 5px 10px; border: 1px solid #0056b3; border-radius: 4px; font-size: 12px; color: #0056b3;">法令表示</a><br><br>
                    <?php
                        $titleKey = $law['law_title'];
                        $compareCount = $compareAvailableByTitle[$titleKey] ?? 0;
                        $canCompare = $compareCount >= 2 && !empty($law['effective_date']);
                    ?>
                    <?php if ($canCompare): ?>
                        <a href="compare.php?id=<?php echo $law['id']; ?>" style="color: orange; font-size: 12px;">⚠ 改正比較</a>
                        <?php endif; ?>
                </td>
                <?php
                    $displayUpdated = $law['updated_date'] ?? '';
                    if ($displayUpdated === '' && !empty($law['created_at'])) {
                        $displayUpdated = date('Y/m/d', strtotime($law['created_at']));
                    }
                ?>
                <td class="column-updated"><?php echo htmlspecialchars($displayUpdated); ?></td>
                <td class="column-effective"><?php echo htmlspecialchars($law['effective_date'] ?? ''); ?></td>
                <td class="column-tags"><?php echo htmlspecialchars($law['tags'] ?? ''); ?></td>
                <td class="column-source">
                    <?php if (!empty($law['source_url'])): ?>
                        <a href="<?php echo htmlspecialchars($law['source_url']); ?>" target="_blank" style="font-size: 11px; color: #007bff;">リンク</a>
                    <?php endif; ?>
                </td>
                <td class="column-dropbox">
                    <?php if (!empty($law['dropbox_url'])): ?>
                        <a href="<?php echo htmlspecialchars($law['dropbox_url']); ?>" target="_blank" style="font-size: 11px; color: #007bff;">リンク</a>
                    <?php endif; ?>
                </td>
                <td class="column-edit"><span class="edit-link" data-law-id="<?php echo $law['id']; ?>">編集</span></td>
            </tr>
            <tr class="detail-row" data-detail-id="<?php echo $law['id']; ?>">
                <td colspan="9">
                    <div class="detail-body">
                        <form action="" method="post" style="font-size: 11px; display: grid; gap: 4px; min-width: 250px; background: #fdfdfd; padding: 8px; border: 1px solid #eee; border-radius: 4px;">
                            <input type="hidden" name="law_id" value="<?php echo $law['id']; ?>">

                            <label>📅 更新日</label>
                            <input type="date" name="updated_date" value="<?php echo htmlspecialchars($law['updated_date'] ?? ''); ?>">

                            <label>📅 法令施行年月</label>
                            <input type="month" name="effective_date" value="<?php echo htmlspecialchars($law['effective_date'] ?? ''); ?>" <?php echo $hasEffectiveDate ? '' : 'disabled'; ?>>

                            <label>🏷️ キーワード (カンマ区切り)</label>
                            <input type="text" name="tags" value="<?php echo htmlspecialchars($law['tags'] ?? ''); ?>" placeholder="例: 賃金, 残業">

                            <label>🌐 出典元URL</label>
                            <input type="text" name="source_url" value="<?php echo htmlspecialchars($law['source_url'] ?? ''); ?>" placeholder="e-Govなど">

                            <label>📄 Dropbox (PDF)</label>
                            <input type="text" name="dropbox_url" value="<?php echo htmlspecialchars($law['dropbox_url'] ?? ''); ?>" placeholder="共有リンク">

                            <button type="submit" name="update_law_info" style="margin-top: 5px; background: #28a745; color: white; border: none; padding: 6px; cursor: pointer; border-radius: 3px; font-weight: bold;">一括保存</button>
                        </form>

                        </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($debugLogs)): ?>
    <script>
        console.group('Dashboard Debug');
        <?php foreach ($debugLogs as $log): ?>
        console.log(<?php echo json_encode($log, JSON_UNESCAPED_UNICODE); ?>);
        <?php endforeach; ?>
        <?php if ($isError): ?>
        console.error('ダッシュボード処理でエラーが発生しました。');
        <?php endif; ?>
        console.groupEnd();
    </script>
<?php endif; ?>

<script>
    const toggleDetails = document.getElementById('toggle-details');
    if (toggleDetails) {
        toggleDetails.addEventListener('click', (event) => {
            event.preventDefault();
            const detailRows = document.querySelectorAll('.detail-row');
            const shouldShow = !document.body.classList.contains('show-detail');
            document.body.classList.toggle('show-detail');
            detailRows.forEach((row) => row.classList.toggle('active', shouldShow));
            console.log('詳細管理の一括表示切り替え:', shouldShow);
        });
    }

    document.querySelectorAll('.edit-link').forEach((link) => {
        link.addEventListener('click', () => {
            const lawId = link.dataset.lawId;
            const detailRow = document.querySelector(`.detail-row[data-detail-id="${lawId}"]`);
            if (detailRow) {
                detailRow.classList.toggle('active');
                console.log('詳細管理の行表示切り替え:', { lawId, active: detailRow.classList.contains('active') });
            }
        });
    });
</script>
</body>
</html>