<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // [關鍵修正] 補上啟動指令
}
// 建議使用絕對路徑防止路徑錯誤
include_once __DIR__ . '/config/google_config.php';
?>
<!-- navbar.php 內容[cite: 10] -->
<nav class="main-nav">
    <div class="nav-container">
        <a href="cafe_map.php" class="nav-logo">☕ 咖啡廳地圖系統</a>

        <ul class="nav-menu">
            <li><a href="cafe_map.php">地圖首頁</a></li>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- 登入後狀態[cite: 10] -->
                <li><a href="favorites.php">我的收藏</a></li>
                <li class="user-info" style="display: flex; align-items: center; margin-left: 15px;">
                    <?php if (isset($_SESSION['user_pic'])): ?>
                        <img src="<?= $_SESSION['user_pic'] ?>" style="width:30px; border-radius:50%; margin-right:8px;">
                    <?php endif; ?>
                    <span style="color: #ffcc00; font-weight: bold;">
                        <?= htmlspecialchars($_SESSION['user_name']) ?>
                    </span>
                    <a href="logout.php" class="btn-logout" style="margin-left: 15px; background: #e74c3c; padding: 5px 10px; border-radius: 4px; color: white; text-decoration: none;">登出</a>
                </li>
            <?php else: ?>
                <!-- 未登入狀態[cite: 10] -->
                <li>
                    <a href="<?= $googleLoginUrl ?>" class="btn-google" style="background: white; color: #444; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-weight: bold;">
                        Google 帳號登入
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<style>
/* 基礎導覽列樣式 */
.main-nav {
    background-color: #3c1200;
    color: white;
    padding: 0.5rem 1rem;
    font-family: Arial, sans-serif;
}
.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.nav-logo {
    font-size: 1.5rem;
    font-weight: bold;
    text-decoration: none;
    color: #ffcc00;
}
.nav-menu {
    list-style: none;
    display: flex;
    align-items: center;
    margin: 0;
    padding: 0;
}
.nav-menu li {
    margin-left: 1.5rem;
}
.nav-menu a {
    color: white;
    text-decoration: none;
    transition: 0.3s;
}
.nav-menu a:hover {
    color: #ffcc00;
}

/* Google 登入按鈕樣式 */
.btn-google {
    background-color: white;
    color: #444 !important;
    padding: 5px 12px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: bold;
}
.btn-google img {
    width: 18px;
    margin-right: 8px;
}

/* 登出按鈕 */
.btn-logout {
    background-color: #e74c3c;
    color: white !important;
    padding: 4px 10px;
    border-radius: 4px;
    margin-left: 10px;
}
.btn-logout:hover {
    background-color: #c0392b;
}
</style>