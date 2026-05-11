<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';

// 1. 檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => '請先登入帳號']);
    exit;
}

// 2. 接收前端傳來的 JSON 資料
$data = json_decode(file_get_contents('php://input'), true);
$r_id = isset($data['review_id']) ? (int)$data['review_id'] : 0;
$act = $data['action'] ?? '';
$u_id = $_SESSION['user_id'];

if ($r_id <= 0 || !in_array($act, ['helpful', 'not_helpful'])) {
    echo json_encode(['status' => 'error', 'message' => '無效的參數']);
    exit;
}

// 3. 檢查先前的投票紀錄
$check_stmt = mysqli_prepare($conn, "SELECT action_type FROM review_reactions WHERE review_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_stmt, "is", $r_id, $u_id);
mysqli_stmt_execute($check_stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

$user_vote = ''; // 紀錄最終的狀態傳給前端

// 4. 執行 新增/收回/切換 邏輯
if (!$existing) {
    // 第一次按
    mysqli_query($conn, "INSERT INTO review_reactions (review_id, user_id, action_type) VALUES ($r_id, '$u_id', '$act')");
    $col = ($act === 'helpful') ? 'helpful_count' : 'not_helpful_count';
    mysqli_query($conn, "UPDATE cafe_reviews SET $col = $col + 1 WHERE id = $r_id");
    $user_vote = $act;
} elseif ($existing['action_type'] === $act) {
    // 按同一個按鈕 -> 收回
    mysqli_query($conn, "DELETE FROM review_reactions WHERE review_id = $r_id AND user_id = '$u_id'");
    $col = ($act === 'helpful') ? 'helpful_count' : 'not_helpful_count';
    mysqli_query($conn, "UPDATE cafe_reviews SET $col = GREATEST(0, $col - 1) WHERE id = $r_id");
    $user_vote = '';
} else {
    // 按另一個按鈕 -> 切換
    mysqli_query($conn, "UPDATE review_reactions SET action_type = '$act' WHERE review_id = $r_id AND user_id = '$u_id'");
    if ($act === 'helpful') {
        mysqli_query($conn, "UPDATE cafe_reviews SET helpful_count = helpful_count + 1, not_helpful_count = GREATEST(0, not_helpful_count - 1) WHERE id = $r_id");
    } else {
        mysqli_query($conn, "UPDATE cafe_reviews SET not_helpful_count = not_helpful_count + 1, helpful_count = GREATEST(0, helpful_count - 1) WHERE id = $r_id");
    }
    $user_vote = $act;
}

// 5. 撈取最新的統計數字回傳
$count_res = mysqli_query($conn, "SELECT helpful_count, not_helpful_count FROM cafe_reviews WHERE id = $r_id");
$counts = mysqli_fetch_assoc($count_res);

echo json_encode([
    'status' => 'success',
    'user_vote' => $user_vote,
    'helpful_count' => $counts['helpful_count'],
    'not_helpful_count' => $counts['not_helpful_count']
]);