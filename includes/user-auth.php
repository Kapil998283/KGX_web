<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';  // Include auth.php for shared functions

// Function to get user data
function getUserData($user_id = null) {
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    if (!$user_id) {
        return null;
    }
    
    $conn = getDbConnection();
    $sql = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get user tickets
function getUserTickets($user_id) {
    $conn = getDbConnection();
    
    $sql = "SELECT tickets FROM user_tickets WHERE user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['tickets'] : 0;
}

// Function to check if user has enough tickets
function hasEnoughTickets($userId, $requiredTickets) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT tickets FROM user_tickets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['tickets'] >= $requiredTickets;
}

// Function to update user's tickets
function updateUserTickets($userId, $amount, $operation = 'subtract') {
    $conn = getDbConnection();
    $sql = "UPDATE user_tickets SET tickets = tickets " . ($operation === 'add' ? '+' : '-') . " ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$amount, $userId]);
}

// Function to get user coins
function getUserCoins($user_id) {
    $conn = getDbConnection();
    
    $sql = "SELECT coins FROM user_coins WHERE user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['coins'] : 0;
}

// Function to get unread notifications count
function getUnreadNotificationsCount($user_id) {
    $conn = getDbConnection();
    
    // Check if notifications table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() == 0) {
        return 0;
    }
    
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['count'] : 0;
}

// Function to get session user data
function getSessionUserData() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'avatar' => $_SESSION['avatar'] ?? ''
    ];
}
?> 