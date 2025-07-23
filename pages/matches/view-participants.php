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
$stmt = $db->prepare("SELECT 
                            u.username, 
                            ug.game_username,
                            ug.game_uid,
                            COALESCE(uk.kills, 0) as kills,
                            COALESCE(uk.kills * m.coins_per_kill, 0) as coins_earned
                     FROM match_participants mp
                     JOIN users u ON mp.user_id = u.id
                     JOIN matches m ON mp.match_id = m.id
                     LEFT JOIN user_games ug ON ug.user_id = u.id AND ug.game_name = m.game_id
                     LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                     WHERE mp.match_id = ?
                     ORDER BY uk.kills DESC, u.username ASC");
$stmt->execute([$match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/matches/view-participants.css">

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
                        <th>Game UID</th>
                        <th>In-Game Name</th>
                        <th>Kills</th>
                        <th>Coins Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="6" class="no-data">No participants found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($participants as $index => $participant): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($participant['username']) ?></td>
                                <td><?= htmlspecialchars($participant['game_uid']) ?></td>
                                <td><?= htmlspecialchars($participant['game_username']) ?></td>
                                <td><?= $participant['kills'] ?></td>
                                <td><?= $participant['coins_earned'] ?> Coins</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 