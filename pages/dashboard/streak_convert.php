<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get JSON input data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get user's current points
    $points_sql = "SELECT streak_points FROM user_streaks WHERE user_id = ?";
    $points_stmt = $conn->prepare($points_sql);
    $points_stmt->execute([$user_id]);
    $points_data = $points_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$points_data) {
        throw new Exception("No streak points found");
    }
    
    $available_points = $points_data['streak_points'];
    
    // Get requested coins from JSON data
    $requested_coins = isset($data['coins']) ? intval($data['coins']) : 0;
    $points_needed = $requested_coins * 10;
    
    // Validate conversion
    if ($requested_coins <= 0) {
        throw new Exception("Invalid coin amount requested");
    }
    
    if ($points_needed > $available_points) {
        throw new Exception("Insufficient points for conversion");
    }
    
    // Deduct points from user_streaks
    $deduct_sql = "UPDATE user_streaks SET streak_points = streak_points - ? WHERE user_id = ?";
    $deduct_stmt = $conn->prepare($deduct_sql);
    $deduct_stmt->execute([$points_needed, $user_id]);
    
    // Add coins to user_coins (handle both insert and update cases)
    $coins_sql = "INSERT INTO user_coins (user_id, coins) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE coins = coins + ?";
    $coins_stmt = $conn->prepare($coins_sql);
    $coins_stmt->execute([$user_id, $requested_coins, $requested_coins]);
    
    // Log the conversion
    $log_sql = "INSERT INTO streak_conversion_log 
                (user_id, points_converted, coins_received, conversion_date) 
                VALUES (?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([$user_id, $points_needed, $requested_coins]);
    
    // Create notification for the user
    $notificationMessage = "Successfully converted {$points_needed} streak points to {$requested_coins} coins!";
    $notification_sql = "INSERT INTO notifications (
        user_id,
        type,
        message,
        related_id,
        related_type,
        created_at
    ) VALUES (
        ?,
        'points_conversion',
        ?,
        ?,
        'streak',
        NOW()
    )";
    $notification_stmt = $conn->prepare($notification_sql);
    $notification_stmt->execute([$user_id, $notificationMessage, $log_stmt->lastInsertId()]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully converted $points_needed points to $requested_coins coins!",
        'points_deducted' => $points_needed,
        'coins_added' => $requested_coins
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 