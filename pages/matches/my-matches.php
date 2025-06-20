<?php
// Start session and check login status first
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Now include the header
include '../../includes/header.php';

// Fetch user's joined matches
$query = "SELECT m.*, g.name as game_name, g.image_url as game_image,
          DATE(m.match_date) as match_date,
          TIME(m.match_date) as match_time,
          mp.status as participation_status,
          mp.join_date,
          m.prize_distribution,
          m.coins_per_kill,
          m.website_currency_type,
          m.website_currency_amount
          FROM matches m 
          JOIN games g ON m.game_id = g.id 
          JOIN match_participants mp ON m.id = mp.match_id
          WHERE mp.user_id = :user_id
          ORDER BY m.match_date ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../../assets/css/matches/matches.css">

<div class="matches-section">
    <div class="matches-container">
        <div class="section-header">
            <h2 class="section-title">My Matches</h2>
            <a href="index.php" class="my-matches-link">
                <i class="bi bi-arrow-left"></i> Back to All Matches
            </a>
        </div>

        <div class="matches-grid">
            <?php if (empty($matches)): ?>
                <div class="no-matches">
                    <i class="bi bi-controller"></i>
                    <p>You haven't joined any matches yet.</p>
                    <a href="index.php" class="btn-join btn-primary">Browse Available Matches</a>
                </div>
            <?php else: ?>
                <?php foreach ($matches as $match): ?>
                    <div class="match-card">
                        <div class="match-header">
                            <div class="game-info">
                                <img src="<?= htmlspecialchars($match['game_image']) ?>" 
                                     alt="<?= htmlspecialchars($match['game_name']) ?>" 
                                     class="game-icon">
                                <div>
                                    <h3 class="game-name"><?= htmlspecialchars($match['game_name']) ?></h3>
                                </div>
                            </div>
                            <span class="match-type">
                                <i class="bi bi-people"></i> <?= ucfirst(htmlspecialchars($match['match_type'])) ?>
                            </span>
                        </div>
                        
                        <div class="match-info">
                            <div class="info-grid">
                                <div class="info-item">
                                    <i class="bi bi-calendar"></i>
                                    <?= date('M j, Y', strtotime($match['match_date'])) ?>
                                </div>
                                <div class="info-item">
                                    <i class="bi bi-clock"></i>
                                    <?= date('g:i A', strtotime($match['match_time'])) ?>
                                </div>
                                <div class="entry-fee">
                                    <i class="bi bi-ticket"></i>
                                    <?php if ($match['entry_type'] === 'free'): ?>
                                        Free Entry
                                    <?php else: ?>
                                        Entry: <?= number_format($match['entry_fee']) ?> <?= ucfirst($match['entry_type']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="prize-pool">
                                    <i class="bi bi-trophy"></i>
                                    <?php if (!empty($match['website_currency_type'])): ?>
                                        Prize Pool: <?= number_format($match['website_currency_amount']) ?> <?= ucfirst($match['website_currency_type']) ?>
                                    <?php elseif ($match['prize_pool'] > 0): ?>
                                        Prize Pool: <?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($match['prize_pool']) ?>
                                    <?php else: ?>
                                        Prize Pool: Not Set
                                    <?php endif; ?>
                                    <?php if ($match['prize_distribution']): ?>
                                        <div class="prize-distribution">
                                            <i class="bi bi-award"></i>
                                            <?php
                                                switch($match['prize_distribution']) {
                                                    case 'single':
                                                        echo 'Winner Takes All';
                                                        break;
                                                    case 'top3':
                                                        echo 'Top 3 Winners';
                                                        break;
                                                    case 'top5':
                                                        echo 'Top 5 Winners';
                                                        break;
                                                }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($match['coins_per_kill'] > 0): ?>
                                        <div class="coins-per-kill">
                                            <i class="bi bi-lightning"></i>
                                            <?= number_format($match['coins_per_kill']) ?> Coins per Kill
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="match-status">
                                <span class="status-badge status-<?= strtolower($match['participation_status']) ?>">
                                    <i class="bi bi-circle-fill"></i>
                                    <?= ucfirst($match['participation_status']) ?>
                                </span>
                            </div>

                            <?php if ($match['status'] === 'in_progress'): ?>
                                <div class="room-details">
                                    <div class="room-info">
                                        <i class="bi bi-door-open"></i>
                                        Room ID: <strong><?= htmlspecialchars($match['room_code']) ?></strong>
                                    </div>
                                    <div class="room-info">
                                        <i class="bi bi-key"></i>
                                        Password: <strong><?= htmlspecialchars($match['room_password']) ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="join-info">
                                <i class="bi bi-calendar-check"></i>
                                Joined: <?= date('M j, Y g:i A', strtotime($match['join_date'])) ?>
                            </div>
                        </div>

                        <?php if ($match['status'] === 'upcoming' || $match['status'] === 'in_progress'): ?>
                            <div class="match-actions">
                                <a href="view-participants.php?match_id=<?= $match['id'] ?>" class="btn-join btn-info">
                                    <i class="bi bi-people"></i> View Participants
                                </a>
                            </div>
                        <?php elseif ($match['status'] === 'completed'): ?>
                            <div class="match-actions">
                                <a href="view-winner.php?match_id=<?= $match['id'] ?>" class="btn-join btn-success">
                                    <i class="bi bi-trophy"></i> View Winner
                                </a>
                                <a href="view-participants.php?match_id=<?= $match['id'] ?>" class="btn-join btn-info">
                                    <i class="bi bi-people"></i> View Participants
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>