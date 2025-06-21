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

// Get daily tasks and their completion status
$daily_tasks_sql = "SELECT 
    st.*,
    CASE WHEN ust.id IS NOT NULL THEN 1 ELSE 0 END as completed
    FROM streak_tasks st
    LEFT JOIN user_streak_tasks ust ON 
        st.id = ust.task_id 
        AND ust.user_id = ? 
        AND DATE(ust.completion_date) = CURDATE()
    WHERE st.is_active = 1 
    AND st.is_daily = 1
    ORDER BY st.reward_points ASC";
$daily_tasks_stmt = $conn->prepare($daily_tasks_sql);
$daily_tasks_stmt->execute([$user_id]);
$daily_tasks = $daily_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get one-time tasks and their completion status
$onetime_tasks_sql = "SELECT 
    st.*,
    CASE WHEN ust.id IS NOT NULL THEN 1 ELSE 0 END as completed
    FROM streak_tasks st
    LEFT JOIN user_streak_tasks ust ON 
        st.id = ust.task_id 
        AND ust.user_id = ?
    WHERE st.is_active = 1 
    AND st.is_daily = 0
    ORDER BY st.reward_points ASC";
$onetime_tasks_stmt = $conn->prepare($onetime_tasks_sql);
$onetime_tasks_stmt->execute([$user_id]);
$onetime_tasks = $onetime_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check and record daily login if not already recorded today
$login_check_sql = "SELECT COUNT(*) as logged_today 
                    FROM user_streak_tasks ust 
                    JOIN streak_tasks st ON ust.task_id = st.id 
                    WHERE ust.user_id = ? 
                    AND st.name = 'Daily Login' 
                    AND DATE(ust.completion_date) = CURDATE()";
$login_check_stmt = $conn->prepare($login_check_sql);
$login_check_stmt->execute([$user_id]);
$login_check = $login_check_stmt->fetch(PDO::FETCH_ASSOC);

if ($login_check['logged_today'] == 0) {
    // Get the Daily Login task ID
    $login_task_sql = "SELECT id, reward_points FROM streak_tasks WHERE name = 'Daily Login'";
    $login_task_stmt = $conn->prepare($login_task_sql);
    $login_task_stmt->execute();
    $login_task = $login_task_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($login_task) {
        // Record the daily login and award points
        $record_login_sql = "INSERT INTO user_streak_tasks (user_id, task_id, points_earned) 
                            VALUES (?, ?, ?)";
        $record_login_stmt = $conn->prepare($record_login_sql);
        $record_login_stmt->execute([$user_id, $login_task['id'], $login_task['reward_points']]);
        
        // Update user streak points
        $update_streak_sql = "UPDATE user_streaks 
                            SET streak_points = streak_points + ?, 
                                total_tasks_completed = total_tasks_completed + 1,
                                current_streak = current_streak + 1,
                                longest_streak = GREATEST(longest_streak, current_streak + 1),
                                last_activity_date = CURDATE()
                            WHERE user_id = ?";
        $update_streak_stmt = $conn->prepare($update_streak_sql);
        $update_streak_stmt->execute([$login_task['reward_points'], $user_id]);
    }
}

// Function to check task completion
function checkTaskCompletion($user_id, $task_name) {
    global $conn;
    
    switch($task_name) {
        case 'Daily Login':
            // Check if login was recorded today
            $sql = "SELECT COUNT(*) as count 
                   FROM user_streak_tasks ust 
                   JOIN streak_tasks st ON ust.task_id = st.id 
                   WHERE ust.user_id = ? 
                   AND st.name = 'Daily Login' 
                   AND DATE(ust.completion_date) = CURDATE()";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
            
        case 'Join a Match':
            // Check if user joined any match today
            $sql = "SELECT COUNT(*) as count 
                   FROM (
                       SELECT user_id, join_date FROM match_participants 
                       WHERE user_id = ? AND DATE(join_date) = CURDATE()
                       UNION ALL
                       SELECT user_id, match_date as join_date FROM match_history_archive 
                       WHERE user_id = ? AND DATE(match_date) = CURDATE()
                   ) combined_matches";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
            
        case 'Win a Match':
            // Check if user won any match today
            $sql = "SELECT COUNT(*) as count 
                   FROM (
                       SELECT user_id, join_date FROM match_participants 
                       WHERE user_id = ? AND status = 'winner' AND DATE(join_date) = CURDATE()
                       UNION ALL
                       SELECT user_id, match_date as join_date FROM match_history_archive 
                       WHERE user_id = ? AND position = 1 AND DATE(match_date) = CURDATE()
                   ) combined_matches";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;

        case 'Account Registration':
            // Always true since user is registered
            return true;

        case 'Game Profile Setup':
            // Check if user has at least one game profile
            $sql = "SELECT COUNT(*) as count FROM user_games WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;

        case 'First Match':
            // Check if user has played at least one match (including history)
            $sql = "SELECT COUNT(*) as count 
                   FROM (
                       SELECT user_id FROM match_participants WHERE user_id = ?
                       UNION ALL
                       SELECT user_id FROM match_history_archive WHERE user_id = ?
                   ) combined_matches";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;

        case 'Team Membership':
            // Check if user is in any team (current or history)
            $sql = "SELECT COUNT(*) as count 
                   FROM (
                       SELECT user_id FROM team_members WHERE user_id = ?
                       UNION ALL
                       SELECT user_id FROM team_member_history WHERE user_id = ?
                   ) combined_teams";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;

        case 'First Tournament':
            // Check if user has participated in any tournament through their team
            $sql = "SELECT COUNT(*) as count 
                   FROM tournament_player_history 
                   WHERE user_id = ? 
                   AND status IN ('registered', 'playing', 'completed')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;

        case 'Match Veteran':
            // Check if user has played 50 matches (including history)
            $sql = "SELECT COUNT(*) as count 
                   FROM (
                       SELECT user_id FROM match_participants WHERE user_id = ?
                       UNION ALL
                       SELECT user_id FROM match_history_archive WHERE user_id = ?
                   ) combined_matches";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] >= 50;

        case 'Tournament Veteran':
            // Check if user has played 50 tournaments
            $sql = "SELECT COUNT(*) as count 
                   FROM tournament_player_history 
                   WHERE user_id = ? 
                   AND status IN ('playing', 'completed')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] >= 50;
            
        default:
            return false;
    }
}

// Initialize user_streaks record if it doesn't exist
$init_streak_sql = "INSERT IGNORE INTO user_streaks (user_id, current_streak, longest_streak, streak_points, total_tasks_completed) 
                    VALUES (?, 0, 0, 0, 0)";
$init_streak_stmt = $conn->prepare($init_streak_sql);
$init_streak_stmt->execute([$user_id]);

// Automatically complete tasks and award points
foreach($daily_tasks as $task) {
    if (!$task['completed']) {
        $isCompleted = checkTaskCompletion($user_id, $task['name']);
        if ($isCompleted) {
            try {
                $conn->beginTransaction();
                
                // Record task completion and award points
                $complete_sql = "INSERT INTO user_streak_tasks (user_id, task_id, points_earned) 
                               VALUES (?, ?, ?)";
                $complete_stmt = $conn->prepare($complete_sql);
                $complete_stmt->execute([$user_id, $task['id'], $task['reward_points']]);
                
                // Update user streak points
                $update_sql = "UPDATE user_streaks 
                             SET streak_points = streak_points + ?, 
                                 total_tasks_completed = total_tasks_completed + 1
                             WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([$task['reward_points'], $user_id]);
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error completing task: " . $e->getMessage());
            }
        }
    }
}

foreach($onetime_tasks as $task) {
    if (!$task['completed']) {
        $isCompleted = checkTaskCompletion($user_id, $task['name']);
        if ($isCompleted) {
            try {
                $conn->beginTransaction();
                
                // Record task completion and award points
                $complete_sql = "INSERT INTO user_streak_tasks (user_id, task_id, points_earned) 
                               VALUES (?, ?, ?)";
                $complete_stmt = $conn->prepare($complete_sql);
                $complete_stmt->execute([$user_id, $task['id'], $task['reward_points']]);
                
                // Update user streak points
                $update_sql = "UPDATE user_streaks 
                             SET streak_points = streak_points + ?, 
                                 total_tasks_completed = total_tasks_completed + 1
                             WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->execute([$task['reward_points'], $user_id]);
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Error completing task: " . $e->getMessage());
            }
        }
    }
}

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
</head>
<body>
    <a href="dashboard.php" class="back-button">
        <ion-icon name="arrow-back-outline"></ion-icon>
        Back to Dashboard
    </a>

    <div class="main-content">
        <div class="streak-container">

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
                    <div class="stat-label">Available Points</div>
                </div>
                <div class="stat-card convert-card">
                    <div class="stat-number">
                        <?php 
                        $convertible_coins = floor(($streakInfo['streak_points'] ?? 0) / 10);
                        echo $convertible_coins;
                        ?>
                    </div>
                    <div class="stat-label">Convertible Coins</div>
                    <?php if ($convertible_coins > 0): ?>
                    <button class="convert-btn" onclick="convertPoints()">
                        Convert to Coins
                        <ion-icon name="swap-horizontal-outline"></ion-icon>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="conversion-modal" class="modal">
                <div class="modal-content">
                    <h3>Convert Points to Coins</h3>
                    <p>Available Points: <span id="available-points"><?php echo $streakInfo['streak_points'] ?? 0; ?></span></p>
                    <p>Conversion Rate: 10 points = 1 coin</p>
                    <p>Maximum coins available: <span id="max-coins"><?php echo floor(($streakInfo['streak_points'] ?? 0) / 10); ?></span></p>
                    <div class="coin-input">
                        <label for="coins-to-convert">How many coins do you want?</label>
                        <input type="number" id="coins-to-convert" min="1" max="<?php echo floor(($streakInfo['streak_points'] ?? 0) / 10); ?>" value="1">
                        <p class="points-needed">Points needed: <span id="points-needed">10</span></p>
                    </div>
                    <div class="modal-buttons">
                        <button onclick="closeModal()" class="cancel-btn">Cancel</button>
                        <button onclick="confirmConversion()" class="confirm-btn">Convert</button>
                    </div>
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
                <?php foreach ($daily_tasks as $task): ?>
                <div class="task-card <?php echo $task['completed'] ? 'completed' : ''; ?>">
                    <div class="task-header">
                        <div class="task-name"><?php echo htmlspecialchars($task['name']); ?></div>
                        <div class="task-points"><?php echo $task['reward_points']; ?> Points</div>
                    </div>
                    <div class="task-description">
                        <?php echo htmlspecialchars($task['description']); ?>
                    </div>
                    <div class="task-status">
                        <?php if ($task['completed']): ?>
                            <div class="status-icon completed">
                                <ion-icon name="checkmark-circle"></ion-icon>
                                <span>Task Completed! +<?php echo $task['reward_points']; ?> points earned</span>
                            </div>
                        <?php else: ?>
                            <?php $status = checkTaskCompletion($user_id, $task['name']); ?>
                            <div class="status-icon <?php echo $status ? 'pending' : 'incomplete'; ?>">
                                <?php if ($status): ?>
                                    <ion-icon name="time"></ion-icon>
                                    <span>Task completed! Points will be awarded soon.</span>
                                <?php else: ?>
                                    <ion-icon name="close-circle"></ion-icon>
                                    <span>Task not completed yet</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($onetime_tasks)): ?>
            <h2>One-Time Achievements</h2>
            <div class="tasks-grid">
                <?php foreach ($onetime_tasks as $task): ?>
                <div class="task-card <?php echo $task['completed'] ? 'completed' : ''; ?>">
                    <div class="task-header">
                        <div class="task-name"><?php echo htmlspecialchars($task['name']); ?></div>
                        <div class="task-points"><?php echo $task['reward_points']; ?> Points</div>
                    </div>
                    <div class="task-description">
                        <?php echo htmlspecialchars($task['description']); ?>
                    </div>
                    <div class="task-status">
                        <?php if ($task['completed']): ?>
                            <div class="status-icon completed">
                                <ion-icon name="checkmark-circle"></ion-icon>
                                <span>Achievement Unlocked! +<?php echo $task['reward_points']; ?> points earned</span>
                            </div>
                        <?php else: ?>
                            <?php $status = checkTaskCompletion($user_id, $task['name']); ?>
                            <div class="status-icon <?php echo $status ? 'pending' : 'incomplete'; ?>">
                                <?php if ($status): ?>
                                    <ion-icon name="time"></ion-icon>
                                    <span>Completed! Points will be awarded soon.</span>
                                <?php else: ?>
                                    <ion-icon name="close-circle"></ion-icon>
                                    <span>Not completed yet</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <h2>Last 7 Days Activity</h2>
            <div class="history-section">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tasks Completed</th>
                            <th>Points Earned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Get the last 7 days, including days with no activity
                        $last7Days = array();
                        for ($i = 0; $i < 7; $i++) {
                            $date = date('Y-m-d', strtotime("-$i days"));
                            $last7Days[$date] = array(
                                'date' => $date,
                                'tasks_completed' => 0,
                                'points_earned' => 0
                            );
                        }
                        
                        // Fill in the actual data
                        foreach ($streak_history as $day) {
                            if (isset($last7Days[$day['date']])) {
                                $last7Days[$day['date']] = $day;
                            }
                        }
                        
                        foreach ($last7Days as $day): 
                        ?>
                        <tr class="<?php echo $day['tasks_completed'] > 0 ? 'active-day' : 'inactive-day'; ?>">
                            <td><?php echo date('D, M j', strtotime($day['date'])); ?></td>
                            <td><?php echo $day['tasks_completed']; ?></td>
                            <td><?php echo $day['points_earned'] ?? 0; ?></td>
                            <td>
                                <?php if ($day['tasks_completed'] > 0): ?>
                                    <span class="status-badge success">
                                        <ion-icon name="checkmark-circle"></ion-icon> Active
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge inactive">
                                        <ion-icon name="close-circle"></ion-icon> Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2>Milestone Achievements</h2>
            <div class="achievements-section">
                <?php if (empty($achievements)): ?>
                    <div class="no-achievements">
                        <ion-icon name="trophy-outline" class="large-icon"></ion-icon>
                        <p>Complete tasks and earn points to unlock achievements!</p>
                        <div class="upcoming-milestones">
                            <h3>Upcoming Milestones:</h3>
                            <ul>
                                <li>
                                    <div class="milestone-name">Bronze Streak</div>
                                    <div class="milestone-points">100 points</div>
                                    <div class="milestone-reward">Earn exclusive profile badge</div>
                                </li>
                                <li>
                                    <div class="milestone-name">Silver Streak</div>
                                    <div class="milestone-points">250 points</div>
                                    <div class="milestone-reward">Get 50 bonus coins</div>
                                </li>
                                <li>
                                    <div class="milestone-name">Gold Streak</div>
                                    <div class="milestone-points">500 points</div>
                                    <div class="milestone-reward">Unlock special tournament access</div>
                                </li>
                                <li>
                                    <div class="milestone-name">Diamond Streak</div>
                                    <div class="milestone-points">1000 points</div>
                                    <div class="milestone-reward">Earn premium membership benefits</div>
                                </li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($achievements as $achievement): ?>
                    <div class="achievement-card">
                        <div class="achievement-icon">
                            <?php
                            $iconName = 'trophy';
                            switch($achievement['name']) {
                                case 'Bronze Streak':
                                    $iconName = 'medal';
                                    break;
                                case 'Silver Streak':
                                    $iconName = 'ribbon';
                                    break;
                                case 'Gold Streak':
                                    $iconName = 'star';
                                    break;
                                case 'Diamond Streak':
                                    $iconName = 'diamond';
                                    break;
                            }
                            ?>
                            <ion-icon name="<?php echo $iconName; ?>-outline"></ion-icon>
                        </div>
                        <div class="achievement-info">
                            <div class="achievement-name">
                                <?php echo htmlspecialchars($achievement['name']); ?>
                            </div>
                            <div class="achievement-description">
                                <?php echo htmlspecialchars($achievement['description']); ?>
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script>
        function convertPoints() {
            document.getElementById('conversion-modal').style.display = 'flex';
            updatePointsNeeded();
        }

        function closeModal() {
            document.getElementById('conversion-modal').style.display = 'none';
        }

        function updatePointsNeeded() {
            const coinsInput = document.getElementById('coins-to-convert');
            const pointsNeeded = document.getElementById('points-needed');
            pointsNeeded.textContent = coinsInput.value * 10;
        }

        document.getElementById('coins-to-convert').addEventListener('input', updatePointsNeeded);

        function confirmConversion() {
            const coinsToConvert = document.getElementById('coins-to-convert').value;
            
            fetch('streak_convert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    coins: coinsToConvert
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error converting points');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error converting points');
            });
        }
    </script>
</body>
</html>
