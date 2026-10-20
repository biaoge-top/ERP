<?php
// 采购管理 API
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
    default:
        response(400, '参数错误');
}

// 获取统计数据
function handleStatistics() {
    global $conn, $user;
    
    $cid = $user['cid'];
    $current_month = date('Y-m');
    
    // 本月采购机器数和金额
    $sql = "SELECT COUNT(*) as count, SUM(purchase_price) as amount FROM phone WHERE DATE_FORMAT(acquisition_time, '%Y-%m') = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $current_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $month_data = mysqli_fetch_assoc($result);
    
    // 当前库存（状态为 1,2,3,4 的机器）
    $sql = "SELECT COUNT(*) as count FROM phone WHERE state IN (1, 2, 3, 4)";
    $result = mysqli_query($conn, $sql);
    $stock_data = mysqli_fetch_assoc($result);
    
    $statistics = [
        'monthCount' => $month_data['count'] ?? 0,
        'monthAmount' => number_format($month_data['amount'] ?? 0, 2),
        'stockCount' => $stock_data['count'] ?? 0
    ];
    
    response(200, '获取统计成功', $statistics);
}

// 获取采购列表
function handleGetPhones() {
    global $conn, $data;
    
    $machine_code = $data['machine_code'] ?? '';
    $brand = $data['brand'] ?? '';
    $series = $data['series'] ?? '';
    $model = $data['model'] ?? '';
    $state = $data['state'] ?? '';
    $acquisition_time_start = $data['acquisition_time_start'] ?? '';
    $acquisition_time_end = $data['acquisition_time_end'] ?? '';
    
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
    
    // 处理状态筛选
    if (!empty($state)) {
        if (is_array($state)) {
            // 如果是数组，生成 IN 查询
            $placeholders = implode(',', array_fill(0, count($state), '?'));
            $sql .= " AND phone.state IN ($placeholders)";
            foreach ($state as $s) {
                $params[] = (int)$s;
                $types .= 'i';
            }
        } else {
            // 如果是单个值
            $sql .= " AND phone.state = ?";
            $params[] = (int)$state;
            $types .= 'i';
        }
    }
    
    if (!empty($acquisition_time_start)) {
        $sql .= " AND phone.acquisition_time >= ?";
        $params[] = $acquisition_time_start;
        $types .= 's';
    }
    
    if (!empty($acquisition_time_end)) {
        $sql .= " AND phone.acquisition_time <= ?";
        $params[] = $acquisition_time_end . ' 23:59:59';
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

// 添加采购
function handleAddPhone() {
    global $conn, $data, $user;
    
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
    $state = $data['state'] ?? 1;
    $remark = $data['remark'] ?? '';
    
    // 计算总成本
    $cost = $purchase_price + $repair + $matching;
    //自动计算售价
    $selling_price = $cost * 1.2;
    // 计算利润
    $profit = $selling_price - $cost;

    //写入数据库
    $sql = "INSERT INTO phone (machine_code, acquisition_time, brand, series, model, storage, color, battery, acquisition_platform, purchase_price, repair, matching, cost, postage, selling_price, profit, state, remark, uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sssssssisdddddddisi', 
        $machine_code, $acquisition_time, $brand, $series, $model, $storage, $color, $battery, 
        $acquisition_platform, $purchase_price, $repair, $matching, $cost, $postage, $selling_price, $profit, $state, $remark, $user['uid']);
    
    if (mysqli_stmt_execute($stmt)) {
        $wid = mysqli_insert_id($conn);
        response(200, '添加成功', $wid);
    } else {
        response(500, '添加失败');
    }
}

// 更新采购
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
    $cost = $data['cost'] ?? 0;
    $profit = $data['profit'] ?? 0;
    $state = $data['state'] ?? 1;
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
        response(500, '更新失败：' . mysqli_error($conn));
    }
}

// 删除采购
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
?>
