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
                        COALESCE(uk.kills, 0) as total_kills,
                        CASE 
                            WHEN m.winner_user_id = mp.user_id THEN 1
                            WHEN m.winner_id = mp.team_id THEN 1
                            ELSE 0
                        END as is_winner,
                        CASE
                            WHEN mr.position IS NOT NULL THEN mr.position
                            ELSE 999999 -- Large number to push NULL positions to the end
                        END as winner_position
                     FROM match_participants mp
                     JOIN users u ON mp.user_id = u.id
                     JOIN matches m ON m.id = mp.match_id
                     LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                     LEFT JOIN (
                        SELECT 
                            match_id,
                            user_id,
                            @row_number:=@row_number + 1 as position
                        FROM user_kills, (SELECT @row_number:=0) as r
                        WHERE match_id = ?
                        ORDER BY kills DESC
                     ) mr ON mr.match_id = mp.match_id AND mr.user_id = mp.user_id
                     WHERE mp.match_id = ?
                     ORDER BY winner_position ASC, uk.kills DESC, u.username ASC");
$stmt->execute([$match_id, $match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the number of winners based on prize distribution
$numWinners = 1;
if ($match['prize_distribution'] === 'top3') {
    $numWinners = 3;
} else if ($match['prize_distribution'] === 'top5') {
    $numWinners = 5;
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
                                                    $position = $index + 1;
                                                    $amount = $match['website_currency_type'] 
                                                        ? floor($match['website_currency_amount'] * $percentage / 100)
                                                        : round($match['prize_pool'] * $percentage / 100, 2);
                                                    
                                                    $currency = $match['website_currency_type'] 
                                                        ? ucfirst($match['website_currency_type'])
                                                        : ($match['prize_type'] === 'USD' ? '$' : '₹');
                                                    
                                                    if ($match['website_currency_type']) {
                                                        echo "<p class='mb-0'>{$position}st Place: " . number_format($amount) . " {$currency}</p>";
                                                    } else {
                                                        echo "<p class='mb-0'>{$position}st Place: {$currency}" . number_format($amount, 2) . "</p>";
                                                    }
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
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): 
                                    $trophyColor = '';
                                    $trophyTitle = '';
                                    if ($match['status'] === 'completed' && $participant['winner_position'] <= $numWinners) {
                                        switch($participant['winner_position']) {
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
                                                $trophyTitle = $participant['winner_position'] . 'th Place';
                                        }
                                    }
                                ?>
                                <tr <?= $participant['winner_position'] <= $numWinners ? 'class="table-success"' : '' ?>>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?= htmlspecialchars($participant['username']) ?>
                                        <?php if ($match['status'] === 'completed' && $participant['winner_position'] <= $numWinners): ?>
                                            <i class="bi bi-trophy-fill" style="color: <?= $trophyColor ?>;" title="<?= $trophyTitle ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($participant['email']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($participant['phone']) ?></small>
                                    </td>
                                    <td><?= $participant['total_kills'] ?></td>
                                    <td><?= $participant['total_kills'] * $match['coins_per_kill'] ?></td>
                                    <td>
                                        <?php if ($match['status'] === 'completed' && $participant['winner_position'] <= $numWinners): ?>
                                            <span class="badge bg-success"><?= $participant['winner_position'] ?></span>
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