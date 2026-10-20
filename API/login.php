<?php
// 数据库连接文件
require_once 'db.php';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'login') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        // 验证参数
        if (empty($username) || empty($password)) {
            echo json_encode(['code' => 400, 'msg' => '用户名和密码不能为空']);
            exit;
        }
        
        // 验证用户
        $sql = "SELECT * FROM users WHERE username = ? AND state = 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // 验证密码（这里假设密码是MD5加密的，实际应该使用更安全的加密方式）
            if (md5($password) === $row['password']) {
                // 生成token
                $token = generateToken();
                $expiration_time = date('Y-m-d H:i:s', strtotime('+7 days'));
                
                // 存储token
                $sql = "INSERT INTO tokens (uid, cid, username, token, authority, expiration_time, state) VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'iissss', $row['uid'], $row['cid'], $row['username'], $token, $row['authority'], $expiration_time);
                
                if (mysqli_stmt_execute($stmt)) {
                    // 检查是否需要选择企业
                    $need_select_company = false;
                    if (empty($row['cid']) || $row['cid'] == 0) {
                        $need_select_company = true;
                    }
                    
                    echo json_encode([
                        'code' => 200,
                        'msg' => '登录成功',
                        'data' => [
                            'uid' => $row['uid'],
                            'cid' => $row['cid'],
                            'username' => $row['username'],
                            'token' => $token,
                            'need_select_company' => $need_select_company
                        ]
                    ]);
                } else {
                    echo json_encode(['code' => 500, 'msg' => '生成token失败']);
                }
            } else {
                echo json_encode(['code' => 401, 'msg' => '密码错误']);
            }
        } else {
            echo json_encode(['code' => 401, 'msg' => '用户不存在']);
        }
    } else {
        echo json_encode(['code' => 400, 'msg' => '无效的请求']);
    }
} else {
    echo json_encode(['code' => 405, 'msg' => '方法不允许']);
}

// 生成token函数
function generateToken() {
    return bin2hex(random_bytes(64));
}
?>