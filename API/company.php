<?php
// 数据库连接文件
require_once 'db.php';
//引入token验证
require_once 'verify_token.php';

//验证token
$user = getCurrentUser();

// 处理企业相关请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($data['action'] ?? '') {
        case 'create_company':
            handleCreateCompany($data);
            break;
        case 'get_companies':
            handleGetCompanies();
            break;
        case 'join_company':
            handleJoinCompany($data);
            break;
        case 'update_company':
            handleUpdateCompany($data);
            break;
        default:
            echo json_encode(['code' => 400, 'msg' => '无效的请求']);
            break;
    }
} else {
    echo json_encode(['code' => 405, 'msg' => '方法不允许']);
}

// 创建企业
function handleCreateCompany($data) {
    global $conn, $user;
    
    $company_name = $data['company_name'] ?? '';
    
    if (empty($company_name)) {
        echo json_encode(['code' => 400, 'msg' => '企业名称不能为空']);
        return;
    }
    
    // 检查企业名称是否已存在
    $sql = "SELECT * FROM companys WHERE company_name = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $company_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['code' => 400, 'msg' => '企业名称已存在']);
        return;
    }
    
    // 从 token 中获取用户信息
    $uid = $user['uid'];
    
    // 插入企业数据
    $sql = "INSERT INTO companys (company_name, uid, state) VALUES (?, ?, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $company_name, $uid);
    
    if (mysqli_stmt_execute($stmt)) {
        $cid = mysqli_insert_id($conn);
        
        // 初始化企业系统配置
        $sql = "INSERT INTO systems (cid) VALUES (?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $cid);
        mysqli_stmt_execute($stmt);
        
        echo json_encode(['code' => 200, 'msg' => '企业创建成功', 'data' => ['cid' => $cid]]);
    } else {
        echo json_encode(['code' => 500, 'msg' => '创建企业失败']);
    }
}

// 获取企业列表
function handleGetCompanies() {
    global $conn, $user;
    
    // 从token中获取用户信息
    $uid = $user['uid'];
    
    // 查询所有企业
    $sql = "SELECT * FROM companys WHERE state = 1";
    $result = mysqli_query($conn, $sql);
    
    $companies = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $companies[] = $row;
        }
    }
    
    echo json_encode(['code' => 200, 'msg' => '获取企业列表成功', 'data' => $companies]);
}

// 加入企业
function handleJoinCompany($data) {
    global $conn, $user;
    
    $cid = $data['cid'] ?? 0;
    
    if (empty($cid)) {
        echo json_encode(['code' => 400, 'msg' => '企业ID不能为空']);
        return;
    }
    
    // 从token中获取用户信息
    $uid = $user['uid'];
    
    // 检查企业是否存在
    $sql = "SELECT * FROM companys WHERE cid = ? AND state = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['code' => 400, 'msg' => '企业不存在']);
        return;
    }
    
    // 更新用户的企业ID
    $sql = "UPDATE users SET cid = ? WHERE uid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $cid, $uid);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['code' => 200, 'msg' => '加入企业成功']);
    } else {
        echo json_encode(['code' => 500, 'msg' => '加入企业失败']);
    }
}

// 更新企业
function handleUpdateCompany($data) {
    global $conn, $user;
    
    $cid = $data['cid'] ?? 0;
    $company_name = $data['company_name'] ?? '';
    $state = $data['state'] ?? 1;
    
    if (empty($cid)) {
        echo json_encode(['code' => 400, 'msg' => '企业 ID 不能为空']);
        return;
    }
    
    if (empty($company_name)) {
        echo json_encode(['code' => 400, 'msg' => '企业名称不能为空']);
        return;
    }
    
    // 检查企业名称是否已存在（排除当前企业）
    $sql = "SELECT * FROM companys WHERE company_name = ? AND cid != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $company_name, $cid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['code' => 400, 'msg' => '企业名称已存在']);
        return;
    }
    
    // 更新企业数据
    $sql = "UPDATE companys SET company_name = ?, state = ? WHERE cid = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $company_name, $state, $cid);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['code' => 200, 'msg' => '更新企业成功']);
    } else {
        echo json_encode(['code' => 500, 'msg' => '更新企业失败']);
    }
}
?>