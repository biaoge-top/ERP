<?php
// 型号管理接口
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
            getTypes();
            break;
        case 'statistics':
            getStatistics();
            break;
        case 'add':
            addType($data);
            break;
        case 'update':
            updateType($data);
            break;
        case 'delete':
            deleteType($data);
            break;
        default:
            response(400, '参数错误');
            break;
    }
} else {
    response(405, '方法不允许');
}

// 获取型号列表
function getTypes() {
    global $conn, $cid;
    
    $sql = "SELECT * FROM types WHERE cid = ? ORDER BY brand, series, model";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $types = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $types[] = $row;
    }
    
    response(200, '获取型号列表成功', $types);
}

// 获取统计数据
function getStatistics() {
    global $conn, $cid;
    
    // 型号总数
    $sql = "SELECT COUNT(*) as count FROM types WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_data = mysqli_fetch_assoc($result);
    
    // 正常型号数量
    $sql = "SELECT COUNT(*) as count FROM types WHERE cid = ? AND state = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $active_data = mysqli_fetch_assoc($result);
    
    // 品牌数量
    $sql = "SELECT COUNT(DISTINCT brand) as count FROM types WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $brand_data = mysqli_fetch_assoc($result);
    
    // 系列数量
    $sql = "SELECT COUNT(DISTINCT CONCAT(brand, series)) as count FROM types WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $series_data = mysqli_fetch_assoc($result);
    
    $statistics = [
        'totalCount' => $total_data['count'] ?? 0,
        'activeCount' => $active_data['count'] ?? 0,
        'brandCount' => $brand_data['count'] ?? 0,
        'seriesCount' => $series_data['count'] ?? 0
    ];
    
    response(200, '获取统计数据成功', $statistics);
}

// 添加型号
function addType($data) {
    global $conn, $cid, $user;
    
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $state = $data['state'] ?? 1;
    $uid = $user['uid'];
    
    if (empty($brand) || empty($series) || empty($model)) {
        response(400, '品牌、系列和型号不能为空');
        return;
    }
    
    // 检查型号是否已存在
    $sql = "SELECT * FROM types WHERE cid = ? AND brand = ? AND series = ? AND model = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isss', $cid, $brand, $series, $model);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        response(400, '该型号已存在');
        return;
    }
    
    // 插入型号数据
    $sql = "INSERT INTO types (cid, brand, series, model, uid, state) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isssii', $cid, $brand, $series, $model, $uid, $state);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '添加型号成功');
    } else {
        response(500, '添加型号失败');
    }
}

// 更新型号
function updateType($data) {
    global $conn, $cid;
    
    $tid = $data['tid'] ?? 0;
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $state = $data['state'] ?? 1;
    
    if (empty($tid) || empty($brand) || empty($series) || empty($model)) {
        response(400, '参数错误');
        return;
    }
    
    // 检查型号是否存在且属于当前企业
    $sql = "SELECT * FROM types WHERE tid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $tid, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        response(400, '型号不存在');
        return;
    }
    
    // 检查型号是否已存在（排除当前型号）
    $sql = "SELECT * FROM types WHERE cid = ? AND brand = ? AND series = ? AND model = ? AND tid != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isssi', $cid, $brand, $series, $model, $tid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        response(400, '该型号已存在');
        return;
    }
    
    // 更新型号数据
    $sql = "UPDATE types SET brand = ?, series = ?, model = ?, state = ? WHERE tid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssiii', $brand, $series, $model, $state, $tid, $cid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '更新型号成功');
    } else {
        response(500, '更新型号失败');
    }
}

// 删除型号
function deleteType($data) {
    global $conn, $cid;
    
    $tid = $data['tid'] ?? 0;
    
    if (empty($tid)) {
        response(400, '参数错误');
        return;
    }
    
    // 检查型号是否存在且属于当前企业
    $sql = "SELECT * FROM types WHERE tid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $tid, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        response(400, '型号不存在');
        return;
    }
    
    // 检查该型号是否被手机表使用
    $sql = "SELECT * FROM phone WHERE tid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $tid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        response(400, '该型号正在被使用，无法删除');
        return;
    }
    
    // 删除型号
    $sql = "DELETE FROM types WHERE tid = ? AND cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $tid, $cid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '删除型号成功');
    } else {
        response(500, '删除型号失败');
    }
}
?>