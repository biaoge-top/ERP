<?php
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Shanghai");

require_once 'config.php';
require_once 'verify_token.php';

// 验证 token
verifyToken();

$action = isset($data['action']) ? $data['action'] : '';

switch ($action) {
    case 'getStats':
        getFinanceStats();
        break;
    case 'getList':
        getFinanceList();
        break;
    case 'getMonthlyStats':
        getMonthlyStats();
        break;
    default:
        response(400, "无效的操作");
}

function getFinanceStats() {
    global $conn, $data;
    
    $startDate = isset($data['startDate']) ? $data['startDate'] : '';
    $endDate = isset($data['endDate']) ? $data['endDate'] : '';
    $state = isset($data['state']) ? $data['state'] : '';
    $brand = isset($data['brand']) ? $data['brand'] : '';
    $acquisition_platform = isset($data['acquisition_platform']) ? $data['acquisition_platform'] : '';
    
    $sql = "SELECT 
                COALESCE(SUM(purchase_price), 0) as total_purchase,
                COALESCE(SUM(repair), 0) as total_repair,
                COALESCE(SUM(matching), 0) as total_matching,
                COALESCE(SUM(cost), 0) as total_cost,
                COALESCE(SUM(postage), 0) as total_postage,
                COALESCE(SUM(selling_price), 0) as total_selling,
                COALESCE(SUM(profit), 0) as total_profit,
                COUNT(*) as total_count
            FROM phone WHERE 1=1";
    
    $params = [];
    $types = "";
    
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
    
    if (!empty($state)) {
        $sql .= " AND state = ?";
        $params[] = $state;
        $types .= "i";
    }
    
    if (!empty($brand)) {
        $sql .= " AND brand = ?";
        $params[] = $brand;
        $types .= "s";
    }
    
    if (!empty($acquisition_platform)) {
        $sql .= " AND acquisition_platform = ?";
        $params[] = $acquisition_platform;
        $types .= "s";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    response(200, "获取成功", $row);
}

function getFinanceList() {
    global $conn, $data;
    
    $startDate = isset($data['startDate']) ? $data['startDate'] : '';
    $endDate = isset($data['endDate']) ? $data['endDate'] : '';
    $state = isset($data['state']) ? $data['state'] : '';
    $brand = isset($data['brand']) ? $data['brand'] : '';
    $acquisition_platform = isset($data['acquisition_platform']) ? $data['acquisition_platform'] : '';
    $page = isset($data['page']) ? intval($data['page']) : 1;
    $pageSize = isset($data['pageSize']) ? intval($data['pageSize']) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $sql = "SELECT * FROM phone WHERE 1=1";
    $params = [];
    $types = "";
    
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
    
    if (!empty($state)) {
        $sql .= " AND state = ?";
        $params[] = $state;
        $types .= "i";
    }
    
    if (!empty($brand)) {
        $sql .= " AND brand = ?";
        $params[] = $brand;
        $types .= "s";
    }
    
    if (!empty($acquisition_platform)) {
        $sql .= " AND acquisition_platform = ?";
        $params[] = $acquisition_platform;
        $types .= "s";
    }
    
    $countSql = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $stmt = mysqli_prepare($conn, $countSql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $countResult = mysqli_stmt_get_result($stmt);
    $totalCount = mysqli_fetch_assoc($countResult)['total'];
    mysqli_stmt_close($stmt);
    
    $sql .= " ORDER BY wid DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $pageSize;
    $types .= "ii";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    response(200, "获取成功", [
        'list' => $list,
        'total' => intval($totalCount),
        'page' => $page,
        'pageSize' => $pageSize
    ]);
}

function getMonthlyStats() {
    global $conn, $data;
    
    $year = isset($data['year']) ? intval($data['year']) : date('Y');
    
    $sql = "SELECT 
                MONTH(acquisition_time) as month,
                COALESCE(SUM(purchase_price), 0) as total_purchase,
                COALESCE(SUM(repair), 0) as total_repair,
                COALESCE(SUM(matching), 0) as total_matching,
                COALESCE(SUM(cost), 0) as total_cost,
                COALESCE(SUM(postage), 0) as total_postage,
                COALESCE(SUM(selling_price), 0) as total_selling,
                COALESCE(SUM(profit), 0) as total_profit,
                COUNT(*) as total_count
            FROM phone 
            WHERE YEAR(acquisition_time) = ?
            GROUP BY MONTH(acquisition_time)
            ORDER BY month";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $stats = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stats[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    response(200, "获取成功", $stats);
}
