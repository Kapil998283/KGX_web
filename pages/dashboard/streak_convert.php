<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Get current streak points
    $points_sql = "SELECT streak_points FROM user_streaks WHERE user_id = ?";
    $points_stmt = $conn->prepare($points_sql);
    $points_stmt->execute([$user_id]);
    $points_data = $points_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$points_data) {
        throw new Exception('No streak data found');
    }
    
    $available_points = $points_data['streak_points'];
    
    // Get requested coins from POST data
    $requested_coins = isset($_POST['coins']) ? intval($_POST['coins']) : 0;
    $points_needed = $requested_coins * 10;
    
    // Validate conversion request
    if ($requested_coins <= 0) {
        throw new Exception('Please specify how many coins you want to convert');
    }
    
    if ($points_needed > $available_points) {
        throw new Exception('Not enough points to convert');
    }
    
    // Update user_streaks - deduct points
    $update_streak_sql = "UPDATE user_streaks 
                         SET streak_points = streak_points - ? 
                         WHERE user_id = ?";
    $update_streak_stmt = $conn->prepare($update_streak_sql);
    $update_streak_stmt->execute([$points_needed, $user_id]);
    
    // Add coins to user_coins
    $add_coins_sql = "INSERT INTO user_coins (user_id, coins) 
                      VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE coins = coins + ?";
    $add_coins_stmt = $conn->prepare($add_coins_sql);
    $add_coins_stmt->execute([$user_id, $requested_coins, $requested_coins]);
    
    // Log the conversion
    $log_sql = "INSERT INTO streak_conversion_log 
                (user_id, points_converted, coins_received, conversion_date) 
                VALUES (?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([$user_id, $points_needed, $requested_coins]);
    
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 