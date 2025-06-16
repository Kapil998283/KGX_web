<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['stream_id']) || !isset($_POST['watch_duration'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

try {
    // Start transaction
    $db->beginTransaction();

    // Get stream details
    $stream_sql = "SELECT * FROM live_streams WHERE id = ?";
    $stream_stmt = $db->prepare($stream_sql);
    $stream_stmt->execute([$_POST['stream_id']]);
    $stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stream) {
        throw new Exception('Stream not found');
    }

    // Check if user has already claimed reward for this stream
    $check_sql = "SELECT id FROM stream_rewards WHERE user_id = ? AND stream_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([$_SESSION['user_id'], $_POST['stream_id']]);

    if ($check_stmt->rowCount() > 0) {
        throw new Exception('Reward already claimed');
    }

    // Verify watch duration
    $watch_duration = intval($_POST['watch_duration']);
    if ($watch_duration < $stream['minimum_watch_duration']) {
        throw new Exception('Minimum watch duration not met');
    }

    // Calculate coin reward (can be decimal)
    $coin_reward = $stream['coin_reward'];

    // Add reward record
    $reward_sql = "INSERT INTO stream_rewards (user_id, stream_id, coins_earned, watch_duration) VALUES (?, ?, ?, ?)";
    $reward_stmt = $db->prepare($reward_sql);
    $reward_stmt->execute([
        $_SESSION['user_id'],
        $_POST['stream_id'],
        $coin_reward,
        $watch_duration
    ]);

    // Update user's coins
    $update_sql = "INSERT INTO user_coins (user_id, coins) VALUES (?, ?) 
                   ON DUPLICATE KEY UPDATE coins = coins + ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute([
        $_SESSION['user_id'],
        $coin_reward,
        $coin_reward
    ]);

    // Commit transaction
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reward claimed successfully',
        'coins_earned' => $coin_reward
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 