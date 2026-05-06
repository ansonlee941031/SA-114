<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/src/CafeQueryBuilder.php';
include_once __DIR__ . '/config/google_config.php';

// 接收所有篩選參數以維持 UI 狀態
$searchTerm   = htmlspecialchars($_GET['search'] ?? '');
$selectedRating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$selectedDistance = isset($_GET['distance']) ? (float)$_GET['distance'] : 0;
$selectedPriceGroups = isset($_GET['price']) ? (is_array($_GET['price']) ? $_GET['price'] : [$_GET['price']]) : [];

// 執行 SQL 查詢[cite: 3, 5]
$queryData = \App\CafeQueryBuilder::build($_GET);
$stmt = mysqli_prepare($conn, $queryData['sql']);
if ($stmt) {
    if (!empty($queryData['params'])) { mysqli_stmt_bind_param($stmt, $queryData['types'], ...$queryData['params']); }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$cafesArray = []; $mapData = [];
date_default_timezone_set('Asia/Taipei');
$current_day = date('N'); $now_min = (int)date('H') * 60 + (int)date('i');

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cafe_id = $row['id'];
        $hour_res = mysqli_query($conn, "SELECT open_time, close_time, is_closed FROM cafe_hours WHERE cafe_id = $cafe_id AND day_of_week = $current_day");
        
        $statusClass = 'dot-closed'; $statusText = '○ 已打烊'; $isOpen = false; 
        $active_open = null; $active_close = null; $is_closed_today = 1; $current_priority = 0; $today_parts = [];

        while ($h = mysqli_fetch_assoc($hour_res)) {
            if ($h['is_closed']) { $statusText = '○ 今日公休'; $today_parts = ["今日公休"]; $current_priority = -1; break; }
            $is_closed_today = 0;
            $o_t = $h['open_time']; $c_t = $h['close_time'];
            $o_m = (int)date('H', strtotime($o_t)) * 60 + (int)date('i', strtotime($o_t));
            $c_m = (int)date('H', strtotime($c_t)) * 60 + (int)date('i', strtotime($c_t));
            $today_parts[] = date('H:i', strtotime($o_t)) . "-" . date('H:i', strtotime($c_t));

            if ($now_min >= $o_m && $now_min < $c_m) {
                $isOpen = true;
                if ($current_priority <= 2) {
                    $statusClass = 'dot-open'; $statusText = '● 營業中'; $current_priority = 2;
                    $active_open = $o_t; $active_close = $c_t;
                    if (($c_m - $now_min) <= 30) { $statusClass = 'dot-closing-soon'; $statusText = '● 即將打烊'; }
                }
            } else if ($now_min < $o_m && ($o_m - $now_min) <= 30) {
                if ($current_priority < 1) { $statusClass = 'dot-opening-soon'; $statusText = '○ 即將開店'; $current_priority = 1; $active_open = $o_t; $active_close = $c_t; }
            }
        }
        $row['status_class'] = $statusClass; $row['status_text'] = $statusText; $row['display_hours'] = implode(", ", $today_parts);
        $cafesArray[] = $row;
        $mapData[] = [ 'id' => $row['id'], 'name' => $row['name'], 'lat' => (float)$row['latitude'], 'lng' => (float)$row['longitude'], 'address' => $row['address'], 'isOpen' => $isOpen, 'open_time' => $active_open, 'close_time' => $active_close, 'is_closed' => $is_closed_today ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>新莊咖啡地圖 - SA-114</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <!-- 地圖與圖例[cite: 5, 6] -->
        <div style="position: relative;">
            <div id="map"></div>
            <div class="map-legend">
                <strong>🕒 營業狀態</strong>
                <div class="legend-item"><span class="dot dot-open"></span> 營業中</div>
                <div class="legend-item"><span class="dot dot-closed"></span> 已打烊</div>
                <div class="legend-item"><span class="dot dot-opening-soon"></span> 即將開店</div>
                <div class="legend-item"><span class="dot dot-closing-soon"></span> 即將打烊</div>
            </div>
        </div>

        <form method="GET" id="filterForm">
            <!-- 恢復：頂部快速篩選標籤[cite: 4, 5] -->
            <div class="filter-header">
                <div class="tag-container">
                    <strong style="margin-right: 10px;">快速篩選</strong>
                    <?php 
                    $tags = ['socket'=>'插座', 'no_limit'=>'不限時', 'parking'=>'停車位', 'wifi'=>'WiFi', 'outdoor'=>'戶外座位', 'seats'=>'室內座位', 'dessert'=>'甜點', 'toilet'=>'廁所', 'no_min_consume'=>'低消限制'];
                    foreach($tags as $key => $lbl): ?>
                        <label><input type="checkbox" name="<?= $key ?>" value="1" <?= isset($_GET[$key]) ? 'checked' : ''; ?>> <?= $lbl ?></label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn" style="margin-left: auto;">執行篩選</button>
                </div>
            </div>

            <div class="main-layout">
                <!-- 恢復：左側進階篩選區[cite: 4, 5] -->
                <aside class="sidebar">
                    <div class="filter-section">
                        <h4>顧客評分</h4>
                        <?php foreach ([4.5, 4.0, 3.5, 0] as $r): ?>
                            <label><input type="radio" name="rating" value="<?= $r ?>" <?= ($selectedRating == $r) ? 'checked' : ''; ?>> <?= $r == 0 ? '不限' : $r.'星以上' ?></label><br>
                        <?php endforeach; ?>
                    </div>
                    <div class="filter-section">
                        <h4>價格範圍 (低消)</h4>
                        <?php foreach (['1'=>'1-50', '2'=>'51-100', '3'=>'101-150', '4'=>'151-200', '5'=>'201-500'] as $v => $l): ?>
                            <label><input type="checkbox" name="price[]" value="<?= $v ?>" <?= in_array($v, $selectedPriceGroups) ? 'checked' : ''; ?>> <?= $l ?></label><br>
                        <?php endforeach; ?>
                    </div>
                    <div class="filter-section">
                        <h4>距離範圍</h4>
                        <?php foreach ([0.5, 1.0, 2.0, 0] as $d): ?>
                            <label><input type="radio" name="distance" value="<?= $d ?>" <?= ($selectedDistance == $d) ? 'checked' : ''; ?>> <?= $d == 0 ? '不限' : $d.'km 內' ?></label><br>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">套用所有篩選</button>
                </aside>

                <!-- 右側內容區[cite: 4, 5] -->
                <div class="content-wrapper">
                    <div class="search-container">
                        <input type="text" name="search" id="keywordSearch" placeholder="搜尋店名或地址..." value="<?= $searchTerm ?>" class="search-input">
                        <button type="submit" class="search-btn">🔍 搜尋</button>
                    </div>

                    <main class="card-list">
                        <?php if(!empty($cafesArray)): ?>
                            <?php foreach ($cafesArray as $row): ?>
                                <div class="card cafe-card" data-name="<?= htmlspecialchars($row['name']) ?>" data-address="<?= htmlspecialchars($row['address']) ?>">
                                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                                    <div class="status-tag">
                                        <span class="dot <?= $row['status_class'] ?>"></span>
                                        <strong class="<?= $row['status_class'] ?>-text"><?= $row['status_text'] ?></strong>
                                    </div>
                                    <p>📍 <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $row['latitude'] ?>,<?= $row['longitude'] ?>" target="_blank" class="nav-link"><?= htmlspecialchars($row['address']) ?></a></p>
                                    <p>🕒 <strong>今日營業時間：</strong><br><?= htmlspecialchars($row['display_hours']) ?></p>
                                    <div class="card-footer">
                                        <a href="reviews.php?id=<?= $row['id'] ?>" class="review-btn">💬 查看與留言</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-result">沒有找到符合條件的咖啡廳，試著放寬篩選條件。</div>
                        <?php endif; ?>
                    </main>
                </div>
            </div>
        </form>
    </div>
    <script>window.cafeData = <?php echo json_encode($mapData); ?>;</script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/map.js"></script>
</body>
</html>