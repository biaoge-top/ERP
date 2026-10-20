<?php
// 验证token的中间件
require_once 'db.php';

function verifyToken() {
    global $conn;
    
    // 从请求头获取token
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // 如果请求头中没有token，尝试从POST数据中获取
    if (empty($token)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';
    }
    
    // 移除Bearer前缀（如果有）
    $token = str_replace('Bearer ', '', $token);
    
    if (empty($token)) {
        echo json_encode(['code' => 401, 'msg' => '未提供token']);
        exit;
    }
    
    // 验证token
    $sql = "SELECT * FROM tokens WHERE token = ? AND state = 1 AND expiration_time > NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // token 有效，更新过期时间
        $new_expiration_time = date('Y-m-d H:i:s', strtotime('+7 days'));
        $sql = "UPDATE tokens SET expiration_time = ? WHERE token = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $new_expiration_time, $token);
        mysqli_stmt_execute($stmt);
        
        // 调试日志
        error_log("Token valid - uid: {$row['uid']}, cid: {$row['cid']}, username: {$row['username']}");
        
        // 返回用户信息
        return [
            'uid' => $row['uid'],
            'cid' => $row['cid'],
            'username' => $row['username'],
            'authority' => $row['authority']
        ];
    } else {
        error_log("Token invalid or expired");
        echo json_encode(['code' => 401, 'msg' => 'token 无效或已过期']);
        exit;
    }
}

// 验证token并返回用户信息
function getCurrentUser() {
    return verifyToken();
}
?>