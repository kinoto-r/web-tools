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
$lawId = (int)($_GET['id'] ?? $_POST['law_id'] ?? 0);
$law = null;

if ($lawId <= 0) {
    $isError = true;
    $message = "対象のIDが指定されていません。";
    log_debug($debugLogs, '編集対象IDが未指定です。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_law_info']) && !$isError) {
    $lawNum = $_POST['law_num'] ?? '';
    $dropbox = $_POST['dropbox_url'] ?? '';
    $source = $_POST['source_url'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $u_date = $_POST['updated_date'] ?? '';
    $effectiveDate = $_POST['effective_date'] ?? '';

    try {
        if ($hasEffectiveDate) {
            $stmt = $pdo->prepare("UPDATE laws SET law_num = ?, dropbox_url = ?, source_url = ?, tags = ?, updated_date = NOW(), effective_date = ? WHERE id = ?");
            $stmt->execute([$lawNum, $dropbox, $source, $tags, $effectiveDate, $lawId]);
        } else {
            $stmt = $pdo->prepare("UPDATE laws SET law_num = ?, dropbox_url = ?, source_url = ?, tags = ?, updated_date = NOW() WHERE id = ?");
            $stmt->execute([$lawNum, $dropbox, $source, $tags, $lawId]);
        }
        $message = "法律情報を更新しました！";
        log_debug($debugLogs, '法律情報を更新しました。', ['lawId' => $lawId]);
    } catch (Exception $e) {
        $isError = true;
        $message = "更新エラー: " . $e->getMessage();
        log_debug($debugLogs, '更新エラーが発生しました。', ['error' => $e->getMessage()]);
    }
}


if (!$isError && $lawId > 0) {
    try {
        $selectEffectiveDate = $hasEffectiveDate ? 'effective_date,' : 'NULL AS effective_date,';
        $stmt = $pdo->prepare("SELECT id, law_title, law_num, created_at, updated_date, {$selectEffectiveDate} source_url, tags, dropbox_url FROM laws WHERE id = ?");
        $stmt->execute([$lawId]);
        $law = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$law) {
            $isError = true;
            $message = "指定されたIDの法令が見つかりませんでした。";
            log_debug($debugLogs, '法令が見つかりませんでした。', ['lawId' => $lawId]);
        } else {
            log_debug($debugLogs, '法令情報を取得しました。', ['lawId' => $lawId]);
        }
    } catch (Exception $e) {
        $isError = true;
        $message = "データ取得エラー: " . $e->getMessage();
        log_debug($debugLogs, 'データ取得エラーが発生しました。', ['error' => $e->getMessage()]);
    }
}

$displayCreated = '';
if (!empty($law['created_at'])) {
    $displayCreated = date('Y/m/d', strtotime($law['created_at']));
}

$displayUpdated = '';
if (!empty($law['updated_date'])) {
    $displayUpdated = date('Y/m/d', strtotime($law['updated_date']));
} elseif (!empty($law['created_at'])) {
    $displayUpdated = date('Y/m/d', strtotime($law['created_at']));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>法令編集</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .main-content { padding: 40px; margin-left: 0; }
        h1 { border-left: 6px solid #0056b3; padding-left: 15px; margin-bottom: 30px; }
        .success-msg { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .error-msg { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .info-msg { color: #0c5460; background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .law-meta { background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .edit-form { font-size: 13px; display: grid; gap: 8px; min-width: 250px; background: #fff; padding: 16px; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .edit-form label { font-weight: bold; }
        .edit-form input[type="text"],
        .edit-form input[type="date"],
        .edit-form input[type="month"] { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        .edit-form button { margin-top: 5px; background: #28a745; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #0056b3; text-decoration: none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <h1>法令編集</h1>

    <?php if (!$hasEffectiveDate): ?>
        <div class="info-msg">※ 法令施行年月（effective_date）カラムが未追加のため、入力欄は保存されません。SQLでカラム追加後に反映されます。</div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="<?php echo $isError ? 'error-msg' : 'success-msg'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($law): ?>
        <div class="law-meta">
            <div><strong>ID:</strong> <?php echo htmlspecialchars($law['id']); ?></div>
            <div><strong>法令名:</strong> <?php echo htmlspecialchars($law['law_title']); ?></div>
           <div><strong>登録日:</strong> <?php echo htmlspecialchars($displayCreated); ?></div>
            <div><strong>更新日:</strong> <?php echo htmlspecialchars($displayUpdated); ?></div>
        </div>

        <form action="" method="post" class="edit-form">
            <input type="hidden" name="law_id" value="<?php echo htmlspecialchars($law['id']); ?>">

             <label>🔢 法令番号</label>
            <input type="text" name="law_num" value="<?php echo htmlspecialchars($law['law_num']); ?>">
            <label>📅 法令施行年月</label>
            <input type="month" name="effective_date" value="<?php echo htmlspecialchars($law['effective_date'] ?? ''); ?>" <?php echo $hasEffectiveDate ? '' : 'disabled'; ?>>

            <label>🏷️ キーワード (カンマ区切り)</label>
            <input type="text" name="tags" value="<?php echo htmlspecialchars($law['tags'] ?? ''); ?>" placeholder="例: 賃金, 残業">

            <label>🌐 出典元URL</label>
            <input type="text" name="source_url" value="<?php echo htmlspecialchars($law['source_url'] ?? ''); ?>" placeholder="e-Govなど">

            <label>📄 Dropbox (PDF)</label>
            <input type="text" name="dropbox_url" value="<?php echo htmlspecialchars($law['dropbox_url'] ?? ''); ?>" placeholder="共有リンク">

            <button type="submit" name="update_law_info">一括保存</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!empty($debugLogs)): ?>
    <script>
        console.group('Edit Law Debug');
        <?php foreach ($debugLogs as $log): ?>
        console.log(<?php echo json_encode($log, JSON_UNESCAPED_UNICODE); ?>);
        <?php endforeach; ?>
        <?php if ($isError): ?>
        console.error('法令編集処理でエラーが発生しました');
        <?php endif; ?>
        console.groupEnd();
    </script>
<?php endif; ?>
</body>
</html>
