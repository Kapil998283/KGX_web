<?php
require_once '../../config/database.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Debug: Print match ID
error_log("Viewing match ID: " . $match_id);

// Get match details
$matchStmt = $db->prepare("SELECT m.*, g.name as game_name, g.image_url as game_image,
                          (SELECT COUNT(*) FROM match_participants mp WHERE mp.match_id = m.id AND mp.position IS NOT NULL) as winners_count
                          FROM matches m 
                          LEFT JOIN games g ON m.game_id = g.id 
                          WHERE m.id = ?");
$matchStmt->execute([$match_id]);
$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

// Debug output
error_log("Match query result: " . print_r($match, true));

if (!$match) {
    error_log("Match not found");
    header('Location: my-matches.php');
    exit();
}

if (!$match['game_name']) {
    error_log("Game not found for match");
    $match['game_name'] = 'Unknown Game';
    $match['game_image'] = '';
}

// Get all winners information (supports multiple positions)
$stmt = $db->prepare("SELECT 
                        u.username, 
                        t.name as team_name,
                        mp.position,
                        COALESCE(uk.kills, 0) as kills,
                        (COALESCE(uk.kills, 0) * m.coins_per_kill) as kill_coins,
                        CASE 
                            WHEN m.website_currency_type IS NOT NULL THEN
                                CASE 
                                    WHEN m.prize_distribution = 'top3' THEN
                                        CASE 
                                            WHEN mp.position = 1 THEN m.website_currency_amount * 0.6
                                            WHEN mp.position = 2 THEN m.website_currency_amount * 0.3
                                            WHEN mp.position = 3 THEN m.website_currency_amount * 0.1
                                            ELSE 0
                                        END
                                    WHEN m.prize_distribution = 'top5' THEN
                                        CASE 
                                            WHEN mp.position = 1 THEN m.website_currency_amount * 0.5
                                            WHEN mp.position = 2 THEN m.website_currency_amount * 0.25
                                            WHEN mp.position = 3 THEN m.website_currency_amount * 0.15
                                            WHEN mp.position = 4 THEN m.website_currency_amount * 0.07
                                            WHEN mp.position = 5 THEN m.website_currency_amount * 0.03
                                            ELSE 0
                                        END
                                    ELSE
                                        CASE 
                                            WHEN mp.position = 1 THEN m.website_currency_amount
                                            ELSE 0
                                        END
                                END
                            ELSE
                                CASE 
                                    WHEN m.prize_distribution = 'top3' THEN
                                        CASE 
                                            WHEN mp.position = 1 THEN m.prize_pool * 0.6
                                            WHEN mp.position = 2 THEN m.prize_pool * 0.3
                                            WHEN mp.position = 3 THEN m.prize_pool * 0.1
                                            ELSE 0
                                        END
                                    WHEN m.prize_distribution = 'top5' THEN
                                        CASE 
                                            WHEN mp.position = 1 THEN m.prize_pool * 0.5
                                            WHEN mp.position = 2 THEN m.prize_pool * 0.25
                                            WHEN mp.position = 3 THEN m.prize_pool * 0.15
                                            WHEN mp.position = 4 THEN m.prize_pool * 0.07
                                            WHEN mp.position = 5 THEN m.prize_pool * 0.03
                                            ELSE 0
                                        END
                                    ELSE
                                        CASE 
                                            WHEN mp.position = 1 THEN m.prize_pool
                                            ELSE 0
                                        END
                                END
                        END as position_prize,
                        m.website_currency_type,
                        m.prize_type
                    FROM match_participants mp
                    JOIN users u ON mp.user_id = u.id
                    JOIN matches m ON m.id = mp.match_id
                    LEFT JOIN teams t ON mp.team_id = t.id
                    LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                    WHERE mp.match_id = ? 
                    AND mp.position IS NOT NULL
                    ORDER BY mp.position ASC");
$stmt->execute([$match_id]);
$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug output
error_log("Winners query result: " . print_r($winners, true));

include '../../includes/header.php';
?>

<div class="winner-page">
    <div class="container">
        <div class="page-header">
            <a href="my-matches.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to My Matches
            </a>
            <div class="match-info">
                <div class="game-info">
                    <?php if ($match['game_image']): ?>
                    <img src="<?= htmlspecialchars($match['game_image']) ?>" 
                         alt="<?= htmlspecialchars($match['game_name']) ?>" 
                         class="game-icon">
                    <?php endif; ?>
                    <div>
                        <h2><?= htmlspecialchars($match['game_name']) ?></h2>
                        <p class="match-date">
                            <i class="bi bi-calendar-event"></i>
                            <?= date('F j, Y g:i A', strtotime($match['match_date'])) ?>
                        </p>
                        <p class="match-status">
                            <i class="bi bi-circle-fill status-<?= strtolower($match['status']) ?>"></i>
                            <?= htmlspecialchars(ucfirst($match['status'])) ?>
                        </p>
                    </div>
                </div>
                <div class="prize-pool">
                    <div class="prize-main">
                        <i class="bi bi-trophy-fill"></i>
                        <?php if ($match['website_currency_type'] && $match['website_currency_amount'] > 0): ?>
                            <span>Prize Pool: <?= number_format($match['website_currency_amount']) ?> <?= ucfirst($match['website_currency_type']) ?></span>
                        <?php else: ?>
                            <span>Prize Pool: <?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($match['prize_pool']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($match['coins_per_kill'] > 0): ?>
                    <div class="prize-kill">
                        <i class="bi bi-star-fill"></i>
                        <small>+<?= number_format($match['coins_per_kill']) ?> Coins per Kill</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($winners): ?>
            <div class="winners-podium">
                <?php
                $podiumOrder = [2, 1, 3]; // Display order for top 3
                $displayedPositions = [];
                
                // First display top 3 in podium style
                foreach ($podiumOrder as $position):
                    $winner = array_filter($winners, function($w) use ($position) {
                        return $w['position'] == $position;
                    });
                    $winner = reset($winner);
                    if ($winner):
                        $displayedPositions[] = $position;
                ?>
                    <div class="podium-spot position-<?= $position ?>" data-position="<?= $position ?>">
                        <div class="winner-avatar">
                            <div class="crown <?= $position === 1 ? 'show' : '' ?>">👑</div>
                            <?php
                            // Get winner's profile image using the same logic as header
                            $winner_profile_image = './assets/images/guest-icon.png'; // Default Guest/Fallback Image Path

                            // Get user's specific setting
                            $stmt = $db->prepare("SELECT profile_image FROM users WHERE username = ?");
                            $stmt->execute([$winner['username']]);
                            $winner_data = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($winner_data && !empty($winner_data['profile_image'])) {
                                $winner_profile_image = $winner_data['profile_image'];
                            } else {
                                // Check for admin-defined default image
                                $stmt = $db->prepare("SELECT image_path FROM profile_images WHERE is_default = 1 AND is_active = 1 LIMIT 1");
                                $stmt->execute();
                                $default_image_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($default_image_data) {
                                    $winner_profile_image = $default_image_data['image_path'];
                                }
                            }

                            // Adjust path for local assets if needed
                            if (strpos($winner_profile_image, '/assets/') === 0 && strpos($winner_profile_image, '.') !== 0) {
                                $winner_profile_image = '.' . $winner_profile_image;
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($winner_profile_image); ?>" alt="<?= htmlspecialchars($winner['username']) ?>" class="profile-image">
                        </div>
                        <div class="winner-details">
                            <div class="position-badge">
                                <?= $position ?><sup><?= $position === 1 ? 'st' : ($position === 2 ? 'nd' : 'rd') ?></sup>
                            </div>
                            <h3><?= htmlspecialchars($winner['username']) ?></h3>
                            <?php if ($winner['team_name']): ?>
                                <p class="team-name"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($winner['team_name']) ?></p>
                            <?php endif; ?>
                            <div class="stats">
                                <div class="stat-item">
                                    <i class="bi bi-star-fill"></i>
                                    <span><?= $winner['kills'] ?> Kills</span>
                                </div>
                                <?php if ($winner['kill_coins'] > 0): ?>
                                <div class="stat-item">
                                    <i class="bi bi-coin"></i>
                                    <span><?= number_format($winner['kill_coins']) ?> Coins</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($winner['position_prize'] > 0): ?>
                                <div class="stat-item">
                                    <i class="bi bi-trophy-fill"></i>
                                    <?php if ($match['website_currency_type']): ?>
                                        <span><?= number_format($winner['position_prize']) ?> <?= ucfirst($match['website_currency_type']) ?></span>
                                    <?php else: ?>
                                        <span><?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($winner['position_prize']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php
                    endif;
                endforeach;
                
                // Display remaining positions in a grid
                if (count($winners) > 3):
                ?>
                    <div class="additional-winners">
                        <h3>Additional Winners</h3>
                        <div class="winners-grid">
                            <?php foreach ($winners as $winner):
                                if (!in_array($winner['position'], $displayedPositions)):
                            ?>
                                <div class="winner-card">
                                    <div class="position-badge">
                                        <?= $winner['position'] ?><sup>th</sup>
                                    </div>
                                    <h4><?= htmlspecialchars($winner['username']) ?></h4>
                                    <?php if ($winner['team_name']): ?>
                                        <p class="team-name"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($winner['team_name']) ?></p>
                                    <?php endif; ?>
                                    <div class="stats">
                                        <div class="stat-item">
                                            <i class="bi bi-star-fill"></i>
                                            <span><?= $winner['kills'] ?> Kills</span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="bi bi-coin"></i>
                                            <span><?= number_format($winner['kill_coins']) ?> Coins</span>
                                        </div>
                                        <?php if ($winner['position_prize'] > 0): ?>
                                        <div class="stat-item">
                                            <i class="bi bi-trophy-fill"></i>
                                            <span><?= number_format($winner['position_prize']) ?> <?= ucfirst($match['website_currency_type'] ?? 'Coins') ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-winner">
                <i class="bi bi-emoji-frown"></i>
                <p>No winners declared yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.winner-page {
    min-height: 100vh;
    padding: 2rem 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.8), rgba(0,0,0,0.95)), url('../../assets/images/section-wrapper-bg.png');
    background-size: cover;
    background-position: center;
    color: #fff;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.page-header {
    margin-bottom: 3rem;
}

.back-link {
    display: inline-flex;
    align-items: center;
    color: #fff;
    text-decoration: none;
    margin-bottom: 1.5rem;
    transition: color 0.3s;
}

.back-link:hover {
    color: var(--primary-color);
}

.back-link i {
    margin-right: 0.5rem;
}

.match-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    backdrop-filter: blur(10px);
}

.game-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.game-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    object-fit: cover;
}

.game-info h2 {
    margin: 0;
    font-size: 1.8rem;
    color: var(--primary-color);
}

.match-date, .match-status {
    margin: 0.3rem 0;
    font-size: 0.9rem;
    opacity: 0.8;
}

.prize-pool {
    text-align: right;
    padding: 1rem;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.prize-pool i {
    color: gold;
    margin-right: 0.5rem;
}

.prize-pool span {
    font-size: 1.2rem;
    font-weight: bold;
    color: gold;
}

.prize-pool small {
    display: block;
    margin-top: 0.5rem;
    color: #ffd700;
}

.winners-podium {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
    padding: 2rem;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.podium-spot {
    position: relative;
    width: 100%;
    max-width: 400px;
    padding: 2rem;
    background: linear-gradient(145deg, rgba(60, 60, 60, 0.4), rgba(30, 30, 30, 0.4));
    border-radius: 20px;
    text-align: center;
    transition: transform 0.3s;
}

.podium-spot:hover {
    transform: translateY(-5px);
}

.position-1 {
    background: linear-gradient(145deg, rgba(255, 215, 0, 0.2), rgba(218, 165, 32, 0.2));
    border: 1px solid rgba(255, 215, 0, 0.3);
}

.winner-avatar {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 1.5rem;
}

.winner-avatar .profile-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255, 255, 255, 0.2);
}

.crown {
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 2.5rem;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    opacity: 0;
    transition: opacity 0.3s, transform 0.3s;
}

.crown.show {
    opacity: 1;
    transform: translateX(-50%) scale(1.1);
}

.position-badge {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.position-1 .position-badge {
    background: linear-gradient(45deg, #ffd700, #daa520);
    color: #000;
}

.winner-details h3 {
    font-size: 1.8rem;
    margin: 0.5rem 0;
    color: #fff;
}

.team-name {
    color: var(--primary-color);
    font-size: 1rem;
    margin: 0.5rem 0;
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
}

.stat-item i {
    color: var(--primary-color);
}

.additional-winners {
    width: 100%;
    margin-top: 2rem;
}

.additional-winners h3 {
    text-align: center;
    margin-bottom: 2rem;
    color: var(--primary-color);
}

.winners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.winner-card {
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    text-align: center;
}

@media (max-width: 768px) {
    .match-info {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .game-info {
        flex-direction: column;
        text-align: center;
    }

    .prize-pool {
        text-align: center;
        width: 100%;
    }

    .podium-spot {
        padding: 1.5rem;
    }

    .winner-avatar {
        width: 120px;
        height: 120px;
    }

    .stats {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?> 