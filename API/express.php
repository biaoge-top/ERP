<?php
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("Asia/Shanghai");

require_once 'config.php';
require_once 'verify_token.php';

// 验证 token
verifyToken();

$action = isset($data['action']) ? $data['action'] : '';

switch ($action) {
    case 'get':
        getExpressList();
        break;
    case 'getByWid':
        getExpressByWid();
        break;
    case 'add':
        addExpress();
        break;
    case 'update':
        updateExpress();
        break;
    case 'delete':
        deleteExpress();
        break;
    case 'getStats':
        getExpressStats();
        break;
    default:
        response(400, "无效的操作");
}

function getExpressList() {
    global $conn, $data;
    
    $tracking_id = isset($data['tracking_id']) ? $data['tracking_id'] : '';
    $express_type = isset($data['express_type']) ? $data['express_type'] : '';
    $courier_company = isset($data['courier_company']) ? $data['courier_company'] : '';
    $startDate = isset($data['startDate']) ? $data['startDate'] : '';
    $endDate = isset($data['endDate']) ? $data['endDate'] : '';
    
    $sql = "SELECT e.*, p.machine_code, p.brand, p.series, p.model, p.postage as phone_postage
            FROM express e 
            LEFT JOIN phone p ON e.wid = p.wid 
            WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($tracking_id)) {
        $sql .= " AND e.tracking_id LIKE ?";
        $params[] = "%$tracking_id%";
        $types .= "s";
    }
    
    if (!empty($express_type)) {
        $sql .= " AND e.express_type = ?";
        $params[] = $express_type;
        $types .= "s";
    }
    
    if (!empty($courier_company)) {
        $sql .= " AND e.courier_company = ?";
        $params[] = $courier_company;
        $types .= "s";
    }
    
    if (!empty($startDate)) {
        $sql .= " AND e.delivery_time >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    
    if (!empty($endDate)) {
        $sql .= " AND e.delivery_time <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    $sql .= " ORDER BY e.express_id DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    response(200, "获取成功", $list);
}

// 根据 wid 获取快递记录
function getExpressByWid() {
    global $conn, $data;
    
    $wid = isset($data['wid']) ? intval($data['wid']) : 0;
    
    if ($wid <= 0) {
        response(400, "参数错误");
        return;
    }
    
    // 默认只获取销售类型的快递记录
    $express_type = isset($data['express_type']) ? $data['express_type'] : '销售';
    
    $sql = "SELECT * FROM express WHERE wid = ? AND express_type = ? ORDER BY express_id DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $wid, $express_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $express = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($express) {
        response(200, "获取成功", $express);
    } else {
        response(200, "暂无快递记录", null);
    }
}

function addExpress() {
    global $conn, $data;
    
    $wid = isset($data['wid']) ? intval($data['wid']) : 0;
    $express_type = isset($data['express_type']) ? $data['express_type'] : '';
    $courier_company = isset($data['courier_company']) ? $data['courier_company'] : '';
    $tracking_id = isset($data['tracking_id']) ? $data['tracking_id'] : '';
    $postage = isset($data['postage']) ? floatval($data['postage']) : 0;
    $delivery_time = isset($data['delivery_time']) ? $data['delivery_time'] : '';
    $courier_address = isset($data['courier_address']) ? $data['courier_address'] : '';
    $remark = isset($data['remark']) ? $data['remark'] : '';
    
    if ($wid <= 0) {
        response(400, "请选择关联手机");
        return;
    }
    
    if (empty($express_type)) {
        response(400, "请选择快递类型");
        return;
    }
    
    $sql = "INSERT INTO express (wid, courier_company, tracking_id, express_type, postage, delivery_time, courier_address, remark, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, "SQL准备失败: " . mysqli_error($conn));
        return;
    }
    mysqli_stmt_bind_param($stmt, "isssdsss", $wid, $courier_company, $tracking_id, $express_type, $postage, $delivery_time, $courier_address, $remark);
    $result = mysqli_stmt_execute($stmt);
    
    if ($result) {
        mysqli_stmt_close($stmt);
        
        // 判断是否是完整提交（收件地址 + 快递单号 + 发货时间）
        $is_complete = !empty($courier_address) && !empty($tracking_id) && !empty($delivery_time);
        
        // 根据快递类型更新 phone 表状态和邮费
        $phone_state_map = [
            '采购' => 2,      // 待质检
            '销售' => 5,      // 已销售
            '售后 (回)' => 6,  // 在售后
            '售后 (发)' => 7   // 已售后
        ];
        
        $phone_state = isset($phone_state_map[$express_type]) ? $phone_state_map[$express_type] : null;
        
        // 只有完整提交时才更新手机状态
        if ($is_complete) {
            // 如果是销售，更新出售时间
            if ($express_type === '销售' && !empty($delivery_time)) {
                $sql = "UPDATE phone SET postage = ?, state = ?, sell_time = ? WHERE wid = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "dssi", $postage, $phone_state, $delivery_time, $wid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } elseif ($phone_state) {
                // 有其他状态，更新邮费和状态
                $sql = "UPDATE phone SET postage = ?, state = ? WHERE wid = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "dii", $postage, $phone_state, $wid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            } else {
                // 只更新邮费
                $sql = "UPDATE phone SET postage = ? WHERE wid = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "di", $postage, $wid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        } else {
            // 不完整提交，只更新邮费
            $sql = "UPDATE phone SET postage = ? WHERE wid = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "di", $postage, $wid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        response(200, "添加成功", ["is_complete" => $is_complete]);
    } else {
        mysqli_stmt_close($stmt);
        response(500, "添加失败");
    }
}

function updateExpress() {
    global $conn, $data;
    
    $express_id = isset($data['express_id']) ? intval($data['express_id']) : 0;
    $wid = isset($data['wid']) ? intval($data['wid']) : 0;
    $express_type = isset($data['express_type']) ? $data['express_type'] : '';
    $courier_company = isset($data['courier_company']) ? $data['courier_company'] : '';
    $tracking_id = isset($data['tracking_id']) ? $data['tracking_id'] : '';
    $postage = isset($data['postage']) ? floatval($data['postage']) : 0;
    $delivery_time = isset($data['delivery_time']) ? $data['delivery_time'] : '';
    $courier_address = isset($data['courier_address']) ? $data['courier_address'] : '';
    $remark = isset($data['remark']) ? $data['remark'] : '';
    
    if ($express_id <= 0) {
        response(400, "快递ID无效");
        return;
    }
    
    $sql = "UPDATE express SET wid = ?, courier_company = ?, tracking_id = ?, express_type = ?, delivery_time = ?, courier_address = ?, remark = ?, postage = ? WHERE express_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        response(500, "SQL准备失败: " . mysqli_error($conn));
        return;
    }
    mysqli_stmt_bind_param($stmt, "issssssdi", $wid, $courier_company, $tracking_id, $express_type, $delivery_time, $courier_address, $remark, $postage, $express_id);
    $result = mysqli_stmt_execute($stmt);
    
    if ($result) {
        mysqli_stmt_close($stmt);
        
        // 根据快递类型更新 phone 表状态和邮费
        $phone_state_map = [
            '采购' => 2,      // 待质检
            '销售' => 5,      // 已销售
            '售后 (回)' => 6,  // 在售后
            '售后 (发)' => 7   // 已售后
        ];
        
        $phone_state = isset($phone_state_map[$express_type]) ? $phone_state_map[$express_type] : null;
        
        // 如果是销售，更新出售时间
        if ($express_type === '销售' && !empty($delivery_time)) {
            $sql = "UPDATE phone SET postage = ?, state = ?, sell_time = ? WHERE wid = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "dssi", $postage, $phone_state, $delivery_time, $wid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } elseif ($phone_state) {
            // 有其他状态，更新邮费和状态
            $sql = "UPDATE phone SET postage = ?, state = ? WHERE wid = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "dii", $postage, $phone_state, $wid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            // 只更新邮费
            $sql = "UPDATE phone SET postage = ? WHERE wid = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "di", $postage, $wid);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        response(200, "更新成功");
    } else {
        mysqli_stmt_close($stmt);
        response(500, "更新失败");
    }
}

function deleteExpress() {
    global $conn, $data;
    
    $express_id = isset($data['express_id']) ? intval($data['express_id']) : 0;
    
    if ($express_id <= 0) {
        response(400, "快递ID无效");
        return;
    }
    
    $sql = "DELETE FROM express WHERE express_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $express_id);
    $result = mysqli_stmt_execute($stmt);
    
    if ($result) {
        mysqli_stmt_close($stmt);
        response(200, "删除成功");
    } else {
        mysqli_stmt_close($stmt);
        response(500, "删除失败");
    }
}

function getExpressStats() {
    global $conn, $data;
    
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    $sql = "SELECT 
                COUNT(*) as total_count,
                COALESCE(SUM(postage), 0) as total_amount,
                SUM(CASE WHEN MONTH(delivery_time) = ? AND YEAR(delivery_time) = ? THEN 1 ELSE 0 END) as month_count,
                SUM(CASE WHEN MONTH(delivery_time) = ? AND YEAR(delivery_time) = ? THEN postage ELSE 0 END) as month_amount,
                SUM(CASE WHEN express_type = '采购' THEN 1 ELSE 0 END) as purchase_count,
                SUM(CASE WHEN express_type = '销售' THEN 1 ELSE 0 END) as sales_count,
                SUM(CASE WHEN express_type = '售后(回)' THEN 1 ELSE 0 END) as after_sales_back_count,
                SUM(CASE WHEN express_type = '售后(发)' THEN 1 ELSE 0 END) as after_sales_send_count
            FROM express";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $currentMonth, $currentYear, $currentMonth, $currentYear);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    response(200, "获取成功", $row);
}
