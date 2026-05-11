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
                <li class="<?= ($current_page == 'favorite.php') ? 'active' : '' ?>">
                    <a href="favorite.php">
                        <span class="m-icon">❤️</span>
                        <span class="m-label">收藏</span>
                    </a>
                </li>
                
                <li class="user-info mobile-user-box">
                    <div class="user-header" onclick="toggleLogoutMenu(event)">
                        <?php if (isset($_SESSION['user_pic'])): ?>
                            <img src="<?= $_SESSION['user_pic'] ?>" class="user-avatar-app" referrerpolicy="no-referrer">
                        <?php endif; ?>
                        <span class="user-name-app">
                            <?= htmlspecialchars($_SESSION['user_name']) ?>
                        </span>
                    </div>
                    
                    <div class="logout-popup" id="logoutPopup">
                        <a href="logout.php" class="btn-logout-animated">
                            <span class="logout-text">登出</span>
                        </a>
                    </div>
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
/* 底部導覽列基礎設定 */
.mobile-app-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background-color: #3c1200; 
    padding: 8px 0 env(safe-area-inset-bottom);
    z-index: 9999;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
    border-radius: 20px 20px 0 0;
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

.nav-menu li {
    margin: 0 !important;
    text-align: center;
}

/* 導覽按鈕：維持垂直堆疊 */
.nav-menu li a:not(.btn-logout-animated) {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #d2b48c;
}

.m-icon { font-size: 22px; margin-bottom: 2px; }
.m-label { font-size: 10px; font-weight: bold; }

/* 使用者資訊區塊 */
.mobile-user-box {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.user-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    cursor: pointer;
}

.user-avatar-app {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #ffcc00;
}

.user-name-app {
    color: #ffcc00;
    font-size: 10px;
    font-weight: bold;
    max-width: 60px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* --- 登出氣泡佈局 --- */
.logout-popup {
    position: absolute;
    bottom: 75px; 
    background: #e74c3c;
    padding: 10px 18px;
    border-radius: 25px;
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
    
    /* 動畫初始狀態 */
    opacity: 0;
    visibility: hidden;
    transform: translateY(15px) scale(0.8);
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 10000;
}

.logout-popup.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.logout-popup::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px 6px 0;
    border-style: solid;
    border-color: #e74c3c transparent transparent transparent;
}

/* 關鍵修改：將登出按鈕設為橫向排列 (row) */
.btn-logout-animated {
    color: white !important;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    display: flex;
    flex-direction: row; /* 👈 強制橫向並排 */
    align-items: center;
    gap: 8px; /* 圖示與文字的間距 */
    white-space: nowrap;
}

.logout-icon { font-size: 16px; }

/* 登入按鈕 */
.app-google-btn {
    background-color: white !important;
    color: #333 !important;
    padding: 8px 15px !important;
    border-radius: 25px !important;
    font-size: 12px;
    font-weight: bold;
}

body { padding-bottom: 90px; }
</style>

<script>
function toggleLogoutMenu(e) {
    e.stopPropagation();
    const popup = document.getElementById('logoutPopup');
    if(popup) popup.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    const popup = document.getElementById('logoutPopup');
    const userHeader = document.querySelector('.user-header');
    if (popup && popup.classList.contains('show') && (!userHeader || !userHeader.contains(e.target))) {
        popup.classList.remove('show');
    }
});
</script>