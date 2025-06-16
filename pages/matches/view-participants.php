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

// Get participants
$stmt = $db->prepare("SELECT u.username, 
                            COALESCE(uk.kills, 0) as kills,
                            COALESCE(uk.kills * m.coins_per_kill, 0) as coins_earned
                     FROM match_participants mp
                     JOIN users u ON mp.user_id = u.id
                     JOIN matches m ON mp.match_id = m.id
                     LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                     WHERE mp.match_id = ?
                     ORDER BY uk.kills DESC, u.username ASC");
$stmt->execute([$match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="participants-page">
    <div class="container">
        <div class="page-header">
            <a href="my-matches.php" class="back-link">
                <i class="bi bi-arrow-left"></i> Back to My Matches
            </a>
            <h1>Match Participants</h1>
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

        <div class="participants-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Player</th>
                        <th>Kills</th>
                        <th>Coins Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="4" class="no-data">No participants found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($participants as $index => $participant): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($participant['username']) ?></td>
                                <td><?= $participant['kills'] ?></td>
                                <td><?= $participant['coins_earned'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.participants-page {
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

.participants-table {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

tr:last-child td {
    border-bottom: none;
}

.no-data {
    text-align: center;
    color: #666;
    padding: 2rem;
}

@media (max-width: 768px) {
    .participants-page {
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
    
    th, td {
        padding: 0.75rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?> 