<?php
// 销售管理 API
require_once 'config.php';
require_once 'verify_token.php';

$user = getCurrentUser();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {
    case 'statistics':
        handleStatistics();
        break;
    case 'get':
        handleGetPhones();
        break;
    case 'get_detail':
        handleGetDetail();
        break;
    case 'add':
        handleAddPhone();
        break;
    case 'update':
        handleUpdatePhone();
        break;
    case 'delete':
        handleDeletePhone();
        break;
    case 'listing':
        handleListing();
        break;
    case 'delisting':
        handleDelisting();
        break;
    default:
        response(400, '参数错误');
}

// 获取统计数据
function handleStatistics() {
    global $conn, $user;
    
    $cid = $user['cid'];
    $current_month = date('Y-m');
    
    // 本月销售机器数和金额
    $sql = "SELECT COUNT(*) as count, SUM(selling_price) as amount FROM phone WHERE DATE_FORMAT(sell_time, '%Y-%m') = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $current_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $month_data = mysqli_fetch_assoc($result);
    
    // 待销售库存（没有销售时间的机器）
    $sql = "SELECT COUNT(*) as count FROM phone WHERE (sell_time IS NULL OR sell_time = '')";
    $result = mysqli_query($conn, $sql);
    $stock_data = mysqli_fetch_assoc($result);
    
    $statistics = [
        'monthCount' => $month_data['count'] ?? 0,
        'monthAmount' => number_format($month_data['amount'] ?? 0, 2),
        'stockCount' => $stock_data['count'] ?? 0
    ];
    
    response(200, '获取统计成功', $statistics);
}

// 获取销售列表
function handleGetPhones() {
    global $conn, $data;
    
    $machine_code = $data['machine_code'] ?? '';
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $state = $data['state'] ?? '';
    $sell_time_start = $data['sell_time_start'] ?? '';
    $sell_time_end = $data['sell_time_end'] ?? '';
    
    $sql = "SELECT phone.*, express.express_id, express.courier_company, express.tracking_id, express.express_type, express.postage as express_postage, express.delivery_time, express.remark as express_remark 
            FROM phone 
            LEFT JOIN express ON phone.wid = express.wid AND express.express_id = (
                SELECT MAX(e.express_id) 
                FROM express e 
                WHERE e.wid = phone.wid
            )
            WHERE 1=1";
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
    
    if (!empty($state)) {
        $sql .= " AND phone.state = ?";
        $params[] = $state;
        $types .= 's';
    } else {
        $sql .= " AND phone.state IN (3, 4)";
    }
    
    if (!empty($sell_time_start)) {
        $sql .= " AND phone.sell_time >= ?";
        $params[] = $sell_time_start;
        $types .= 's';
    }
    
    if (!empty($sell_time_end)) {
        $sql .= " AND phone.sell_time <= ?";
        $params[] = $sell_time_end . ' 23:59:59';
        $types .= 's';
    }
    
    $sql .= " ORDER BY phone.acquisition_time DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, 'SQL准备失败: ' . mysqli_error($conn));
    }
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        response(500, 'SQL执行失败: ' . mysqli_stmt_error($stmt));
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

// 添加销售
function handleAddPhone() {
    global $conn, $data, $user;
    
    $machine_code = $data['machine_code'] ?? '';
    $sell_time = $data['sell_time'] ?? '';
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $storage = $data['storage'] ?? '';
    $color = $data['color'] ?? '';
    $battery = $data['battery'] ?? 100;
    $acquisition_platform = $data['acquisition_platform'] ?? '';
    $purchase_price = $data['purchase_price'] ?? 0;
    $repair = $data['repair'] ?? 0;
    $matching = $data['matching'] ?? 1;
    $cost = $data['cost'] ?? 0;
    $postage = $data['postage'] ?? 0;
    $selling_price = $data['selling_price'] ?? 0;
    $profit = $data['profit'] ?? 0;
    $state = $data['state'] ?? 4;
    $remark = $data['remark'] ?? '';
    
    $sql = "INSERT INTO phone (machine_code, sell_time, brand, series, model, storage, color, battery, acquisition_platform, purchase_price, repair, matching, cost, postage, selling_price, profit, state, remark, uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssssisddddddisi', 
        $machine_code, $sell_time, $brand, $series, $model, $storage, $color, $battery, 
        $acquisition_platform, $purchase_price, $repair, $matching, $cost, $postage, $selling_price, $profit, $state, $remark, $user['uid']);
    
    if (mysqli_stmt_execute($stmt)) {
        $wid = mysqli_insert_id($conn);
        response(200, '添加成功', $wid);
    } else {
        response(500, '添加失败');
    }
}

// 更新销售
function handleUpdatePhone() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    $machine_code = $data['machine_code'] ?? '';
    $sell_time = $data['sell_time'] ?? '';
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $storage = $data['storage'] ?? '';
    $color = $data['color'] ?? '';
    $battery = $data['battery'] ?? 100;
    $acquisition_platform = $data['acquisition_platform'] ?? '';
    $purchase_price = $data['purchase_price'] ?? 0;
    $repair = $data['repair'] ?? 0;
    $matching = $data['matching'] ?? 1;
    $cost = $data['cost'] ?? 0;
    $postage = $data['postage'] ?? 0;
    $selling_price = $data['selling_price'] ?? 0;
    $profit = $data['profit'] ?? 0;
    $state = $data['state'] ?? 4;
    $shop_id = $data['shop_id'] ?? 0;
    $remark = $data['remark'] ?? '';
    
    $sql = "UPDATE phone SET machine_code=?, sell_time=?, brand=?, series=?, model=?, storage=?, color=?, battery=?, acquisition_platform=?, purchase_price=?, repair=?, matching=?, cost=?, postage=?, selling_price=?, profit=?, state=?, shop_id=?, remark=? WHERE wid=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssssssisdddddddiisi', 
        $machine_code, $sell_time, $brand, $series, $model, $storage, $color, $battery, 
        $acquisition_platform, $purchase_price, $repair, $matching, $cost, $postage, $selling_price, $profit, $state, $shop_id, $remark, $wid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '更新成功');
    } else {
        response(500, '更新失败: ' . mysqli_error($conn));
    }
}

// 删除销售
function handleDeletePhone() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    
    if (!$wid) {
        response(400, '无效的机器 ID');
    }
    
    $sql = "DELETE FROM phone WHERE wid=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $wid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '删除成功');
    } else {
        response(500, '删除失败');
    }
}

// 上架
function handleListing() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    $shop_id = $data['shop_id'] ?? 0;
    $state = 4;
    
    if (!$wid) {
        response(400, '无效的机器 ID');
    }
    
    $sql = "UPDATE phone SET state = ?, shop_id = ? WHERE wid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $state, $shop_id, $wid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '上架成功');
    } else {
        response(500, '上架失败');
    }
}

// 下架
function handleDelisting() {
    global $conn, $data;
    
    $wid = $data['wid'] ?? 0;
    $state = 3;
    
    if (!$wid) {
        response(400, '无效的机器 ID');
    }
    
    $sql = "UPDATE phone SET state = ? WHERE wid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $state, $wid);
    
    if (mysqli_stmt_execute($stmt)) {
        response(200, '下架成功');
    } else {
        response(500, '下架失败');
    }
}
?>
