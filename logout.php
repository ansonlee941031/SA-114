<?php
// logout.php[cite: 9]
session_start();

// 清除所有 Session 變數[cite: 9]
$_SESSION = array();

// 徹底銷毀 Session[cite: 9]
session_destroy();

// 導回地圖首頁[cite: 9]
header("Location: cafe_map.php");
exit();
?>