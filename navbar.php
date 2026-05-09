<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/config/google_config.php';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="main-nav mobile-app-nav">
    <div class="nav-container">
        <ul class="nav-menu">
            
            <li class="<?= ($current_page == 'cafe_map.php') ? 'active' : '' ?>">
                <a href="cafe_map.php">
                    <span class="m-icon">🧭</span>
                    <span class="m-label">地圖</span>
                </a>
            </li>
            
            <li class="<?= ($current_page == 'route_plan.php') ? 'active' : '' ?>">
                <a href="route_plan.php">
                    <span class="m-icon">🚌</span>
                    <span class="m-label">路線</span>
                </a>
            </li>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="<?= ($current_page == 'favorites.php') ? 'active' : '' ?>">
                    <a href="favorite.php">
                        <span class="m-icon">❤️</span>
                        <span class="m-label">收藏</span>
                    </a>
                </li>
                
                <li class="user-info mobile-user-box">
                    <div class="user-header">
                        <?php if (isset($_SESSION['user_pic'])): ?>
                            <img src="<?= $_SESSION['user_pic'] ?>" class="user-avatar-app">
                        <?php endif; ?>
                        <span class="user-name-app">
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </span>
                    </div>
                    <a href="logout.php" class="btn-logout">登出</a>
                </li>
                
            <?php else: ?>
                <li>
                    <a href="<?= $googleLoginUrl ?>" class="btn-google app-google-btn">
                        <span>登入</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<style>
/* 核心：將導覽列固定在底部並美化 */
.mobile-app-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background-color: #3c1200; /* 保留 Espresso 深褐 */
    padding: 8px 0 env(safe-area-inset-bottom); /* 適應 iPhone 底部線條 */
    z-index: 9999;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
    border-radius: 20px 20px 0 0; /* 頂部圓角增加現代感 */
}

.nav-menu {
    list-style: none;
    display: flex;
    justify-content: space-around;
    align-items: center;
    margin: 0;
    padding: 0 10px;
    height: 60px;
}

/* 按鈕項目樣式 */
.nav-menu li {
    margin: 0 !important; /* 蓋掉原本的 margin-left */
    text-align: center;
}

.nav-menu li a {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    transition: all 0.3s ease;
    color: #d2b48c; /* 淺褐色預設 */
}

.nav-menu li.active a {
    color: #ffcc00; /* 選中時的金黃色 */
    transform: translateY(-2px);
}

.m-icon {
    font-size: 22px;
    margin-bottom: 2px;
}

.m-label {
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 0.5px;
}

/* 使用者資訊區塊美化 */
.mobile-user-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.user-header {
    display: flex;
    align-items: center;
    gap: 5px;
}

.user-avatar-app {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1.5px solid #ffcc00;
}

.user-name-app {
    color: #ffcc00;
    font-size: 11px;
    font-weight: bold;
    max-width: 60px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 登出按鈕：微縮並精緻化 */
.btn-logout {
    background-color: #e74c3c !important;
    font-size: 9px !important;
    padding: 2px 8px !important;
    border-radius: 4px !important;
    margin: 0 !important;
    line-height: 1.2;
}

/* Google 登入按鈕：保留原有類名但重新修飾 */
.app-google-btn {
    background-color: white !important;
    color: #333 !important;
    padding: 8px 15px !important;
    border-radius: 25px !important;
    font-size: 12px !important;
    display: flex !important;
    align-items: center !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.app-google-btn img {
    width: 16px;
    margin-right: 6px;
}

/* 避免內容被遮擋 */
body {
    padding-bottom: 90px;
}
</style>
