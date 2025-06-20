<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/streak_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$streakHandler = new StreakHandler($db, $_SESSION['user_id']);

// Handle task completion
if (isset($_POST['complete_task'])) {
    $taskId = (int)$_POST['task_id'];
    $success = $streakHandler->completeTask($taskId);
    
    if ($success) {
        header('Location: streak_tasks.php?success=1');
        exit;
    } else {
        header('Location: streak_tasks.php?error=1');
        exit;
    }
}

// Get streak info
$streakInfo = $streakHandler->getStreakInfo();

// Get all tasks and their completion status for today
$stmt = $db->prepare("
    SELECT 
        t.*,
        CASE WHEN ut.id IS NOT NULL THEN 1 ELSE 0 END as completed
    FROM streak_tasks t
    LEFT JOIN user_streak_tasks ut ON 
        t.id = ut.task_id 
        AND ut.user_id = ? 
        AND DATE(ut.completed_at) = CURDATE()
    ORDER BY t.points ASC
");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Streak Tasks</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .tasks-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .task-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .task-card:hover {
            transform: translateY(-2px);
        }

        .task-info {
            flex: 1;
        }

        .task-name {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .task-description {
            font-size: 14px;
            color: #666;
        }

        .task-points {
            background: var(--blue);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            margin-left: 15px;
        }

        .task-complete {
            background: #4CAF50;
        }

        .streak-summary {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .streak-count {
            font-size: 48px;
            font-weight: bold;
            color: var(--blue);
            margin: 10px 0;
        }

        .streak-label {
            font-size: 16px;
            color: #666;
            margin-bottom: 15px;
        }

        .complete-btn {
            background: var(--blue);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .complete-btn:hover {
            background: var(--dark-blue);
        }

        .complete-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #A5D6A7;
        }

        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #FFCDD2;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../../includes/header.php'; ?>
        
        <div class="tasks-container">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    Task completed successfully! Keep up the great work!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    Unable to complete task. Please try again.
                </div>
            <?php endif; ?>

            <div class="streak-summary">
                <div class="streak-count"><?php echo $streakInfo['current_streak']; ?></div>
                <div class="streak-label">Day Streak</div>
                <div>Tasks Completed Today: <?php echo $streakInfo['tasks_completed_today']; ?></div>
                <div>Total Points: <?php echo $streakInfo['total_points']; ?></div>
                
                <?php if ($streakInfo['next_milestone']): ?>
                    <div class="streak-progress">
                        <div class="progress-text">
                            Next Milestone: <?php echo $streakInfo['next_milestone']['required_streak']; ?> Days
                        </div>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php 
                                echo min(100, ($streakInfo['current_streak'] / $streakInfo['next_milestone']['required_streak']) * 100);
                            ?>%"></div>
                        </div>
                        <div class="milestone-reward">
                            Reward: <?php echo $streakInfo['next_milestone']['reward_points']; ?> Points
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <h2>Daily Tasks</h2>
            <?php foreach ($tasks as $task): ?>
                <div class="task-card">
                    <div class="task-info">
                        <div class="task-name"><?php echo htmlspecialchars($task['name']); ?></div>
                        <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <div class="task-points <?php echo $task['completed'] ? 'task-complete' : ''; ?>">
                            <?php echo $task['points']; ?> Points
                        </div>
                        <?php if (!$task['completed']): ?>
                            <form method="POST" style="margin-left: 10px;">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" name="complete_task" class="complete-btn">
                                    Complete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html> 