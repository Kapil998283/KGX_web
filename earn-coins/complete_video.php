<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if required parameters are provided
if(!isset($_POST['video_id']) || !isset($_POST['watch_duration'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

try {
    // Start transaction
    $db->beginTransaction();

    // Check if video was already watched
    $check_sql = "SELECT id FROM video_watch_history WHERE user_id = ? AND video_id = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([$_SESSION['user_id'], $_POST['video_id']]);

    if($check_stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Video already watched']);
        exit();
    }

    // Record video watch history
    $history_sql = "INSERT INTO video_watch_history (user_id, video_id, watch_duration, watched_at) VALUES (?, ?, ?, NOW())";
    $history_stmt = $db->prepare($history_sql);
    $history_stmt->execute([$_SESSION['user_id'], $_POST['video_id'], $_POST['watch_duration']]);

    // Add coins to user's balance (50 coins per video)
    $coins_sql = "UPDATE users SET coins = coins + 50 WHERE id = ?";
    $coins_stmt = $db->prepare($coins_sql);
    $coins_stmt->execute([$_SESSION['user_id']]);

    // Commit transaction
    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Video completed and coins awarded']);
} catch(Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error processing video completion']);
}
?> 