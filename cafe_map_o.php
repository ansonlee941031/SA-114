<?php
require_once __DIR__ . '/config/db.php';

// --- [功能完整保留] 接收所有篩選參數 ---
$hasSocket = isset($_GET['socket']) ? 1 : 0;
$hasNoLimit = isset($_GET['no_limit']) ? 1 : 0;
$hasParking = isset($_GET['parking']) ? 1 : 0;
$hasWiFi    = isset($_GET['wifi']) ? 1 : 0;
$hasOutdoor = isset($_GET['outdoor']) ? 1 : 0;
$hasDessert = isset($_GET['dessert']) ? 1 : 0;
$hasToilet  = isset($_GET['toilet']) ? 1 : 0;
$noMinConsume = isset($_GET['no_min_consume']) ? 1 : 0;
$hasSeat    = isset($_GET['seats']) ? 1 : 0;

$selectedRating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$selectedPriceGroups = isset($_GET['price']) ? $_GET['price'] : [];
$selectedDistance = isset($_GET['distance']) ? (float)$_GET['distance'] : 0;

// --- [功能完整保留] SQL 查詢邏輯 ---
$sql = "SELECT cafe_shop.* FROM cafe_shop INNER JOIN label ON cafe_shop.id = label.cafe_id WHERE 1 = 1";
$params = [];
$types = "";

if ($hasSocket) { $sql .= " AND label.`插座` = ?"; $params[] = 1; $types .= "i"; }
if ($hasNoLimit) { $sql .= " AND label.`不限時` = ?"; $params[] = 1; $types .= "i"; }
if ($hasParking) { $sql .= " AND label.`停車位` = ?"; $params[] = 1; $types .= "i"; }
if ($hasWiFi) { $sql .= " AND label.`wifi` = ?"; $params[] = 1; $types .= "i"; }
if ($hasOutdoor) { $sql .= " AND label.`戶外座位` = ?"; $params[] = 1; $types .= "i"; }
if ($hasDessert) { $sql .= " AND label.`甜點` = ?"; $params[] = 1; $types .= "i"; }
if ($hasToilet) { $sql .= " AND label.`廁所` = ?"; $params[] = 1; $types .= "i"; }
if ($noMinConsume) { $sql .= " AND cafe_shop.`min_consumption` = ?"; $params[] = 0; $types .= "i"; }
if ($hasSeat) { $sql .= " AND label.`室內座位` = ?"; $params[] = 1; $types .= "i"; }

if ($selectedRating > 0) { $sql .= " AND cafe_shop.`rating` >= ?"; $params[] = $selectedRating; $types .= "d"; }
if (!empty($selectedPriceGroups)) {
    $priceClauses = [];
    foreach ($selectedPriceGroups as $group) {
        if ($group == "1") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 1 AND 50";
        if ($group == "2") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 51 AND 100";
        if ($group == "3") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 101 AND 150";
        if ($group == "4") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 151 AND 200";
        if ($group == "5") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 201 AND 500";
    }
    if (!empty($priceClauses)) { $sql .= " AND (" . implode(" OR ", $priceClauses) . ")"; }
}
if ($selectedDistance > 0) { $sql .= " AND cafe_shop.`distance_meters` <= ?"; $params[] = $selectedDistance * 1000; $types .= "i"; }

$sql .= " ORDER BY cafe_shop.id ASC";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$cafesArray = [];
$mapData = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cafesArray[] = $row;
        $mapData[] = [
            'id' => $row['id'], 
            'name' => $row['name'], 
            'lat' => (float)($row['latitude'] ?? 25.035), 
            'lng' => (float)($row['longitude'] ?? 121.445), 
            'address' => $row['address'],
            'rating' => (float)($row['rating'] ?? 0),
            'opening_hours' => $row['opening_hours'] // [新增] 傳入地圖資料
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root { --primary-color: #8d6e63; --bg-color: #fcfaf7; }
        body { font-family: 'PingFang TC', 'Microsoft JhengHei', sans-serif; background: var(--bg-color); margin: 0; color: #444; }
        .container { width: 95%; max-width: 1200px; margin: 20px auto; }
        
        #map { width: 100%; height: 420px; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.12); margin-bottom: 25px; border: 4px solid #fff; z-index: 1; }
        .filter-header { background: #fff; padding: 20px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .tag-container { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .tag-container label { background: #f5f5f5; padding: 8px 14px; border-radius: 20px; cursor: pointer; font-size: 14px; transition: 0.2s; border: 1px solid transparent; }
        .tag-container label:hover { background: #ececec; }
        .tag-container input[type="checkbox"] { margin-right: 5px; }
        .btn { background: var(--primary-color); color: white; border: none; padding: 10px 22px; border-radius: 25px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn:hover { background: #6d4c41; transform: translateY(-2px); }
        .main-layout { display: flex; gap: 25px; align-items: flex-start; }
        .sidebar { width: 260px; flex-shrink: 0; background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .filter-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .filter-section h4 { margin-bottom: 10px; color: var(--primary-color); }
        .card-list { flex-grow: 1; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 15px; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s; position: relative; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
        .card h3 { margin: 0 0 10px 0; font-size: 1.2em; color: #333; }
        .card .rating { color: #f39c12; font-weight: bold; margin-bottom: 8px; }
        .card p { margin: 5px 0; font-size: 0.9em; color: #666; }
        .highlight-card { border: 2px solid var(--primary-color); background: #fffef0; }

        .marker-pin {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            background: #8d6e63;
            position: absolute;
            transform: rotate(-45deg);
            left: 50%;
            top: 50%;
            margin: -15px 0 0 -15px;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .marker-pin::after {
            content: '';
            width: 14px;
            height: 14px;
            margin: 8px 0 0 8px;
            background: #fff;
            position: absolute;
            border-radius: 50%;
        }
        .pin-gold { background: #f1c40f; }
        .pin-red { background: #e74c3c; }
        .pin-brown { background: #8d6e63; }

        /* 地圖圖例樣式 */
.map-legend {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.9);
    padding: 10px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 1000;
    font-size: 13px;
    border: 1px solid #ddd;
}
.legend-item { display: flex; align-items: center; margin-bottom: 5px; }
.dot { width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; border: 1px solid #fff; }
.dot-red { background: #e74c3c; }   /* 對應 pin-red */
.dot-gold { background: #f1c40f; }  /* 對應 pin-gold */
.dot-brown { background: #8d6e63; } /* 對應 pin-brown */

    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; color: var(--primary-color); margin-bottom: 25px;">☕ 新莊咖啡廳搜尋地圖</h1>
        
        <div style="position: relative;"> <div id="map"></div>
    <div class="map-legend">
        <strong>⭐ 評分等級</strong>
        <hr style="margin: 5px 0; border: 0; border-top: 1px solid #eee;">
        <div class="legend-item"><span class="dot dot-red"></span> 4.5 以上 (極高評價)</div>
        <div class="legend-item"><span class="dot dot-gold"></span> 4.0 - 4.4 (優質推薦)</div>
        <div class="legend-item"><span class="dot dot-brown"></span> 4.0 以下 / 新開幕</div>
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
                                <p>🕒 <strong>營業時間：</strong><?= nl2br(htmlspecialchars($row['opening_hours'])); ?></p>
                                <p>💰 低消：<?= ($row['min_consumption'] == 0) ? "無低消" : $row['min_consumption'] . " 元"; ?></p>
                                <p>📏 距離：<?= htmlspecialchars($row['distance_meters']); ?> 公尺</p>
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

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    var map = L.map('map', { scrollWheelZoom: true }).setView([25.035, 121.445], 15);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(map);

    var cafes = <?php echo json_encode($mapData); ?>;
    var markers = [];

    if (cafes.length > 0) {
        cafes.forEach(function(cafe) {
            var pinColorClass = 'pin-brown';
            if (cafe.rating >= 4.5) {
                pinColorClass = 'pin-red';
            } else if (cafe.rating >= 4.0) {
                pinColorClass = 'pin-gold';
            }

           // 在 cafes.forEach 內部加入顏色判斷
var pinColorClass = 'pin-brown';
if (cafe.rating >= 4.5) {
    pinColorClass = 'pin-red';
} else if (cafe.rating >= 4.0) {
    pinColorClass = 'pin-gold';
}

// 建立 Icon 時套用對應 Class
var icon = L.divIcon({
    className: 'custom-div-icon',
    html: `<div class='marker-pin ${pinColorClass}'></div>`,
    iconSize: [30, 42],
    iconAnchor: [15, 42]
});
            // [修改] 彈出視窗加入營業時間
            var popupContent = `
                <div style="font-family: sans-serif; min-width: 150px;">
                    <b style="color:#8d6e63; font-size:14px;">${cafe.name}</b><br>
                    <span style="color:#666; font-size:12px;">${cafe.address}</span><br>
                    <div style="font-size:11px; color:#444; margin-top:5px; border-top:1px solid #eee; padding-top:5px;">
                        🕒 營業時間：<br>${cafe.opening_hours.replace(/\r\n|\n/g, '<br>')}
                    </div>
                    <button onclick="scrollToCafe(${cafe.id})" style="margin-top:8px; background:#8d6e63; color:white; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; width:100%;">查看詳細卡片</button>
                </div>
            `;

            var marker = L.marker([cafe.lat, cafe.lng], { icon: icon }).addTo(map).bindPopup(popupContent);
            markers.push(marker);
        });
        var group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }

    function scrollToCafe(id) {
        document.querySelectorAll('.card').forEach(c => c.classList.remove('highlight-card'));
        var target = document.getElementById('cafe-' + id);
        if (target) {
            target.classList.add('highlight-card');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
</script>
</body>
</html>