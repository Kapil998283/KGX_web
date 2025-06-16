<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function getDbConnection() {
    $database = new Database();
    return $database->connect();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Please log in to access this page.";
        header("Location: /KGX/pages/login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: /KGX/index.php");
        exit();
    }
}

function getUserRole() {
    if (!isLoggedIn()) {
        return 'guest';
    }
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] ? 'admin' : 'user';
}

function checkPermission($requiredRole) {
    $userRole = getUserRole();
    
    switch($requiredRole) {
        case 'admin':
            return $userRole === 'admin';
        case 'user':
            return $userRole === 'user' || $userRole === 'admin';
        case 'guest':
            return true;
        default:
            return false;
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Logout user
function logout() {
    session_unset();
    session_destroy();
    header('Location: /k/pages/auth/login.php');
    exit();
} 