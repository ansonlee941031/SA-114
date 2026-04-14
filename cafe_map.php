<?php
require_once __DIR__ . '/config/db.php';

// 取得使用者選擇的標籤
$hasSocket = isset($_GET['socket']) ? 1 : 0;
$hasNoLimit = isset($_GET['no_limit']) ? 1 : 0;

// 基本 SQL：從 cafe_shop 連接 label
$sql = "
    SELECT 
        cafe_shop.id,
        cafe_shop.name,
        cafe_shop.address,
        cafe_shop.phone,
        cafe_shop.opening_hours
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
        body {
            font-family: Arial, sans-serif;
            background: #f8f6f2;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 30px auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 24px;
        }

        .filter-box {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .tags {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .btn {
            background: #6b4f3b;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .card-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }

        .rating {
            color: #c77d2b;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .empty {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>咖啡廳地圖</h1>

        <form method="GET" class="filter-box">
            <div class="tags">
                <label>
                    <input type="checkbox" name="socket" value="1" <?php echo $hasSocket ? 'checked' : ''; ?>>
                    插座
                </label>

                <label>
                    <input type="checkbox" name="no_limit" value="1" <?php echo $hasNoLimit ? 'checked' : ''; ?>>
                    不限時
                </label>
            </div>

            <button type="submit" class="btn">篩選</button>
        </form>

        <div class="card-list">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        <div class="rating">評價：尚無評價資料</div>
                        <p><strong>地址：</strong><?php echo htmlspecialchars($row['address']); ?></p>
                        <p><strong>電話：</strong><?php echo htmlspecialchars($row['phone']); ?></p>
                        <p><strong>營業時間：</strong><?php echo nl2br(htmlspecialchars($row['opening_hours'])); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    查無符合「插座」與「不限時」條件的咖啡廳
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>