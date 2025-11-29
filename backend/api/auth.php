<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../utils/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'register':
                handleRegister($auth, $input);
                break;
            case 'login':
                handleLogin($auth, $input);
                break;
            case 'admin-login':
                handleAdminLogin($auth, $input);
                break;
            case 'logout':
                handleLogout($auth);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'check':
                handleAuthCheck($auth);
                break;
            case 'user':
                handleGetUser($auth);
                break;
            case 'admin':
                handleGetAdmin($auth);
                break;
            default:
                sendErrorResponse('Invalid action');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

function handleRegister($auth, $input) {
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $first_name = sanitizeInput($input['first_name'] ?? '');
    $last_name = sanitizeInput($input['last_name'] ?? '');
    $phone = sanitizeInput($input['phone'] ?? '');
    $address = sanitizeInput($input['address'] ?? '');
    
    $result = $auth->register($username, $email, $password, $first_name, $last_name, $phone, $address);
    
    if ($result['success']) {
        sendSuccessResponse($result, $result['message']);
    } else {
        sendErrorResponse($result['message']);
    }
}

function handleLogin($auth, $input) {
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        sendSuccessResponse($result, $result['message']);
    } else {
        sendErrorResponse($result['message']);
    }
}

function handleAdminLogin($auth, $input) {
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    $result = $auth->adminLogin($username, $password);
    
    if ($result['success']) {
        sendSuccessResponse($result, $result['message']);
    } else {
        sendErrorResponse($result['message']);
    }
}

function handleLogout($auth) {
    $result = $auth->logout();
    sendSuccessResponse($result, $result['message']);
}

function handleAuthCheck($auth) {
    $isLoggedIn = $auth->isLoggedIn();
    $isAdminLoggedIn = $auth->isAdminLoggedIn();
    
    sendSuccessResponse([
        'user_logged_in' => $isLoggedIn,
        'admin_logged_in' => $isAdminLoggedIn
    ]);
}

function handleGetUser($auth) {
    $user = $auth->getCurrentUser();
    
    if ($user) {
        sendSuccessResponse($user);
    } else {
        sendErrorResponse('User not logged in', 401);
    }
}

function handleGetAdmin($auth) {
    $admin = $auth->getCurrentAdmin();
    
    if ($admin) {
        sendSuccessResponse($admin);
    } else {
        sendErrorResponse('Admin not logged in', 401);
    }
}
?>

