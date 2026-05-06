<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// 取得 Google 登入狀態與名稱[cite: 8]
$is_logged_in = isset($_SESSION['user_id']); 
$google_user_name = $_SESSION['user_name'] ?? '';

$cafe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 取得咖啡廳基本資訊[cite: 8]
$cafe_sql = "SELECT name FROM cafe_shop WHERE id = $cafe_id";
$cafe_res = mysqli_query($conn, $cafe_sql);
$cafe_info = mysqli_fetch_assoc($cafe_res);

if (!$cafe_info) { die("找不到該咖啡廳資訊。"); }

// 處理新增評論提交 (僅限登入者)[cite: 8]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$is_logged_in) {
        die("請先登入後再發布意見。");
    }

    $user_name = mysqli_real_escape_string($conn, $google_user_name);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    if (!empty($comment)) {
        $insert_sql = "INSERT INTO cafe_reviews (cafe_id, user_name, comment) VALUES ($cafe_id, '$user_name', '$comment')";
        mysqli_query($conn, $insert_sql);
        header("Location: reviews.php?id=$cafe_id"); 
        exit;
    }
}

// 處理按讚/倒讚互動邏輯 (支援收回與切換)
if (isset($_GET['action']) && isset($_GET['review_id']) && $is_logged_in) {
    $r_id = (int)$_GET['review_id'];
    $u_id = $_SESSION['user_id'];
    $new_action = $_GET['action']; // 'helpful' 或 'not_helpful'

    // 1. 檢查舊紀錄
    $check_stmt = mysqli_prepare($conn, "SELECT action_type FROM review_reactions WHERE review_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($check_stmt, "is", $r_id, $u_id);
    mysqli_stmt_execute($check_stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

    if (!$existing) {
        // --- 情況 A: 第一次點擊 (新增) ---
        mysqli_query($conn, "INSERT INTO review_reactions (review_id, user_id, action_type) VALUES ($r_id, '$u_id', '$new_action')");
        $col = ($new_action === 'helpful') ? 'helpful_count' : 'not_helpful_count';
        mysqli_query($conn, "UPDATE cafe_reviews SET $col = $col + 1 WHERE id = $r_id");
    } 
    elseif ($existing['action_type'] === $new_action) {
        // --- 情況 B: 點擊同一個按鈕 (收回/取消) ---
        mysqli_query($conn, "DELETE FROM review_reactions WHERE review_id = $r_id AND user_id = '$u_id'");
        $col = ($new_action === 'helpful') ? 'helpful_count' : 'not_helpful_count';
        mysqli_query($conn, "UPDATE cafe_reviews SET $col = GREATEST(0, $col - 1) WHERE id = $r_id");
    } 
    else {
        // --- 情況 C: 點擊另一個按鈕 (切換) ---
        mysqli_query($conn, "UPDATE review_reactions SET action_type = '$new_action' WHERE review_id = $r_id AND user_id = '$u_id'");
        if ($new_action === 'helpful') {
            mysqli_query($conn, "UPDATE cafe_reviews SET helpful_count = helpful_count + 1, not_helpful_count = GREATEST(0, not_helpful_count - 1) WHERE id = $r_id");
        } else {
            mysqli_query($conn, "UPDATE cafe_reviews SET not_helpful_count = not_helpful_count + 1, helpful_count = GREATEST(0, helpful_count - 1) WHERE id = $r_id");
        }
    }

    header("Location: reviews.php?id=$cafe_id");
    exit;
}

    // 1. 檢查此使用者是否已經對這則評論表過態
    $check_sql = "SELECT action_type FROM review_reactions WHERE review_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "is", $review_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);

    if (!$existing) {
        // 2. 若無紀錄，則寫入紀錄表並更新評論表的總數[cite: 7, 8]
        $insert_sql = "INSERT INTO review_reactions (review_id, user_id, action_type) VALUES (?, ?, ?)";
        $ins_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($ins_stmt, "iss", $review_id, $user_id, $action);
        
        if (mysqli_stmt_execute($ins_stmt)) {
            if ($action === 'helpful') {
                mysqli_query($conn, "UPDATE cafe_reviews SET helpful_count = helpful_count + 1 WHERE id = $review_id");
            } else {
                mysqli_query($conn, "UPDATE cafe_reviews SET not_helpful_count = not_helpful_count + 1 WHERE id = $review_id");
            }
        }
    }
    header("Location: reviews.php?id=$cafe_id");
    exit;

// 撈取所有評論[cite: 8]
$reviews_sql = "SELECT * FROM cafe_reviews WHERE cafe_id = $cafe_id ORDER BY created_at DESC";
$reviews_res = mysqli_query($conn, $reviews_sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($cafe_info['name']) ?> - 咖啡廳意見箱</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .review-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .review-meta { font-size: 0.85em; color: #888; margin-bottom: 10px; }
        .review-comment { font-size: 1.1em; line-height: 1.6; color: #444; }
        .interaction-bar { margin-top: 15px; display: flex; gap: 15px; }
        .action-link { text-decoration: none; font-size: 0.9em; color: #8d6e63; border: 1px solid #8d6e63; padding: 4px 10px; border-radius: 20px; transition: 0.3s; }
        .action-link:hover { background: #8d6e63; color: #fff; }
        .review-form { background: #fdfaf8; padding: 20px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #eee; }
        .login-prompt { text-align: center; padding: 20px; background: #fff5f5; border-radius: 12px; border: 1px solid #ffcdd2; color: #c62828; }
        /* 已投票狀態的樣式 */
        .voted-status { font-size: 0.9em; color: #777; background: #f0f0f0; border: 1px solid #ccc; padding: 4px 12px; border-radius: 20px; cursor: not-allowed; opacity: 0.8; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container" style="max-width: 800px; margin: 40px auto;">
        <h1>💬 <?= htmlspecialchars($cafe_info['name']) ?></h1>
        <p><a href="cafe_map.php" style="color: #8d6e63;">← 返回地圖搜尋</a></p>

        <?php if ($is_logged_in): ?>
            <div class="review-form">
                <h3>分享您的環境心得 (以 <?= htmlspecialchars($google_user_name) ?> 身分發佈)</h3>
                <form method="POST">
                    <textarea name="comment" rows="4" placeholder="分享一下這家店的環境、插座位置或適合讀書嗎？" style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ddd;" required></textarea>
                    <button type="submit" name="submit_review" class="search-btn" style="width: 100%;">發布意見</button>
                </form>
            </div>
        <?php else: ?>
            <div class="login-prompt">
                <strong>💡 提醒：</strong> 請先進行 Google 登入，即可分享您的環境意見。
            </div>
        <?php endif; ?>

        <hr>

        <h2>大眾意見 (<?= mysqli_num_rows($reviews_res) ?>)</h2>
        <?php if (mysqli_num_rows($reviews_res) > 0): ?>
            <?php while($rev = mysqli_fetch_assoc($reviews_res)): 
    // 判定目前使用者的投票狀態[cite: 8]
    $r_id = $rev['id'];
    $u_id = $_SESSION['user_id'] ?? '';
    $user_vote = '';

    if ($is_logged_in) {
        $v_res = mysqli_query($conn, "SELECT action_type FROM review_reactions WHERE review_id = $r_id AND user_id = '$u_id'");
        if ($v_row = mysqli_fetch_assoc($v_res)) {
            $user_vote = $v_row['action_type'];
        }
    }
?>
    <div class="review-card">
        <div class="review-meta">
            <strong><?= htmlspecialchars($rev['user_name']) ?></strong> 
            <span style="color: #ccc; margin-left: 10px;">於 <?= date('Y-m-d H:i', strtotime($rev['created_at'])) ?></span>
        </div>
        <div class="review-comment">
            <?= nl2br(htmlspecialchars($rev['comment'])) ?>
        </div>

        <div class="interaction-bar" style="margin-top: 15px; display: flex; gap: 10px;">
            <!-- 有幫助按鈕[cite: 7, 8] -->
            <a href="?id=<?= $cafe_id ?>&review_id=<?= $r_id ?>&action=helpful" 
               class="action-link <?= ($user_vote === 'helpful') ? 'active' : '' ?>">
                👍 有幫助 (<?= $rev['helpful_count'] ?>)
            </a>

            <!-- 沒幫助按鈕[cite: 7, 8] -->
            <a href="?id=<?= $cafe_id ?>&review_id=<?= $r_id ?>&action=not_helpful" 
               class="action-link <?= ($user_vote === 'not_helpful') ? 'active' : '' ?>">
                👎 沒幫助 (<?= $rev['not_helpful_count'] ?>)
            </a>
        </div>
    </div>
<?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center; color: #888;">目前還沒有意見，成為第一個分享的人吧！</p>
        <?php endif; ?>
    </div>
</body>
</html>