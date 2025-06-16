<?php
// Admin Panel Configuration
define('ADMIN_PATH', dirname(__FILE__) . '/../');
define('ADMIN_URL', '/KGX/admin-panel/');

// Admin Panel Settings
$admin_settings = [
    'site_name' => 'Admin Panel',
    'admin_email' => 'admin@example.com',
    'items_per_page' => 10,
    'debug_mode' => false
];

// Security Settings
$security_settings = [
    'session_timeout' => 3600, // 1 hour
    'max_login_attempts' => 5,
    'password_min_length' => 8
];

// Check if user is logged in and is an admin
function check_admin_auth() {
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: " . ADMIN_URL . "login.php");
        exit();
    }
}

// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Log admin actions
function log_admin_action($action, $details = '') {
    $log_file = ADMIN_PATH . 'logs/admin_actions.log';
    $timestamp = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'] ?? 'unknown';
    $log_entry = "[$timestamp] User ID: $user_id - Action: $action - Details: $details\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?> 