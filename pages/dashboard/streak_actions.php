<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/streak_handler.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$streakHandler = new StreakHandler($db, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'complete_task':
            if (!isset($_POST['task_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID is required']);
                exit;
            }
            
            $taskId = (int)$_POST['task_id'];
            $success = $streakHandler->completeTask($taskId);
            
            if ($success) {
                $streakInfo = $streakHandler->getStreakInfo();
                echo json_encode([
                    'success' => true,
                    'message' => 'Task completed successfully!',
                    'streak_info' => $streakInfo
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to complete task'
                ]);
            }
            break;
            
        case 'get_streak_info':
            $streakInfo = $streakHandler->getStreakInfo();
            echo json_encode([
                'success' => true,
                'streak_info' => $streakInfo
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?> 