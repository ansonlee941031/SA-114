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

// --- 3. 處理「互動」邏輯 ---
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

// --- 4. 處理「刪除」與「上傳」邏輯 ---
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
    header("Location: reviews.php?id=$cafe_id&sort=$sort"); exit;
}

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

// --- 5. 撈取資料與排序 ---
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
    <title><?= htmlspecialchars($cafe_info['name']) ?> - 評論詳情</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --primary-brown: #8B4513;
            --soft-brown: #a67c52; /* 較淺的棕色 */
            --bg-color: #fcfaf7;
        }
        body { background-color: var(--bg-color); font-size: 15px; margin: 0; }

        .page-container {
            max-width: 900px;
            margin: 30px auto 120px; 
            padding: 0 20px;
        }

        .section-box { 
            background: #fff; 
            padding: 25px; 
            border-radius: 18px; 
            margin-bottom: 25px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); 
        }

        .main-title { font-size: 1.8rem; color: #4a2c2a; margin-bottom: 20px; font-weight: bold; }
        .tab-title { 
            border-left: 5px solid var(--primary-brown); 
            padding-left: 12px; 
            margin-bottom: 20px; 
            color: #4a2c2a; 
            font-size: 1.35rem; 
            font-weight: bold;
            display: flex; align-items: center; justify-content: space-between; 
        }

        .review-card { padding: 25px; border-radius: 15px; margin-bottom: 15px; border: 1px solid #f2f2f2; }
        .user-name { font-size: 1.15rem; color: var(--primary-brown); font-weight: 700; }
        .post-time { font-size: 0.95rem; color: #999; }
        .display-stars { color: #f1c40f; font-size: 1.2rem; margin: 8px 0; }
        .comment-text { font-size: 1.1rem; line-height: 1.7; color: #444; margin: 12px 0; }

        .photo-grid img { width: 140px; height: 140px; object-fit: cover; border-radius: 10px; margin: 4px; cursor: zoom-in; }

        .form-area { background: #fffcf5; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #faead5; }
        textarea { font-size: 1.05rem; padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 100%; resize: none; }
        
        .btn-custom { background: var(--primary-brown); color: white; border: none; padding: 10px 22px; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-custom:hover { opacity: 0.9; }

        /* 調整互動連結顏色：降低選取後的對比度 */
        .action-link { 
            font-size: 1rem; 
            text-decoration: none; 
            color: #888; 
            margin-right: 15px; 
            transition: 0.2s; 
            padding: 4px 8px;
            border-radius: 6px;
        }
        .action-link:hover { background: #f5f5f5; }
        .action-link.active { 
            color: var(--soft-brown); /* 改用較淺的棕色 */
            background: #fdf5e6; /* 加入極淺的背景底色取代深色字 */
            font-weight: 500;
        }

        /* 燈箱樣式 */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); }
        .modal-content { margin: auto; display: block; max-width: 85%; max-height: 80%; position: relative; top: 50%; transform: translateY(-50%); border-radius: 5px; }
        .prev, .next { cursor: pointer; position: absolute; top: 50%; color: white; font-size: 50px; padding: 20px; z-index: 10000; text-decoration: none; user-select: none; }
        .next { right: 30px; } .prev { left: 30px; }
        .close-modal { position: absolute; top: 25px; right: 35px; color: white; font-size: 40px; cursor: pointer; z-index: 10001; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="page-container">
        <h1 class="main-title">☕ <?= htmlspecialchars($cafe_info['name']) ?></h1>

        <div class="section-box">
            <h3 class="tab-title"><span><i class="fa-solid fa-book-open"></i> 菜單相簿</span></h3>
            <div class="menu-scroll" style="display: flex; gap: 12px; overflow-x: auto; padding-bottom: 12px;">
                <?php while($m = mysqli_fetch_assoc($menu_res)): ?>
                    <div style="position: relative; flex-shrink: 0;">
                        <img src="<?= $m['menu_image_path'] ?>" class="enlarge-img" style="width: 160px; height: 210px; object-fit: cover; border-radius: 10px;">
                        <?php if ($is_logged_in && $m['user_name'] === $google_user_name): ?>
                            <a href="?id=<?= $cafe_id ?>&delete_menu=<?= $m['id'] ?>&sort=<?= $sort ?>" style="position:absolute; top:6px; right:6px; background:rgba(255,77,77,0.9); color:white; border-radius:50%; width:24px; height:24px; text-align:center; line-height:24px; text-decoration:none; font-weight:bold; font-size:14px;" onclick="return confirm('刪除照片？')">×</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php if ($is_logged_in): ?>
                <form method="POST" enctype="multipart/form-data" style="margin-top: 12px; font-size: 0.9rem;">
                    <input type="file" name="menu_images[]" multiple required>
                    <button type="submit" name="submit_menu" class="btn-custom">上傳菜單</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="section-box">
            <h3 class="tab-title">
                <span><i class="fa-solid fa-comments"></i> 大眾意見</span>
                <div>
                    <?php if($avg_score > 0): ?><span class="avg-badge" style="background:#f1c40f; color:#fff; padding:4px 10px; border-radius:15px; font-size:0.65em;">★ <?= $avg_score ?> (<?= $avg_data['total_count'] ?>)</span><?php endif; ?>
                    <select onchange="location.href='reviews.php?id=<?= $cafe_id ?>&sort=' + this.value;" style="font-size:0.9rem; padding:6px; border-radius:6px; border:1px solid #ddd;">
                        <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>星等高到低</option>
                        <option value="rating_asc" <?= $sort === 'rating_asc' ? 'selected' : '' ?>>星等低到高</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>最新評論</option>
                    </select>
                </div>
            </h3>

            <?php if ($is_logged_in): ?>
                <div class="form-area">
                    <form method="POST" enctype="multipart/form-data">
                        <label style="font-weight: bold; font-size: 1rem;">給予評分：</label>
                        <div style="display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 6px; margin: 8px 0;">
                            <?php for($i=5; $i>=1; $i--): ?>
                                <input type="radio" id="fstar<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i==5?'checked':'' ?> style="display:none;">
                                <label for="fstar<?= $i ?>" style="font-size: 26px; color: #ddd; cursor: pointer;" class="star-label">★</label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="comment" rows="4" placeholder="分享您在這間咖啡廳的體驗..." required></textarea>
                        <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;">
                            <input type="file" name="review_images[]" multiple style="font-size: 0.85rem;">
                            <button type="submit" name="submit_review" class="btn-custom">發佈評論</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="reviews-list">
                <?php while($rev = mysqli_fetch_assoc($reviews_res)): 
                    $group_ts = $rev['created_at']; $r_id = $rev['id']; $user_vote = '';
                    if ($is_logged_in) {
                        $v_res = mysqli_query($conn, "SELECT action_type FROM review_reactions WHERE review_id = $r_id AND user_id = '$u_id'");
                        if ($v_row = mysqli_fetch_assoc($v_res)) $user_vote = $v_row['action_type'];
                    }
                ?>
                    <div class="review-card">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="user-name"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($rev['user_name']) ?></span>
                            <span class="post-time"><?= date('Y-m-d H:i', strtotime($group_ts)) ?></span>
                        </div>
                        <div class="display-stars"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></div>
                        <div class="photo-grid"><?php if($rev['all_images']) foreach(explode(',', $rev['all_images']) as $img) if($img) echo '<img src="'.$img.'" class="enlarge-img">'; ?></div>
                        
                        <div style="margin-top:15px; display: flex; align-items: center; border-top: 1px solid #f9f9f9; padding-top: 12px;">
                            <a href="?id=<?= $cafe_id ?>&group_ts=<?= urlencode($group_ts) ?>&action=helpful&sort=<?= $sort ?>" class="action-link <?= $user_vote === 'helpful' ? 'active' : '' ?>">
                                <i class="fa-solid fa-thumbs-up"></i> 有幫助 (<?= $rev['h_count'] ?>)
                            </a>
                            <a href="?id=<?= $cafe_id ?>&group_ts=<?= urlencode($group_ts) ?>&action=not_helpful&sort=<?= $sort ?>" class="action-link <?= $user_vote === 'not_helpful' ? 'active' : '' ?>">
                                <i class="fa-solid fa-thumbs-down"></i> 沒幫助 (<?= $rev['nh_count'] ?>)
                            </a>
                            <?php if ($is_logged_in && $rev['user_name'] === $google_user_name): ?>
                                <a href="?id=<?= $cafe_id ?>&delete_group=1&ts=<?= urlencode($group_ts) ?>&sort=<?= $sort ?>" style="color:#ff4d4d; text-decoration:none; margin-left:auto; font-weight: bold; font-size: 0.95rem;" onclick="return confirm('刪除評論？')"><i class="fa-solid fa-trash-can"></i> 刪除</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div id="imgModal" class="modal">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <a class="prev" id="prevBtn">&#10094;</a>
        <img id="modalImg" class="modal-content">
        <a class="next" id="nextBtn">&#10095;</a>
    </div>

    <script>
    // 星星互動
    const stars = document.querySelectorAll('.star-label');
    stars.forEach((s, idx) => {
        s.onmouseover = () => {
            stars.forEach((st, i) => st.style.color = i >= idx ? '#f1c40f' : '#ddd');
        };
    });

    // 燈箱邏輯
    let currentImgIdx = 0;
    let allImgs = [];
    const modal = document.getElementById('imgModal');
    const modalImg = document.getElementById('modalImg');

    function initLightbox() {
        const enlargeImgs = document.querySelectorAll('.enlarge-img');
        // 抓取頁面上所有帶有 enlarge-img 類別的照片
        allImgs = Array.from(enlargeImgs).map(img => img.src);
        
        enlargeImgs.forEach((img, index) => {
            img.onclick = function() {
                modal.style.display = "block";
                currentImgIdx = index;
                modalImg.src = allImgs[currentImgIdx];
            }
        });
    }

    function changeImg(step) {
        currentImgIdx = (currentImgIdx + step + allImgs.length) % allImgs.length;
        modalImg.src = allImgs[currentImgIdx];
    }

    document.getElementById('prevBtn').onclick = (e) => { e.stopPropagation(); changeImg(-1); };
    document.getElementById('nextBtn').onclick = (e) => { e.stopPropagation(); changeImg(1); };
    window.closeModal = () => modal.style.display = "none";
    
    // 點擊背景關閉
    modal.onclick = (e) => { if(e.target === modal) closeModal(); };

    // 鍵盤支援
    document.onkeydown = (e) => {
        if (modal.style.display === "block") {
            if (e.key === "ArrowLeft") changeImg(-1);
            if (e.key === "ArrowRight") changeImg(1);
            if (e.key === "Escape") closeModal();
        }
    };

    initLightbox();
    </script>
</body>
</html>
