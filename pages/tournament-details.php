<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Get tournament details with registration status
$stmt = $conn->prepare("
    SELECT t.*, 
           tr.status as registration_status,
           tr.team_id,
           tm.name as team_name,
           COUNT(DISTINCT reg.id) as total_registered_teams
    FROM tournaments t
    LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id 
        AND tr.team_id IN (SELECT team_id FROM team_members WHERE user_id = ?)
    LEFT JOIN teams tm ON tr.team_id = tm.id
    LEFT JOIN tournament_registrations reg ON t.id = reg.tournament_id AND reg.status = 'approved'
    WHERE t.id = ?
    GROUP BY t.id
");
$stmt->execute([$user_id, $tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found!";
    header("Location: all-tournaments.php");
    exit();
}

// Get user's team rounds information if registered
$team_rounds = [];
if ($tournament['team_id']) {
    $stmt = $conn->prepare("
        SELECT 
            td.day_number,
            tr.round_number,
            tr.name as round_name,
            tr.start_time,
            tr.map_name,
            rt.status as round_status,
            rt.placement,
            rt.kills,
            rt.total_points
        FROM tournament_days td
        JOIN tournament_rounds tr ON td.id = tr.day_id
        JOIN round_teams rt ON tr.id = rt.round_id
        WHERE td.tournament_id = ? AND rt.team_id = ?
        ORDER BY td.day_number, tr.round_number
    ");
    $stmt->execute([$tournament_id, $tournament['team_id']]);
    $team_rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get tournament rules and point system
$placement_points = json_decode($tournament['placement_points'] ?? '{}', true);
?>

<div class="container mt-4">
    <div class="row">
        <!-- Tournament Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                        <div class="mt-2">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($tournament['game_name']); ?></span>
                            <span class="badge bg-info"><?php echo htmlspecialchars($tournament['mode']); ?></span>
                            <span class="badge bg-secondary">Entry Fee: <?php echo $tournament['entry_fee']; ?> Tickets</span>
                        </div>
                    </div>
                    <?php if (isset($_SESSION['user_id']) && !$tournament['registration_status']): ?>
                        <?php if ($tournament['total_registered_teams'] < $tournament['max_teams']): ?>
                            <a href="tournament-registration.php?id=<?php echo $tournament_id; ?>" class="btn btn-success">Register Now</a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Tournament Full</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Tournament Status -->
                    <?php if ($tournament['registration_status']): ?>
                        <div class="alert alert-info">
                            Your team "<?php echo htmlspecialchars($tournament['team_name']); ?>" is 
                            <?php echo htmlspecialchars($tournament['registration_status']); ?> for this tournament.
                        </div>
                    <?php endif; ?>

                    <!-- Tournament Information -->
                    <div class="mb-4">
                        <h5>Tournament Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Start Date:</strong> <?php echo date('d M Y', strtotime($tournament['playing_start_date'])); ?></p>
                                <p><strong>Registration Deadline:</strong> <?php echo date('d M Y', strtotime($tournament['registration_end_date'])); ?></p>
                                <p><strong>Teams:</strong> <?php echo $tournament['total_registered_teams']; ?>/<?php echo $tournament['max_teams']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Prize Pool:</strong> <?php echo htmlspecialchars($tournament['prize_pool']); ?></p>
                                <p><strong>Mode:</strong> <?php echo htmlspecialchars($tournament['mode']); ?></p>
                                <p><strong>Status:</strong> <?php echo htmlspecialchars($tournament['status']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Team Performance -->
                    <?php if ($team_rounds): ?>
                        <div class="mb-4">
                            <h5>Your Team's Performance</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Round</th>
                                            <th>Time</th>
                                            <th>Map</th>
                                            <th>Status</th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($team_rounds as $round): ?>
                                            <tr>
                                                <td>Day <?php echo $round['day_number']; ?></td>
                                                <td><?php echo htmlspecialchars($round['round_name']); ?></td>
                                                <td><?php echo date('H:i', strtotime($round['start_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($round['map_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($round['round_status']) {
                                                            'qualified' => 'success',
                                                            'eliminated' => 'danger',
                                                            default => 'primary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($round['round_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($round['placement']): ?>
                                                        Rank: #<?php echo $round['placement']; ?><br>
                                                        Kills: <?php echo $round['kills']; ?><br>
                                                        Points: <?php echo $round['total_points']; ?>
                                                    <?php else: ?>
                                                        Pending
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Rules and Points -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tournament Rules</h5>
                </div>
                <div class="card-body">
                    <h6>Point System</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">Kill Points: <?php echo $tournament['kill_points'] ?? 2; ?> points per kill</li>
                        <li class="mb-2">Placement Points:</li>
                        <ul>
                            <?php foreach ($placement_points as $place => $points): ?>
                                <li>#<?php echo $place; ?>: <?php echo $points; ?> points</li>
                            <?php endforeach; ?>
                        </ul>
                    </ul>

                    <h6 class="mt-4">Qualification</h6>
                    <p>Top teams from each round will qualify for the next round based on:</p>
                    <ul>
                        <li>Total points (Kills + Placement)</li>
                        <li>Consistency in performance</li>
                        <li>Fair play and rule compliance</li>
                    </ul>

                    <?php if ($tournament['description']): ?>
                        <h6 class="mt-4">Additional Rules</h6>
                        <p><?php echo nl2br(htmlspecialchars($tournament['description'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    background: rgba(20, 20, 20, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.card-header {
    background: rgba(0, 0, 0, 0.3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.table-dark {
    background: rgba(0, 0, 0, 0.2);
}

.badge {
    margin-right: 5px;
}

.alert {
    background: rgba(0, 123, 255, 0.1);
    border: 1px solid rgba(0, 123, 255, 0.2);
}
</style>

<?php require_once '../includes/footer.php'; ?> 