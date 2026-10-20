<?php
// 质检管理 API
require_once 'config.php';
require_once 'verify_token.php';

$user = getCurrentUser();
$cid = $user['cid'];

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {
    case 'statistics':
        handleStatistics();
        break;
    case 'get':
        handleGetPhones();
        break;
    case 'quality_check':
        handleQualityCheck();
        break;
    case 'batch_quality_check':
        handleBatchQualityCheck($cid);
        break;
    case 'update':
        handleUpdatePhone();
        break;
    default:
        response(400, '参数错误');
}

// 获取统计数据
function handleStatistics() {
    global $conn;
    
    // 待质检数量（状态为 2 的机器）
    $sql = "SELECT COUNT(*) as count FROM phone WHERE state = 2";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL 准备失败：' . mysqli_error($conn));
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $quality_data = mysqli_fetch_assoc($result);
    
    // 当前库存数量（状态为 3 的机器 - 待上架）
    $sql = "SELECT COUNT(*) as count FROM phone WHERE state = 3";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL 准备失败：' . mysqli_error($conn));
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $inventory_data = mysqli_fetch_assoc($result);
    
    $statistics = [
        'qualityCount' => $quality_data['count'] ?? 0,
        'inventoryCount' => $inventory_data['count'] ?? 0
    ];
    
    response(200, '获取统计成功', $statistics);
}

// 获取待质检列表
function handleGetPhones() {
    global $conn, $data;
    
    $machine_code = $data['machine_code'] ?? '';
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    
    // 使用子查询获取每个 phone 的最新快递记录，确保每个 phone 只返回一条记录
    $sql = "SELECT phone.*, express.express_id, express.courier_company, express.tracking_id, express.express_type, express.postage as express_postage, express.delivery_time, express.remark as express_remark 
            FROM phone 
            LEFT JOIN express ON phone.wid = express.wid AND express.express_id = (
                SELECT MAX(e.express_id) 
                FROM express e 
                WHERE e.wid = phone.wid
            )
            WHERE phone.state = 2";
    $params = [];
    $types = '';
    
    if (!empty($machine_code)) {
        $sql .= " AND phone.machine_code LIKE ?";
        $params[] = "%$machine_code%";
        $types .= 's';
    }
    
    if (!empty($brand)) {
        $sql .= " AND phone.brand = ?";
        $params[] = $brand;
        $types .= 's';
    }
    
    if (!empty($series)) {
        $sql .= " AND phone.series = ?";
        $params[] = $series;
        $types .= 's';
    }
    
    if (!empty($model)) {
        $sql .= " AND phone.model = ?";
        $params[] = $model;
        $types .= 's';
    }
    
    $sql .= " ORDER BY phone.acquisition_time DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL 准备失败：' . mysqli_error($conn));
    }
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        response(500, 'SQL 执行失败：' . mysqli_stmt_error($stmt));
    }
    $result = mysqli_stmt_get_result($stmt);
    
    $phones = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $phones[] = $row;
    }
    
    response(200, '获取成功', $phones);
}

// 获取单个机器详情
function handleGetDetail() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    
    $sql = "SELECT phone.*, express.express_id, express.courier_company, express.tracking_id, express.express_type, express.postage as express_postage, express.delivery_time, express.remark as express_remark 
            FROM phone 
            LEFT JOIN express ON phone.wid = express.wid AND express.express_id = (
                SELECT MAX(e.express_id) 
                FROM express e 
                WHERE e.wid = phone.wid
            )
            WHERE phone.wid = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL 准备失败：' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "i", $wid);
    if (!mysqli_stmt_execute($stmt)) {
        response(500, 'SQL 执行失败：' . mysqli_stmt_error($stmt));
    }
    $result = mysqli_stmt_get_result($stmt);
    $phone = mysqli_fetch_assoc($result);
    
    if ($phone) {
        response(200, '获取成功', $phone);
    } else {
        response(404, '机器不存在');
    }
}

// 一键质检
function handleQualityCheck() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    $state = $data['state'] ?? 3;
    
    if ($wid <= 0) {
        response(400, '机器 ID 无效');
        return;
    }
 
    
    $sql = "UPDATE phone SET state = ? WHERE wid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL 准备失败：' . mysqli_error($conn));
        return;
    }
    mysqli_stmt_bind_param($stmt, "ii", $state, $wid);
    $result = mysqli_stmt_execute($stmt);
    
    if ($result) {
        mysqli_stmt_close($stmt);
        response(200, '质检成功');
    } else {
        mysqli_stmt_close($stmt);
        response(500, '质检失败：' . mysqli_stmt_error($stmt));
    }
}

// 批量一键质检
function handleBatchQualityCheck($cid) {
    global $conn;
    
    // 将所有待质检（state=2）的机器改为待上架（state=3）
    $sql = "UPDATE phone p 
        JOIN users u ON p.uid = u.uid 
        SET p.state = 3 
        WHERE p.state = 2 AND u.cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL 准备失败：' . mysqli_error($conn));
        return;
    }
    mysqli_stmt_bind_param($stmt, "i", $cid);
    $result = mysqli_stmt_execute($stmt);
    
    if ($result) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        response(200, '批量质检成功', ['count' => $affected_rows]);
    } else {
        mysqli_stmt_close($stmt);
        response(500, '批量质检失败：' . mysqli_stmt_error($stmt));
    }
}

// 更新机器信息
function handleUpdatePhone() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    $machine_code = $data['machine_code'] ?? '';
    $acquisition_time = $data['acquisition_time'] ?? '';
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $storage = $data['storage'] ?? '';
    $color = $data['color'] ?? '';
    $battery = $data['battery'] ?? 100;
    $acquisition_platform = $data['acquisition_platform'] ?? '';
    $purchase_price = $data['purchase_price'] ?? 0;
    $repair = $data['repair'] ?? 0;
    $matching = $data['matching'] ?? 0;
    $postage = $data['postage'] ?? 0;
    $state = $data['state'] ?? 2;
    $remark = $data['remark'] ?? '';
    // 计算总成本
    $cost = $purchase_price + $repair + $matching;
    //自动计算售价
    $selling_price = $cost * 1.2;
    // 计算利润
    $profit = $selling_price - $cost;

    
    $sql = "UPDATE phone SET machine_code=?, acquisition_time=?, brand=?, series=?, model=?, storage=?, color=?, battery=?, acquisition_platform=?, purchase_price=?, repair=?, matching=?, postage=?, cost=?, selling_price=?, profit=?, state=?, remark=? WHERE wid=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssssssisdddddddisi', 
        $machine_code, $acquisition_time, $brand, $series, $model, $storage, $color, $battery, 
        $acquisition_platform, $purchase_price, $repair, $matching, $postage, $cost, $selling_price, $profit, $state, $remark, $wid);   
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '更新成功');
    } else {
        response(500, '更新失败');
    }
}
?>
