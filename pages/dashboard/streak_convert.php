<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['coins']) || !is_numeric($data['coins']) || $data['coins'] < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid coin amount']);
    exit;
}

$user_id = $_SESSION['user_id'];
$coins_to_convert = intval($data['coins']);
$points_needed = $coins_to_convert * 10;

$database = new Database();
$conn = $database->connect();

try {
    $conn->beginTransaction();

    // Check if user has enough points
    $check_points_sql = "SELECT streak_points FROM user_streaks WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_points_sql);
    $check_stmt->execute([$user_id]);
    $user_points = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_points || $user_points['streak_points'] < $points_needed) {
        throw new Exception('Not enough points available');
    }

    // Update user streak points
    $update_streak_sql = "UPDATE user_streaks 
                         SET streak_points = streak_points - ? 
                         WHERE user_id = ?";
    $update_streak_stmt = $conn->prepare($update_streak_sql);
    $update_streak_stmt->execute([$points_needed, $user_id]);

    // Add coins to user_coins table
    $update_coins_sql = "INSERT INTO user_coins (user_id, coins) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE coins = coins + ?";
    $update_coins_stmt = $conn->prepare($update_coins_sql);
    $update_coins_stmt->execute([$user_id, $coins_to_convert, $coins_to_convert]);

    // Record the conversion in streak_conversion_log
    $log_sql = "INSERT INTO streak_conversion_log 
                (user_id, points_converted, coins_received) 
                VALUES (?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->execute([$user_id, $points_needed, $coins_to_convert]);

    // Record transaction
    $transaction_sql = "INSERT INTO transactions 
                       (user_id, amount, type, description, currency_type) 
                       VALUES (?, ?, 'reward', ?, 'coins')";
    $transaction_stmt = $conn->prepare($transaction_sql);
    $transaction_stmt->execute([
        $user_id,
        $coins_to_convert,
        "Converted $points_needed streak points to $coins_to_convert coins"
    ]);

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => "Successfully converted $points_needed points to $coins_to_convert coins!"
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 