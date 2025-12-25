<?php
require_once __DIR__ . '/../includes/db_config.php';

// 統計情報を取得
try {
    $countLaws = $pdo->query("SELECT COUNT(*) FROM laws")->fetchColumn();
    $countArticles = $pdo->query("SELECT COUNT(*) FROM law_contents")->fetchColumn();
    $latestLaw = $pdo->query("SELECT law_title FROM laws ORDER BY created_at DESC LIMIT 1")->fetchColumn();
} catch (Exception $e) {
    $countLaws = $countArticles = 0;
    $latestLaw = "未登録";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>法令管理システム - ホーム</title>
    <style>
        /* home.php 専用のスタイル（カードやボタン） */
        ..main-content {
    padding: 40px;
    max-width: 1200px;
    margin-left: 250px; /* サイドバーの幅と同じ250pxを確保 */
    transition: margin-left 0.3s; /* サイドバーを閉じた時のアニメーション用 */
}

        /* サイドバーが閉じられた時（bodyにmenu-closedクラスがつく場合）の調整 */
        body.menu-closed .main-content {
        margin-left: 0;
}

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
        }

        .card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 0.9em;
            letter-spacing: 1px;
        }

        .card .value {
            font-size: 2.2em;
            font-weight: bold;
            color: #007bff;
        }

        .card small {
            font-size: 0.5em;
            color: #888;
            margin-left: 5px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .action-btn {
            display: block;
            padding: 35px 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: white;
            font-weight: bold;
            font-size: 1.2em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-blue { background: linear-gradient(135deg, #007bff, #0056b3); }
        .btn-green { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .btn-purple { background: linear-gradient(135deg, #6f42c1, #4e2d8b); }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
            opacity: 0.9;
        }

        .action-btn small {
            display: block;
            font-weight: normal;
            font-size: 0.7em;
            margin-top: 8px;
            opacity: 0.8;
        }

        h2 {
            margin-top: 40px;
            margin-bottom: 20px;
            font-size: 1.4em;
            color: #333;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <h1>管理ダッシュボード</h1>
    
    <div class="dashboard-grid">
        <div class="card">
            <h3>登録法令数</h3>
            <div class="value"><?php echo $countLaws; ?><small>件</small></div>
        </div>
        <div class="card">
            <h3>総条文数</h3>
            <div class="value"><?php echo number_format($countArticles); ?><small>条</small></div>
        </div>
        <div class="card">
            <h3>最終更新</h3>
            <div class="value" style="font-size: 1.2em;"><?php echo htmlspecialchars($latestLaw ?: '未登録'); ?></div>
        </div>
    </div>

    <h2>クイックアクセス</h2>
    <div class="quick-actions">
        <a href="index.php" class="action-btn btn-blue">
            XMLファイルを読み込む
            <small>新規法令をデータベースへ登録</small>
        </a>
        <a href="dashboard.php" class="action-btn btn-green">
            法令一覧を見る
            <small>登録済みデータの閲覧・詳細確認</small>
        </a>
        <a href="word-view.php" class="action-btn btn-purple">
            キーワードで探す
            <small>蓄積された全条文から横断検索</small>
        </a>
    </div>
</div>

</body>
</html>