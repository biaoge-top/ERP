<?php
// 数据库连接配置（复制本文件为 db.php 并填写真实配置）
$servername = "localhost";
$username = "your_db_user";
$password = "your_db_password";
$dbname = "erp";

// 创建连接
$conn = mysqli_connect($servername, $username, $password, $dbname);

// 检查连接
if (!$conn) {
    die("连接失败: " . mysqli_connect_error());
}

// 设置字符集
mysqli_set_charset($conn, "utf8mb4");
?>
