<?php
// 1. 設定台灣時區
date_default_timezone_set('Asia/Taipei'); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// 強制資料庫連線使用台灣時區 (+08:00)
mysqli_query($conn, "SET time_zone = '+08:00'");

// --- 取得狀態與變數 ---
$is_logged_in = isset($_SESSION['user_id']); 
$google_user_name = $_SESSION['user_name'] ?? '';
$u_id = $_SESSION['user_id'] ?? '';
$cafe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sort = $_GET['sort'] ?? 'rating_desc'; 

// 2. 取得咖啡廳基本資訊
$cafe_sql = "SELECT name FROM cafe_shop WHERE id = $cafe_id";
$cafe_res = mysqli_query($conn, $cafe_sql);
$cafe_info = mysqli_fetch_assoc($cafe_res);
if (!$cafe_info) { die("找不到該咖啡廳資訊。"); }

// --- 3. 處理「互動」邏輯 (按讚/沒幫助) ---
if (isset($_GET['action']) && isset($_GET['group_ts']) && $is_logged_in) {
    $ts_esc = mysqli_real_escape_string($conn, $_GET['group_ts']);
    $act = mysqli_real_escape_string($conn, $_GET['action']); 

    $find_id = mysqli_query($conn, "SELECT id FROM cafe_reviews WHERE created_at = '$ts_esc' AND cafe_id = $cafe_id LIMIT 1");
    if ($row = mysqli_fetch_assoc($find_id)) {
        $r_id = $row['id'];
        $check_stmt = mysqli_prepare($conn, "SELECT action_type FROM review_reactions WHERE review_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($check_stmt, "is", $r_id, $u_id);
        mysqli_stmt_execute($check_stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

        if (!$existing) {
            mysqli_query($conn, "INSERT INTO review_reactions (review_id, user_id, action_type) VALUES ($r_id, '$u_id', '$act')");
            $col = ($act === 'helpful') ? 'helpful_count' : 'not_helpful_count';
            mysqli_query($conn, "UPDATE cafe_reviews SET $col = $col + 1 WHERE id = $r_id");
        } elseif ($existing['action_type'] === $act) {
            mysqli_query($conn, "DELETE FROM review_reactions WHERE review_id = $r_id AND user_id = '$u_id'");
            $col = ($act === 'helpful') ? 'helpful_count' : 'not_helpful_count';
            mysqli_query($conn, "UPDATE cafe_reviews SET $col = GREATEST(0, $col - 1) WHERE id = $r_id");
        } else {
            mysqli_query($conn, "UPDATE review_reactions SET action_type = '$act' WHERE review_id = $r_id AND user_id = '$u_id'");
            if ($act === 'helpful') {
                mysqli_query($conn, "UPDATE cafe_reviews SET helpful_count = helpful_count + 1, not_helpful_count = GREATEST(0, not_helpful_count - 1) WHERE id = $r_id");
            } else {
                mysqli_query($conn, "UPDATE cafe_reviews SET not_helpful_count = not_helpful_count + 1, helpful_count = GREATEST(0, helpful_count - 1) WHERE id = $r_id");
            }
        }
    }
    header("Location: reviews.php?id=$cafe_id&sort=$sort"); exit;
}

// --- 4. 處理「刪除」邏輯 ---
if (isset($_GET['delete_group']) && $is_logged_in) {
    $u_name_esc = mysqli_real_escape_string($conn, $google_user_name);
    $ts = mysqli_real_escape_string($conn, $_GET['ts']);
    $files = mysqli_query($conn, "SELECT image_path FROM cafe_reviews WHERE user_name = '$u_name_esc' AND created_at = '$ts'");
    while($f = mysqli_fetch_assoc($files)) {
        if ($f['image_path'] && file_exists($f['image_path'])) unlink($f['image_path']);
    }
    mysqli_query($conn, "DELETE FROM cafe_reviews WHERE user_name = '$u_name_esc' AND created_at = '$ts'");
    header("Location: reviews.php?id=$cafe_id&sort=$sort"); exit;
}

if (isset($_GET['delete_menu']) && $is_logged_in) {
    $m_id = (int)$_GET['delete_menu'];
    $u_name_esc = mysqli_real_escape_string($conn, $google_user_name);
    $res = mysqli_query($conn, "SELECT menu_image_path FROM cafe_menus WHERE id = $m_id AND user_name = '$u_name_esc'");
    if ($row = mysqli_fetch_assoc($res)) {
        if (file_exists($row['menu_image_path'])) unlink($row['menu_image_path']);
        mysqli_query($conn, "DELETE FROM cafe_menus WHERE id = $m_id");
    }
    $anchor = isset($r_id) ? "#review-" . $r_id : "";
    header("Location: reviews.php?id=$cafe_id&sort=$sort" . $anchor); 
    exit;
}

// --- 5. 處理「上傳」邏輯 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_menu'])) {
    foreach ($_FILES['menu_images']['tmp_name'] as $key => $tmp) {
        if ($_FILES['menu_images']['error'][$key] === UPLOAD_ERR_OK) {
            $dir = 'uploads/menus/'; if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fn = 'menu_' . uniqid() . '.' . pathinfo($_FILES['menu_images']['name'][$key], PATHINFO_EXTENSION);
            if (move_uploaded_file($tmp, $dir . $fn)) {
                $u_esc = mysqli_real_escape_string($conn, $google_user_name);
                mysqli_query($conn, "INSERT INTO cafe_menus (cafe_id, user_name, menu_image_path) VALUES ($cafe_id, '$u_esc', '$dir$fn')");
            }
        }
    }
    header("Location: reviews.php?id=$cafe_id&sort=$sort"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $u_esc = mysqli_real_escape_string($conn, $google_user_name);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
    $current_ts = date('Y-m-d H:i:s'); 

    if (!empty($_FILES['review_images']['tmp_name'][0])) {
        foreach ($_FILES['review_images']['tmp_name'] as $key => $tmp) {
            $path = '';
            if ($_FILES['review_images']['error'][$key] === UPLOAD_ERR_OK) {
                $dir = 'uploads/reviews/'; if (!is_dir($dir)) mkdir($dir, 0777, true);
                $fn = 'rev_' . uniqid() . '.' . pathinfo($_FILES['review_images']['name'][$key], PATHINFO_EXTENSION);
                if (move_uploaded_file($tmp, $dir . $fn)) $path = $dir . $fn;
            }
            mysqli_query($conn, "INSERT INTO cafe_reviews (cafe_id, user_name, comment, rating, image_path, created_at) VALUES ($cafe_id, '$u_esc', '$comment', $rating, '$path', '$current_ts')");
        }
    } else {
        mysqli_query($conn, "INSERT INTO cafe_reviews (cafe_id, user_name, comment, rating, created_at) VALUES ($cafe_id, '$u_esc', '$comment', $rating, '$current_ts')");
    }
    header("Location: reviews.php?id=$cafe_id&sort=$sort"); exit;
}

// --- 6. 撈取資料與排序 ---
$avg_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(rating) as avg_score, COUNT(id) as total_count FROM cafe_reviews WHERE cafe_id = $cafe_id"));
$avg_score = $avg_data['avg_score'] ? round($avg_data['avg_score'], 1) : 0;

$order_by = ($sort === 'rating_desc') ? "rating DESC, created_at DESC" : (($sort === 'rating_asc') ? "rating ASC, created_at DESC" : "created_at DESC");

$menu_res = mysqli_query($conn, "SELECT * FROM cafe_menus WHERE cafe_id = $cafe_id ORDER BY created_at DESC");
$review_sql = "SELECT id, user_name, comment, rating, created_at, GROUP_CONCAT(image_path) as all_images, MAX(helpful_count) as h_count, MAX(not_helpful_count) as nh_count 
               FROM cafe_reviews WHERE cafe_id = $cafe_id GROUP BY created_at, user_name, comment ORDER BY $order_by";
$reviews_res = mysqli_query($conn, $review_sql);
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cafe_info['name']) ?> - 咖啡廳資訊</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .section-box { background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .tab-title { border-left: 5px solid #8d6e63; padding-left: 12px; margin-bottom: 20px; color: #5d4037; font-size: 1.4em; display: flex; align-items: center; justify-content: space-between; }
        .avg-badge { background: #f1c40f; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.6em; }
        .menu-scroll { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 15px; }
        .menu-item { position: relative; flex-shrink: 0; }
        .menu-item img { width: 140px; height: 180px; object-fit: cover; border-radius: 8px; cursor: zoom-in; }
        .review-card { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; }
        .display-stars { color: #f1c40f; font-size: 1.1em; margin-bottom: 5px; }
        .photo-grid img { width: 140px; height: 140px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; cursor: zoom-in; margin: 5px; }
        
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 30px; color: #ddd; cursor: pointer; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f1c40f; }

        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); }
        .modal-content { margin: auto; display: block; max-width: 85%; max-height: 85%; position: relative; top: 50%; transform: translateY(-50%); border: 3px solid #fff; }
        .prev, .next { cursor: pointer; position: absolute; top: 50%; color: white; font-size: 50px; padding: 20px; z-index: 2100; text-decoration: none; }
        .next { right: 20px; } .prev { left: 20px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container" style="max-width: 850px; margin: 40px auto; padding: 0 20px;">
        <h1>☕ <?= htmlspecialchars($cafe_info['name']) ?></h1>

        <div class="section-box">
            <h3 class="tab-title">📖 菜單相簿</h3>
            <div class="menu-scroll">
                <?php while($m = mysqli_fetch_assoc($menu_res)): ?>
                    <div class="menu-item">
                        <img src="<?= $m['menu_image_path'] ?>" class="enlarge-img">
                        <?php if ($is_logged_in && $m['user_name'] === $google_user_name): ?>
                            <a href="?id=<?= $cafe_id ?>&delete_menu=<?= $m['id'] ?>&sort=<?= $sort ?>" style="position:absolute; top:5px; right:5px; background:red; color:white; border-radius:50%; width:20px; height:20px; text-align:center; text-decoration:none;" onclick="return confirm('刪除照片？')">×</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php if ($is_logged_in): ?>
                <form method="POST" enctype="multipart/form-data"><input type="file" name="menu_images[]" multiple required><button type="submit" name="submit_menu" style="background:#8d6e63; color:white; border:none; padding:6px 12px; border-radius:5px; cursor:pointer;">上傳菜單</button></form>
            <?php endif; ?>
        </div>

        <div class="section-box">
            <h3 class="tab-title">
                <span>大眾意見</span>
                <div>
                    <?php if($avg_score > 0): ?><span class="avg-badge">★ <?= $avg_score ?> (<?= $avg_data['total_count'] ?> 則)</span><?php endif; ?>
                    <select onchange="location.href='reviews.php?id=<?= $cafe_id ?>&sort=' + this.value;" style="font-size:14px; margin-left:10px; padding:5px; border-radius:5px; border:1px solid #ddd;">
                        <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>星等高到低</option>
                        <option value="rating_asc" <?= $sort === 'rating_asc' ? 'selected' : '' ?>>星等低到高</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>最新評論</option>
                    </select>
                </div>
            </h3>

            <?php if ($is_logged_in): ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="star-rating"><?php for($i=5; $i>=1; $i--): ?><input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i==5?'checked':'' ?>><label for="star<?= $i ?>">★</label><?php endfor; ?></div>
                    <textarea name="comment" rows="4" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;" placeholder="分享評價..." required></textarea>
                    <input type="file" name="review_images[]" multiple style="margin: 10px 0;"><button type="submit" name="submit_review" style="background:#8d6e63; color:white; width:100%; border:none; padding:10px; border-radius:8px; cursor:pointer;">發佈評論</button>
                </form>
            <?php endif; ?>

            <?php while($rev = mysqli_fetch_assoc($reviews_res)): 
                $group_ts = $rev['created_at']; $r_id = $rev['id']; $user_vote = '';
                if ($is_logged_in) {
                    $v_res = mysqli_query($conn, "SELECT action_type FROM review_reactions WHERE review_id = $r_id AND user_id = '$u_id'");
                    if ($v_row = mysqli_fetch_assoc($v_res)) $user_vote = $v_row['action_type'];
                }
            ?>
                <div class="review-card" id="review-<?= $r_id ?>">
                    <div style="display:flex; justify-content:space-between; color:#888; font-size:0.85em;"><strong><?= htmlspecialchars($rev['user_name']) ?></strong><span><?= date('Y-m-d H:i', strtotime($group_ts)) ?></span></div>
                    <div class="display-stars"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                    <p><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                    <div class="photo-grid"><?php if($rev['all_images']) foreach(explode(',', $rev['all_images']) as $img) echo '<img src="'.$img.'" class="enlarge-img">'; ?></div>
                    <div style="margin-top: 15px; display: flex; align-items: center; gap: 10px;">
    <a href="javascript:void(0);" 
       class="action-link <?= ($user_vote === 'helpful') ? 'active' : '' ?>" 
       id="btn-helpful-<?= $r_id ?>"
       onclick="toggleReaction(<?= $r_id ?>, 'helpful')">
        👍 有幫助 (<span id="count-helpful-<?= $r_id ?>"><?= $rev['h_count'] ?></span>)
    </a>

    <a href="javascript:void(0);" 
       class="action-link <?= ($user_vote === 'not_helpful') ? 'active' : '' ?>" 
       id="btn-not_helpful-<?= $r_id ?>"
       onclick="toggleReaction(<?= $r_id ?>, 'not_helpful')">
        👎 沒幫助 (<span id="count-not_helpful-<?= $r_id ?>"><?= $rev['nh_count'] ?></span>)
    </a>

    <?php if ($is_logged_in && $rev['user_name'] === $google_user_name): ?>
        <a href="?id=<?= $cafe_id ?>&delete_group=1&ts=<?= urlencode($group_ts) ?>&sort=<?= $sort ?>" style="color:red; text-decoration:none; margin-left:auto;" onclick="return confirm('刪除整則評論？')">刪除</a>
    <?php endif; ?>
</div>

    <?php if ($is_logged_in && $rev['user_name'] === $google_user_name): ?>
        <a href="?id=<?= $cafe_id ?>&delete_group=1&ts=<?= urlencode($group_ts) ?>&sort=<?= $sort ?>" 
           style="color: #e74c3c; text-decoration: none; font-size: 0.9em; margin-left: auto;" 
           onclick="return confirm('確定要刪除這則評論嗎？')">
        </a>
    <?php endif; ?>
</div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div id="imgModal" class="modal">
        <span style="position:absolute; top:20px; right:35px; color:white; font-size:40px; cursor:pointer;" onclick="closeModal()">&times;</span>
        <a class="prev" id="prevBtn">&#10094;</a><img class="modal-content" id="modalImg"><a class="next" id="nextBtn">&#10095;</a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById("imgModal"), modalImg = document.getElementById("modalImg");
        let currentIdx = 0, imgList = [];
        function initLightbox() {
            const imgs = document.querySelectorAll('.enlarge-img'); imgList = Array.from(imgs).map(img => img.src);
            imgs.forEach((img, i) => { img.onclick = () => { modal.style.display = "block"; currentIdx = i; modalImg.src = imgList[currentIdx]; } });
        }
        function slide(n) { currentIdx = (currentIdx + n + imgList.length) % imgList.length; modalImg.src = imgList[currentIdx]; }
        document.getElementById("prevBtn").onclick = (e) => { e.stopPropagation(); slide(-1); };
        document.getElementById("nextBtn").onclick = (e) => { e.stopPropagation(); slide(1); };
        window.closeModal = () => modal.style.display = "none";
        modal.onclick = (e) => { if(e.target === modal) closeModal(); };
        document.onkeydown = (e) => { if (modal.style.display === "block") { if (e.key === "ArrowLeft") slide(-1); if (e.key === "ArrowRight") slide(1); if (e.key === "Escape") closeModal(); } };
        initLightbox();
    });

    // 處理按讚/沒幫助的非同步請求
    async function toggleReaction(reviewId, action) {
        try {
            const response = await fetch('api_reaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId, action: action })
            });
            const data = await response.json();
            
            if (data.status === 'success') {
                // 1. 即時更新畫面上的數字
                document.getElementById('count-helpful-' + reviewId).innerText = data.helpful_count;
                document.getElementById('count-not_helpful-' + reviewId).innerText = data.not_helpful_count;
                
                // 2. 清除兩個按鈕的實色狀態
                const btnHelpful = document.getElementById('btn-helpful-' + reviewId);
                const btnNotHelpful = document.getElementById('btn-not_helpful-' + reviewId);
                btnHelpful.classList.remove('active');
                btnNotHelpful.classList.remove('active');
                
                // 3. 根據背景傳回的真實狀態，幫按鈕塗上實色
                if (data.user_vote === 'helpful') {
                    btnHelpful.classList.add('active');
                } else if (data.user_vote === 'not_helpful') {
                    btnNotHelpful.classList.add('active');
                }
            } else {
                alert(data.message);
                if (data.message.includes('登入')) {
                    // 若未登入，導向 Google 登入
                    window.location.href = '<?= $googleLoginUrl ?>';
                }
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    </script>
</body>
</html>
