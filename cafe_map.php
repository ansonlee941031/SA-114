<?php
require_once __DIR__ . '/config/db.php';

// 取得使用者選擇的標籤
$hasSocket = isset($_GET['socket']) ? 1 : 0;
$hasNoLimit = isset($_GET['no_limit']) ? 1 : 0;
$hasParking = isset($_GET['parking']) ? 1 : 0;
$hasWiFi    = isset($_GET['wifi']) ? 1 : 0;
$hasOutdoor = isset($_GET['outdoor']) ? 1 : 0;
$hasDessert = isset($_GET['dessert']) ? 1 : 0;
$hasToilet  = isset($_GET['toilet']) ? 1 : 0;
$noMinConsume = isset($_GET['no_min_consume']) ? 1 : 0;
$hasSeat    = isset($_GET['seats']) ? 1 : 0;

// 側欄參數
$selectedRating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$selectedPriceGroups = isset($_GET['price']) ? $_GET['price'] : [];
$selectedDistance = isset($_GET['distance']) ? (float)$_GET['distance'] : 0;

// 基本 SQL：從 cafe_shop 連接 label
$sql = "
    SELECT 
        cafe_shop.id,
        cafe_shop.name,
        cafe_shop.address,
        cafe_shop.phone,
        cafe_shop.opening_hours,
        cafe_shop.rating,
        cafe_shop.distance_meters,
        cafe_shop.min_consumption
    FROM cafe_shop
    INNER JOIN label ON cafe_shop.id = label.cafe_id
    WHERE 1 = 1
";

$params = [];
$types = "";

// 動態條件：有勾才加進去
if ($hasSocket) {
    $sql .= " AND label.`插座` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($hasNoLimit) {
    $sql .= " AND label.`不限時` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($hasParking) {
    $sql .= " AND label.`停車位` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($hasWiFi) {
    $sql .= " AND label.`wifi` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($hasOutdoor) {
    $sql .= " AND label.`戶外座位` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($hasDessert) {
    $sql .= " AND label.`甜點` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($hasToilet) {
    $sql .= " AND label.`廁所` = ?";
    $params[] = 1;
    $types .= "i";
}
if ($noMinConsume) {
    $sql .= " AND cafe_shop.`min_consumption` = ?";
    $params[] = 0; 
    $types .= "i";
}
if ($hasSeat) {
    $sql .= " AND label.`室內座位` = ?";
    $params[] = 1;
    $types .= "i";
}

// 側欄篩選
if ($selectedRating > 0) {
    $sql .= " AND cafe_shop.`rating` >= ?";
    $params[] = $selectedRating;
    $types .= "d";
}
if (!empty($selectedPriceGroups)) {
    $priceClauses = [];
    foreach ($selectedPriceGroups as $group) {
        if ($group == "1") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 1 AND 50";
        if ($group == "2") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 51 AND 100";
        if ($group == "3") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 101 AND 150";
        if ($group == "4") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 151 AND 200";
        if ($group == "5") $priceClauses[] = "cafe_shop.`min_consumption` BETWEEN 201 AND 500";
    }
    if (!empty($priceClauses)) {
        $sql .= " AND (" . implode(" OR ", $priceClauses) . ")";
    }
}
if ($selectedDistance > 0) {
    $sql .= " AND cafe_shop.`distance_meters` <= ?";
    $params[] = $selectedDistance * 1000;
    $types .= "i";
}

$sql .= " ORDER BY cafe_shop.id ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    die("SQL 準備失敗：" . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>咖啡廳地圖</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f6f2; margin: 0; padding: 0; }
        .container { width: 90%; max-width: 1000px; margin: 30px auto; }
        h1 { text-align: center; margin-bottom: 24px; }
        .filter-box { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .tags { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .btn { background: #6b4f3b; color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
        .card-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card h3 { margin-top: 0; margin-bottom: 10px; color: #333; }
        .rating { color: #c77d2b; font-weight: bold; margin-bottom: 10px; }
        .empty { background: #fff; padding: 20px; border-radius: 12px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>咖啡廳地圖</h1>
        
        <form method="GET">
            
            <div class="filter-box">
                <div class="tags">
                    <label><input type="checkbox" name="socket" value="1" <?php echo $hasSocket ? 'checked' : ''; ?>>插座</label>
                    <label><input type="checkbox" name="no_limit" value="1" <?php echo $hasNoLimit ? 'checked' : ''; ?>>不限時</label>
                    <label><input type="checkbox" name="parking" value="1" <?php echo $hasParking ? 'checked' : ''; ?>> 停車位</label>
                    <label><input type="checkbox" name="wifi" value="1" <?php echo $hasWiFi ? 'checked' : ''; ?>> WiFi</label>
                    <label><input type="checkbox" name="outdoor" value="1" <?php echo $hasOutdoor ? 'checked' : ''; ?>> 戶外座位</label>
                    <label><input type="checkbox" name="seats" value="1" <?php echo $hasSeat ? 'checked' : ''; ?>> 室內座位</label>
                    <label><input type="checkbox" name="dessert" value="1" <?php echo $hasDessert ? 'checked' : ''; ?>> 甜點</label>
                    <label><input type="checkbox" name="toilet" value="1" <?php echo $hasToilet ? 'checked' : ''; ?>> 廁所</label>
                    <label><input type="checkbox" name="no_min_consume" value="1" <?php echo $noMinConsume ? 'checked' : ''; ?>> 無低消</label>
                    <button type="submit" class="btn">快速篩選</button>
                </div>
            </div>

            <div class="main-layout" style="display: flex; gap: 30px; align-items: flex-start; margin-top: 20px;">
                
                <aside class="filter-sidebar" style="width: 250px; flex-shrink: 0;">
                    <div class="filter-box" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <h3>篩選條件</h3>
                        <div class="filter-section">
                        <h4>顧客評分</h4>
                            <label><input type="radio" name="rating" value="0" <?php echo ($selectedRating == 0) ? 'checked' : ''; ?>> 不限</label><br>
                            <label><input type="radio" name="rating" value="3.5" <?php echo ($selectedRating == 3.5) ? 'checked' : ''; ?>> 3.5 以上</label><br>
                            <label><input type="radio" name="rating" value="4.0" <?php echo ($selectedRating == 4.0) ? 'checked' : ''; ?>> 4.0 以上</label><br>
                            <label><input type="radio" name="rating" value="4.5" <?php echo ($selectedRating == 4.5) ? 'checked' : ''; ?>> 4.5 以上</label><br>
                        </div>

                        <div class="filter-section">
                            <h4>低消範圍</h4>
                            <hr style="border: 0.5px solid #eee; margin: 10px 0;">
                            <label><input type="checkbox" name="price[]" value="1" <?php echo in_array("1", $selectedPriceGroups) ? 'checked' : ''; ?>> $1 ~ $50</label><br>
                            <label><input type="checkbox" name="price[]" value="2" <?php echo in_array("2", $selectedPriceGroups) ? 'checked' : ''; ?>> $51 ~ $100</label><br>
                            <label><input type="checkbox" name="price[]" value="3" <?php echo in_array("3", $selectedPriceGroups) ? 'checked' : ''; ?>> $101 ~ $150</label><br>
                            <label><input type="checkbox" name="price[]" value="4" <?php echo in_array("4", $selectedPriceGroups) ? 'checked' : ''; ?>> $151 ~ $200</label><br>
                            <label><input type="checkbox" name="price[]" value="5" <?php echo in_array("5", $selectedPriceGroups) ? 'checked' : ''; ?>> $201 ~ $250</label><br>
                        </div>

                        <div class="filter-section">
                            <h4>距離</h4>
                            <label><input type="radio" name="distance" value="0" <?php echo ($selectedDistance == 0) ? 'checked' : ''; ?>> 不限</label><br>
                            <label><input type="radio" name="distance" value="0.5" <?php echo ($selectedDistance == 0.5) ? 'checked' : ''; ?>> 0.5 公里內</label><br>
                            <label><input type="radio" name="distance" value="1.0" <?php echo ($selectedDistance == 1.0) ? 'checked' : ''; ?>> 1.0 公里內</label><br>
                            <label><input type="radio" name="distance" value="1.5" <?php echo ($selectedDistance == 1.5) ? 'checked' : ''; ?>> 1.5 公里內</label><br>
                            <label><input type="radio" name="distance" value="2.0" <?php echo ($selectedDistance == 2.0) ? 'checked' : ''; ?>> 2.0 公里內</label><br>
                        </div>

                        <button type="submit" class="btn filter-btn" style="width:100%; margin-top:15px;">套用篩選</button>
                    </div>
                </aside>

                <main class="content-area" style="flex: 1;">
                    <div class="card-list">
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <div class="card">
                                    <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                                    <div class="rating">
                                        <?php if (!empty($row['rating'])): ?>
                                            <?php echo htmlspecialchars($row['rating']); ?> / 5.0
                                        <?php else: ?>
                                            評價：尚無評價資料
                                        <?php endif; ?>
                                    </div>
                                    <p><strong>地址：</strong><?php echo htmlspecialchars($row['address']); ?></p>
                                    <p><strong>電話：</strong><?php echo htmlspecialchars($row['phone']); ?></p>
                                    <p><strong>營業時間：</strong><?php echo nl2br(htmlspecialchars($row['opening_hours'])); ?></p>
                                    <p><strong>距離(公尺)：</strong><?php echo htmlspecialchars($row['distance_meters']); ?></p>
                                    <p><strong>最低消費：</strong><?php echo ($row['min_consumption'] == 0) ? "無低消" : htmlspecialchars($row['min_consumption']) . " 元"; ?></p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty">查無符合條件的咖啡廳</div>
                        <?php endif; ?>
                    </div>
                </main>

            </div> </form> </div>
</body>
</html>
