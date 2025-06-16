<?php
require_once '../includes/admin-auth.php';
require_once '../../config/database.php';
include '../includes/admin-header.php';

// Add these headers at the top of the file, after the require statements
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get match ID from URL
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch match details
$stmt = $db->prepare("SELECT m.*, g.name as game_name, g.image_url as game_image,
                            t1.name as team1_name, t2.name as team2_name,
                            DATE(m.match_date) as match_date,
                            TIME(m.match_date) as match_time,
                            t.name as tournament_name,
                            m.website_currency_type,
                            m.website_currency_amount,
                            m.prize_distribution,
                            m.coins_per_kill
                     FROM matches m 
                     LEFT JOIN games g ON m.game_id = g.id 
                     LEFT JOIN teams t1 ON m.team1_id = t1.id
                     LEFT JOIN teams t2 ON m.team2_id = t2.id
                     LEFT JOIN tournaments t ON m.tournament_id = t.id
                     WHERE m.id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    header("Location: " . strtolower($match['game_name']) . ".php");
    exit;
}

// Fetch participants
$stmt = $db->prepare("SELECT mp.*, u.username, u.email, u.phone,
                            COALESCE(uk.kills, 0) as total_kills
                     FROM match_participants mp
                     JOIN users u ON mp.user_id = u.id
                     LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                     WHERE mp.match_id = ?
                     ORDER BY uk.kills DESC, u.username ASC");
$stmt->execute([$match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add this function after the require statements at the top
function distributePrize($db, $match_id, $winner_id, $match) {
    if (!$winner_id) return;

    // Get all participants sorted by score/kills
    $stmt = $db->prepare("SELECT mp.user_id, mp.team_id, COALESCE(uk.kills, 0) as kills 
                         FROM match_participants mp 
                         LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id 
                         WHERE mp.match_id = ? 
                         ORDER BY uk.kills DESC");
    $stmt->execute([$match_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle website currency distribution
    if ($match['website_currency_type'] && $match['website_currency_amount'] > 0) {
        $total_prize = $match['website_currency_amount'];
        $currency_type = $match['website_currency_type'];
        
        // Define prize distribution percentages
        $distribution_percentages = [];
        switch($match['prize_distribution']) {
            case 'top3':
                $distribution_percentages = [60, 30, 10]; // 60% for 1st, 30% for 2nd, 10% for 3rd
                break;
            case 'top5':
                $distribution_percentages = [50, 25, 15, 7, 3]; // 50% for 1st, 25% for 2nd, etc.
                break;
            default: // 'single' - winner takes all
                $distribution_percentages = [100];
                break;
        }

        // Distribute prizes according to the percentages
        foreach ($participants as $index => $participant) {
            if ($index >= count($distribution_percentages)) break;
            
            $prize_amount = floor($total_prize * $distribution_percentages[$index] / 100);
            if ($prize_amount <= 0) continue;

            if ($currency_type === 'coins') {
                $stmt = $db->prepare("INSERT INTO user_coins (user_id, coins) 
                                    VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE coins = coins + ?");
            } else {
                $stmt = $db->prepare("INSERT INTO user_tickets (user_id, tickets) 
                                    VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE tickets = tickets + ?");
            }
            $stmt->execute([$participant['user_id'], $prize_amount, $prize_amount]);
        }
    }

    // Handle real money distribution (for admin reference)
    if ($match['prize_pool'] > 0) {
        $total_prize = $match['prize_pool'];
        $currency_type = $match['prize_type'];
        
        // Define prize distribution percentages
        $distribution_percentages = [];
        switch($match['prize_distribution']) {
            case 'top3':
                $distribution_percentages = [60, 30, 10];
                break;
            case 'top5':
                $distribution_percentages = [50, 25, 15, 7, 3];
                break;
            default: // 'single'
                $distribution_percentages = [100];
                break;
        }

        // Calculate and store prize amounts for each winner
        foreach ($participants as $index => $participant) {
            if ($index >= count($distribution_percentages)) break;
            
            $prize_amount = round($total_prize * $distribution_percentages[$index] / 100, 2);
            if ($prize_amount <= 0) continue;

            // Store in match_results table
            $stmt = $db->prepare("INSERT INTO match_results (match_id, team_id, prize_amount, prize_currency) 
                                VALUES (?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE 
                                prize_amount = VALUES(prize_amount),
                                prize_currency = VALUES(prize_currency)");
            $stmt->execute([$match_id, $participant['team_id'], $prize_amount, $currency_type]);
        }
    }
}

// Handle POST requests for updating kills and completing match
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_kills':
                $user_id = $_POST['user_id'];
                $kills = intval($_POST['kills']);
                
                try {
                    $db->beginTransaction();
                    
                    // Update or insert kills
                    $stmt = $db->prepare("INSERT INTO user_kills (match_id, user_id, kills) 
                                        VALUES (?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE kills = ?");
                    $stmt->execute([$match_id, $user_id, $kills, $kills]);
                    
                    // Calculate and award coins for kills
                    if ($match['coins_per_kill'] > 0) {
                        $coins_earned = $kills * $match['coins_per_kill'];
                        $stmt = $db->prepare("INSERT INTO user_coins (user_id, coins) 
                                            VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE coins = coins + ?");
                        $stmt->execute([$user_id, $coins_earned, $coins_earned]);
                    }
                    
                    $db->commit();
                    header("Location: match_scoring.php?id=" . $match_id . "&success=1");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error updating kills: " . $e->getMessage());
                    header("Location: match_scoring.php?id=" . $match_id . "&error=1");
                    exit;
                }
                break;

            case 'select_winner':
                try {
                    $db->beginTransaction();
                    
                    $winner_id = $_POST['winner_id'];
                    
                    // Update match status and winner
                    $stmt = $db->prepare("UPDATE matches SET 
                                        status = 'completed', 
                                        completed_at = NOW(), 
                                        winner_id = ?
                                        WHERE id = ?");
                    $stmt->execute([$winner_id, $match_id]);
                    
                    // Award prizes based on distribution type
                    distributePrize($db, $match_id, $winner_id, $match);
                    
                    $db->commit();
                    // Redirect back to the game-specific page
                    $game_page = strtolower($match['game_name']) . ".php";
                    header("Location: $game_page?completed=1");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error selecting winner: " . $e->getMessage());
                    header("Location: match_scoring.php?id=" . $match_id . "&error=1");
                    exit;
                }
                break;

            case 'complete_match':
                try {
                    $db->beginTransaction();
                    
                    // Get total kills for each team
                    $stmt = $db->prepare("SELECT mp.team_id, SUM(uk.kills) as total_kills 
                                        FROM match_participants mp 
                                        LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id 
                                        WHERE mp.match_id = ? 
                                        GROUP BY mp.team_id");
                    $stmt->execute([$match_id]);
                    $team_scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Update match scores
                    $score_team1 = 0;
                    $score_team2 = 0;
                    foreach ($team_scores as $score) {
                        if ($score['team_id'] == $match['team1_id']) {
                            $score_team1 = $score['total_kills'];
                        } else if ($score['team_id'] == $match['team2_id']) {
                            $score_team2 = $score['total_kills'];
                        }
                    }
                    
                    // Determine winner
                    $winner_id = null;
                    if ($score_team1 > $score_team2) {
                        $winner_id = $match['team1_id'];
                    } elseif ($score_team2 > $score_team1) {
                        $winner_id = $match['team2_id'];
                    }
                    
                    // Update match status and scores
                    $stmt = $db->prepare("UPDATE matches SET 
                                        status = 'completed', 
                                        completed_at = NOW(), 
                                        winner_id = ?,
                                        score_team1 = ?,
                                        score_team2 = ?
                                        WHERE id = ?");
                    $stmt->execute([$winner_id, $score_team1, $score_team2, $match_id]);
                    
                    // Award prizes based on distribution type
                    distributePrize($db, $match_id, $winner_id, $match);
                    
                    $db->commit();
                    // Redirect back to the game-specific page
                    $game_page = strtolower($match['game_name']) . ".php";
                    header("Location: $game_page?completed=1");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error completing match: " . $e->getMessage());
                    header("Location: match_scoring.php?id=" . $match_id . "&error=1");
                    exit;
                }
                break;
        }
    }
}

// Add success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    $success_message = 'Kills updated successfully!';
} elseif (isset($_GET['error'])) {
    $error_message = 'An error occurred. Please try again.';
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Update Match Score</h3>
                        <div>
                            <a href="match_details.php?id=<?= $match_id ?>" class="btn btn-info me-2">
                                <i class="bi bi-people"></i> View Participants
                            </a>
                            <a href="javascript:history.back()" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Match Info -->
                    <div class="match-info-header mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><?= htmlspecialchars($match['game_name']) ?> - <?= ucfirst($match['match_type']) ?></h4>
                                <p class="text-muted">
                                    <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($match['match_date'])) ?>
                                    <i class="bi bi-clock ms-3"></i> <?= date('g:i A', strtotime($match['match_time'])) ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="prize-info">
                                    <?php if ($match['website_currency_type'] && $match['website_currency_amount'] > 0): ?>
                                        <h5>Prize Pool: <?= number_format($match['website_currency_amount']) ?> <?= ucfirst($match['website_currency_type']) ?></h5>
                                    <?php else: ?>
                                        <h5>Prize Pool: <?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($match['prize_pool']) ?></h5>
                                    <?php endif; ?>
                                    <?php if ($match['coins_per_kill'] > 0): ?>
                                        <p class="text-success">
                                            <i class="bi bi-star"></i> <?= number_format($match['coins_per_kill']) ?> Coins per Kill
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Complete Match Button -->
                    <?php if ($match['status'] === 'in_progress'): ?>
                    <div class="text-end mb-4">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="complete_match">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to complete this match? This will calculate final scores and award prizes.')">
                                <i class="bi bi-check-lg"></i> Complete Match
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Add success/error messages -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Participants Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Player</th>
                                    <th>Contact</th>
                                    <th>Kills</th>
                                    <th>Coins Earned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($participant['username']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($participant['email']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($participant['phone']) ?></small>
                                    </td>
                                    <td><?= $participant['total_kills'] ?></td>
                                    <td><?= $participant['total_kills'] * $match['coins_per_kill'] ?></td>
                                    <td>
                                        <?php if ($match['status'] === 'in_progress'): ?>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="updateKills(<?= $participant['user_id'] ?>, '<?= htmlspecialchars($participant['username']) ?>', <?= $participant['total_kills'] ?>)">
                                            <i class="bi bi-pencil"></i> Update Kills
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="selectWinner(<?= $participant['user_id'] ?>, '<?= htmlspecialchars($participant['username']) ?>')">
                                            <i class="bi bi-trophy"></i> Select as Winner
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Kills Modal -->
<div class="modal fade" id="updateKillsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_kills">
                <input type="hidden" name="user_id" id="kill_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Kills</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Update kills for <strong id="kill_username"></strong></p>
                    <div class="mb-3">
                        <label for="kills" class="form-label">Number of Kills</label>
                        <input type="number" class="form-control" id="kills" name="kills" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Winner Modal -->
<div class="modal fade" id="selectWinnerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="select_winner">
                <input type="hidden" name="winner_id" id="winner_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Select Winner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to select <strong id="winner_username"></strong> as the winner?</p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This action will:
                        <ul class="mb-0">
                            <li>Mark this player as the match winner</li>
                            <li>Award the prize pool to this player</li>
                            <li>Cannot be undone</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Winner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize Bootstrap modals
const updateKillsModal = new bootstrap.Modal(document.getElementById('updateKillsModal'));
const selectWinnerModal = new bootstrap.Modal(document.getElementById('selectWinnerModal'));

function updateKills(userId, username, currentKills) {
    document.getElementById('kill_user_id').value = userId;
    document.getElementById('kill_username').textContent = username;
    document.getElementById('kills').value = currentKills;
    updateKillsModal.show();
}

function selectWinner(userId, username) {
    document.getElementById('winner_user_id').value = userId;
    document.getElementById('winner_username').textContent = username;
    selectWinnerModal.show();
}
</script>

<style>
.match-info-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.prize-info {
    background: #e8f5e9;
    padding: 1rem;
    border-radius: 8px;
    display: inline-block;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
}

.table td {
    vertical-align: middle;
}

.btn-sm {
    margin: 0.25rem;
}

.modal-content {
    border-radius: 8px;
    border: none;
}

.modal-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.alert {
    border-left: 4px solid #0dcaf0;
}

.alert ul {
    padding-left: 1.25rem;
}
</style>

<?php include '../includes/admin-footer.php'; ?> 