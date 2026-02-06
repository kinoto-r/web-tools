<div id="mySidebar" class="sidebar">
    <div class="sidebar-header">
        <h2>法令管理ツール</h2>
        <button onclick="toggleSidebar()" class="toggle-btn">×</button>
    </div>
    <ul class="nav-menu">
        <li><a href="home.php">🏠 ホーム</a></li>
        <li><a href="dashboard.php">📊 サマリーボード</a></li>
        <li><a href="word-view.php">🔍 単語検索</a></li>
        <li><a href="index.php">📥 XML新規登録</a></li>
        <li><a href="csv-diff-word.php">🟥 CSV差分→Word表</a></li>
    </ul>
</div>

<button id="openBtn" class="open-btn" onclick="toggleSidebar()" style="display:none;">☰ メニュー</button>

<style>
    /* サイドバーの基本スタイル */
    .sidebar { width: 250px; height: 100vh; background: #343a40; color: white; position: fixed; top: 0; left: 0; padding: 20px; transition: 0.3s; z-index: 1000; overflow-x: hidden; }
    .sidebar.closed { width: 0; padding: 0; }
    
    .sidebar-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #4b545c; padding-bottom: 10px; }
    .toggle-btn { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
    
    .nav-menu { list-style: none; padding: 0; margin-top: 20px; }
    .nav-menu li { margin: 15px 0; }
    .nav-menu a { color: #c2c7d0; text-decoration: none; display: block; padding: 10px; border-radius: 4px; white-space: nowrap; }
    .nav-menu a:hover { background: #495057; color: white; }

    /* コンテンツ側の余白調整用 */
    body { transition: margin-left 0.3s; margin-left: 250px; }
    body.menu-closed { margin-left: 0; }
/* sidebar.php の style 内に追加・修正 */
body { 
    margin: 0; 
    padding: 0; 
    transition: 0.3s; 
}

/* メニューが開いている時 */
body:not(.menu-closed) .main-content {
    margin-left: 250px !important; /* 強制的にサイドバーの分だけ右に寄せる */
}

/* メニューが閉じている時 */
body.menu-closed .main-content {
    margin-left: 0 !important;
}
    /* 開くボタンのスタイル */
    .open-btn { position: fixed; top: 20px; left: 20px; font-size: 18px; background: #343a40; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; z-index: 999; }
</style>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById("mySidebar");
        const openBtn = document.getElementById("openBtn");
        const body = document.body;

        if (sidebar.classList.contains("closed")) {
            sidebar.classList.remove("closed");
            openBtn.style.display = "none";
            body.classList.remove("menu-closed");
        } else {
            sidebar.classList.add("closed");
            openBtn.style.display = "block";
            body.classList.add("menu-closed");
        }
    }
</script>