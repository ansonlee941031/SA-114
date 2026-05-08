<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config/db.php';

// 檢查是否登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => '請先登入帳號']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

// 取得參數
$action = $data['action'] ?? '';
$cafe_id = isset($data['cafe_id']) ? intval($data['cafe_id']) : 0;

if ($cafe_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => '無效的咖啡廳 ID']);
    exit;
}

if ($action === 'add') {
    // 新增收藏
    $sql = "INSERT IGNORE INTO favorite (user_id, cafe_id) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $user_id, $cafe_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => '已加入收藏']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '資料庫執行失敗']);
    }

} elseif ($action === 'remove') {
    // 移除收藏
    $sql = "DELETE FROM favorite WHERE user_id = ? AND cafe_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $user_id, $cafe_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => '已移除收藏']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '移除失敗']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => '未定義的操作']);
}
?>