<?php
// 总览接口
//引入数据库连接
require "config.php";
//引入token验证
require "verify_token.php";

//验证token
$user = getCurrentUser();

//接收POST请求
$data = json_decode(file_get_contents("php://input"), true);
$action = isset($data["action"]) ? $data["action"] : '';

switch($action) {
    case "get":
        // 处理查询逻辑
        getOverview();
        break;
    case "get_trend":
        // 获取指定日期范围的趋势数据
        getTrendData();
        break;
    default:
        response(400, "参数错误");
        exit;
}

function getOverview() {
    global $conn;
    
    // 总销售
    $sql = "SELECT SUM(selling_price) as total_sales FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0'";
    $result = mysqli_query($conn, $sql);
    $total_sales = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_sales = $row['total_sales'] ? $row['total_sales'] : 0;
    }
    
    // 总采购
    $sql = "SELECT SUM(purchase_price) as total_purchase FROM phone";
    $result = mysqli_query($conn, $sql);
    $total_purchase = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_purchase = $row['total_purchase'] ? $row['total_purchase'] : 0;
    }
    
    // 总利润
    $sql = "SELECT SUM(profit) as total_profit FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0'";
    $result = mysqli_query($conn, $sql);
    $total_profit = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_profit = $row['total_profit'] ? $row['total_profit'] : 0;
    }
    
    // 总手机数量
    $sql = "SELECT COUNT(*) as total_phones FROM phone";
    $result = mysqli_query($conn, $sql);
    $total_phones = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $total_phones = $row['total_phones'];
    }
    
    // 本月数据
    $current_month = date('Y-m');
    $sql = "SELECT SUM(selling_price) as month_sales, SUM(profit) as month_profit, COUNT(*) as month_sales_count FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' AND DATE_FORMAT(sell_time, '%Y-%m') = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $current_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $month_sales = 0;
    $month_profit = 0;
    $month_sales_count = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $month_sales = $row['month_sales'] ? $row['month_sales'] : 0;
        $month_profit = $row['month_profit'] ? $row['month_profit'] : 0;
        $month_sales_count = $row['month_sales_count'] ? $row['month_sales_count'] : 0;
    }
    
    // 本月采购（按 acquisition_time）
    $sql = "SELECT SUM(purchase_price) as month_purchase FROM phone WHERE DATE_FORMAT(acquisition_time, '%Y-%m') = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $current_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $month_purchase = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $month_purchase = $row['month_purchase'] ? $row['month_purchase'] : 0;
    }
    
    // 上月数据 - 使用可靠方法计算上月
    $last_month = date('Y-m', strtotime(date('Y-m-01') . ' -1 month'));
    $sql = "SELECT SUM(selling_price) as last_month_sales, SUM(profit) as last_month_profit, COUNT(*) as last_month_sales_count FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' AND DATE_FORMAT(sell_time, '%Y-%m') = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $last_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $last_month_sales = 0;
    $last_month_profit = 0;
    $last_month_sales_count = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $last_month_sales = $row['last_month_sales'] ? $row['last_month_sales'] : 0;
        $last_month_profit = $row['last_month_profit'] ? $row['last_month_profit'] : 0;
        $last_month_sales_count = $row['last_month_sales_count'] ? $row['last_month_sales_count'] : 0;
    }
    
    // 上月采购（按 acquisition_time）
    $sql = "SELECT SUM(purchase_price) as last_month_purchase FROM phone WHERE DATE_FORMAT(acquisition_time, '%Y-%m') = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $last_month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $last_month_purchase = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $last_month_purchase = $row['last_month_purchase'] ? $row['last_month_purchase'] : 0;
    }
    
    // 计算环比变化
    $sales_change = $month_sales - $last_month_sales;
    $purchase_change = $month_purchase - $last_month_purchase;
    $profit_change = $month_profit - $last_month_profit;
    $sales_count_change = $month_sales_count - $last_month_sales_count;
    
    // 近 12 个月趋势数据
    $trend_months = [];
    $trend_sales = [];
    $trend_purchase = [];
    $trend_profit = [];
    
    for ($i = 11; $i >= 0; $i--) {
        // 使用可靠方法计算每个月，避免月末溢出问题
        $month = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
        $month_label = date('m 月', strtotime(date('Y-m-01') . " -$i month"));
        $trend_months[] = $month_label;
        
        // 该月销售和利润
        $sql = "SELECT SUM(selling_price) as sales, SUM(profit) as profit FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' AND DATE_FORMAT(sell_time, '%Y-%m') = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $month);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $sales = 0;
        $profit = 0;
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $sales = $row['sales'] ? floatval($row['sales']) : 0;
            $profit = $row['profit'] ? floatval($row['profit']) : 0;
        }
        $trend_sales[] = $sales;
        $trend_profit[] = $profit;
        
        // 该月采购（按acquisition_time）
        $sql = "SELECT SUM(purchase_price) as purchase FROM phone WHERE DATE_FORMAT(acquisition_time, '%Y-%m') = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $month);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $purchase = 0;
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $purchase = $row['purchase'] ? floatval($row['purchase']) : 0;
        }
        $trend_purchase[] = $purchase;
    }
    
    // 库存数据：待销售手机（未出售）
    $sql = "SELECT COUNT(*) as pending_sales, SUM(purchase_price + repair + matching) as pending_value, SUM(profit) as pending_profit FROM phone WHERE sell_time IS NULL OR sell_time = '' OR sell_time = '0'";
    $result = mysqli_query($conn, $sql);
    $pending_sales = 0;
    $pending_value = 0;
    $pending_profit = 0;
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $pending_sales = $row['pending_sales'];
        $pending_value = $row['pending_value'] ? $row['pending_value'] : 0;
        $pending_profit = $row['pending_profit'] ? $row['pending_profit'] : 0;
    }
    
    // 利润最高的手机前5
    $sql = "SELECT * FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' ORDER BY profit DESC LIMIT 5";
    $result = mysqli_query($conn, $sql);
    $top_profit_phones = [];
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $top_profit_phones[] = $row;
        }
    }
    
    // 销售最多的手机前5（按型号分组统计）
    $sql = "SELECT model, brand, series, COUNT(*) as sales_count, AVG(profit) as avg_profit FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' GROUP BY model, brand, series ORDER BY sales_count DESC LIMIT 5";
    $result = mysqli_query($conn, $sql);
    $top_sales_phones = [];
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $top_sales_phones[] = $row;
        }
    }
    
    // 库存最多的手机前5（按型号分组统计）
    $sql = "SELECT model, brand, series, COUNT(*) as stock_count FROM phone WHERE sell_time IS NULL OR sell_time = '' OR sell_time = '0' GROUP BY model, brand, series ORDER BY stock_count DESC LIMIT 5";
    $result = mysqli_query($conn, $sql);
    $top_stock_phones = [];
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $top_stock_phones[] = $row;
        }
    }
    
    // 亏损最多的手机前 5
    $sql = "SELECT * FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' AND profit < 0 ORDER BY profit ASC LIMIT 5";
    $result = mysqli_query($conn, $sql);
    $top_loss_phones = [];
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $top_loss_phones[] = $row;
        }
    }
    
    // 利润最高的手机前 5
    $sql = "SELECT * FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' AND profit > 0 ORDER BY profit DESC LIMIT 5";
    $result = mysqli_query($conn, $sql);
    $top_profit_phones = [];
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $top_profit_phones[] = $row;
        }
    }
    
    $overview_data = [
        'total_sales' => $total_sales,
        'total_purchase' => $total_purchase,
        'total_profit' => $total_profit,
        'total_phones' => $total_phones,
        'month_sales' => $month_sales,
        'month_purchase' => $month_purchase,
        'month_profit' => $month_profit,
        'month_sales_count' => $month_sales_count,
        'last_month_sales' => $last_month_sales,
        'last_month_purchase' => $last_month_purchase,
        'last_month_profit' => $last_month_profit,
        'last_month_sales_count' => $last_month_sales_count,
        'sales_change' => $sales_change,
        'purchase_change' => $purchase_change,
        'profit_change' => $profit_change,
        'sales_count_change' => $sales_count_change,
        'trend_months' => $trend_months,
        'trend_sales' => $trend_sales,
        'trend_purchase' => $trend_purchase,
        'trend_profit' => $trend_profit,
        'pending_sales' => $pending_sales,
        'pending_value' => $pending_value,
        'top_profit_phones' => $top_profit_phones,
        'top_sales_phones' => $top_sales_phones,
        'top_stock_phones' => $top_stock_phones,
        'top_loss_phones' => $top_loss_phones,
        'pending_profit' => $pending_profit
    ];
    
    response(200, "查询成功", $overview_data);
}

function getTrendData() {
    global $conn, $data;
    
    $start_date = isset($data['start_date']) ? $data['start_date'] : '';
    $end_date = isset($data['end_date']) ? $data['end_date'] : '';
    
    if (empty($start_date) || empty($end_date)) {
        response(400, "请选择日期范围");
        return;
    }
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $months_diff = ($interval->y * 12) + $interval->m;
    
    if ($months_diff > 24) {
        response(400, "日期范围不能超过24个月");
        return;
    }
    
    $months = [];
    $sales = [];
    $purchase = [];
    $profit = [];
    
    $current = clone $start;
    while ($current <= $end) {
        $month_str = $current->format('Y-m');
        $month_label = $current->format('Y年m月');
        $months[] = $month_label;
        
        // 该月销售和利润
        $sql = "SELECT SUM(selling_price) as sales, SUM(profit) as profit FROM phone WHERE sell_time IS NOT NULL AND sell_time != '' AND sell_time != '0' AND DATE_FORMAT(sell_time, '%Y-%m') = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $month_str);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $month_sales = 0;
        $month_profit = 0;
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $month_sales = $row['sales'] ? floatval($row['sales']) : 0;
            $month_profit = $row['profit'] ? floatval($row['profit']) : 0;
        }
        $sales[] = $month_sales;
        $profit[] = $month_profit;
        
        // 该月采购
        $sql = "SELECT SUM(purchase_price) as purchase FROM phone WHERE DATE_FORMAT(acquisition_time, '%Y-%m') = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $month_str);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $month_purchase = 0;
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $month_purchase = $row['purchase'] ? floatval($row['purchase']) : 0;
        }
        $purchase[] = $month_purchase;
        
        $current->modify('+1 month');
    }
    
    $trend_data = [
        'months' => $months,
        'sales' => $sales,
        'purchase' => $purchase,
        'profit' => $profit
    ];
    
    response(200, "查询成功", $trend_data);
}
