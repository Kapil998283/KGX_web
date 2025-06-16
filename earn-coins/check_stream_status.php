<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if stream_id is provided
if (!isset($_GET['stream_id'])) {
    echo json_encode(['error' => 'Missing stream ID']);
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Check stream status
$sql = "SELECT status FROM live_streams WHERE id = ? AND video_type = 'tournament'";
$stmt = $db->prepare($sql);
$stmt->execute([$_GET['stream_id']]);
$stream = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'is_live' => ($stream && $stream['status'] === 'live')
]); 