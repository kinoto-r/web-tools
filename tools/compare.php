<?php
require_once __DIR__ . '/../includes/db_config.php';

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

// 比較したい法令IDを取得
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmtSelected = $pdo->prepare("SELECT * FROM laws WHERE id = ?");
    $stmtSelected->execute([$selectedId]);
    $selectedLaw = $stmtSelected->fetch();

    if (!$selectedLaw) {
        log_debug($debugLogs, '指定IDの法令が見つかりません。', ['selectedId' => $selectedId]);
        die("指定された法令が見つかりません。");
    }

    if (empty($selectedLaw['effective_date'])) {
        log_debug($debugLogs, '法令施行年月が未登録のため比較できません。', ['selectedId' => $selectedId]);
        die("法令施行年月が未登録のため比較できません。");
    }

    $stmtCandidates = $pdo->prepare("SELECT id, law_title, version_label, effective_date FROM laws WHERE law_title = ? AND effective_date IS NOT NULL AND effective_date <> '' ORDER BY effective_date ASC, id ASC");
    $stmtCandidates->execute([$selectedLaw['law_title']]);
    $candidates = $stmtCandidates->fetchAll(PDO::FETCH_ASSOC);

    if (count($candidates) < 2) {
        log_debug($debugLogs, '比較対象の法令が2件未満です。', ['lawTitle' => $selectedLaw['law_title']]);
        die("比較対象の法令が2件以上必要です。");
    }

    $selectedIndex = null;
    foreach ($candidates as $index => $candidate) {
        if ((int)$candidate['id'] === (int)$selectedId) {
            $selectedIndex = $index;
            break;
        }
    }

    if ($selectedIndex === null) {
        log_debug($debugLogs, '比較候補に指定法令が含まれていません。', ['selectedId' => $selectedId]);
        die("比較対象の特定に失敗しました。");
    }

    if ($selectedIndex > 0) {
        $compareCandidate = $candidates[$selectedIndex - 1];
    } else {
        $compareCandidate = $candidates[$selectedIndex + 1];
    }

    $selectedEffective = $selectedLaw['effective_date'];
    $compareEffective = $compareCandidate['effective_date'];

    if ($selectedEffective >= $compareEffective) {
        $newLawId = $selectedLaw['id'];
        $oldLawId = $compareCandidate['id'];
    } else {
        $newLawId = $compareCandidate['id'];
        $oldLawId = $selectedLaw['id'];
    }

    $stmtNewLaw = $pdo->prepare("SELECT * FROM laws WHERE id = ?");
    $stmtNewLaw->execute([$newLawId]);
    $newLaw = $stmtNewLaw->fetch();

    $stmtOldLaw = $pdo->prepare("SELECT * FROM laws WHERE id = ?");
    $stmtOldLaw->execute([$oldLawId]);
    $oldLaw = $stmtOldLaw->fetch();

    log_debug($debugLogs, '比較対象を決定しました。', [
        'selectedId' => $selectedId,
        'newLawId' => $newLawId,
        'oldLawId' => $oldLawId,
        'lawTitle' => $selectedLaw['law_title'],
    ]);

    $stmtContents = $pdo->prepare("SELECT * FROM law_contents WHERE law_id = ? ORDER BY id ASC");

    $stmtContents->execute([$newLawId]);
    $newContents = $stmtContents->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

    $newItems = [];
    foreach($newContents as $c) $newItems[$c['article_title']] = $c['content_text'];

    $stmtContents->execute([$oldLawId]);
    $oldContents = $stmtContents->fetchAll();
    $oldItems = [];
    foreach($oldContents as $c) $oldItems[$c['article_title']] = $c['content_text'];

} catch (Exception $e) {
    log_debug($debugLogs, '比較処理でエラーが発生しました。', ['error' => $e->getMessage()]);
    die("エラー: " . $e->getMessage());
}

// 簡易的な差分抽出関数（単語単位ではなく、変更があったかどうかの判定）
function getDiffHtml($old, $new) {
    if ($old === $new) return htmlspecialchars($new);
    if (empty($old)) return '<span class="diff-add">' . htmlspecialchars($new) . '</span>';
    
    // 本来は詳細なdiffライブラリを使うべきですが、簡易的に変更箇所を強調
    return '<span class="diff-change">' . htmlspecialchars($new) . '</span><br><small class="diff-old-text">【旧】' . htmlspecialchars($old) . '</small>';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新旧対照比較 - <?php echo htmlspecialchars($newLaw['law_title']); ?></title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; margin: 0; }
        .main-content { padding: 20px; margin-left: 250px; transition: 0.3s; }
        body.menu-closed .main-content { margin-left: 0; }

        .compare-container { display: flex; gap: 10px; background: white; padding: 10px; border-radius: 8px; }
        .pane { flex: 1; min-width: 0; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
        .pane-header { background: #eee; padding: 10px; font-weight: bold; border-bottom: 1px solid #ddd; }
        
        .article-row { display: flex; border-bottom: 1px solid #eee; min-height: 100px; }
        .cell { flex: 1; padding: 15px; border-right: 1px solid #eee; white-space: pre-wrap; font-size: 13px; }
        .cell-title { width: 120px; background: #f9f9f9; padding: 15px; font-weight: bold; font-size: 12px; border-right: 1px solid #eee; }
        
        /* 差分ハイライト */
        .diff-add { background-color: #e6ffec; font-weight: bold; }
        .diff-change { background-color: #fffbdd; font-weight: bold; }
        .diff-old-text { color: #999; text-decoration: line-through; }
        
        .law-info-header { margin-bottom: 20px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="law-info-header">
        <h1>新旧対照表</h1>
        <p><strong>法令名：</strong><?php echo htmlspecialchars($newLaw['law_title']); ?></p>
        <p>比較：<?php echo htmlspecialchars($oldLaw['version_label'] ?: $oldLaw['effective_date']); ?>（左） ↔ <strong><?php echo htmlspecialchars($newLaw['version_label'] ?: $newLaw['effective_date']); ?>（右）</strong></p>
    </div>

    <div class="compare-container">
        <div style="width: 100%;">
            <div class="article-row" style="background: #0056b3; color: white; font-weight: bold;">
                <div class="cell-title" style="color: white;">条番号</div>
                <div class="cell">旧（改正前）</div>
                <div class="cell">新（最新版）</div>
            </div>

            <?php 
            // 全ての条文タイトル（第一条など）をマージしてループ
            $allTitles = array_unique(array_merge(array_keys($oldItems), array_keys($newItems)));
            
            foreach ($allTitles as $title): 
                $oldText = $oldItems[$title] ?? "";
                $newText = $newItems[$title] ?? "";
                $isChanged = ($oldText !== $newText);
            ?>
                <div class="article-row" <?php if($isChanged) echo 'style="background: #fff8e1;"'; ?>>
                    <div class="cell-title"><?php echo htmlspecialchars($title); ?></div>
                    <div class="cell" style="color: #666;"><?php echo htmlspecialchars($oldText ?: '(規定なし)'); ?></div>
                    <div class="cell">
                        <?php echo getDiffHtml($oldText, $newText); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($debugLogs)): ?>
    <script>
        console.group('Compare Debug');
        <?php foreach ($debugLogs as $log): ?>
        console.log(<?php echo json_encode($log, JSON_UNESCAPED_UNICODE); ?>);
        <?php endforeach; ?>
        console.groupEnd();
    </script>
<?php endif; ?>

</body>
</html>