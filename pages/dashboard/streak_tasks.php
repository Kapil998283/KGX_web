<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

require_once '../../includes/user-auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get today's tasks and completion status
$stmt = $conn->prepare("
    SELECT 
        st.*,
        CASE 
            WHEN ust.id IS NOT NULL THEN 1 
            ELSE 0 
        END as completed
    FROM streak_tasks st
    LEFT JOIN user_streak_tasks ust ON 
        ust.task_id = st.id 
        AND ust.user_id = ? 
        AND DATE(ust.completion_date) = CURDATE()
    ORDER BY st.reward_points ASC
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user streak info
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

// Get next milestone
$stmt = $conn->prepare("
    SELECT * FROM streak_milestones 
    WHERE points_required > ? 
    ORDER BY points_required ASC 
    LIMIT 1
");
$stmt->execute([$streakInfo['streak_points']]);
$nextMilestone = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Streak Tasks</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="streak.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container">
        <div class="dashboard-container">
            <div class="streak-info-card">
                <h2>Your Streak Stats</h2>
                <div class="streak-stats">
                    <div class="stat">
                        <span class="label">Current Streak</span>
                        <span class="value" id="current-streak"><?php echo $streakInfo['current_streak']; ?></span>
                    </div>
                    <div class="stat">
                        <span class="label">Longest Streak</span>
                        <span class="value"><?php echo $streakInfo['longest_streak']; ?></span>
                    </div>
                    <div class="stat">
                        <span class="label">Total Points</span>
                        <span class="value" id="streak-points"><?php echo $streakInfo['streak_points']; ?></span>
                    </div>
                </div>
                <?php if ($nextMilestone): ?>
                <div class="milestone-progress">
                    <h3>Next Milestone: <?php echo htmlspecialchars($nextMilestone['name']); ?></h3>
                    <div class="progress-bar">
                        <?php 
                        $progress = min(100, ($streakInfo['streak_points'] / $nextMilestone['points_required']) * 100);
                        ?>
                        <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <span class="progress-text"><?php echo $streakInfo['streak_points']; ?> / <?php echo $nextMilestone['points_required']; ?> points</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="tasks-container">
                <h2>Today's Tasks</h2>
                <div id="alert-container"></div>
                <div class="tasks-grid">
                    <?php foreach ($tasks as $task): ?>
                    <div class="task-card <?php echo $task['completed'] ? 'completed' : ''; ?>" data-task-id="<?php echo $task['id']; ?>">
                        <h3><?php echo htmlspecialchars($task['name']); ?></h3>
                        <p><?php echo htmlspecialchars($task['description']); ?></p>
                        <div class="task-footer">
                            <span class="points"><?php echo $task['reward_points']; ?> points</span>
                            <?php if (!$task['completed']): ?>
                            <button class="complete-task-btn" onclick="completeTask(<?php echo $task['id']; ?>)">Complete</button>
                            <?php else: ?>
                            <span class="completed-label">Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>
</html> 