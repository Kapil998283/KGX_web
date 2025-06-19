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

// Get match ID from URL
$match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$match_id) {
    $_SESSION['error'] = "Invalid match ID!";
    header("Location: index.php");
    exit();
}

// Get match details first
$stmt = $db->prepare("
    SELECT m.*, g.name as game_name, g.image_url as game_image, g.id as game_id,
    DATE(m.match_date) as match_date,
    TIME(m.match_date) as match_time,
    m.website_currency_type,
    m.website_currency_amount
    FROM matches m 
    JOIN games g ON m.game_id = g.id 
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    $_SESSION['error'] = "Match not found!";
    header("Location: index.php");
    exit();
}

// Map game names from games table to user_games table format
$game_name_mapping = [
    'BGMI' => 'BGMI',
    'PUBG' => 'PUBG',
    'Free Fire' => 'FREE FIRE',
    'Call of Duty Mobile' => 'COD'
];

// Get the mapped game name
$mapped_game_name = isset($game_name_mapping[$match['game_name']]) ? $game_name_mapping[$match['game_name']] : $match['game_name'];

// Check if user has game profile for this game
$stmt = $db->prepare("
    SELECT * FROM user_games 
    WHERE user_id = ? AND game_name = ?
");
$stmt->execute([$user_id, $mapped_game_name]);
$game_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// If no game profile, redirect to game profile page
if (!$game_profile) {
    $_SESSION['error'] = "You need to set up your " . $match['game_name'] . " game profile before joining this match!";
    $_SESSION['redirect_after_profile'] = $_SERVER['REQUEST_URI'];
    header("Location: /KGX/pages/dashboard/game-profile.php?game=" . urlencode($match['game_name']));
    exit();
}

// Check if user has already joined
$stmt = $db->prepare("SELECT COUNT(*) FROM match_participants WHERE match_id = ? AND user_id = ?");
$stmt->execute([$match_id, $user_id]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['error'] = "You have already joined this match!";
    header("Location: index.php");
    exit();
}

// Check if match is full
$stmt = $db->prepare("SELECT COUNT(*) FROM match_participants WHERE match_id = ?");
$stmt->execute([$match_id]);
$current_participants = $stmt->fetchColumn();

if ($current_participants >= $match['max_participants']) {
    $_SESSION['error'] = "Match is full!";
    header("Location: index.php");
    exit();
}

// Get user's balance
if ($match['entry_type'] === 'coins') {
    $stmt = $db->prepare("SELECT coins FROM user_coins WHERE user_id = ?");
} else {
    $stmt = $db->prepare("SELECT tickets FROM user_tickets WHERE user_id = ?");
}
$stmt->execute([$user_id]);
$user_balance = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $match['entry_type'] === 'coins' ? ($user_balance['coins'] ?? 0) : ($user_balance['tickets'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Check balance again
        if ($balance < $match['entry_fee']) {
            throw new Exception("Insufficient " . $match['entry_type'] . "!");
        }
        
        // Deduct entry fee
        if ($match['entry_type'] === 'coins') {
            $stmt = $db->prepare("UPDATE user_coins SET coins = coins - ? WHERE user_id = ?");
        } else {
            $stmt = $db->prepare("UPDATE user_tickets SET tickets = tickets - ? WHERE user_id = ?");
        }
        $stmt->execute([$match['entry_fee'], $user_id]);
        
        // Add user to match participants
        $stmt = $db->prepare("
            INSERT INTO match_participants (
                match_id, 
                user_id, 
                join_date,
                in_game_name,
                game_uid,
                experience_level
            ) VALUES (
                ?, ?, NOW(), ?, ?, ?
            )
        ");
        $stmt->execute([
            $match_id, 
            $user_id, 
            $game_profile['game_username'] ?? 'Unknown',
            $game_profile['game_uid'] ?? 'Unknown',
            'Experienced'
        ]);
        
        // Create notification
        $notificationMessage = "You have successfully joined the {$match['game_name']} {$match['match_type']} match";
        $stmt = $db->prepare("
            INSERT INTO notifications (
                user_id, 
                type, 
                message, 
                related_id, 
                related_type,
                created_at
            ) VALUES (
                ?, 
                'match_joined', 
                ?, 
                ?, 
                'match',
                NOW()
            )
        ");
        $stmt->execute([$user_id, $notificationMessage, $match_id]);
        
        $db->commit();
        $_SESSION['success'] = "Successfully joined the match!";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error joining match: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Now include the header and start output
include '../../includes/header.php';
?>

<!-- Link to external CSS file -->
<link rel="stylesheet" href="../../assets/css/matches/matches.css">

<style>
/* Add this at the end of your existing styles */
.prize-distribution {
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 10px;
    margin-top: 10px;
}

.prize-tier {
    padding: 4px 0;
    color: #ffd700;
    font-size: 0.9em;
}

.prize-tier:nth-child(2) {
    color: #c0c0c0;
}

.prize-tier:nth-child(3) {
    color: #cd7f32;
}

.prize-tier i {
    margin-right: 5px;
}

.coins-per-kill {
    color: #4caf50;
    background-color: rgba(76, 175, 80, 0.1);
    border-radius: 8px;
    padding: 8px;
    font-size: 0.9em;
}

.coins-per-kill i {
    color: #ffd700;
    margin-right: 5px;
}

/* Add these styles for game profile section */
.game-profile-info {
    background: rgba(37, 211, 102, 0.1);
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.game-profile-info h4 {
    color: #25d366;
    margin-bottom: 15px;
    font-size: 1.1em;
}

.profile-details {
    display: grid;
    gap: 12px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}

.detail-item i {
    color: #25d366;
    font-size: 1.1em;
}

.detail-item span {
    font-size: 0.95em;
}
</style>

<div class="matches-section">
    <div class="matches-container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="section-header">
            <h2 class="section-title">Join Match</h2>
            <a href="index.php" class="my-matches-link">
                <i class="bi bi-arrow-left"></i> Back to Matches
            </a>
        </div>
        
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
                        <i class="bi bi-<?= $match['entry_type'] === 'coins' ? 'coin' : 'ticket' ?>"></i>
                        <?php if ($match['entry_type'] === 'free'): ?>
                            Free Entry
                        <?php else: ?>
                            Entry: <?= number_format($match['entry_fee']) ?> <?= ucfirst($match['entry_type']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="prize-pool">
                        <i class="bi bi-trophy"></i>
                        Prize Pool: 
                        <?php if ($match['website_currency_type'] && $match['website_currency_amount'] > 0): ?>
                            <?= number_format($match['website_currency_amount']) ?> <?= ucfirst($match['website_currency_type']) ?>
                        <?php else: ?>
                            <?= $match['prize_type'] === 'USD' ? '$' : '₹' ?><?= number_format($match['prize_pool']) ?>
                        <?php endif; ?>

                        <?php if ($match['prize_distribution']): ?>
                            <div class="prize-distribution mt-2">
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
                                            echo "<div class='prize-tier'><i class='bi bi-award'></i> {$position}st Place: " . number_format($amount) . " {$currency}</div>";
                                        } else {
                                            echo "<div class='prize-tier'><i class='bi bi-award'></i> {$position}st Place: {$currency}" . number_format($amount, 2) . "</div>";
                                        }
                                    }
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($match['coins_per_kill'] > 0): ?>
                            <div class="coins-per-kill mt-2">
                                <i class="bi bi-star"></i> <?= number_format($match['coins_per_kill']) ?> Coins per Kill
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-wallet2"></i>
                        Your Balance: <?= number_format($balance) ?> <?= ucfirst($match['entry_type']) ?>
                    </div>

                    <!-- Add Game Profile Info -->
                    <div class="game-profile-info">
                        <h4>Your Game Profile</h4>
                        <div class="profile-details">
                            <div class="detail-item">
                                <i class="bi bi-person-badge"></i>
                                <span>In-Game Name: <?= htmlspecialchars($game_profile['game_username'] ?? 'Not set') ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-fingerprint"></i>
                                <span>Game UID: <?= htmlspecialchars($game_profile['game_uid'] ?? 'Not set') ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-star"></i>
                                <span>Experience Level: <?= htmlspecialchars('Experienced') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($balance < $match['entry_fee']): ?>
                    <div class="error-message">
                        <i class="bi bi-exclamation-circle"></i>
                        Insufficient <?= $match['entry_type'] ?>! You need <?= number_format($match['entry_fee']) ?> <?= $match['entry_type'] ?> to join this match.
                    </div>
                <?php else: ?>
                    <form method="POST" class="match-actions">
                        <input type="hidden" name="game_uid" value="<?= htmlspecialchars($game_profile['game_uid'] ?? '') ?>">
                        <input type="hidden" name="in_game_name" value="<?= htmlspecialchars($game_profile['game_username'] ?? '') ?>">
                        <button type="submit" class="btn-join btn-primary">
                            <i class="bi bi-plus-circle"></i>
                            Confirm Join (<?= number_format($match['entry_fee']) ?> <?= ucfirst($match['entry_type']) ?>)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>