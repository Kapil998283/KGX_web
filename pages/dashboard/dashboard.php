<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/header.php';

// Helper function to get position suffix
function getPositionSuffix($position) {
    if ($position >= 11 && $position <= 13) {
        return 'th';
    }
    switch ($position % 10) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

session_start();
require_once '../../includes/user-auth.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: /KGX/pages/login.php");
    exit();
}

// Get database connection
$conn = getDbConnection();

// Get user ID from session
$user_id = $_SESSION['user_id'];

// --- Determine Profile Image ---
$profile_image = '/KGX/assets/images/team-member-8.png'; // Ultimate fallback path
$user_specific_image = null;

// 1. Check if user has a specific profile image set
$sql_user_img = "SELECT profile_image FROM users WHERE id = :user_id";
$stmt_user_img = $conn->prepare($sql_user_img);
if ($stmt_user_img) {
    $stmt_user_img->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($stmt_user_img->execute()) {
        $user_data_img = $stmt_user_img->fetch(PDO::FETCH_ASSOC);
        if ($user_data_img && !empty($user_data_img['profile_image'])) {
            $user_specific_image = $user_data_img['profile_image'];
        }
    } else {
        error_log("Dashboard: Error executing user image statement: " . $stmt_user_img->errorInfo()[2]);
    }
} else {
     error_log("Dashboard: Failed to prepare user image statement: " . $conn->errorInfo()[2]);
}

if ($user_specific_image) {
    // Use the user's specific image
    $profile_image = $user_specific_image;
} else {
    // 2. If no user-specific image, find the default image from profile_images table
    $sql_default = "SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1";
    $stmt_default = $conn->prepare($sql_default);
    if ($stmt_default) {
         if ($stmt_default->execute()) {
             $default_image_data = $stmt_default->fetch(PDO::FETCH_ASSOC);
             if ($default_image_data) {
                 $profile_image = $default_image_data['image_path'];
             }
         } else {
              error_log("Dashboard: Error executing default image statement: " . $stmt_default->errorInfo()[2]);
         }
    } else {
        error_log("Dashboard: Failed to prepare default image statement: " . $conn->errorInfo()[2]);
    }
    // If no default found, $profile_image remains the ultimate fallback
}

// Adjust path for local assets if needed (prepend /KGX)
if (strpos($profile_image, '/assets/') === 0 && strpos($profile_image, '/KGX') !== 0) {
    $profile_image = '/KGX' . $profile_image;
}
// --- End Determine Profile Image ---


// --- Existing PHP code for dashboard data ---
// Fetch user basic data (if needed elsewhere, otherwise remove)
$sql_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_user->execute();
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Fetch coins
$coins = 0;
$sql_coins = "SELECT coins FROM user_coins WHERE user_id = :user_id";
$stmt_coins = $conn->prepare($sql_coins);
if ($stmt_coins) {
    $stmt_coins->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($stmt_coins->execute()) {
        $row_coins = $stmt_coins->fetch(PDO::FETCH_ASSOC);
        if ($row_coins) {
            $coins = $row_coins['coins'] ?? 0;
        }
    } else {
         error_log("Dashboard: Error executing coins statement: " . $stmt_coins->errorInfo()[2]);
    }
} else {
     error_log("Dashboard: Failed to prepare coins statement: " . $conn->errorInfo()[2]);
}

// Fetch tickets
$tickets = 0;
$sql_tickets = "SELECT tickets as total_tickets FROM user_tickets WHERE user_id = :user_id";
$stmt_tickets = $conn->prepare($sql_tickets);
if ($stmt_tickets) {
    $stmt_tickets->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($stmt_tickets->execute()) {
        $row_tickets = $stmt_tickets->fetch(PDO::FETCH_ASSOC);
        if ($row_tickets) {
            $tickets = $row_tickets['total_tickets'] ?? 0;
        }
    } else {
         error_log("Dashboard: Error executing tickets statement: " . $stmt_tickets->errorInfo()[2]);
    }
} else {
     error_log("Dashboard: Failed to prepare tickets statement: " . $conn->errorInfo()[2]);
}

// Get user's team score if they are in a team
$team_score = 0;
$has_team = false;
$team_id = null;
$team_score_sql = "SELECT t.total_score, t.id 
                   FROM teams t 
                   JOIN team_members tm ON t.id = tm.team_id 
                   WHERE tm.user_id = :user_id 
                   AND tm.status = 'active' 
                   LIMIT 1";
$stmt_team = $conn->prepare($team_score_sql);
$stmt_team->execute(['user_id' => $user_id]);
$team_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
if ($team_data) {
    $team_score = $team_data['total_score'];
    $team_id = $team_data['id'];
    $has_team = true;
}

// Get tournament count for the team
$tournament_count = 0;
if ($has_team) {
    $tournament_count_sql = "SELECT COUNT(DISTINCT tr.tournament_id) as count 
                            FROM tournament_registrations tr 
                            WHERE tr.team_id = :team_id 
                            AND tr.status = 'approved'";
    $stmt_count = $conn->prepare($tournament_count_sql);
    $stmt_count->execute(['team_id' => $team_id]);
    $count_data = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $tournament_count = $count_data['count'];
}

// Get matches count and total kills from permanent stats
$stats_stmt = $conn->prepare("SELECT COALESCE(total_matches_played, 0) as matches_count, 
                                    COALESCE(total_kills, 0) as total_kills 
                             FROM user_match_stats 
                             WHERE user_id = ?");
$stats_stmt->execute([$user_id]);
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$matches_count = $user_stats ? $user_stats['matches_count'] : 0;
$total_kills = $user_stats ? $user_stats['total_kills'] : 0;

// Fetch redeemed items
$redemption_items = [];
$sql_redemption = "SELECT ri.name, rh.coins_spent, rh.status, rh.redeemed_at
                   FROM redemption_history rh
                   JOIN redeemable_items ri ON rh.item_id = ri.id
                   WHERE rh.user_id = :user_id
                   ORDER BY rh.redeemed_at DESC
                   LIMIT 10";
$stmt_redemption = $conn->prepare($sql_redemption);
if ($stmt_redemption) {
    $stmt_redemption->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if (!$stmt_redemption->execute()) {
        error_log("Dashboard: Error executing redemption statement: " . $stmt_redemption->errorInfo()[2]);
    } else {
        $redemption_items = $stmt_redemption->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    error_log("Dashboard: Failed to prepare redemption statement: " . $conn->errorInfo()[2]);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Dashboard </title>
    <!-- ======= Styles ====== -->
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
   <!-- =============== Navigation ================ -->
   <div class="container">
        <div class="navigation">
            <ul>
                <li>
                    <a href="#">
                        <span class="icon">
                            <ion-icon name="game-controller-outline"></ion-icon>
                        </span>
                        <span class="title">web site name </span>
                    </a>
                </li>

                <li>
                    <a href="../../home.php">
                        <span class="icon">
                            <ion-icon name="home-outline"></ion-icon>
                        </span>
                        <span class="title">Home</span>
                    </a>
                </li>

                <li>
                    <a href="./redeem.php">
                        <span class="icon">
                            <ion-icon name="gift-outline"></ion-icon>
                        </span>
                        <span class="title">Redeem</span>
                    </a>
                </li>

                
                <li>
                    <a href="./strike.php">
                        <span class="icon">
                            <ion-icon name="flame-outline"></ion-icon>
                        </span>
                        <span class="title">Strike</span>
                    </a>
                </li>

                <li>
                    <a href="./game-profile.php">
                        <span class="icon">
                            <ion-icon name="game-controller-outline"></ion-icon>
                        </span>
                        <span class="title">Game Profile</span>
                    </a>
                </li>

                <li>
                    <a href="./help-contact.php">
                        <span class="icon">
                            <ion-icon name="help-outline"></ion-icon>
                        </span>
                        <span class="title">Help</span>
                    </a>
                </li>

                <li>
                    <a href="setting.php">
                        <span class="icon">
                            <ion-icon name="settings-outline"></ion-icon>
                        </span>
                        <span class="title">Settings</span>
                    </a>
                </li>

                <li>
                    <a href="../../pages/forgot-password.php">
                        <span class="icon">
                            <ion-icon name="lock-closed-outline"></ion-icon>
                        </span>
                        <span class="title">Password Reset</span>
                    </a>
                </li>

            </ul>
        </div>

        <!-- ========================= Main ==================== -->
        <div class="main">
            <div class="topbar">
                <div class="toggle">
                    <ion-icon name="menu-outline"></ion-icon>
                </div>

                <div class="search">
                    <label>
                        <input type="text" placeholder="Search here">
                        <ion-icon name="search-outline"></ion-icon>
                    </label>
                </div>

                <div class="user">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="User Profile">
                </div>
            </div>

            <!-- ======================= Dashboard================== -->
        <section class="dashboard">
            <div class="cardBox">
                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $coins; ?></div>
                        <div class="cardName">Coins</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="wallet-outline"></ion-icon>
                    </div>
                </div>

                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $tickets; ?></div>
                        <div class="cardName">Tickets</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="cash-outline"></ion-icon>
                    </div>
                </div>

                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $tickets; ?></div>
                        <div class="cardName">STRIKE</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="flame-outline"></ion-icon>
                    </div>
                </div>

                <?php if ($has_team): ?>
                <div class="card">
                    <div>
                        <div class="numbers"><?php echo number_format($team_score); ?></div>
                        <div class="cardName">Team Score</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="people-outline"></ion-icon>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $matches_count; ?></div>
                        <div class="cardName">Matches Played</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="game-controller-outline"></ion-icon>
                    </div>
                </div>

                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $total_kills; ?></div>
                        <div class="cardName">Total Kills</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="skull-outline"></ion-icon>
                    </div>
                </div>

                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $tournament_count; ?></div>
                        <div class="cardName">Played Tournaments</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="trophy-outline"></ion-icon>
                    </div>
                </div>

                <div class="card">
                    <div>
                        <div class="numbers"><?php echo $matches_count; ?></div>
                        <div class="cardName">videos watched</div>
                    </div>

                    <div class="iconBx">
                        <ion-icon name="game-controller-outline"></ion-icon>
                    </div>
                </div>

                
            </div>
        </section>
            <!-- ================ Labels & Content Sections ================= -->
            <div class="labels-container">
                <div class="labels-nav">
                    <button class="label-btn active" data-section="top10">Top 10</button>
                    <button class="label-btn" data-section="matches">Matches</button>
                    <button class="label-btn" data-section="tournaments">Tournaments</button>
                </div>

                <!-- Top 10 Section -->
                <div class="content-section active" id="top10-section">
                    <div class="section-header">
                        <h2>Top 10 Teams</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Score</th>
                                <th>Members</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get top 8 teams by score
                            $top_teams_sql = "SELECT t.*, 
                                (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
                                FROM teams t 
                                WHERE t.is_active = 1
                                ORDER BY t.total_score DESC 
                                LIMIT 8";
                            $stmt = $conn->prepare($top_teams_sql);
                            $stmt->execute();
                            $top_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($top_teams) > 0):
                                foreach ($top_teams as $team):
                            ?>
                                <tr>
                                    <td>
                                        <div class="team-info">
                                            <?php if (!empty($team['logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                                     alt="<?php echo htmlspecialchars($team['name']); ?>"
                                                     class="team-logo">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($team['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($team['total_score']); ?></td>
                                    <td><?php echo $team['member_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No teams found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Matches Section -->
                <div class="content-section" id="matches-section">
                    <div class="section-header">
                        <h2>Recent Matches</h2>
                        <a href="../matches/my-matches.php" class="btn">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Type</th>
                                <th>Performance</th>
                                <th>Rewards</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get user's recent matches
                            $match_history_sql = "SELECT 
                                m.id,
                                g.name as game_name,
                                m.match_type,
                                mp.position,
                                COALESCE(uk.kills, 0) as kills,
                                m.completed_at,
                                m.website_currency_type,
                                (COALESCE(uk.kills, 0) * m.coins_per_kill) as kill_rewards
                            FROM matches m
                            JOIN match_participants mp ON m.id = mp.match_id
                            JOIN games g ON m.game_id = g.id
                            LEFT JOIN user_kills uk ON uk.match_id = m.id AND uk.user_id = mp.user_id
                            WHERE mp.user_id = ? AND m.status = 'completed'
                            ORDER BY m.completed_at DESC
                            LIMIT 5";
                            
                            $stmt = $conn->prepare($match_history_sql);
                            $stmt->execute([$user_id]);
                            $match_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($match_history) > 0):
                                foreach ($match_history as $match): 
                                    // Get position suffix
                                    $suffix = 'th';
                                    if ($match['position'] == 1) $suffix = 'st';
                                    else if ($match['position'] == 2) $suffix = 'nd';
                                    else if ($match['position'] == 3) $suffix = 'rd';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($match['game_name']); ?></td>
                                    <td><?php echo htmlspecialchars($match['match_type']); ?></td>
                                    <td>
                                        Position: <?php echo $match['position'] . $suffix; ?><br>
                                        Kills: <?php echo $match['kills']; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($match['kill_rewards'] > 0) {
                                            echo $match['kill_rewards'] . ' Coins';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($match['completed_at'])); ?></td>
                                </tr>
                            <?php 
                                endforeach; 
                            else:
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center">No match history found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Tournaments Section -->
                <div class="content-section" id="tournaments-section">
                    <div class="section-header">
                        <h2>Tournament History</h2>
                        <a href="../tournaments/my-registrations.php" class="btn">View All</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Tournament</th>
                                <th>Game</th>
                                <th>Team</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get user's recent tournament participation
                            $tournament_sql = "SELECT 
                                t.name as tournament_name,
                                t.game_name,
                                tm.name as team_name,
                                tph.status,
                                tph.registration_date,
                                tph.rounds_played,
                                tph.total_kills,
                                tph.total_points,
                                tph.best_placement,
                                tph.final_position,
                                tph.prize_amount,
                                tph.prize_currency,
                                tph.website_currency_earned,
                                tph.website_currency_type
                            FROM tournament_player_history tph
                            JOIN tournaments t ON tph.tournament_id = t.id
                            JOIN teams tm ON tph.team_id = tm.id
                            WHERE tph.user_id = ?
                            ORDER BY tph.registration_date DESC
                            LIMIT 5";
                            
                            $stmt = $conn->prepare($tournament_sql);
                            $stmt->execute([$user_id]);
                            $tournament_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($tournament_history) > 0):
                                foreach ($tournament_history as $tournament): 
                                    // Get performance details
                                    $performance = [];
                                    if ($tournament['rounds_played'] > 0) {
                                        $performance[] = $tournament['rounds_played'] . ' rounds played';
                                    }
                                    if ($tournament['total_kills'] > 0) {
                                        $performance[] = $tournament['total_kills'] . ' kills';
                                    }
                                    if ($tournament['total_points'] > 0) {
                                        $performance[] = $tournament['total_points'] . ' points';
                                    }
                                    if ($tournament['best_placement']) {
                                        $performance[] = 'Best: ' . $tournament['best_placement'] . getPositionSuffix($tournament['best_placement']);
                                    }
                                    if ($tournament['final_position'] && $tournament['status'] === 'completed') {
                                        $performance[] = 'Final: ' . $tournament['final_position'] . getPositionSuffix($tournament['final_position']);
                                    }
                                    if ($tournament['prize_amount'] > 0) {
                                        $performance[] = 'Prize: ' . $tournament['prize_currency'] . ' ' . number_format($tournament['prize_amount'], 2);
                                    }
                                    if ($tournament['website_currency_earned'] > 0) {
                                        $performance[] = 'Earned: ' . $tournament['website_currency_earned'] . ' ' . $tournament['website_currency_type'];
                                    }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tournament['tournament_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tournament['game_name']); ?></td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($tournament['team_name']);
                                        if (!empty($performance)) {
                                            echo '<br><small class="text-muted">' . implode(', ', $performance) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td><span class="status <?php echo strtolower($tournament['status']); ?>"><?php echo ucfirst($tournament['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($tournament['registration_date'])); ?></td>
                                </tr>
                            <?php 
                                endforeach; 
                            else:
                            ?>
                                <tr>
                                    <td colspan="5" class="text-center">No tournament history found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== ionicons ======= -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    
    <!-- Dashboard JavaScript -->
    <script src="dashboard.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labelBtns = document.querySelectorAll('.label-btn');
            const contentSections = document.querySelectorAll('.content-section');

            labelBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all buttons and sections
                    labelBtns.forEach(b => b.classList.remove('active'));
                    contentSections.forEach(s => s.classList.remove('active'));

                    // Add active class to clicked button and corresponding section
                    btn.classList.add('active');
                    const sectionId = btn.getAttribute('data-section') + '-section';
                    document.getElementById(sectionId).classList.add('active');
                });
            });
        });
    </script>
</body>

</html> 