<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';
include_once __DIR__ . '/config/google_config.php';

// 檢查使用者是否登入
if (!isset($_SESSION['user_id'])) {
    header("Location: $googleLoginUrl"); // 若未登入則導向登入頁
    exit;
}

$user_id = $_SESSION['user_id'];

// 撈取使用者的收藏清單 (JOIN favorite 與 cafe_shop)
$sql = "SELECT c.*, f.created_at AS fav_added_at 
        FROM favorite f 
        JOIN cafe_shop c ON f.cafe_id = c.id 
        WHERE f.user_id = ? 
        ORDER BY f.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$fav_cafes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $fav_cafes[] = $row;
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的收藏 - Cafe Map</title>
    <link rel="stylesheet" href="css/style.css"> <style>
        .fav-container { max-width: 1200px; margin: 80px auto 20px; padding: 20px; }
        .fav-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .fav-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; transition: transform 0.2s; }
        .fav-card:hover { transform: translateY(-5px); }
        .fav-card h3 { margin: 0 0 10px 0; color: #333; }
        .fav-info { font-size: 0.9em; color: #666; margin-bottom: 15px; }
        .fav-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #eee; pt: 10px; }
        .btn-detail { background: #6F4E37; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
        .btn-remove { color: #ff4d4d; text-decoration: none; font-size: 0.85em; }
        .no-fav { text-align: center; padding: 100px; color: #888; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="fav-container">
        <h1>☕ 我的個人收藏</h1>
        <p>這裡記錄了你感興趣的咖啡廳</p>

        <?php if (count($fav_cafes) > 0): ?>
            <div class="fav-grid">
                <?php foreach ($fav_cafes as $cafe): ?>
                    <div class="fav-card">
                        <h3><?= htmlspecialchars($cafe['name']) ?></h3>
                        <div class="fav-info">
                            <p>⭐ 評分：<?= $cafe['rating'] ?></p>
                            <p>📍 <?= htmlspecialchars($cafe['address']) ?></p>
                            <p>🕒 收藏時間：<?= date('Y-m-d', strtotime($cafe['fav_added_at'])) ?></p>
                        </div>
                        <div class="fav-footer">
                            <a href="reviews.php?id=<?= $cafe['id'] ?>" class="btn-detail">查看詳情</a>
                            <a href="javascript:void(0);" 
   class="btn-remove" 
   onclick="handleRemoveFavorite(<?= $cafe['id'] ?>)">
   💔 移除
</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-fav">
                <img src="https://cdn-icons-png.flaticon.com/512/113/113339.png" width="100" style="opacity: 0.3;">
                <p>目前還沒有任何收藏，快去地圖找找吧！</p>
                <a href="cafe_map.php" class="btn-detail">回到地圖</a>
            </div>
        <?php endif; ?>
    </div>
<script>
    function handleRemoveFavorite(cafeId) {
        if (!confirm('確定要從收藏清單移除這間咖啡廳嗎？')) return;

        // 呼叫剛剛建立的 api_favorite.php
        fetch('api_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'remove',
                cafe_id: cafeId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // 成功後直接把該卡片從畫面上移除，或是重新整理頁面
                alert('已移除收藏');
                location.reload(); 
            } else {
                alert('錯誤：' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('連線 API 發生錯誤');
        });
    }
    </script>
</body>
</html>