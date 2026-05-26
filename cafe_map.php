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

// 接收從 route_plan.php 傳來的店家 ID
$targetCafeId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// 執行 SQL 查詢
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
        $cafeName = $row['name'];
        $lat = (float)$row['latitude'];
        $lng = (float)$row['longitude'];

        // --- [保留原有] 座標校正邏輯 ---
        if (strpos($cafeName, '左轉靠右') !== false) { $lat = 25.03221100; $lng = 121.44773300; }
        elseif (strpos($cafeName, '漂夢島') !== false) { $lat = 25.06321100; $lng = 121.45552200; }
        elseif (strpos($cafeName, "D'or caf'e") !== false || strpos($cafeName, '兜咖啡') !== false) { $lat = 25.06151100; $lng = 121.45992200; }
        elseif (strpos($cafeName, 'May i COFFEE you') !== false || strpos($cafeName, '美艾咖啡友') !== false) { $lat = 25.05892100; $lng = 121.44723400; }
        elseif (strpos($cafeName, 'm&y cafe') !== false) { $lat = 25.05553300; $lng = 121.46112200; }
        elseif (strpos($cafeName, '朝暮豆行') !== false) { $lat = 25.05112200; $lng = 121.45223300; }
        elseif (strpos($cafeName, '山林咖啡') !== false) { $lat = 25.02113300; $lng = 121.42554400; }
        elseif (strpos($cafeName, '林椐咖啡') !== false) { $lat = 25.02556600; $lng = 121.41957700; }
        
        if (strpos($cafeName, '迴龍站') !== false) { $lat = 25.02190; $lng = 121.41130; }
        elseif (strpos($cafeName, '新莊站') !== false) { $lat = 25.03472; $lng = 121.45583; }

        $row['latitude'] = $lat;
        $row['longitude'] = $lng;

        // 距離格式化
        $dist = $row['distance_meters'] ?? 0;
        $row['dist_text'] = ($dist >= 1000) ? number_format($dist / 1000, 1) . ' km' : $dist . ' m';

        // 檢查收藏狀態
        $is_favorite = false;
        if (isset($_SESSION['user_id'])) {
            $fav_sql = "SELECT 1 FROM favorite WHERE user_id = ? AND cafe_id = ?";
            $fav_stmt = mysqli_prepare($conn, $fav_sql);
            mysqli_stmt_bind_param($fav_stmt, "si", $_SESSION['user_id'], $cafe_id);
            mysqli_stmt_execute($fav_stmt);
            $fav_res = mysqli_stmt_get_result($fav_stmt);
            $is_favorite = mysqli_num_rows($fav_res) > 0;
        }
        $row['is_favorite'] = $is_favorite;

        // 營業時間處理
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
        $formatted_hours = implode(", ", $today_parts);
        $row['status_class'] = $statusClass; 
        $row['status_text'] = $statusText; 
        $row['display_hours'] = $formatted_hours;
        
        $cafesArray[] = $row;
        $mapData[] = [ 
            'id' => $row['id'], 'name' => $row['name'], 'lat' => $lat, 'lng' => $lng, 
            'address' => $row['address'], 'today_hours' => $formatted_hours, 'isOpen' => $isOpen,
            'open_time' => $active_open, 'close_time' => $active_close, 'is_closed' => $is_closed_today 
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>新莊咖啡地圖 - SA-114</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* 補回原本遺失的狀態樣式 */
        .dot-opening-soon { background-color: #f1c40f !important; }
        .dot-closing-soon { background-color: #e67e22 !important; }
        .dot-opening-soon-text { color: #f1c40f; }
        .dot-closing-soon-text { color: #e67e22; }
        .highlight-card { border: 2px solid #8B4513 !important; background-color: #fffaf0 !important; }
        .rating-stars { color: #f1c40f; margin: 4px 0; font-size: 0.9rem; }
        .cafe-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; font-size: 0.85rem; color: #555; margin: 8px 0; border-top: 1px solid #eee; padding-top: 8px; }
        .cafe-meta i { width: 16px; margin-right: 5px; color: #8B4513; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
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
            <div class="filter-header">
                <div class="tag-container">
                    <strong style="margin-right: 10px;">快速篩選</strong>
                    <?php 
                    $tags = ['socket'=>'插座', 'no_limit'=>'不限時', 'parking'=>'停車位', 'wifi'=>'WiFi', 'outdoor'=>'戶外座位', 'seats'=>'室內座位', 'dessert'=>'甜點', 'toilet'=>'廁所', 'no_min_consume'=>'無低消'];
                    foreach($tags as $key => $lbl): ?>
                        <label><input type="checkbox" name="<?= $key ?>" value="1" <?= isset($_GET[$key]) ? 'checked' : ''; ?>> <?= $lbl ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="main-layout">
                <aside class="sidebar">
                    <div class="filter-section">
                        <h4>顧客評分</h4>
                        <?php foreach ([4.5, 4.0, 3.5, 0] as $r): ?>
                            <label><input type="radio" name="rating" value="<?= $r ?>" <?= ($selectedRating == $r) ? 'checked' : ''; ?>> <?= $r == 0 ? '不限' : $r.'星以上' ?></label><br>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-section">
                        <h4>距離範圍</h4>
                        <?php foreach ([0.5, 1.0, 2.0, 0] as $d): ?>
                            <label>
                                <input type="radio" name="distance" value="<?= $d ?>" <?= ($selectedDistance == $d) ? 'checked' : ''; ?>> 
                                <?= $d == 0 ? '不限' : $d.'km 內' ?>
                            </label><br>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-section">
                        <h4>價格範圍 (低消)</h4>
                        <?php foreach (['1'=>'1-50', '2'=>'51-100', '3'=>'101-150', '4'=>'151-200', '5'=>'201-500'] as $v => $l): ?>
                            <label><input type="checkbox" name="price[]" value="<?= $v ?>" <?= in_array($v, $selectedPriceGroups) ? 'checked' : ''; ?>> <?= $l ?></label><br>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">套用所有篩選</button>
                </aside>

                <div class="content-wrapper">
                    <div class="search-container">
                        <input type="text" name="search" placeholder="搜尋店名或地址..." value="<?= $searchTerm ?>" class="search-input">
                        <button type="submit" class="search-btn">🔍 搜尋</button>
                    </div>

                    <main class="card-list">
                        <?php if(!empty($cafesArray)): ?>
                            <?php foreach ($cafesArray as $row): ?>
                                <div class="card cafe-card <?= ($targetCafeId == $row['id']) ? 'highlight-card' : '' ?>" id="cafe-<?= $row['id'] ?>">
                                    <div style="display: flex; justify-content: space-between;">
                                        <h3 style="margin: 0;"><?= htmlspecialchars($row['name']) ?></h3>
                                        <button type="button" class="fav-btn" onclick="toggleFav(<?= $row['id'] ?>, this)" style="background:none; border:none; cursor:pointer; font-size: 1.4rem;">
                                            <i class="<?= $row['is_favorite'] ? 'fa-solid' : 'fa-regular' ?> fa-heart" style="color: <?= $row['is_favorite'] ? '#ff4d4d' : '#ccc' ?>;"></i>
                                        </button>
                                    </div>

                                    <div class="rating-stars">
                                        <?php 
                                        $ratingValue = (float)$row['rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $ratingValue) echo '<i class="fa-solid fa-star"></i>';
                                            elseif ($i - 0.5 <= $ratingValue) echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                            else echo '<i class="fa-regular fa-star" style="color:#ccc;"></i>';
                                        }
                                        ?>
                                        <span class="rating-num">(<?= number_format($ratingValue, 1) ?>)</span>
                                    </div>

                                    <div class="cafe-meta">
                                        <span><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($row['phone'] ?: '無電話') ?></span>
                                        <span><i class="fa-solid fa-coins"></i> 低消: <?= (int)$row['min_consumption'] > 0 ? $row['min_consumption'].'元' : '無限制' ?></span>
                                        <span><i class="fa-solid fa-person-walking"></i> 距離: <?= $row['dist_text'] ?></span>
                                        <span><i class="fa-solid fa-clock"></i> 今日: <?= htmlspecialchars($row['display_hours']) ?></span>
                                    </div>

                                    <div class="status-tag">
                                        <span class="dot <?= $row['status_class'] ?>"></span>
                                        <strong class="<?= $row['status_class'] ?>-text"><?= $row['status_text'] ?></strong>
                                    </div>

<p>📍 <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $row['latitude'] ?>,<?= $row['longitude'] ?>" target="_blank" class="nav-link"><?= htmlspecialchars($row['address']) ?></a></p>                                    <div class="card-footer">
                                        <a href="reviews.php?id=<?= $row['id'] ?>" class="review-btn">💬 查看與留言</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </main>
                </div>
            </div>
        </form>
    </div>

    <script>
        window.cafeData = <?php echo json_encode($mapData); ?>;
        window.targetCafeId = <?php echo json_encode($targetCafeId); ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/map.js"></script>
</body>
</html>
