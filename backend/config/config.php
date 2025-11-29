<?php
/**
 * General Configuration
 */

// JWT Secret Key (in production, use environment variable)
define('JWT_SECRET', 'your-secret-key-change-in-production');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Hotel settings
define('HOTEL_NAME', 'Grand Luxury Hotel');
define('HOTEL_EMAIL', 'info@grandluxuryhotel.com');
define('HOTEL_PHONE', '+1 (555) 123-4567');

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', '../frontend/images/uploads/');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Email settings (configure for production)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

/**
 * Utility functions
 */

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

function sendErrorResponse($message, $status_code = 400) {
    sendJsonResponse(['error' => $message], $status_code);
}

function sendSuccessResponse($data = [], $message = 'Success') {
    sendJsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}
?>

