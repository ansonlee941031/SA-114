<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';

// 獲取目前選擇的路線
$selectedRoute = $_GET['route'] ?? '235';
$isMRT = (strpos($selectedRoute, '捷運') !== false);

// 查詢該路線經過的所有店家及其座標
$sql = "SELECT s.id, s.name, s.address, s.latitude, s.longitude, t.transport_name 
        FROM cafe_shop s
        JOIN cafe_transport t ON s.id = t.cafe_id
        WHERE t.transport_name = ? OR t.transport_name LIKE ?";
$searchParam = "%$selectedRoute%";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $selectedRoute, $searchParam);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$stations = [];
$cafes = [];

while ($row = mysqli_fetch_assoc($result)) {
    $lat = (float)$row['latitude']; $lng = (float)$row['longitude'];
    $stName = $row['transport_name']; $cafeName = $row['name'];
    
    // 1. 捷運站點座標修正
    if ($isMRT && !isset($stations[$stName])) {
        if (strpos($stName, '新北產業園區站') !== false) {
            $lat = 25.06155; $lng = 121.45985;
        } elseif (strpos($stName, '丹鳳站') !== false) {
            $lat = 25.02900; $lng = 121.42294;
        } elseif (strpos($stName, '輔大站') !== false) {
            $lat = 25.03265; $lng = 121.43494;
        } elseif (strpos($stName, '新莊站') !== false) {
            $lat = 25.03612; $lng = 121.45213;
        } elseif (strpos($stName, '頭前庄站') !== false) {
            $lat = 25.03930; $lng = 121.46113;
        } elseif (strpos($stName, '幸福站') !== false) {
            $lat = 25.04965; $lng = 121.45983;
        } 
        $stations[$stName] = ['name' => $stName, 'lat' => $lat, 'lng' => $lng];
    }
    
    // 2. 店家座標手動校正 (根據使用者資料提供) - 100% 維持原樣不變
    $cafeLat = $lat; $cafeLng = $lng;
    if (strpos($cafeName, '左轉靠右') !== false) { $cafeLat = 25.06040; $cafeLng = 121.45820; }
    elseif (strpos($cafeName, '漂夢島') !== false) { $cafeLat = 25.06450; $cafeLng = 121.45630; }
    elseif (strpos($cafeName, "D'or caf'e") !== false || strpos($cafeName, '兜咖啡') !== false) { $cafeLat = 25.06280; $cafeLng = 121.45920; }
    elseif (strpos($cafeName, 'May i COFFEE you') !== false || strpos($cafeName, '美艾咖啡友') !== false) { $cafeLat = 25.05602; $cafeLng = 121.45145; }
    elseif (strpos($cafeName, 'm&y cafe') !== false) { $cafeLat = 25.04350; $cafeLng = 121.43420; }
    elseif (strpos($cafeName, '朝暮豆行') !== false) { $cafeLat = 25.03450; $cafeLng = 121.44685; }
    elseif (strpos($cafeName, '山林咖啡') !== false) { $cafeLat = 25.02105; $cafeLng = 121.43037; }
    elseif (strpos($cafeName, '林椐咖啡') !== false) { $cafeLat = 25.03550; $cafeLng = 121.45030; }
    $cafes[] = ['id' => $row['id'], 'name' => $cafeName, 'lat' => $cafeLat, 'lng' => $cafeLng];
}

$routeListSql = "SELECT DISTINCT transport_name FROM cafe_transport ORDER BY transport_name";
$routeListRes = mysqli_query($conn, $routeListSql);

// 公車轉折點定義 (維持原邏輯) - 100% 維持原樣不變
$allRoutePaths = [
    '公車 235' => [[25.0165,121.421],[25.0215,121.4245],[25.0345,121.44685],[25.03472,121.45583]],
    '公車 299' => [[25.02,121.42],[25.0345,121.44685],[25.0485,121.455],[25.061,121.4655]],
    '公車 615' => [[25.0215,121.4245],[25.0425,121.4445],[25.061,121.4655],[25.049,121.5135]],
    '公車 845' => [[25.0215,121.4245],[25.0385,121.4455],[25.0485,121.455]],
    '公車 859' => [[25.06,121.445],[25.045,121.445],[25.0345,121.44685],[25.028,121.4315],[25.021,121.418],[25.025,121.41]],
    '公車 99'  => [[25.0165,121.4245],[25.0215,121.4245],[25.0345,121.44685],[25.041,121.4465],[25.0485,121.455],[25.046,121.4625]]
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>路線規劃 - 咖啡巡禮</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2><i class="fa-solid fa-map-location-dot"></i> <?= htmlspecialchars($selectedRoute) ?> 路線規劃</h2>
        <div class="selector-box">
            <form method="GET">
                <label>選擇路線：</label>
                <select name="route" onchange="this.form.submit()" class="search-input" style="width: auto;">
                    <?php while($r = mysqli_fetch_assoc($routeListRes)): ?>
                        <option value="<?= $r['transport_name'] ?>" <?= $selectedRoute == $r['transport_name'] ? 'selected' : '' ?>>
                            <?= $r['transport_name'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <div class="route-container">
            <div id="routeMap"></div>
            <aside class="route-sidebar">
                <h3>沿線店家清單</h3>
                <?php foreach ($cafes as $c): ?>
                    <div class="cafe-item-block">
                        <a href="cafe_map.php?id=<?= $c['id'] ?>">☕ <?= htmlspecialchars($c['name']) ?></a>
                    </div>
                <?php endforeach; ?>
            </aside>
        </div>
    </div>

    <button type="button" id="routeLocateBtn" class="route-locate-btn" title="定位我的位置">
        <i class="fa-solid fa-crosshairs"></i>
    </button>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
    <script>
        var map = L.map('routeMap').setView([25.045, 121.450], 14);
        var userLocationMarker = null;
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var currentRoute = <?php echo json_encode($selectedRoute); ?>;
        var stations = <?php echo json_encode(array_values($stations)); ?>;
        var cafes = <?php echo json_encode($cafes); ?>;
        var allRoutePaths = <?php echo json_encode($allRoutePaths); ?>;
        var markers = [];

        var activePath = allRoutePaths[currentRoute] || [];
        if (activePath.length > 1) {
            L.Routing.control({
                waypoints: activePath.map(p => L.latLng(p[0], p[1])),
                lineOptions: {
                    styles: [{ color: '#FFD700', opacity: 0.7, weight: 8 }]
                },
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                show: false,
                createMarker: function() { return null; } 
            }).addTo(map);
        }

        var mrtIcon = L.divIcon({ className: 'dot-mrt', iconSize: [14, 14] });
        stations.forEach(function(st) {
            var m = L.marker([st.lat, st.lng], { icon: mrtIcon, zIndexOffset: 1000 })
                     .addTo(map).bindPopup(`<b>🚉 ${st.name}</b>`);
            markers.push(m);
        });

        var cafeIcon = L.divIcon({ className: 'dot-cafe', iconSize: [12, 12] });
        cafes.forEach(function(c) {
            var m = L.marker([c.lat, c.lng], { icon: cafeIcon })
                     .addTo(map).bindPopup(`<b>☕ ${c.name}</b><br><a href="cafe_map.php?id=${c.id}">詳細資訊</a>`);
            markers.push(m);
        });

        var legend = L.control({position: 'bottomright'});
        legend.onAdd = function (map) {
            var div = L.DomUtil.create('div', 'legend');
            div.innerHTML += '<i style="background: #FFD700; border-radius: 0; height: 3px; width: 15px;"></i> 公車路徑<br>';
            div.innerHTML += '<i style="background: #ff4d4d"></i> 捷運站點<br>';
            div.innerHTML += '<i style="background: #1976d2"></i> 咖啡店家';
            return div;
        };
        legend.addTo(map);

        document.getElementById('routeLocateBtn').addEventListener('click', function() {
            var locateBtn = this;
            var icon = locateBtn.querySelector('i');
            icon.className = 'fa-solid fa-spinner fa-spin';

            map.locate({ setView: true, maxZoom: 16 });

            map.on('locationfound', function(e) {
                icon.className = 'fa-solid fa-crosshairs';
                if (userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                }

                var blueDotIcon = L.divIcon({
                    className: 'user-location-dot',
                    html: '<div style="background-color: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 8px rgba(0,0,0,0.4);"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });

                userLocationMarker = L.marker(e.latlng, { icon: blueDotIcon }).addTo(map)
                    .bindPopup("📍目前的位置").openPopup();
            });

            map.on('locationerror', function(err) {
                icon.className = 'fa-solid fa-crosshairs';
                alert("無法取得您的位置，請確認瀏覽器是否已開啟定位權限！");
            });
        });
    </script>
</body>
</html>
