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

// 執行 SQL 查詢 (攔截距離參數，將過濾工作交由前端真實 GPS 處理)
$apiParams = $_GET;
if (isset($apiParams['distance'])) {
    unset($apiParams['distance']); 
}
$queryData = \App\CafeQueryBuilder::build($apiParams);$stmt = mysqli_prepare($conn, $queryData['sql']);
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
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
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
                    $tags = ['socket'=>'插座', 'no_limit'=>'不限時', 'parking'=>'停車位', 'wifi'=>'WiFi', 'seats'=>'室內座位', 'dessert'=>'甜點', 'toilet'=>'廁所', 'no_min_consume'=>'無低消'];
                    foreach($tags as $key => $lbl): ?>
                        <label><input type="checkbox" name="<?= $key ?>" value="1" <?= isset($_GET[$key]) ? 'checked' : ''; ?>> <?= $lbl ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="clearFiltersBtn" class="clear-filters-btn">
                    <i class="fa-solid fa-trash-can"></i> 清除所有選項
                </button>
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
                        <?php foreach (['1'=>'1-50', '2'=>'51-100', '3'=>'101-150', '4'=>'151-200'] as $v => $l): ?>
                            <label><input type="checkbox" name="price[]" value="<?= $v ?>" <?= in_array($v, $selectedPriceGroups) ? 'checked' : ''; ?>> <?= $l ?></label><br>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <div class="content-wrapper">
                    <div class="search-container">
                        <div class="search-box-wrapper">
                            <input type="text" name="search" id="searchInput" placeholder="搜尋店名或地址..." value="<?= $searchTerm ?>" class="search-input">
                            <button type="button" id="searchBtn" class="search-btn" title="點擊搜尋"><i class="fa-solid fa-magnifying-glass"></i> 確認</button>
                        </div>
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
<span class="cafe-distance-field" style="display: none;" 
      data-lat="<?= $row['latitude'] ?>" 
      data-lng="<?= $row['longitude'] ?>">
    <i class="fa-solid fa-person-walking"></i> 距離: <span class="dist-num">計算中</span>
</span>
                                        <span><i class="fa-solid fa-clock"></i> 今日: <?= htmlspecialchars($row['display_hours']) ?></span>
                                    </div>

                                    <div class="status-tag">
                                        <span class="dot <?= $row['status_class'] ?>"></span>
                                        <strong class="<?= $row['status_class'] ?>-text"><?= $row['status_text'] ?></strong>
                                    </div>

                                    <p>📍 <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($row['name'] . ' ' . $row['address']) ?>" target="_blank" class="nav-link" title="點擊開啟 Google 導航">
                                        <?= htmlspecialchars($row['address']) ?>
                                    </a></p>                                        
                                    <a href="reviews.php?id=<?= $row['id'] ?>" class="review-btn">💬 查看與留言</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </main>
                </div>
            </div>
        </form>
    </div>

    <button type="button" id="locateMeBtn" class="floating-btn locate-me-btn" title="定位我的位置">
        <i class="fa-solid fa-crosshairs"></i>
    </button>

    <button type="button" id="backToTopBtn" class="floating-btn back-to-top" title="返回最頂端">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <script>
        window.cafeData = <?php echo json_encode($mapData); ?>;
        window.targetCafeId = <?php echo json_encode($targetCafeId); ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/map.js?v=<?= time() ?>"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const filterForm = document.getElementById('filterForm');
            const searchInput = document.getElementById('searchInput');
            const searchBtn = document.getElementById('searchBtn');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');
            const backToTopBtn = document.getElementById('backToTopBtn');
            const locateMeBtn = document.getElementById('locateMeBtn');
            
            let userLocationMarker = null;
            // 🌟 記住使用者的真實座標
            let currentUserLat = null;
            let currentUserLng = null;

            const submitBtns = filterForm.querySelectorAll('button[type="submit"]');
            submitBtns.forEach(btn => { if(btn.id !== 'searchBtn') btn.style.display = 'none'; });

            // 🌟 頁面載入防呆：如果有距離參數但沒定位，強制切回「不限」
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('distance') && urlParams.get('distance') !== "0") {
                document.querySelector('input[name="distance"][value="0"]').checked = true;
                urlParams.set('distance', "0");
                window.history.replaceState({}, '', window.location.pathname + '?' + urlParams.toString());
            }

            function fetchAndUpdate() {
                const formData = new FormData(filterForm);
                const params = new URLSearchParams(formData);
                const url = 'cafe_map.php?' + params.toString();
                
                // 讀取當前選擇的「距離限制」
                const currentDistLimit = parseFloat(formData.get('distance')) || 0;

                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        const newCardList = doc.querySelector('.card-list');
                        let newMapData = [];
                        
                        const scripts = doc.querySelectorAll('script');
                        scripts.forEach(script => {
                            if (script.textContent.includes('window.cafeData')) {
                                const match = script.textContent.match(/window\.cafeData\s*=\s*(\[.*?\]);/s);
                                if (match && match[1]) {
                                    newMapData = JSON.parse(match[1]);
                                }
                            }
                        });

                        let filteredMapData = [];
                        let visibleCardsCount = 0;

                        if (newCardList && newMapData.length > 0) {
                            const cards = newCardList.querySelectorAll('.card');
                            const R = 6371000; // 地球半徑

                            cards.forEach(card => {
                                const field = card.querySelector('.cafe-distance-field');
                                const cafeIdStr = card.id.replace('cafe-', '');
                                const cafeId = parseInt(cafeIdStr);

                                // 如果已經定位，開始計算真實距離
                                if (currentUserLat !== null && currentUserLng !== null && field) {
                                    const cafeLat = parseFloat(field.getAttribute('data-lat'));
                                    const cafeLng = parseFloat(field.getAttribute('data-lng'));
                                    
                                    const dLat = (cafeLat - currentUserLat) * Math.PI / 180;
                                    const dLng = (cafeLng - currentUserLng) * Math.PI / 180;
                                    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(currentUserLat * Math.PI / 180) * Math.cos(cafeLat * Math.PI / 180) * Math.sin(dLng/2) * Math.sin(dLng/2);
                                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                                    const distanceMeters = R * c;
                                    const distanceKm = distanceMeters / 1000;

                                    // 🌟 核心過濾：如果超過篩選距離，直接隱藏卡片！
                                    if (currentDistLimit > 0 && distanceKm > currentDistLimit) {
                                        card.style.display = 'none';
                                    } else {
                                        // 沒超過，填入距離並顯示
                                        let distText = distanceMeters >= 1000 ? distanceKm.toFixed(1) + " km" : Math.round(distanceMeters) + " m";
                                        field.querySelector('.dist-num').innerText = distText;
                                        field.style.display = 'inline-block';
                                        
                                        // 把這家店保留到地圖標記清單中
                                        const md = newMapData.find(m => m.id === cafeId);
                                        if (md) filteredMapData.push(md);
                                        visibleCardsCount++;
                                    }
                                } else {
                                    // 尚未定位 (此時距離篩選必為「不限」)，全部保留
                                    const md = newMapData.find(m => m.id === cafeId);
                                    if (md) filteredMapData.push(md);
                                    visibleCardsCount++;
                                }
                            });
                            
                            // 🌟 防呆：如果過濾後沒有半家店，顯示提示
                            if (visibleCardsCount === 0) {
                                newCardList.innerHTML = '<div class="no-result" style="grid-column: 1/-1; text-align: center; padding: 50px; color: #888;">找不到符合距離與條件的咖啡廳 ☕</div>';
                            }
                            
                            document.querySelector('.card-list').innerHTML = newCardList.innerHTML;
                        }

                        // 通知地圖只重繪「有通過距離測試」的店家
                        if (typeof window.updateMapMarkers === 'function') {
                            window.updateMapMarkers(filteredMapData);
                        }

                        window.history.pushState({}, '', url);
                    })
                    .catch(err => console.error('更新失敗:', err));
            }

            const inputs = filterForm.querySelectorAll('input[type="checkbox"], input[type="radio"]');
            inputs.forEach(input => {
                input.addEventListener('change', function(e) {
                    // 🌟 攔截器：如果點了距離篩選，但沒開 GPS，跳警告並退回「不限」
                    if (this.name === 'distance' && this.value !== "0" && currentUserLat === null) {
                        alert("⚠️ 請先點擊右下角的「📍定位我的位置」按鈕，獲取真實位置後才能使用距離篩選！");
                        document.querySelector('input[name="distance"][value="0"]').checked = true;
                        return; // 終止後續動作
                    }
                    fetchAndUpdate();
                });
            });
            
            searchBtn.addEventListener('click', fetchAndUpdate);
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); fetchAndUpdate(); }
            });

            clearFiltersBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterForm.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                filterForm.querySelectorAll('input[type="radio"]').forEach(radio => {
                    if (radio.value === "0") radio.checked = true; else radio.checked = false;
                });
                fetchAndUpdate();
            });

            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) backToTopBtn.classList.add('show');
                else backToTopBtn.classList.remove('show');
            });
            backToTopBtn.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });

            // --- 定位按鈕邏輯 ---
            locateMeBtn.addEventListener('click', function() {
                const leafletMap = window.map || (typeof map !== 'undefined' ? map : null);
                if (!leafletMap) return;

                const icon = locateMeBtn.querySelector('i');
                icon.className = 'fa-solid fa-spinner fa-spin';

                leafletMap.locate({ setView: true, maxZoom: 16 });

                leafletMap.on('locationfound', function(e) {
                    icon.className = 'fa-solid fa-crosshairs';
                    if (userLocationMarker) leafletMap.removeLayer(userLocationMarker);

                    const blueDotIcon = L.divIcon({
                        className: 'user-location-dot',
                        html: '<div style="background-color: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 8px rgba(0,0,0,0.4);"></div>',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8]
                    });

                    userLocationMarker = L.marker(e.latlng, { icon: blueDotIcon }).addTo(leafletMap).bindPopup("📍目前的位置").openPopup();

                    // 🌟 定位成功後：記住座標，並「直接觸發一次 AJAX」讓畫面把距離算出來！
                    currentUserLat = e.latlng.lat;
                    currentUserLng = e.latlng.lng;
                    fetchAndUpdate();
                });

                leafletMap.on('locationerror', function(err) {
                    icon.className = 'fa-solid fa-crosshairs';
                    alert("無法取得您的位置，請確認瀏覽器是否已開啟定位權限！");
                });
            });
        });
        </script>
</body>
</html>
