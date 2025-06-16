<?php
session_start();
require_once '../config/database.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Mark all notifications as read
$sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

// Redirect back to referring page or home
$redirect_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/KGX/index.php';
header("Location: " . $redirect_to);
exit();
?> 