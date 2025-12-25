<?php
// データベース接続情報 (heteml用)
$host = '★mysql801.phy.heteml.lan'; // hetemlのサーバー名
$dbname = '_www'; 
$user = '_www'; 
$pass = '★fmkv7a23h4qa9'; 

try {
    // データベースに接続するための魔法の呪文
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    // エラーがあったら教えてくれる設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // データを取得するときに扱いやすい形式にする設定
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 接続に失敗した場合はエラーを表示
    die('データベース接続失敗: ' . $e->getMessage());
}