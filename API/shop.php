<?php
// 店铺管理接口
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
            getShops();
            break;
        case 'add':
            addShop($data);
            break;
        case 'update':
            updateShop($data);
            break;
        case 'delete':
            deleteShop($data);
            break;
        default:
            response(400, '参数错误');
            break;
    }
} else {
    response(405, '方法不允许');
}

// 获取店铺列表
function getShops() {
    global $conn, $cid;
    
    $sql = "SELECT * FROM shops WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $shops = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $shops[] = $row;
    }
    
    response(200, '获取店铺列表成功', $shops);
}

// 添加店铺
function addShop($data) {
    global $conn, $cid, $user;
    
    $shop_name = $data['shop_name'] ?? '';
    $platform = $data['platform'] ?? '';
    $state = $data['state'] ?? 1;
    $uid = $user['uid'];
    
    if (empty($shop_name)) {
        response(400, '店铺名称不能为空');
        return;
    }
    
    // 检查店铺名称是否已存在
    $sql = "SELECT * FROM shops WHERE cid = ? AND shop_name = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $cid, $shop_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        response(400, '店铺名称已存在');
        return;
    }
    
    // 插入店铺数据
    $sql = "INSERT INTO shops (cid, shop_name, platform, uid, state) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issii', $cid, $shop_name, $platform, $uid, $state);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '添加店铺成功');
    } else {
        response(500, '添加店铺失败');
    }
}

// 更新店铺
function updateShop($data) {
    global $conn, $cid;
    
    $sid = $data['sid'] ?? 0;
    $shop_name = $data['shop_name'] ?? '';
    $platform = $data['platform'] ?? '';
    $state = $data['state'] ?? 1;
    
    if (empty($sid) || empty($shop_name)) {
        response(400, '参数错误');
        return;
    }
    
    // 检查店铺是否存在且属于当前企业
    $sql = "SELECT * FROM shops WHERE sid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $sid, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        response(400, '店铺不存在');
        return;
    }
    
    // 检查店铺名称是否已存在（排除当前店铺）
    $sql = "SELECT * FROM shops WHERE cid = ? AND shop_name = ? AND sid != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isi', $cid, $shop_name, $sid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        response(400, '店铺名称已存在');
        return;
    }
    
    // 更新店铺数据
    $sql = "UPDATE shops SET shop_name = ?, platform = ?, state = ? WHERE sid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssiii', $shop_name, $platform, $state, $sid, $cid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '更新店铺成功');
    } else {
        response(500, '更新店铺失败');
    }
}

// 删除店铺
function deleteShop($data) {
    global $conn, $cid;
    
    $sid = $data['sid'] ?? 0;
    
    if (empty($sid)) {
        response(400, '参数错误');
        return;
    }
    
    // 检查店铺是否存在且属于当前企业
    $sql = "SELECT * FROM shops WHERE sid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $sid, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        response(400, '店铺不存在');
        return;
    }
    
    // 删除店铺
    $sql = "DELETE FROM shops WHERE sid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $sid, $cid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '删除店铺成功');
    } else {
        response(500, '删除店铺失败');
    }
}
?>