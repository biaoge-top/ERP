<?php
// 用户管理接口
require_once 'config.php';
require_once 'verify_token.php';

// 验证token
$user = getCurrentUser();
$cid = $user['cid'];

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'get':
            getUsers();
            break;
        case 'get_available':
            getAvailableUsers();
            break;
        case 'add':
            addUser($data);
            break;
        case 'update':
            updateUser($data);
            break;
        case 'delete':
            deleteUser($data);
            break;
        case 'join_company':
            joinCompany($data);
            break;
        default:
            response(400, '参数错误');
            break;
    }
} else {
    response(405, '方法不允许');
}

// 获取用户列表
function getUsers() {
    global $conn, $cid;
    
    $sql = "SELECT * FROM users WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // 移除密码字段
        unset($row['password']);
        $users[] = $row;
    }
    
    response(200, '获取用户列表成功', $users);
}

// 获取未加入企业的用户列表
function getAvailableUsers() {
    global $conn, $cid;
    
    // 查询已注册但未加入当前企业的用户（cid 为 0 或 NULL）
    $sql = "SELECT uid, username, email, mobile_phone, state FROM users WHERE (cid = 0 OR cid IS NULL) AND state = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    response(200, '获取可用用户列表成功', $users);
}

// 添加用户到企业
function joinCompany($data) {
    global $conn, $cid;
    
    $uid = $data['uid'] ?? 0;
    
    if ($uid <= 0) {
        response(400, '用户 ID 无效');
        return;
    }
    
    // 更新用户的 cid
    $sql = "UPDATE users SET cid = ? WHERE uid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $cid, $uid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '添加用户成功');
    } else {
        response(500, '添加用户失败');
    }
}

// 添加用户
function addUser($data) {
    global $conn, $cid;
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $email = $data['email'] ?? '';
    $mobile_phone = $data['mobile_phone'] ?? '';
    $authority = $data['authority'] ?? '';
    $state = $data['state'] ?? 1;
    
    if (empty($username) || empty($password)) {
        response(400, '用户名和密码不能为空');
        return;
    }
    
    // 检查用户名是否已存在
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        response(400, '用户名已存在');
        return;
    }
    
    // 加密密码
    $hashed_password = md5($password);
    
    // 获取注册时间和IP
    $register_time = date('Y-m-d H:i:s');
    $register_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // 插入用户数据
    $sql = "INSERT INTO users (cid, username, password, email, mobile_phone, authority, register_time, register_ip, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isssssssi', $cid, $username, $hashed_password, $email, $mobile_phone, $authority, $register_time, $register_ip, $state);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '添加用户成功');
    } else {
        response(500, '添加用户失败');
    }
}

// 更新用户
function updateUser($data) {
    global $conn, $cid;
    
    $uid = $data['uid'] ?? 0;
    $password = $data['password'] ?? '';
    $email = $data['email'] ?? '';
    $mobile_phone = $data['mobile_phone'] ?? '';
    $authority = $data['authority'] ?? '';
    $state = $data['state'] ?? 1;
    
    if (empty($uid)) {
        response(400, '参数错误');
        return;
    }
    
    // 检查用户是否存在且属于当前企业
    $sql = "SELECT * FROM users WHERE uid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        response(400, '用户不存在');
        return;
    }
    
    // 构建更新语句
    $update_fields = [];
    $params = [];
    $param_types = '';
    
    if (!empty($password)) {
        $update_fields[] = "password = ?";
        $params[] = md5($password);
        $param_types .= 's';
    }
    
    if (!empty($email)) {
        $update_fields[] = "email = ?";
        $params[] = $email;
        $param_types .= 's';
    }
    
    if (!empty($mobile_phone)) {
        $update_fields[] = "mobile_phone = ?";
        $params[] = $mobile_phone;
        $param_types .= 's';
    }
    
    if (!empty($authority)) {
        $update_fields[] = "authority = ?";
        $params[] = $authority;
        $param_types .= 's';
    }
    
    $update_fields[] = "state = ?";
    $params[] = $state;
    $param_types .= 'i';
    
    $params[] = $uid;
    $params[] = $cid;
    $param_types .= 'ii';
    
    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE uid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '更新用户成功');
    } else {
        response(500, '更新用户失败');
    }
}

// 删除用户
function deleteUser($data) {
    global $conn, $cid;
    
    $uid = $data['uid'] ?? 0;
    
    if (empty($uid)) {
        response(400, '参数错误');
        return;
    }
    
    // 检查用户是否存在且属于当前企业
    $sql = "SELECT * FROM users WHERE uid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        response(400, '用户不存在');
        return;
    }
    
    // 删除用户
    $sql = "DELETE FROM users WHERE uid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $cid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '删除用户成功');
    } else {
        response(500, '删除用户失败');
    }
}
?>