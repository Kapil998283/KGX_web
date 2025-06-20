<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

require_once '../../includes/user-auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

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
            
            try {
                $conn->beginTransaction();
                
                // Check if task already completed today
                $stmt = $conn->prepare("
                    SELECT id 
                    FROM user_streak_tasks 
                    WHERE user_id = ? 
                        AND task_id = ? 
                        AND DATE(completion_date) = CURDATE()
                ");
                $stmt->execute([$user_id, $taskId]);
                
                if (!$stmt->fetch()) {
                    // Get task points
                    $stmt = $conn->prepare("
                        SELECT reward_points FROM streak_tasks WHERE id = ?
                    ");
                    $stmt->execute([$taskId]);
                    $task = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Record task completion
                    $stmt = $conn->prepare("
                        INSERT INTO user_streak_tasks (
                            user_id,
                            task_id,
                            completion_date,
                            points_earned
                        ) VALUES (?, ?, NOW(), ?)
                    ");
                    $stmt->execute([$user_id, $taskId, $task['reward_points']]);

                    // Update user streak
                    $stmt = $conn->prepare("
                        UPDATE user_streaks 
                        SET streak_points = streak_points + ?,
                            total_tasks_completed = total_tasks_completed + 1
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$task['reward_points'], $user_id]);

                    $conn->commit();
                    
                    // Get updated streak info
                    $stmt = $conn->prepare("
                        SELECT * FROM user_streaks WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $streakInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Task completed successfully!',
                        'streak_info' => $streakInfo
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'error' => 'Task already completed today'
                    ]);
                }
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error completing task: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to complete task'
                ]);
            }
            break;
            
        case 'get_streak_info':
            try {
                $stmt = $conn->prepare("
                    SELECT * FROM user_streaks WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                $streakInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$streakInfo) {
                    $streakInfo = [
                        'current_streak' => 0,
                        'longest_streak' => 0,
                        'streak_points' => 0,
                        'total_tasks_completed' => 0
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'streak_info' => $streakInfo
                ]);
            } catch (Exception $e) {
                error_log("Error getting streak info: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to get streak info'
                ]);
            }
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