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
    header("Location: bgmi.php");
    exit;
}

// Fetch participants with winner information
$stmt = $db->prepare("SELECT 
                        mp.*, 
                        u.username, 
                        u.email, 
                        u.phone,
                        ug.game_uid,
                        ug.ingame_name,
                        COALESCE(uk.kills, 0) as total_kills,
                        mp.position as winner_position,
                        CASE 
                            WHEN m.winner_user_id = mp.user_id THEN 1
                            WHEN m.winner_id = mp.team_id THEN 1
                            ELSE 0
                        END as is_winner
                     FROM match_participants mp
                     JOIN users u ON mp.user_id = u.id
                     JOIN matches m ON m.id = mp.match_id
                     LEFT JOIN user_games ug ON ug.user_id = u.id AND ug.game_id = m.game_id
                     LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                     WHERE mp.match_id = ?
                     ORDER BY 
                        CASE 
                            WHEN mp.position IS NULL THEN 999999
                            ELSE mp.position 
                        END ASC,
                        uk.kills DESC, 
                        u.username ASC");
$stmt->execute([$match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the number of winners based on prize distribution
$numWinners = 1;
if ($match['prize_distribution'] === 'top3') {
    $numWinners = 3;
} else if ($match['prize_distribution'] === 'top5') {
    $numWinners = 5;
}

// Calculate prize amounts for each position
$prizeAmounts = [];
if ($match['prize_distribution']) {
    $percentages = [];
    switch($match['prize_distribution']) {
        case 'top3':
            $percentages = [60, 30, 10];
            break;
        case 'top5':
            $percentages = [50, 25, 15, 7, 3];
            break;
        default:
            $percentages = [100];
    }

    foreach ($percentages as $index => $percentage) {
        if ($match['website_currency_type']) {
            $prizeAmounts[$index + 1] = floor($match['website_currency_amount'] * $percentage / 100);
        } else {
            $prizeAmounts[$index + 1] = round($match['prize_pool'] * $percentage / 100, 2);
        }
    }
}

?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Match Participants</h3>
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
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
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i> Status: <span class="badge bg-<?= $match['status'] === 'completed' ? 'success' : ($match['status'] === 'in_progress' ? 'primary' : 'warning') ?>">
                                        <?= ucfirst($match['status']) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="prize-info">
                                    <?php if ($match['website_currency_type'] && $match['website_currency_amount'] > 0): ?>
                                        <h5>Prize Pool: <?= number_format($match['website_currency_amount']) ?> <?= ucfirst($match['website_currency_type']) ?></h5>
                                    <?php else: ?>
                                        <h5>Prize Pool: <?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($match['prize_pool']) ?></h5>
                                    <?php endif; ?>

                                    <?php if ($match['prize_distribution']): ?>
                                        <div class="prize-distribution">
                                            <p class="text-muted mb-1">Prize Distribution:</p>
                                            <?php
                                                foreach ($prizeAmounts as $position => $amount) {
                                                    $currency = $match['website_currency_type'] 
                                                        ? ucfirst($match['website_currency_type'])
                                                        : ($match['prize_type'] === 'USD' ? '$' : '₹');
                                                    
                                                    echo "<p class='mb-0'>{$position}st Place: " . number_format($amount) . " {$currency}</p>";
                                                }
                                            ?>
                                        </div>
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

                    <!-- Add search box -->
                    <div class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search by username, game UID, or in-game name...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Participants Table -->
                    <div class="table-responsive">
                        <table class="table table-hover" id="participantsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Player</th>
                                    <th>Contact</th>
                                    <th>Game UID</th>
                                    <th>In-Game Name</th>
                                    <th>Kills</th>
                                    <th>Coins Earned</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): 
                                    $trophyColor = '';
                                    $trophyTitle = '';
                                    $position = $participant['winner_position'];
                                    $coinsEarned = $participant['total_kills'] * $match['coins_per_kill'];
                                    
                                    if ($match['status'] === 'completed' && $position && $position <= $numWinners) {
                                        switch($position) {
                                            case 1:
                                                $trophyColor = 'gold';
                                                $trophyTitle = '1st Place';
                                                break;
                                            case 2:
                                                $trophyColor = 'silver';
                                                $trophyTitle = '2nd Place';
                                                break;
                                            case 3:
                                                $trophyColor = '#CD7F32';
                                                $trophyTitle = '3rd Place';
                                                break;
                                            default:
                                                $trophyColor = '#2196F3';
                                                $trophyTitle = $position . 'th Place';
                                        }
                                    }
                                ?>
                                <tr <?= $position && $position <= $numWinners ? 'class="table-success"' : '' ?>>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?= htmlspecialchars($participant['username']) ?>
                                        <?php if ($match['status'] === 'completed' && $position && $position <= $numWinners): ?>
                                            <i class="bi bi-trophy-fill" style="color: <?= $trophyColor ?>;" title="<?= $trophyTitle ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($participant['email']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($participant['phone']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($participant['game_uid']) ?></td>
                                    <td><?= htmlspecialchars($participant['ingame_name']) ?></td>
                                    <td><?= $participant['total_kills'] ?></td>
                                    <td><?= $coinsEarned ?> Coins</td>
                                    <td>
                                        <?php if ($position): ?>
                                            <span class="badge bg-<?= $position <= $numWinners ? 'success' : 'secondary' ?>">
                                                <?= $position ?><?= getOrdinalSuffix($position) ?> Place
                                            </span>
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

.badge {
    padding: 0.5em 1em;
}

.bi-trophy-fill {
    margin-left: 5px;
    font-size: 1.1em;
}

.table-success {
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.table-success:hover {
    background-color: rgba(40, 167, 69, 0.15) !important;
}
</style>

<?php include '../includes/admin-footer.php'; ?>

<?php
// Add this helper function at the bottom of the file
function getOrdinalSuffix($number) {
    if (!in_array(($number % 100), array(11,12,13))) {
        switch ($number % 10) {
            case 1:  return 'st';
            case 2:  return 'nd';
            case 3:  return 'rd';
        }
    }
    return 'th';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('participantsTable');
    const rows = table.getElementsByTagName('tr');

    searchInput.addEventListener('keyup', function() {
        const searchTerm = searchInput.value.toLowerCase();

        // Start from index 1 to skip the header row
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const username = row.cells[1].textContent.toLowerCase();
            const gameUid = row.cells[3].textContent.toLowerCase();
            const inGameName = row.cells[4].textContent.toLowerCase();

            if (username.includes(searchTerm) || 
                gameUid.includes(searchTerm) || 
                inGameName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
});
</script> 