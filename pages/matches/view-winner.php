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
                          WHERE m.id = ? AND m.status = 'completed'");
$matchStmt->execute([$match_id]);
$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    header('Location: my-matches.php');
    exit();
}

// Get all winners information (supports multiple positions)
$stmt = $db->prepare("SELECT 
                        u.username, 
                        t.name as team_name,
                        mp.position,
                        COALESCE(uk.kills, 0) as kills,
                        COALESCE(uk.kills * m.coins_per_kill + 
                            CASE 
                                WHEN mp.position = 1 THEN m.prize_pool * 0.5
                                WHEN mp.position = 2 THEN m.prize_pool * 0.3
                                WHEN mp.position = 3 THEN m.prize_pool * 0.2
                                ELSE 0
                            END, 0) as coins_earned
                    FROM match_participants mp
                    JOIN users u ON mp.user_id = u.id
                    JOIN matches m ON m.id = mp.match_id
                    LEFT JOIN teams t ON mp.team_id = t.id
                    LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                    WHERE mp.match_id = ? 
                    AND m.status = 'completed'
                    AND mp.position IS NOT NULL
                    ORDER BY mp.position ASC");
$stmt->execute([$match_id]);
$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <div class="prize-pool">
                    <i class="bi bi-trophy-fill"></i>
                    <span>Prize Pool: <?= number_format($match['prize_pool']) ?> Coins</span>
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
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="winner-details">
                            <div class="position-badge">
                                <?= $position ?><sup><?= $position === 1 ? 'st' : ($position === 2 ? 'nd' : 'rd') ?></sup>
                            </div>
                            <h3><?= htmlspecialchars($winner['username']) ?></h3>
                            <?php if ($winner['team_name']): ?>
                                <p class="team-name"><?= htmlspecialchars($winner['team_name']) ?></p>
                            <?php endif; ?>
                            <div class="stats">
                                <div class="stat-item">
                                    <i class="bi bi-star-fill"></i>
                                    <span><?= $winner['kills'] ?> Kills</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-coin"></i>
                                    <span><?= number_format($winner['coins_earned']) ?> Coins</span>
                                </div>
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
                                        <p class="team-name"><?= htmlspecialchars($winner['team_name']) ?></p>
                                    <?php endif; ?>
                                    <div class="stats">
                                        <div class="stat-item">
                                            <i class="bi bi-star-fill"></i>
                                            <span><?= $winner['kills'] ?> Kills</span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="bi bi-coin"></i>
                                            <span><?= number_format($winner['coins_earned']) ?> Coins</span>
                                        </div>
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
    padding: 2rem 0;
    background: linear-gradient(135deg, #1a1f25 0%, #2b3240 100%);
    min-height: calc(100vh - 60px);
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
    margin-bottom: 1rem;
    transition: color 0.3s;
    font-size: 1.1rem;
}

.back-link:hover {
    color: #ffd700;
}

.back-link i {
    margin-right: 0.5rem;
}

.match-info {
    background: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 15px;
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
}

.game-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.game-icon {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.game-info h2 {
    margin: 0;
    font-size: 2rem;
    font-weight: 600;
    color: #fff;
}

.match-date {
    margin: 0.5rem 0 0;
    color: rgba(255,255,255,0.7);
    font-size: 1rem;
}

.prize-pool {
    background: rgba(255, 215, 0, 0.2);
    padding: 1rem 2rem;
    border-radius: 50px;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    font-size: 1.2rem;
    font-weight: 600;
    color: #ffd700;
}

.winners-podium {
    display: grid;
    grid-template-areas: 
        ". first ."
        "second first third"
        "additional additional additional";
    gap: 2rem;
    padding: 2rem 0;
    justify-content: center;
    align-items: flex-end;
}

.podium-spot {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease;
    animation: fadeIn 0.5s ease-out;
}

.podium-spot:hover {
    transform: translateY(-10px);
}

.position-1 {
    grid-area: first;
    margin-bottom: 4rem;
    background: linear-gradient(135deg, rgba(255,215,0,0.2) 0%, rgba(255,215,0,0.1) 100%);
}

.position-2 {
    grid-area: second;
    margin-bottom: 2rem;
    background: linear-gradient(135deg, rgba(192,192,192,0.2) 0%, rgba(192,192,192,0.1) 100%);
}

.position-3 {
    grid-area: third;
    margin-bottom: 2rem;
    background: linear-gradient(135deg, rgba(205,127,50,0.2) 0%, rgba(205,127,50,0.1) 100%);
}

.winner-avatar {
    position: relative;
    margin-bottom: 1.5rem;
}

.winner-avatar i {
    font-size: 5rem;
    color: rgba(255,255,255,0.9);
}

.crown {
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 2rem;
    opacity: 0;
    transition: all 0.3s ease;
}

.crown.show {
    opacity: 1;
    top: -40px;
}

.position-badge {
    background: #ffd700;
    color: #000;
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 1rem;
    font-weight: 600;
    font-size: 1.2rem;
}

.winner-details h3 {
    font-size: 1.8rem;
    margin: 0 0 0.5rem;
    color: #fff;
}

.team-name {
    color: rgba(255,255,255,0.7);
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.stats {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255,255,255,0.9);
}

.stat-item i {
    color: #ffd700;
    font-size: 1.2rem;
}

.additional-winners {
    grid-area: additional;
    margin-top: 2rem;
}

.additional-winners h3 {
    text-align: center;
    margin-bottom: 2rem;
    color: rgba(255,255,255,0.9);
}

.winners-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.winner-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease;
}

.winner-card:hover {
    transform: translateY(-5px);
}

.no-winner {
    text-align: center;
    padding: 4rem 0;
    color: rgba(255,255,255,0.7);
}

.no-winner i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
}

.no-winner p {
    font-size: 1.5rem;
    margin: 0;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 992px) {
    .winners-podium {
        grid-template-areas: 
            "first"
            "second"
            "third"
            "additional";
        gap: 1.5rem;
    }

    .position-1, .position-2, .position-3 {
        margin-bottom: 0;
    }

    .match-info {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .game-info {
        flex-direction: column;
    }

    .prize-pool {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .winner-page {
        padding: 1rem;
    }
    
    .game-icon {
        width: 60px;
        height: 60px;
    }
    
    .game-info h2 {
        font-size: 1.5rem;
    }
    
    .stats {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?> 