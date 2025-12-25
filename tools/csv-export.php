<?php
require_once __DIR__ . '/../includes/db_config.php';

$lawId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT law_title, law_num FROM laws WHERE id = ?");
    $stmt->execute([$lawId]);
    $law = $stmt->fetch();
    if (!$law) die("データが見つかりません。");

    $stmtContent = $pdo->prepare("SELECT chapter_title, article_title, content_text FROM law_contents WHERE law_id = ? ORDER BY id ASC");
    $stmtContent->execute([$lawId]);
    $contents = $stmtContent->fetchAll();

    $filename = $law['law_title'] . "_structured_" . date('Ymd') . ".csv";

    header('Content-Type: text/csv; charset=shift_jis');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // 理想のフォーマットに合わせたヘッダー
    $head = ['法規名', '章', '節', '条', '条（節）見出し', '項', '内容'];
    mb_convert_variables('SJIS-win', 'UTF-8', $head);
    fputcsv($output, $head);

    // 共通の法規名
    $lawName = $law['law_title'];

    foreach ($contents as $row) {
        $chapter = "";
        $section = "";
        
        // 章・節の判定（DBのデータ構造に合わせる）
        if (strpos($row['chapter_title'], '章') !== false) {
            $chapter = $row['chapter_title'];
        } elseif (strpos($row['chapter_title'], '節') !== false) {
            $section = $row['chapter_title'];
        }

        // 内容を「項」ごとに分割する処理
        // 全角数字の「２」「３」や「(2)」などで改行されている場合を想定
        $paragraphs = preg_split('/(\n(?=[０-９]|[0-9]|（[０-９]）|\([0-9]\)))/', $row['content_text']);
        
        foreach ($paragraphs as $index => $para) {
            $para = trim($para);
            if (empty($para)) continue;

            // 項番号の抽出（1項なら空、2項以降なら番号を入れるなど）
            $kou = ($index === 0 && !preg_match('/^[０-９2-9]/u', $para)) ? "" : ($index + 1);

            $line = [
                $lawName,
                $chapter,
                $section,
                $row['article_title'], // 「第一条」など
                "",                    // 条見出し（XMLの構造から抽出可能な場合はここに入れる）
                $kou,                  // 項番号
                $para                  // 本文
            ];

            mb_convert_variables('SJIS-win', 'UTF-8', $line);
            fputcsv($output, $line);
        }
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("CSV生成エラー: " . $e->getMessage());
}