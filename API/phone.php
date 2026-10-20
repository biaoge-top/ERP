<?php
// 手机回收销售接口
//引入数据库连接
require "config.php";
//引入token验证
require "verify_token.php";

//验证token
$user = getCurrentUser();

//接收POST请求
$data = json_decode(file_get_contents("php://input"), true);
$action = isset($data["action"]) ? $data["action"] : '';

//增删改查
switch($action) {
    case "get":
        // 处理查询逻辑
        getPhones();
        break;
    case "add":
        // 处理添加逻辑
        addPhone();
        break;
    case "update":
        // 处理更新逻辑
        updatePhone();
        break;
    case "delete":
        // 处理删除逻辑
        deletePhone();
        break;
    default:
        response(400, "参数错误");
        exit;
}

function getPhones() {
    global $conn, $data;
    
    // 获取筛选参数
    $wid = isset($data['wid']) ? $data['wid'] : '';
    $machine_code = isset($data['machine_code']) ? $data['machine_code'] : '';
    $brand = isset($data['brand']) ? $data['brand'] : '';
    $series = isset($data['series']) ? $data['series'] : '';
    $model = isset($data['model']) ? $data['model'] : '';
    $color = isset($data['color']) ? $data['color'] : '';
    $storage = isset($data['storage']) ? $data['storage'] : '';
    $acquisition_platform = isset($data['acquisition_platform']) ? $data['acquisition_platform'] : '';
    $state = isset($data['state']) ? $data['state'] : '';
    $shop_id = isset($data['shop_id']) ? $data['shop_id'] : '';
    $profit_type = isset($data['profit_type']) ? $data['profit_type'] : '';
    
    // 构建 SQL 查询
    $sql = "SELECT * FROM phone WHERE 1=1";
    $params = [];
    $types = "";
    
    // 编号筛选
    if (!empty($wid)) {
        $sql .= " AND wid = ?";
        $params[] = $wid;
        $types .= "i";
    }
    
    if (!empty($machine_code)) {
        $sql .= " AND machine_code LIKE ?";
        $params[] = "%$machine_code%";
        $types .= "s";
    }
    
    if (!empty($brand)) {
        $sql .= " AND brand = ?";
        $params[] = $brand;
        $types .= "s";
    }
    
    if (!empty($series)) {
        $sql .= " AND series = ?";
        $params[] = $series;
        $types .= "s";
    }
    
    if (!empty($model)) {
        $sql .= " AND model = ?";
        $params[] = $model;
        $types .= "s";
    }
    
    if (!empty($color)) {
        $sql .= " AND color = ?";
        $params[] = $color;
        $types .= "s";
    }
    
    if (!empty($storage)) {
        $sql .= " AND storage = ?";
        $params[] = $storage;
        $types .= "s";
    }
    
    if (!empty($acquisition_platform)) {
        $sql .= " AND acquisition_platform = ?";
        $params[] = $acquisition_platform;
        $types .= "s";
    }
    
    if (!empty($state)) {
        $sql .= " AND state = ?";
        $params[] = $state;
        $types .= "i";
    }
    
    // 店铺筛选
    if (!empty($shop_id)) {
        $sql .= " AND shop_id = ?";
        $params[] = $shop_id;
        $types .= "i";
    }
    
    // 盈亏筛选
    if (!empty($profit_type)) {
        if ($profit_type === 'positive') {
            $sql .= " AND profit > 0";
        } elseif ($profit_type === 'negative') {
            $sql .= " AND profit < 0";
        } elseif ($profit_type === 'zero') {
            $sql .= " AND profit = 0";
        }
    }
    
    // 日期筛选
    $startDate = isset($data['startDate']) ? $data['startDate'] : '';
    $endDate = isset($data['endDate']) ? $data['endDate'] : '';
    $soldStartDate = isset($data['soldStartDate']) ? $data['soldStartDate'] : '';
    $soldEndDate = isset($data['soldEndDate']) ? $data['soldEndDate'] : '';
    
    // 采购时间筛选
    if (!empty($startDate)) {
        $sql .= " AND acquisition_time >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    
    if (!empty($endDate)) {
        $sql .= " AND acquisition_time <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    // 售出时间筛选
    if (!empty($soldStartDate)) {
        $sql .= " AND sell_time >= ?";
        $params[] = $soldStartDate;
        $types .= "s";
    }
    
    if (!empty($soldEndDate)) {
        $sql .= " AND sell_time <= ?";
        $params[] = $soldEndDate;
        $types .= "s";
    }
    
    $sql .= " ORDER BY wid DESC";
    
    // 执行查询
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        $result = mysqli_stmt_execute($stmt);
        if ($result) {
            $result = mysqli_stmt_get_result($stmt);
            $phones = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $phones[] = $row;
            }
            response(200, "查询成功", $phones);
        } else {
            response(500, "查询失败");
        }
        mysqli_stmt_close($stmt);
    } else {
        response(500, "查询失败");
    }
}

function addPhone() {
    global $conn, $data;
    // 处理添加逻辑
    $machine_code = isset($data['machine_code']) ? $data['machine_code'] : '';
    $acquisition_time = isset($data['acquisition_time']) ? $data['acquisition_time'] : date('Y-m-d H:i:s');
    $brand = isset($data['brand']) ? $data['brand'] : '';
    $series = isset($data['series']) ? $data['series'] : '';
    $model = isset($data['model']) ? $data['model'] : '';
    $storage = isset($data['storage']) ? $data['storage'] : '';
    $color = isset($data['color']) ? $data['color'] : '';
    $battery = isset($data['battery']) ? $data['battery'] : 0;
    $acquisition_platform = isset($data['acquisition_platform']) ? $data['acquisition_platform'] : '';
    $purchase_price = isset($data['purchase_price']) ? $data['purchase_price'] : 0;
    $repair = isset($data['repair']) ? $data['repair'] : 0;
    $matching = isset($data['matching']) ? $data['matching'] : 0;
    $cost = isset($data['cost']) ? $data['cost'] : 0;
    $postage = isset($data['postage']) ? $data['postage'] : 0;
    $selling_price = isset($data['selling_price']) ? $data['selling_price'] : 0;
    $sell_time = isset($data['sell_time']) ? $data['sell_time'] : '';
    $remark = isset($data['remark']) ? $data['remark'] : '';
    $state = isset($data['state']) ? $data['state'] : 1;
    
    // 自动计算总成本 = 维修 + 配套 + 收购价
    $total_cost = $repair + $matching + $purchase_price;
    
    // 将总成本赋值给cost字段
    $cost = $total_cost;
    
    // 自动计算利润 = 售价 - 总成本 - 邮费
    $profit = $selling_price - $total_cost - $postage;
    
    $sql = "INSERT INTO phone (machine_code, acquisition_time, brand, series, model, storage, color, battery, acquisition_platform, purchase_price, repair, matching, cost, postage, selling_price, sell_time, profit, remark, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssssisddddddsdsi", $machine_code, $acquisition_time, $brand, $series, $model, $storage, $color, $battery, $acquisition_platform, $purchase_price, $repair, $matching, $cost, $postage, $selling_price, $sell_time, $profit, $remark, $state);
    $result = mysqli_stmt_execute($stmt);
    if($result) {
        response(200, "添加成功");
    } else {
        response(500, "添加失败");
    }
    mysqli_stmt_close($stmt);
}

function updatePhone() {
    global $conn, $data;
    // 处理更新逻辑
    $wid = isset($data['wid']) ? $data['wid'] : 0;
    $machine_code = isset($data['machine_code']) ? $data['machine_code'] : '';
    $acquisition_time = isset($data['acquisition_time']) ? $data['acquisition_time'] : '';
    $brand = isset($data['brand']) ? $data['brand'] : '';
    $series = isset($data['series']) ? $data['series'] : '';
    $model = isset($data['model']) ? $data['model'] : '';
    $storage = isset($data['storage']) ? $data['storage'] : '';
    $color = isset($data['color']) ? $data['color'] : '';
    $battery = isset($data['battery']) ? $data['battery'] : 0;
    $acquisition_platform = isset($data['acquisition_platform']) ? $data['acquisition_platform'] : '';
    $purchase_price = isset($data['purchase_price']) ? $data['purchase_price'] : 0;
    $repair = isset($data['repair']) ? $data['repair'] : 0;
    $matching = isset($data['matching']) ? $data['matching'] : 0;
    $cost = isset($data['cost']) ? $data['cost'] : 0;
    $postage = isset($data['postage']) ? $data['postage'] : 0;
    $selling_price = isset($data['selling_price']) ? $data['selling_price'] : 0;
    $sell_time = isset($data['sell_time']) ? $data['sell_time'] : '';
    $remark = isset($data['remark']) ? $data['remark'] : '';
    $state = isset($data['state']) ? $data['state'] : 3;
    
    // 自动计算总成本 = 维修 + 配套 + 收购价
    $total_cost = $repair + $matching + $purchase_price;
    
    // 将总成本赋值给cost字段
    $cost = $total_cost;
    
    // 自动计算利润 = 售价 - 总成本 - 邮费
    $profit = $selling_price - $total_cost - $postage;
    
    $sql = "UPDATE phone SET machine_code = ?, acquisition_time = ?, brand = ?, series = ?, model = ?, storage = ?, color = ?, battery = ?, acquisition_platform = ?, purchase_price = ?, repair = ?, matching = ?, cost = ?, postage = ?, selling_price = ?, sell_time = ?, profit = ?, remark = ?, state = ? WHERE wid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssssisddddddsdsii", $machine_code, $acquisition_time, $brand, $series, $model, $storage, $color, $battery, $acquisition_platform, $purchase_price, $repair, $matching, $cost, $postage, $selling_price, $sell_time, $profit, $remark, $state, $wid);
    $result = mysqli_stmt_execute($stmt);
    if($result) {
        response(200, "更新成功");
    } else {
        response(500, "更新失败");
    }
    mysqli_stmt_close($stmt);
}

function deletePhone() {
    global $conn, $data;
    // 处理删除逻辑
    $wid = isset($data['wid']) ? $data['wid'] : 0;
    
    $sql = "DELETE FROM phone WHERE wid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $wid);
    $result = mysqli_stmt_execute($stmt);
    if($result) {
        response(200, "删除成功");
    } else {
        response(500, "删除失败");
    }
    mysqli_stmt_close($stmt);
}
