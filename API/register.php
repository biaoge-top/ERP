<?php
// 数据库连接文件
require_once 'db.php';

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'register') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        // 验证参数
        if (empty($username) || empty($password)) {
            echo json_encode(['code' => 400, 'msg' => '用户名和密码不能为空']);
            exit;
        }
        
        // 检查用户名是否已存在
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            echo json_encode(['code' => 400, 'msg' => '用户名已存在']);
            exit;
        }
        
        // 加密密码
        $hashed_password = md5($password);
        
        // 获取注册时间和IP
        $register_time = date('Y-m-d H:i:s');
        $register_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // 插入用户数据
        $sql = "INSERT INTO users (cid, username, password, register_time, register_ip, state) VALUES (0, ?, ?, ?, ?, 1)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssss', $username, $hashed_password, $register_time, $register_ip);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['code' => 200, 'msg' => '注册成功']);
        } else {
            echo json_encode(['code' => 500, 'msg' => '注册失败']);
        }
    } else {
        echo json_encode(['code' => 400, 'msg' => '无效的请求']);
    }
} else {
    echo json_encode(['code' => 405, 'msg' => '方法不允许']);
}
?>