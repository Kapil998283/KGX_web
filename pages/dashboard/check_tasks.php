<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$conn = $database->connect();

// Get last check timestamp from session or set current time
$last_check = $_SESSION['last_task_check'] ?? time();
$_SESSION['last_task_check'] = time();

// Check for any new task completions since last check
$check_sql = "SELECT COUNT(*) as new_completions 
              FROM user_streak_tasks 
              WHERE user_id = ? 
              AND UNIX_TIMESTAMP(completion_date) > ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->execute([$user_id, $last_check]);
$result = $check_stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'new_completion' => $result['new_completions'] > 0
]); 