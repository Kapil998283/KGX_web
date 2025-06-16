<?php
session_start();
require_once '../config/database.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

// Check if notification ID is provided
if (!isset($_POST['notification_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = $_POST['notification_id'];

// Initialize database connection
$database = new Database();
$conn = $database->connect();

try {
    // Soft delete the notification if it belongs to the user
    $sql = "UPDATE notifications SET deleted_at = CURRENT_TIMESTAMP 
            WHERE id = :notification_id AND user_id = :user_id AND deleted_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':notification_id' => $notification_id,
        ':user_id' => $user_id
    ]);

    // Redirect back to referring page or home
    $redirect_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
    header("Location: " . $redirect_to);
    exit();
} catch (PDOException $e) {
    error_log("Error deleting notification: " . $e->getMessage());
    header("Location: ../index.php");
    exit();
}
?> 