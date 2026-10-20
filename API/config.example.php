<?php
//utf-8,中国时区
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Shanghai");

// 数据库配置（复制本文件为 config.php 并填写真实配置）
$db_host = "localhost";
$db_port = 3306;
$db_name = "erp";
$db_user = "your_db_user";
$db_password = "your_db_password";

//设置跨域
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: *");
header("Access-Control-Allow-Headers: *");

//接收POST请求
$data = json_decode(file_get_contents("php://input"), true);

//数据库连接
$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);
// 检查连接
if (!$conn) {
    die("数据库连接失败：" . mysqli_connect_error());
}
// 设置MySQL时区为东八区
mysqli_query($conn, "SET time_zone = '+08:00'");

//统一响应格式
function response($code, $msg, $data = null) {
    $response = [
        "code" => $code,
        "msg" => $msg,
        "data" => $data
    ];
    echo json_encode($response);
}


//API Key
//deepseek api key
$api_key = "your_api_key";
