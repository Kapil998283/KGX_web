<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check required parameters
if (!isset($_POST['stream_id']) || !isset($_POST['coins']) || !isset($_POST['watch_duration'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

try {
    // Start transaction
    $db->beginTransaction();

    // Check if stream is still live
    $stream_sql = "SELECT status, coin_reward FROM live_streams WHERE id = ? AND video_type = 'tournament'";
    $stream_stmt = $db->prepare($stream_sql);
    $stream_stmt->execute([$_POST['stream_id']]);
    $stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stream || $stream['status'] !== 'live') {
        throw new Exception('Stream is no longer live');
    }

    // Update or insert stream reward
    $reward_sql = "INSERT INTO stream_rewards (user_id, stream_id, coins_earned, watch_duration, last_update) 
                   VALUES (?, ?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE 
                   coins_earned = ?, 
                   watch_duration = ?,
                   last_update = NOW()";

    $reward_stmt = $db->prepare($reward_sql);
    $reward_stmt->execute([
        $_SESSION['user_id'],
        $_POST['stream_id'],
        $_POST['coins'],
        $_POST['watch_duration'],
        $_POST['coins'],
        $_POST['watch_duration']
    ]);

    // Update user's total coins
    $coins_sql = "INSERT INTO user_coins (user_id, coins) 
                  VALUES (?, ?)
                  ON DUPLICATE KEY UPDATE 
                  coins = coins + ?";

    $coins_stmt = $db->prepare($coins_sql);
    $coins_stmt->execute([
        $_SESSION['user_id'],
        $_POST['coins'],
        $_POST['coins']
    ]);

    // Commit transaction
    $db->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
} 