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
</style>

<?php include '../includes/admin-footer.php'; ?> 