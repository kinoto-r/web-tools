<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$results = [];
$start_idx = isset($_POST['start']) ? (int)$_POST['start'] : 1;
$end_idx = isset($_POST['end']) ? (int)$_POST['end'] : 9999;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['word_file'])) {
    $filename = $_FILES['word_file']['tmp_name'];
    $zip = new ZipArchive();
    
    if ($zip->open($filename) === TRUE) {
        $xml_content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml_content) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($xml_content);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            // 段落のみを取得（表の中の段落を除外したい場合は "body/w:p" と指定）
            $paragraphs = $xpath->query('//w:p');

            $count = 0;
            foreach ($paragraphs as $p) {
                // 表の中にある段落かどうか判定（表自体はスキップし、タイトル段落のみ取るため）
                // 直近の親要素が tc (table cell) であれば表の中とみなす
                if ($p->parentNode->nodeName === 'w:tc') continue;

                $text = "";
                // 箇条書き記号の判定（簡易再現）
                $numPr = $xpath->query('.//w:numPr', $p);
                if ($numPr->length > 0) {
                    $text .= "・ "; // 箇条書きがある場合は先頭に記号を付与
                }

                // 段落内のテキストを結合
                $text .= trim($p->textContent);
                if ($text === '') continue;

                $count++;
                if ($count < $start_idx || $count > $end_idx) continue;

                // スタイルの取得
                $style = 'Normal';
                $pStyle = $xpath->query('.//w:pStyle', $p)->item(0);
                if ($pStyle) {
                    $style = $pStyle->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
                }

                $chapter_num = '';
                $content_text = $text;

                // 章番号の切り出し (Style 52) 
                // A.1 や 1.1 など アルファベット＋数字＋ドット に対応
                if ($style === '52') {
                    if (preg_match('/^([A-Z0-9\.]+)([\s　]+)(.*)$/u', $text, $matches)) {
                        $chapter_num  = $matches[1];
                        $content_text = $matches[3];
                    }
                }

                $results[] = [
                    'index' => $count,
                    'style' => $style,
                    'chapter_num' => $chapter_num,
                    'text'  => $content_text
                ];
            }
        }
    }
}

// CSVダウンロード処理（Shift-JIS変換含む）
if (isset($_POST['download_csv']) && !empty($_POST['csv_data'])) {
    $data = json_decode($_POST['csv_data'], true);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="word_export_' . date('YmdHis') . '.csv"');
    $stream = fopen('php://output', 'w');
    fputcsv($stream, [mb_convert_encoding('No.', 'SJIS-win', 'UTF-8'), mb_convert_encoding('Style', 'SJIS-win', 'UTF-8'), mb_convert_encoding('章番号', 'SJIS-win', 'UTF-8'), mb_convert_encoding('内容', 'SJIS-win', 'UTF-8')]);
    foreach ($data as $row) {
        fputcsv($stream, [$row['index'], mb_convert_encoding($row['style'], 'SJIS-win', 'UTF-8'), mb_convert_encoding($row['chapter_num'], 'SJIS-win', 'UTF-8'), mb_convert_encoding($row['text'], 'SJIS-win', 'UTF-8')]);
    }
    fclose($stream);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Word高度解析ツール</title>
    <style>
        body { font-family: sans-serif; margin: 30px; background: #f4f7f6; }
        .container { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .setting-box { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: #fff; }
        .style-52 { background: #d4edda; font-weight: bold; }
        .btn { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h2>Word高度解析ツール (Appendix対応版)</h2>
    <div class="setting-box">
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="word_file" accept=".docx" required>
            範囲: <input type="number" name="start" value="<?= $start_idx ?>" style="width:50px"> 〜 
            <input type="number" name="end" value="<?= $end_idx ?>" style="width:50px">
            <button type="submit">解析</button>
        </form>
    </div>

    <?php if ($results): ?>
        <table>
            <tr><th>No.</th><th>Style</th><th>章番号</th><th>内容</th></tr>
            <?php foreach ($results as $row): ?>
            <tr class="<?= ($row['style']=='52'?'style-52':'') ?>">
                <td><?= $row['index'] ?></td>
                <td><?= htmlspecialchars($row['style']) ?></td>
                <td style="color:red;"><?= htmlspecialchars($row['chapter_num']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['text'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <form method="post">
            <input type="hidden" name="csv_data" value='<?= htmlspecialchars(json_encode($results), ENT_QUOTES) ?>'>
            <button type="submit" name="download_csv" class="btn">CSVダウンロード</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>