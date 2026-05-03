<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$dbname = "sa_project";
$port = 3306; // 如果你的 MariaDB 是別的 port 就改掉

$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    die("資料庫連線失敗：" . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>