<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';
require_once '../../includes/user-auth.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get game type from URL parameter and sanitize it
$game = isset($_GET['game']) ? filter_var($_GET['game'], FILTER_SANITIZE_STRING) : '';

// Get current user's wallet balance if logged in
$user_balance = 0;
$user_tickets = 0;
if (isset($_SESSION['user_id'])) {
    // Get coins balance
    $stmt = $db->prepare("SELECT COALESCE(coins, 0) as coins FROM user_coins WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $coins_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_balance = $coins_data['coins'] ?? 0;
    
    // Get tickets balance
    $stmt = $db->prepare("SELECT COALESCE(tickets, 0) as tickets FROM user_tickets WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $tickets_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_tickets = $tickets_data['tickets'] ?? 0;
}

// Fetch all active games for the filter - updated query with specific order
$games_stmt = $db->query("SELECT DISTINCT g.name, g.image_url 
                         FROM games g 
                         WHERE g.status = 'active' 
                         AND g.name IN ('BGMI', 'PUBG', 'Free Fire', 'COD')
                         ORDER BY FIELD(g.name, 'BGMI', 'PUBG', 'Free Fire', 'COD')");
$games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no games are found, let's insert them
if (empty($games)) {
    $default_games = [
        ['name' => 'BGMI', 'image_url' => '../../assets/images/games/bgmi.png'],
        ['name' => 'PUBG', 'image_url' => '../../assets/images/games/pubg.png'],
        ['name' => 'Free Fire', 'image_url' => '../../assets/images/games/freefire.png'],
        ['name' => 'COD', 'image_url' => '../../assets/images/games/cod.png']
    ];
    
    foreach ($default_games as $game) {
        $stmt = $db->prepare("INSERT INTO games (name, image_url, status) VALUES (?, ?, 'active')");
        $stmt->execute([$game['name'], $game['image_url']]);
    }
    
    // Fetch games again
    $games_stmt = $db->query("SELECT DISTINCT g.name, g.image_url 
                             FROM games g 
                             WHERE g.status = 'active' 
                             ORDER BY FIELD(g.name, 'BGMI', 'PUBG', 'Free Fire', 'COD')");
    $games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build the matches query with proper conditions
$query = "SELECT 
            m.*, 
            g.name as game_name, 
            g.image_url as game_image,
            DATE(m.match_date) as match_date,
            TIME(m.match_date) as match_time,
            t.name as tournament_name,
            m.prize_type,
            m.prize_distribution,
            m.coins_per_kill,
            m.website_currency_type,
            m.website_currency_amount,
            (SELECT COUNT(*) FROM match_participants WHERE match_id = m.id) as current_participants,
            CASE 
                WHEN m.status = 'completed' THEN 'Completed'
                WHEN m.status = 'in_progress' THEN 'In Progress'
                WHEN m.match_date < NOW() THEN 'Started'
                ELSE 'Upcoming'
            END as match_status,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM match_participants 
                    WHERE match_id = m.id AND user_id = :user_id
                ) THEN 1 
                ELSE 0 
            END as has_joined
          FROM matches m 
          JOIN games g ON m.game_id = g.id 
          LEFT JOIN tournaments t ON m.tournament_id = t.id
          WHERE m.status != 'cancelled'";

// Add game filter if specified
if (!empty($game)) {
    $query .= " AND UPPER(g.name) = UPPER(:game)";
}

// Order matches: Upcoming first, then In Progress, then Completed
$query .= " ORDER BY 
            CASE m.status 
                WHEN 'upcoming' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'completed' THEN 3
            END,
            m.match_date ASC";

$stmt = $db->prepare($query);
$stmt->bindValue(':user_id', $_SESSION['user_id'] ?? 0);
if (!empty($game)) {
    $stmt->bindValue(':game', $game);
}
$stmt->execute();
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Link to external CSS file -->
<link rel="stylesheet" href="../../assets/css/matches/matches.css">

<div class="matches-section">
    <div class="matches-container">
        <div class="section-header">
            <h2 class="section-title">Available Matches</h2>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-balance">
                    <span class="balance-item">
                        <i class="bi bi-coin"></i> <?= number_format($user_balance) ?> Coins
                    </span>
                    <span class="balance-item">
                        <i class="bi bi-ticket-perforated"></i> <?= number_format($user_tickets) ?> Tickets
                    </span>
                    <a href="my-matches.php" class="my-matches-link">
                        <i class="bi bi-trophy"></i> My Matches
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Games Filter -->
        <div class="games-filter">
            <a href="?game=" class="game-filter-btn <?= empty($game) ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i> All Games
            </a>
            <?php foreach ($games as $game_item): ?>
            <a href="?game=<?= urlencode($game_item['name']) ?>" 
               class="game-filter-btn <?= strtoupper($game) === strtoupper($game_item['name']) ? 'active' : '' ?>">
                <img src="<?= htmlspecialchars($game_item['image_url']) ?>" alt="<?= htmlspecialchars($game_item['name']) ?>">
                <?= htmlspecialchars($game_item['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Matches Grid -->
        <div class="matches-grid">
            <?php if (empty($matches)): ?>
            <div class="no-matches">
                <i class="bi bi-controller"></i>
                <p>No matches available at the moment.</p>
                <a href="?game=" class="btn-join btn-primary">View All Games</a>
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
                                <?php if ($match['tournament_name']): ?>
                                <div class="tournament-name">
                                    <i class="bi bi-trophy"></i> <?= htmlspecialchars($match['tournament_name']) ?>
                                </div>
                                <?php endif; ?>
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
                            <div class="info-item">
                                <i class="bi bi-map"></i>
                                <?= htmlspecialchars($match['map_name']) ?>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-people"></i>
                                <?= $match['current_participants'] ?>/<?= $match['max_participants'] ?>
                            </div>
                            <div class="prize-pool">
                                <i class="bi bi-trophy"></i>
                                <?php 
                                    if (!empty($match['website_currency_type']) && $match['website_currency_amount'] > 0) {
                                        echo 'Prize Pool: ' . number_format($match['website_currency_amount']) . ' ' . ucfirst($match['website_currency_type']);
                                    } elseif (!empty($match['prize_pool']) && $match['prize_pool'] > 0) {
                                        $currency_symbol = ($match['prize_type'] === 'USD') ? '$' : '₹';
                                        echo 'Prize Pool: ' . $currency_symbol . number_format($match['prize_pool']);
                                    } else {
                                        echo 'Prize Pool: Not Set';
                                    }
                                ?>
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
                            <div class="entry-fee">
                                <i class="bi bi-ticket"></i>
                                <?php if ($match['entry_type'] === 'free'): ?>
                                    Free Entry
                                <?php else: ?>
                                    Entry: <?= number_format($match['entry_fee']) ?> <?= ucfirst($match['entry_type']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="match-status">
                            <span class="status-badge status-<?= strtolower($match['match_status']) ?>">
                                <i class="bi bi-circle-fill"></i>
                                <?= $match['match_status'] ?>
                            </span>
                        </div>

                        <?php if ($match['has_joined'] && $match['status'] === 'in_progress'): ?>
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
                    </div>

                    <div class="match-actions">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="../login.php" class="btn-join btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Login to Join
                            </a>
                        <?php elseif ($match['status'] === 'completed'): ?>
                            <?php if ($match['has_joined']): ?>
                            <a href="view-winner.php?match_id=<?= $match['id'] ?>" class="btn-join btn-success">
                                <i class="bi bi-trophy"></i> View Winner
                            </a>
                            <?php endif; ?>
                            <a href="view-participants.php?match_id=<?= $match['id'] ?>" class="btn-join btn-info">
                                <i class="bi bi-people"></i> View Participants
                            </a>
                        <?php elseif ($match['has_joined']): ?>
                            <button class="btn-join btn-success" disabled>
                                <i class="bi bi-check2-circle"></i> Already Joined
                            </button>
                            <a href="view-participants.php?match_id=<?= $match['id'] ?>" class="btn-join btn-info">
                                <i class="bi bi-people"></i> View Participants
                            </a>
                        <?php elseif ($match['status'] === 'upcoming'): ?>
                            <?php
                            $can_join = true;
                            $error_message = '';
                            
                            if ($match['current_participants'] >= $match['max_participants']) {
                                $can_join = false;
                                $error_message = 'Match is full';
                            } elseif ($match['entry_type'] === 'coins' && $user_balance < $match['entry_fee']) {
                                $can_join = false;
                                $error_message = 'Insufficient coins';
                            } elseif ($match['entry_type'] === 'tickets' && $user_tickets < $match['entry_fee']) {
                                $can_join = false;
                                $error_message = 'Insufficient tickets';
                            }
                            ?>
                            
                            <?php if ($can_join): ?>
                                <a href="join.php?match_id=<?= $match['id'] ?>" class="btn-join btn-primary">
                                    <i class="bi bi-plus-circle"></i> Join Match
                                </a>
                            <?php else: ?>
                                <button class="btn-join btn-primary" disabled>
                                    <i class="bi bi-exclamation-circle"></i> <?= $error_message ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($match['current_participants'] > 0): ?>
                            <a href="view-participants.php?match_id=<?= $match['id'] ?>" class="btn-join btn-info">
                                <i class="bi bi-people"></i> View Participants
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-join btn-primary" disabled>
                                <i class="bi bi-clock"></i> Match Started
                            </button>
                            <a href="view-participants.php?match_id=<?= $match['id'] ?>" class="btn-join btn-info">
                                <i class="bi bi-people"></i> View Participants
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle success message fadeout
    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 300);
        }, 3000);
    }
});
</script>

<style>
/* Add these styles to your existing CSS */
.match-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.btn-join {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-join i {
    font-size: 1.1em;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
    border: none;
}

.btn-info:hover {
    background-color: #138496;
    color: white;
}

.btn-success {
    background-color: #28a745;
    color: white;
    border: none;
}

.btn-success:hover {
    background-color: #218838;
    color: white;
}
</style>

<?php include '../../includes/footer.php'; ?> 