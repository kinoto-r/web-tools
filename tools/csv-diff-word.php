<?php
require_once __DIR__ . '/../includes/db_config.php';

$debugLogs = [];
$errors = [];
$fullRows = [];
$diffRows = [];
$mergedRows = [];
$missingDiffRows = [];
$header = ['法規名', '章', '節', '条', '条（節）見出し', '項', '内容'];

function addDebugLog(array &$debugLogs, string $message): void
{
    $debugLogs[] = date('Y-m-d H:i:s') . ' ' . $message;
}

function readCsvFile(string $filePath, array &$debugLogs, string $label): array
{
    $rows = [];
    addDebugLog($debugLogs, "{$label} CSV読み込み開始: {$filePath}");

    if (!file_exists($filePath)) {
        addDebugLog($debugLogs, "{$label} CSVファイルが見つかりません。");
        return $rows;
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        addDebugLog($debugLogs, "{$label} CSVファイルを開けませんでした。");
        return $rows;
    }

    $header = null;
    while (($data = fgetcsv($handle)) !== false) {
        if (!$header) {
            $header = array_map(
                fn($value) => trim(mb_convert_encoding($value, 'UTF-8', 'SJIS-win,UTF-8')),
                $data
            );
            addDebugLog($debugLogs, "{$label} ヘッダー検出: " . implode(', ', $header));
            continue;
        }

        $row = [];
        foreach ($data as $index => $value) {
            $columnName = $header[$index] ?? "col_{$index}";
            $row[$columnName] = mb_convert_encoding($value, 'UTF-8', 'SJIS-win,UTF-8');
        }
        $rows[] = $row;
    }

    fclose($handle);
    addDebugLog($debugLogs, "{$label} CSV読み込み完了: " . count($rows) . " 行");
    return $rows;
}

function buildRowKey(array $row): string
{
    $law = $row['法規名'] ?? '';
    $chapter = $row['章'] ?? '';
    $section = $row['節'] ?? '';
    $article = $row['条'] ?? '';
    $articleHeading = $row['条（節）見出し'] ?? '';
    $kou = $row['項'] ?? '';

    return implode('|', [$law, $chapter, $section, $article, $articleHeading, $kou]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    addDebugLog($debugLogs, "フォーム送信を検出しました。");

    if (!isset($_FILES['full_csv'], $_FILES['diff_csv'])) {
        $errors[] = "CSVファイルが見つかりません。";
        addDebugLog($debugLogs, "CSVファイルがアップロードされていません。");
    } else {
        if ($_FILES['full_csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "全文CSVのアップロードに失敗しました。";
            addDebugLog($debugLogs, "全文CSVアップロードエラーコード: " . $_FILES['full_csv']['error']);
        }
        if ($_FILES['diff_csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "差分CSVのアップロードに失敗しました。";
            addDebugLog($debugLogs, "差分CSVアップロードエラーコード: " . $_FILES['diff_csv']['error']);
        }
    }

    if (empty($errors)) {
        $fullRows = readCsvFile($_FILES['full_csv']['tmp_name'], $debugLogs, '全文');
        $diffRows = readCsvFile($_FILES['diff_csv']['tmp_name'], $debugLogs, '差分');

        $diffMap = [];
        foreach ($diffRows as $diffRow) {
            $key = buildRowKey($diffRow);
            if ($key !== '') {
                $diffMap[$key] = $diffRow;
            }
        }

        addDebugLog($debugLogs, "差分マップ作成完了: " . count($diffMap) . " 件");

        foreach ($fullRows as $fullRow) {
            $key = buildRowKey($fullRow);
            $diffRow = $diffMap[$key] ?? null;
            $fullContent = $fullRow['内容'] ?? '';
            $diffContent = $diffRow['内容'] ?? null;

            if ($diffRow !== null && $diffContent !== null && $diffContent !== $fullContent) {
                $mergedRows[] = [
                    'row' => $fullRow,
                    'has_diff' => true,
                    'old' => $fullContent,
                    'new' => $diffContent,
                ];
            } else {
                $mergedRows[] = [
                    'row' => $fullRow,
                    'has_diff' => false,
                    'old' => $fullContent,
                    'new' => $fullContent,
                ];
            }
        }

        foreach ($diffMap as $key => $diffRow) {
            $exists = false;
            foreach ($fullRows as $fullRow) {
                if ($key === buildRowKey($fullRow)) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $missingDiffRows[] = $diffRow;
            }
        }

        addDebugLog($debugLogs, "差分CSVにのみ存在する行: " . count($missingDiffRows) . " 件");
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>CSV差分をWord貼り付け用に整形</title>
    <style>
        body { font-family: "Helvetica Neue", Arial, sans-serif; background: #f4f7f6; margin: 0; padding: 0; }
        .main-content { padding: 40px; margin-left: 250px; transition: margin-left 0.3s; }
        body.menu-closed .main-content { margin-left: 0; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-row { display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px; }
        label { font-weight: bold; }
        input[type="file"] { padding: 6px; }
        button { background: #0056b3; color: white; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .diff-old { color: #c82333; text-decoration: line-through; }
        .diff-new { color: #c82333; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { border: 1px solid #ccc; padding: 8px; vertical-align: top; }
        th { background: #f1f8ff; }
        .note { font-size: 0.9em; color: #555; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; background: #ffe8a1; color: #856404; font-size: 0.8em; }
         .table-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin: 10px 0 16px; }
        .table-toolbar input[type="text"] { padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 240px; }
        .table-toolbar .hint { font-size: 0.85em; color: #666; }
        .table-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .table-header h2 { margin: 0; }    
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="card">
        <h1>CSV差分表示ツール</h1>
        <p class="note">
            「CSV①」と「CVSV②」をアップロードすると、CSVの内容に赤字・斜線付きで差分を反映した表を作成します。
            完成した表を選択してWordに貼り付けてください。
        </p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="form-row">
                <label>CSV①（Excel形式でダウンロードしたもの）</label>
                <input type="file" name="full_csv" accept=".csv" required>
            </div>
            <div class="form-row">
                <label>CSV②</label>
                <input type="file" name="diff_csv" accept=".csv" required>
            </div>
            <button type="submit">差分を反映して表を作成</button>
        </form>
    </div>

    <?php if (!empty($mergedRows)): ?>
        <div class="card">
            <div class="table-header">
                <h2>CSV差分表示の表</h2>
                <button type="button" id="copy-diff-table">作成した表全体をコピー</button>
            </div>
            <p class="note">変更がある行には <span class="badge">差分あり</span> が表示されます。</p>
            <div class="table-toolbar">
                <label>
                    フィルター:
                    <input type="text" id="diff-table-filter" placeholder="キーワードを入力してください">
                </label>
                <span class="hint">入力内容に一致する行だけを表示します。</span>
            </div>
            <table id="diff-table">
                <thead>
                    <tr>
                        <?php foreach ($header as $label): ?>
                            <th><?php echo htmlspecialchars($label); ?></th>
                        <?php endforeach; ?>
                        <th>変更</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mergedRows as $merged): ?>
                        <?php $row = $merged['row']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['法規名'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['章'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['節'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['条'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['条（節）見出し'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['項'] ?? ''); ?></td>
                            <td>
                                <?php if ($merged['has_diff']): ?>
                                    <div class="diff-old"><?php echo nl2br(htmlspecialchars($merged['old'])); ?></div>
                                    <div class="diff-new"><?php echo nl2br(htmlspecialchars($merged['new'])); ?></div>
                                <?php else: ?>
                                    <?php echo nl2br(htmlspecialchars($merged['old'])); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $merged['has_diff'] ? '<span class="badge">差分あり</span>' : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($missingDiffRows)): ?>
        <div class="card">
            <h2>差分CSVにのみ存在する行</h2>
            <p class="note">全文CSVに存在しない行です。必要であれば手動で追加してください。</p>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($header as $label): ?>
                            <th><?php echo htmlspecialchars($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($missingDiffRows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['法規名'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['章'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['節'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['条'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['条（節）見出し'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['項'] ?? ''); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($row['内容'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($debugLogs)): ?>
    <script>
        const debugLogs = <?php echo json_encode($debugLogs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
        debugLogs.forEach((log) => console.log('[CSV差分]', log));
    </script>
<?php endif; ?>
<?php if (!empty($mergedRows)): ?>
    <script>
        const copyButton = document.getElementById('copy-diff-table');
        const diffTable = document.getElementById('diff-table');
        const filterInput = document.getElementById('diff-table-filter');

        if (copyButton && diffTable) {
            copyButton.addEventListener('click', () => {
                try {
                    const selection = window.getSelection();
                    if (!selection) {
                        console.warn('[CSV差分] 選択範囲を取得できませんでした。');
                        return;
                    }
                    selection.removeAllRanges();
                    const range = document.createRange();
                    range.selectNodeContents(diffTable);
                    selection.addRange(range);
                    const successful = document.execCommand('copy');
                    selection.removeAllRanges();

                    if (successful) {
                        console.log('[CSV差分] 表のコピーが完了しました。');
                    } else {
                        console.warn('[CSV差分] 表のコピーに失敗しました。');
                    }
                } catch (error) {
                    console.error('[CSV差分] コピー処理でエラーが発生しました。', error);
                }
            });
        }

        if (filterInput && diffTable) {
            filterInput.addEventListener('input', () => {
                const keyword = filterInput.value.trim().toLowerCase();
                const rows = diffTable.querySelectorAll('tbody tr');
                rows.forEach((row) => {
                    const rowText = row.textContent.toLowerCase();
                    const shouldShow = keyword === '' || rowText.includes(keyword);
                    row.style.display = shouldShow ? '' : 'none';
                });
                console.log('[CSV差分] フィルター適用:', keyword || '（未指定）');
            });
        }
    </script>
<?php endif; ?>
</body>
</html>
