<?php
// 系统配置接口
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
            getSystemConfig();
            break;
        case 'update':
            updateSystemConfig($data,$cid);
            break;
        default:
            response(400, '参数错误');
            break;
    }
} else {
    response(405, '方法不允许');
}

// 获取系统配置
function getSystemConfig() {
    global $conn, $cid;
    
    $sql = "SELECT * FROM systems WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $cid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $config = [];
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $config = [
                'color_system' => $row['color_system'] ?? '',
                'brand_system' => $row['brand_system'] ?? '',
                'platform_system' => $row['platform_system'] ?? '',
                'storage_system' => $row['storage_system'] ?? '',
                'express_system' => $row['express_system'] ?? '',
                'shop_system' => isset($row['shop_system']) ? $row['shop_system'] : ''
            ];
        } else {
            // 如果没有配置，返回默认值
            $config = [
                'color_system' => '',
                'brand_system' => '',
                'platform_system' => '',
                'storage_system' => '',
                'express_system' => '',
                'shop_system' => ''
            ];
        }
        
        response(200, '获取系统配置成功', $config);
    } else {
        // 如果表不存在，返回默认配置
        $config = [
            'color_system' => '',
            'brand_system' => '',
            'platform_system' => '',
            'storage_system' => '',
            'express_system' => ''
        ];
        response(200, '获取系统配置成功', $config);
    }
}

// 更新系统配置
function updateSystemConfig($data, $cid) {
    global $conn;
    
    $color_system = $data['color_system'] ?? '';
    $brand_system = $data['brand_system'] ?? '';
    $platform_system = $data['platform_system'] ?? '';
    $storage_system = $data['storage_system'] ?? '';
    $express_system = $data['express_system'] ?? '';
    $shop_system = $data['shop_system'] ?? '';
    
    // 验证JSON格式
    if (!empty($color_system) && !isValidJson($color_system)) {
        response(400, '颜色配置格式错误');
        return;
    }
    
    if (!empty($brand_system) && !isValidJson($brand_system)) {
        response(400, '品牌+系列配置格式错误');
        return;
    }
    
    if (!empty($platform_system) && !isValidJson($platform_system)) {
        response(400, '平台配置格式错误');
        return;
    }
    
    if (!empty($storage_system) && !isValidJson($storage_system)) {
        response(400, '存储配置格式错误');
        return;
    }
    
    if (!empty($express_system) && !isValidJson($express_system)) {
        response(400, '快递公司配置格式错误');
        return;
    }
    
    if (!empty($shop_system) && !isValidJson($shop_system)) {
        response(400, '店铺配置格式错误');
        return;
    }
    
    // 检查是否存在配置记录
    $sql = "SELECT * FROM systems WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $cid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // 更新配置
            $sql = "UPDATE systems SET color_system = ?, brand_system = ?, platform_system = ?, storage_system = ?, express_system = ?, shop_system = ? WHERE cid = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssssi', $color_system, $brand_system, $platform_system, $storage_system, $express_system, $shop_system, $cid);
                if (mysqli_stmt_execute($stmt)) {
                    response(200, '更新系统配置成功');
                } else {
                    response(500, '更新系统配置失败');
                }
            } else {
                response(500, '更新系统配置失败');
            }
        } else {
            // 插入配置
            $sql = "INSERT INTO systems (cid, color_system, brand_system, platform_system, storage_system, express_system, shop_system) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'issssss', $cid, $color_system, $brand_system, $platform_system, $storage_system, $express_system, $shop_system);
                if (mysqli_stmt_execute($stmt)) {
                    response(200, '更新系统配置成功');
                } else {
                    response(500, '更新系统配置失败！');
                }
            } else {
                response(500, '更新系统配置失败!');
            }
        }
    } else {
        response(500, '更新配置失败!');
    }
}

// 验证JSON格式
function isValidJson($json) {
    json_decode($json);
    return json_last_error() === JSON_ERROR_NONE;
}
?>