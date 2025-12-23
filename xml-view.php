<?php
// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

$data = [];
$headers = [];

// ファイルがアップロードされた時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $xml_file = $_FILES['xml_file']['tmp_name'];
    
    if (is_uploaded_file($xml_file)) {
        $xml = simplexml_load_file($xml_file);
        
        if ($xml) {
            foreach ($xml->children() as $child) {
                $row = [];
                foreach ($child as $key => $value) {
                    $row[$key] = (string)$value;
                    if (!in_array($key, $headers)) {
                        $headers[] = $key;
                    }
                }
                $data[] = $row;
            }
        }
    }
}

// CSVダウンロード処理
if (isset($_POST['download_xml_csv']) && !empty($_POST['json_data'])) {
    $data_to_export = json_decode($_POST['json_data'], true);
    $headers_to_export = json_decode($_POST['json_headers'], true);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="xml_export_' . date('YmdHis') . '.csv"');
    
    $stream = fopen('php://output', 'w');
    
    // ヘッダーをShift-JISに変換して出力
    $sjis_headers = [];
    foreach ($headers_to_export as $h) {
        $sjis_headers[] = mb_convert_encoding($h, 'SJIS-win', 'UTF-8');
    }
    fputcsv($stream, $sjis_headers);

    // データをShift-JISに変換して出力
    foreach ($data_to_export as $row) {
        $sjis_row = [];
        foreach ($headers_to_export as $h) {
            $val = isset($row[$h]) ? $row[$h] : '';
            $sjis_row[] = mb_convert_encoding($val, 'SJIS-win', 'UTF-8');
        }
        fputcsv($stream, $sjis_row);
    }
    fclose($stream);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>XML to CSV エクスポーター</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f4f7f6; color: #333; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .upload-area { background: #e9ecef; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px dashed #ccc; text-align: center; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; margin-bottom: 20px; }
        th, td { border: 1px solid #dee2e6; padding: 10px; text-align: left; font-size: 0.9em; }
        th { background: #495057; color: white; }
        .btn-download { background: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn-download:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h2>XML to CSV エクスポーター</h2>
        
        <div class="upload-area">
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="xml_file" accept=".xml" required>
                <button type="submit">XMLを解析する</button>
            </form>
        </div>

        <?php if (!empty($data)): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php foreach ($headers as $header): ?>
                                    <td><?php echo htmlspecialchars($row[$header] ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form action="" method="post">
                <input type="hidden" name="json_data" value='<?php echo htmlspecialchars(json_encode($data), ENT_QUOTES); ?>'>
                <input type="hidden" name="json_headers" value='<?php echo htmlspecialchars(json_encode($headers), ENT_QUOTES); ?>'>
                <button type="submit" name="download_xml_csv" class="btn-download">CSVファイルをダウンロード</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>