<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/header.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

require_once '../../includes/user-auth.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: /KGX/pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's streak information
$streak_sql = "SELECT current_streak, longest_streak, streak_points, total_tasks_completed 
               FROM user_streaks 
               WHERE user_id = ?";
$streak_stmt = $conn->prepare($streak_sql);
$streak_stmt->execute([$user_id]);
$streakInfo = $streak_stmt->fetch(PDO::FETCH_ASSOC);

// Initialize streak info if not found
if (!$streakInfo) {
    $streakInfo = [
        'current_streak' => 0,
        'longest_streak' => 0,
        'streak_points' => 0,
        'total_tasks_completed' => 0
    ];
}

// Get next milestone
$milestone_sql = "SELECT sm.* 
                 FROM streak_milestones sm
                 LEFT JOIN user_streak_milestones usm ON sm.id = usm.milestone_id AND usm.user_id = ?
                 WHERE usm.id IS NULL AND sm.is_active = 1
                 ORDER BY sm.points_required ASC
                 LIMIT 1";
$milestone_stmt = $conn->prepare($milestone_sql);
$milestone_stmt->execute([$user_id]);
$next_milestone = $milestone_stmt->fetch(PDO::FETCH_ASSOC);

// Get today's completed tasks
$today_tasks_sql = "SELECT COUNT(*) as completed_count
                    FROM user_streak_tasks 
                    WHERE user_id = ? AND DATE(completion_date) = CURDATE()";
$today_tasks_stmt = $conn->prepare($today_tasks_sql);
$today_tasks_stmt->execute([$user_id]);
$today_tasks = $today_tasks_stmt->fetch(PDO::FETCH_ASSOC);

// Get all available tasks and their completion status
$tasks_sql = "SELECT 
    st.*,
    CASE WHEN ust.id IS NOT NULL THEN 1 ELSE 0 END as completed
    FROM streak_tasks st
    LEFT JOIN user_streak_tasks ust ON 
        st.id = ust.task_id 
        AND ust.user_id = ? 
        AND DATE(ust.completion_date) = CURDATE()
    ORDER BY st.reward_points ASC";
$tasks_stmt = $conn->prepare($tasks_sql);
$tasks_stmt->execute([$user_id]);
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get streak history
$history_sql = "SELECT 
    DATE(completion_date) as date,
    COUNT(*) as tasks_completed,
    SUM(points_earned) as points_earned
    FROM user_streak_tasks
    WHERE user_id = ?
    GROUP BY DATE(completion_date)
    ORDER BY date DESC
    LIMIT 7";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->execute([$user_id]);
$streak_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get milestone achievements
$achievements_sql = "SELECT 
    sm.*,
    usm.achieved_at
    FROM user_streak_milestones usm
    JOIN streak_milestones sm ON usm.milestone_id = sm.id
    WHERE usm.user_id = ?
    ORDER BY usm.achieved_at DESC";
$achievements_stmt = $conn->prepare($achievements_sql);
$achievements_stmt->execute([$user_id]);
$achievements = $achievements_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streak Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/streak/streak.css">
    <link rel="stylesheet" href="../../assets/css/streak/alerts.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="streak.js"></script>
    <style>
        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: #19fb00;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: #16e100;
            transform: translateY(-2px);
        }

        .main-content {
            padding: 80px 20px 20px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-button">
        <ion-icon name="arrow-back-outline"></ion-icon>
        Back to Dashboard
    </a>

    <div class="main-content">
        <div class="streak-container">
            <div class="streak-header">
                <div class="streak-count"><?php echo $streakInfo['current_streak'] ?? 0; ?></div>
                <div class="streak-label">Day Streak</div>
            </div>

            <div class="streak-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $streakInfo['longest_streak'] ?? 0; ?></div>
                    <div class="stat-label">Longest Streak</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $today_tasks['completed_count'] ?? 0; ?></div>
                    <div class="stat-label">Tasks Completed Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $streakInfo['streak_points'] ?? 0; ?></div>
                    <div class="stat-label">Total Points</div>
                </div>
            </div>

            <?php if ($next_milestone): ?>
            <div class="milestone-progress">
                <h3>Next Milestone: <?php echo htmlspecialchars($next_milestone['name']); ?></h3>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php 
                        echo min(100, (($streakInfo['streak_points'] ?? 0) / $next_milestone['points_required']) * 100);
                    ?>%"></div>
                </div>
                <div class="milestone-reward">
                    Reward: <?php echo $next_milestone['reward_points']; ?> Points
                </div>
                <div class="milestone-description">
                    <?php echo htmlspecialchars($next_milestone['description']); ?>
                </div>
            </div>
            <?php endif; ?>

            <h2>Daily Tasks</h2>
            <div class="tasks-grid">
                <?php foreach ($tasks as $task): ?>
                <div class="task-card <?php echo $task['completed'] ? 'completed' : ''; ?>">
                    <div class="task-header">
                        <div class="task-name"><?php echo htmlspecialchars($task['name']); ?></div>
                        <div class="task-points"><?php echo $task['reward_points']; ?> Points</div>
                    </div>
                    <div class="task-description">
                        <?php echo htmlspecialchars($task['description']); ?>
                    </div>
                    <?php if (!$task['completed']): ?>
                    <form method="POST">
                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                        <button type="submit" name="complete_task" class="complete-task-btn">
                            Complete Task
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="complete-task-btn" disabled>Completed</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <h2>Last 7 Days</h2>
            <div class="history-section">
                <div class="history-grid">
                    <?php foreach ($streak_history as $day): ?>
                    <div class="history-day <?php echo $day['tasks_completed'] > 0 ? 'completed' : ''; ?>">
                        <div class="day-date"><?php echo date('M j', strtotime($day['date'])); ?></div>
                        <div class="day-tasks"><?php echo $day['tasks_completed']; ?> Tasks</div>
                        <div class="day-points"><?php echo $day['points_earned']; ?> Points</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <h2>Achievements</h2>
            <div class="achievements-section">
                <?php foreach ($achievements as $achievement): ?>
                <div class="achievement-card">
                    <div class="achievement-icon">
                        <ion-icon name="trophy-outline"></ion-icon>
                    </div>
                    <div class="achievement-info">
                        <div class="achievement-name">
                            <?php echo htmlspecialchars($achievement['name']); ?>
                        </div>
                        <div class="achievement-date">
                            Achieved on <?php echo date('M j, Y', strtotime($achievement['achieved_at'])); ?>
                        </div>
                    </div>
                    <div class="achievement-points">
                        +<?php echo $achievement['reward_points']; ?> Points
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>
