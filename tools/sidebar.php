<div class="topbar">
    <div class="topbar-title">法令管理ツール</div>
    <nav class="topbar-menu">
        <a href="home.php">🏠 ホーム</a>
        <a href="dashboard.php">📊 サマリーボード</a>
        <a href="word-view.php">🔍 単語検索</a>
        <a href="index.php">📥 XML新規登録</a>
        <a href="csv-diff-word.php">🟥 CSV差分→Word表</a>
    </nav>
</div>

<style>
    body {
        margin: 0;
        padding: 0;
        color: #333;
    }
 .topbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        background: #343a40;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 24px;
        box-sizing: border-box;
        z-index: 1000;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .topbar-title {
        font-size: 18px;
        font-weight: bold;
    }
.topbar-menu {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .topbar-menu a {
        color: #c2c7d0;
        text-decoration: none;
        padding: 6px 10px;
        border-radius: 4px;
        white-space: nowrap;
        font-size: 14px;
    }

    .topbar-menu a:hover {
        background: #495057;
        color: #ffffff;
    }

    .main-content {
        margin-left: 0 !important;
        padding-top: 80px !important;
    }
</style>

