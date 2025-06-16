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
    SELECT m.*, g.name as game_name, g.image_url as game_image,
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
        $stmt = $db->prepare("INSERT INTO match_participants (match_id, user_id, join_date) VALUES (?, ?, NOW())");
        $stmt->execute([$match_id, $user_id]);
        
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
                    </div>
                    <div class="info-item">
                        <i class="bi bi-wallet2"></i>
                        Your Balance: <?= number_format($balance) ?> <?= ucfirst($match['entry_type']) ?>
                    </div>
                </div>

                <?php if ($balance < $match['entry_fee']): ?>
                    <div class="error-message">
                        <i class="bi bi-exclamation-circle"></i>
                        Insufficient <?= $match['entry_type'] ?>! You need <?= number_format($match['entry_fee']) ?> <?= $match['entry_type'] ?> to join this match.
                    </div>
                <?php else: ?>
                    <form method="POST" class="match-actions">
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