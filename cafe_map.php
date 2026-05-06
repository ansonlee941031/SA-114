<?php
// 1. 啟動 Session 並引入設定 (必須在最上方)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/CafeQueryBuilder.php';
include_once __DIR__ . '/config/google_config.php';

// 2. 接收所有篩選參數
$hasSocket    = isset($_GET['socket']) ? 1 : 0;
$hasNoLimit   = isset($_GET['no_limit']) ? 1 : 0;
$hasParking   = isset($_GET['parking']) ? 1 : 0;
$hasWiFi      = isset($_GET['wifi']) ? 1 : 0;
$hasOutdoor   = isset($_GET['outdoor']) ? 1 : 0;
$hasDessert   = isset($_GET['dessert']) ? 1 : 0;
$hasToilet    = isset($_GET['toilet']) ? 1 : 0;
$noMinConsume = isset($_GET['no_min_consume']) ? 1 : 0;
$hasSeat      = isset($_GET['seats']) ? 1 : 0;

$selectedRating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$selectedPriceGroups = isset($_GET['price']) ? $_GET['price'] : [];
$selectedDistance = isset($_GET['distance']) ? (float)$_GET['distance'] : 0;

$queryData = \App\CafeQueryBuilder::build($_GET);
$sql = $queryData['sql'];
$params = $queryData['params'];
$types = $queryData['types'];
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

// 3. 處理查詢結果
$cafesArray = [];
$mapData = [];

// 設定時區並取得現在時間
date_default_timezone_set('Asia/Taipei');
$current_day = date('N'); // 1-7
$current_time = date('H:i:s');

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // [新增] 查詢這家店今天的營業時間
        $cafe_id = $row['id'];
        $hour_sql = "SELECT open_time, close_time, is_closed FROM cafe_hours WHERE cafe_id = $cafe_id AND day_of_week = $current_day";
        $hour_res = mysqli_query($conn, $hour_sql);
        
        $isOpen = false; // 預設為打烊
        if ($hour_row = mysqli_fetch_assoc($hour_res)) {
            // 如果今天沒有公休，且現在時間介於營業時間內
            if ($hour_row['is_closed'] == 0 && $current_time >= $hour_row['open_time'] && $current_time <= $hour_row['close_time']) {
                $isOpen = true;
            }
        }

        $cafesArray[] = $row;
        $mapData[] = [
            'id' => $row['id'], 
            'name' => $row['name'], 
            'lat' => (float)($row['latitude'] ?? 25.035), 
            'lng' => (float)($row['longitude'] ?? 121.445), 
            'address' => $row['address'],
            'rating' => (float)($row['rating'] ?? 0),
            'opening_hours' => $row['opening_hours'],
            'isOpen' => $isOpen // 將營業狀態傳給前端
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新莊咖啡地圖</title>
    <!-- 引入外部 CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    
    <!-- 引入導覽列 -->
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        
        <div style="position: relative;">
            <!-- 地圖區塊 -->
            <div id="map"></div>
            <div class="map-legend">
                <strong>🕒 營業狀態</strong>
                <hr style="margin: 5px 0; border: 0; border-top: 1px solid #eee;">
                <div class="legend-item"><span class="dot dot-open"></span> 營業中</div>
                <div class="legend-item"><span class="dot dot-closed"></span> 已打烊 / 公休</div>
            </div>
        </div>

        <form method="GET">
            <div class="filter-header">
                <div class="tag-container">
                    <strong style="margin-right: 10px;">快速篩選：</strong>
                    <label><input type="checkbox" name="socket" value="1" <?= $hasSocket ? 'checked' : ''; ?>>插座</label>
                    <label><input type="checkbox" name="no_limit" value="1" <?= $hasNoLimit ? 'checked' : ''; ?>>不限時</label>
                    <label><input type="checkbox" name="parking" value="1" <?= $hasParking ? 'checked' : ''; ?>>停車位</label>
                    <label><input type="checkbox" name="wifi" value="1" <?= $hasWiFi ? 'checked' : ''; ?>>WiFi</label>
                    <label><input type="checkbox" name="outdoor" value="1" <?= $hasOutdoor ? 'checked' : ''; ?>>戶外座位</label>
                    <label><input type="checkbox" name="seats" value="1" <?= $hasSeat ? 'checked' : ''; ?>>室內座位</label>
                    <label><input type="checkbox" name="dessert" value="1" <?= $hasDessert ? 'checked' : ''; ?>>甜點</label>
                    <label><input type="checkbox" name="toilet" value="1" <?= $hasToilet ? 'checked' : ''; ?>>廁所</label>
                    <label><input type="checkbox" name="no_min_consume" value="1" <?= $noMinConsume ? 'checked' : ''; ?>>低消限制</label>
                    <button type="submit" class="btn" style="margin-left: auto;">執行篩選</button>
                </div>
            </div>

            <div class="main-layout">
                <aside class="sidebar">
                    <div class="filter-section">
                        <h4>顧客評分</h4>
                        <label><input type="radio" name="rating" value="3.5" <?= ($selectedRating == 3.5) ? 'checked' : ''; ?>> 3.5星以上</label><br>
                        <label><input type="radio" name="rating" value="4.0" <?= ($selectedRating == 4.0) ? 'checked' : ''; ?>> 4.0星以上</label><br>
                        <label><input type="radio" name="rating" value="4.5" <?= ($selectedRating == 4.5) ? 'checked' : ''; ?>> 4.5星以上</label><br>
                        <label><input type="radio" name="rating" value="0" <?= ($selectedRating == 0) ? 'checked' : ''; ?>> 不限</label>
                    </div>

                    <div class="filter-section">
                        <h4>價格範圍 (低消)</h4>
                        <label><input type="checkbox" name="price[]" value="1" <?= in_array("1", $selectedPriceGroups) ? 'checked' : ''; ?>> 1-50</label><br>
                        <label><input type="checkbox" name="price[]" value="2" <?= in_array("2", $selectedPriceGroups) ? 'checked' : ''; ?>> 51-100</label><br>
                        <label><input type="checkbox" name="price[]" value="3" <?= in_array("3", $selectedPriceGroups) ? 'checked' : ''; ?>> 101-150</label><br>
                        <label><input type="checkbox" name="price[]" value="4" <?= in_array("4", $selectedPriceGroups) ? 'checked' : ''; ?>> 151-200</label><br>
                        <label><input type="checkbox" name="price[]" value="5" <?= in_array("5", $selectedPriceGroups) ? 'checked' : ''; ?>> 201-500</label>
                    </div>

                    <div class="filter-section">
                        <h4>距離範圍</h4>
                        <label><input type="radio" name="distance" value="0.5" <?= ($selectedDistance == 0.5) ? 'checked' : ''; ?>> 0.5km 內</label><br>
                        <label><input type="radio" name="distance" value="1.0" <?= ($selectedDistance == 1.0) ? 'checked' : ''; ?>> 1.0km 內</label><br>
                        <label><input type="radio" name="distance" value="2.0" <?= ($selectedDistance == 2.0) ? 'checked' : ''; ?>> 2.0km 內</label><br>
                        <label><input type="radio" name="distance" value="0" <?= ($selectedDistance == 0) ? 'checked' : ''; ?>> 不限</label>
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%;">套用篩選</button>
                </aside>

                <main class="card-list">
                    <?php if (!empty($cafesArray)): ?>
                        <?php foreach ($cafesArray as $row): ?>
                            <div class="card" id="cafe-<?= $row['id'] ?>">
                                <div class="rating">★ <?= $row['rating'] ?? '新開幕' ?></div>
                                <h3><?= htmlspecialchars($row['name']); ?></h3>
                                <p>📍 <?= htmlspecialchars($row['address']); ?></p>
                                <p>📞 <?= htmlspecialchars($row['phone']); ?></p>
                                <p>🕒 <strong>營業時間：</strong><br><?= nl2br(htmlspecialchars($row['opening_hours'])); ?></p>
                                <p>💰 低消：<?= ($row['min_consumption'] == 0) ? "無低消" : $row['min_consumption'] . " 元"; ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: #fff; border-radius: 15px;">
                            沒有找到符合條件的咖啡廳，試試看減少篩選標籤吧！
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        </form>
    </div>

    <!-- 傳遞 PHP 變數給 JS -->
    <script>
        window.cafeData = <?php echo json_encode($mapData); ?>;
    </script>
    
    <!-- 引入外部 JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/map.js"></script>

</body>
</html>