<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if ID is provided
if(!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No video ID provided']);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

try {
    // Start transaction
    $db->beginTransaction();

    // Delete related records first
    // Delete from video_watch_history
    $delete_history_sql = "DELETE FROM video_watch_history WHERE video_id = ?";
    $history_stmt = $db->prepare($delete_history_sql);
    $history_stmt->execute([$_POST['id']]);

    // Delete from stream_rewards
    $delete_rewards_sql = "DELETE FROM stream_rewards WHERE stream_id = ?";
    $rewards_stmt = $db->prepare($delete_rewards_sql);
    $rewards_stmt->execute([$_POST['id']]);

    // Finally, delete the video
    $delete_video_sql = "DELETE FROM live_streams WHERE id = ?";
    $video_stmt = $db->prepare($delete_video_sql);
    $result = $video_stmt->execute([$_POST['id']]);

    if($result) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
    } else {
        throw new Exception('Failed to delete video');
    }

} catch(Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 