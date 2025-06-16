<?php
require_once '../../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Get match ID from URL
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

if (!$match_id) {
    header('Location: my-matches.php');
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get match details
$matchStmt = $db->prepare("SELECT m.*, g.name as game_name, g.image_url as game_image 
                          FROM matches m 
                          JOIN games g ON m.game_id = g.id 
                          WHERE m.id = ?");
$matchStmt->execute([$match_id]);
$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    header('Location: my-matches.php');
    exit();
}

// Get winner information
$stmt = $db->prepare("SELECT u.username, t.name as team_name,
                            COALESCE(uk.kills, 0) as kills,
                            COALESCE(uk.kills * m.coins_per_kill, 0) as coins_earned
                     FROM matches m
                     JOIN teams t ON m.winner_id = t.id
                     JOIN match_participants mp ON mp.match_id = m.id AND mp.team_id = t.id
                     JOIN users u ON mp.user_id = u.id
                     LEFT JOIN user_kills uk ON uk.match_id = m.id AND uk.user_id = u.id
                     WHERE m.id = ? AND m.status = 'completed'
                     LIMIT 1");
$stmt->execute([$match_id]);
$winner = $stmt->fetch(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="winner-page">
    <div class="container">
        <div class="page-header">
            <a href="my-matches.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to My Matches
            </a>
            <h1>Match Winner</h1>
        </div>

        <div class="match-info">
            <div class="game-info">
                <img src="<?= htmlspecialchars($match['game_image']) ?>" 
                     alt="<?= htmlspecialchars($match['game_name']) ?>" 
                     class="game-icon">
                <div>
                    <h2><?= htmlspecialchars($match['game_name']) ?></h2>
                    <p class="match-date">
                        <?= date('F j, Y g:i A', strtotime($match['match_date'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="winner-card">
            <?php if ($winner): ?>
                <div class="winner-badge">
                    <i class="bi bi-trophy-fill"></i> Winner
                </div>
                <div class="winner-info">
                    <h3><?= htmlspecialchars($winner['username']) ?></h3>
                    <p class="team-name">Team: <?= htmlspecialchars($winner['team_name']) ?></p>
                    <div class="stats">
                        <div class="stat-item">
                            <i class="bi bi-star-fill"></i>
                            <span>Kills: <?= $winner['kills'] ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="bi bi-coin"></i>
                            <span>Coins Earned: <?= $winner['coins_earned'] ?></span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-winner">
                    <i class="bi bi-emoji-frown"></i>
                    <p>No winner declared yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.winner-page {
    padding: 2rem 0;
    background: #f8f9fa;
    min-height: calc(100vh - 60px);
}

.page-header {
    margin-bottom: 2rem;
}

.back-link {
    display: inline-flex;
    align-items: center;
    color: #666;
    text-decoration: none;
    margin-bottom: 1rem;
}

.back-link i {
    margin-right: 0.5rem;
}

.match-info {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.game-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.game-icon {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 10px;
}

.game-info h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.match-date {
    margin: 0.5rem 0 0;
    color: #666;
    font-size: 0.9rem;
}

.winner-card {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.winner-badge {
    display: inline-block;
    background: #ffd700;
    color: #000;
    padding: 0.5rem 1.5rem;
    border-radius: 20px;
    font-weight: 600;
    margin-bottom: 1.5rem;
}

.winner-badge i {
    margin-right: 0.5rem;
}

.winner-info h3 {
    font-size: 1.8rem;
    margin: 0 0 0.5rem;
    color: #333;
}

.team-name {
    color: #666;
    margin-bottom: 1.5rem;
}

.stats {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
}

.stat-item i {
    color: #ffd700;
    font-size: 1.2rem;
}

.no-winner {
    padding: 3rem 0;
    color: #666;
}

.no-winner i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.no-winner p {
    margin: 0;
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .winner-page {
        padding: 1rem;
    }
    
    .game-info {
        flex-direction: column;
        text-align: center;
    }
    
    .game-icon {
        width: 80px;
        height: 80px;
    }
    
    .stats {
        flex-direction: column;
        gap: 1rem;
    }
    
    .winner-card {
        padding: 1.5rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?> 