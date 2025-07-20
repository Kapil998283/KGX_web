<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';
require_once '../../includes/team-auth.php';
?>
<link rel="stylesheet" href="../../assets/css/tournament/index.css">
<?php

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Build the query based on active tab
$where_clause = "";
switch ($active_tab) {
    case 'active':
        $where_clause = "WHERE status = 'ongoing' OR (status = 'upcoming' AND registration_phase = 'playing')";
        break;
    case 'upcoming':
        $where_clause = "WHERE status = 'upcoming' AND registration_phase = 'open'";
        break;
    case 'finished':
        $where_clause = "WHERE status = 'completed'";
        break;
    default: // 'all'
        $where_clause = "";
}

// Fetch tournaments based on filter
$stmt = $db->prepare("
    SELECT * FROM tournaments 
    " . ($where_clause ? $where_clause : "") . "
    ORDER BY 
        CASE 
            WHEN status = 'ongoing' THEN 1
            WHEN status = 'upcoming' AND registration_phase = 'playing' THEN 2
            WHEN status = 'upcoming' AND registration_phase = 'open' THEN 3
            WHEN status = 'completed' THEN 4
        END,
        playing_start_date ASC
");
$stmt->execute();
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get the correct registration URL based on tournament mode
function getRegistrationUrl($tournament) {
    switch ($tournament['mode']) {
        case 'Solo':
            return "register_solo.php?id=" . $tournament['id'];
        case 'Duo':
            return "register_duo.php?id=" . $tournament['id'];
        case 'Squad':
            return "register_squad.php?id=" . $tournament['id'];
        default:
            return "details.php?id=" . $tournament['id'];
    }
}
?>

<section class="tournaments-section">
    <div class="container">
        <h1 class="tournaments-title">Tournaments</h1>
        
        <div class="tournament-tabs">
            <div class="tabs-group">
                <a href="?tab=all" class="tab-btn <?php echo $active_tab === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?tab=active" class="tab-btn <?php echo $active_tab === 'active' ? 'active' : ''; ?>">Active</a>
                <a href="?tab=upcoming" class="tab-btn <?php echo $active_tab === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
                <a href="?tab=finished" class="tab-btn <?php echo $active_tab === 'finished' ? 'active' : ''; ?>">Finished</a>
            </div>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="my-registrations.php" class="register-btn">
                    <ion-icon name="trophy-outline"></ion-icon>
                    Registered
                </a>
            <?php endif; ?>
        </div>
        
        <div class="tournaments-grid">
            <?php if (empty($tournaments)): ?>
                <div class="no-tournaments">
                    <ion-icon name="calendar-outline" class="large-icon"></ion-icon>
                    <h3>No Tournaments Found</h3>
                    <p>Check back later for upcoming tournaments!</p>
                </div>
            <?php else: ?>
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="tournament-card">
                        <div class="card-banner">
                            <img src="<?php echo htmlspecialchars($tournament['banner_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($tournament['name']); ?>" 
                                 class="tournament-banner">
                            
                            <?php if ($tournament['registration_phase'] === 'playing'): ?>
                                <div class="tournament-status status-playing">
                                    <ion-icon name="play-circle-outline"></ion-icon>
                                    Playing
                                </div>
                            <?php elseif ($tournament['registration_phase'] === 'open'): ?>
                                <div class="tournament-status status-upcoming">
                                    <ion-icon name="time-outline"></ion-icon>
                                    Upcoming
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-content">
                            <h3 class="game-name"><?php echo htmlspecialchars($tournament['game_name']); ?></h3>
                            
                            <div class="tournament-meta">
                                <div class="meta-item prize-pool">
                                    <ion-icon name="trophy-outline"></ion-icon>
                                    <?php 
                                        if (!empty($tournament['website_currency_type']) && $tournament['website_currency_amount'] > 0) {
                                            echo number_format($tournament['website_currency_amount']) . ' ' . ucfirst($tournament['website_currency_type']);
                                        } else {
                                            echo $tournament['prize_currency'] === 'USD' ? '$' : '₹';
                                            echo number_format($tournament['prize_pool']); 
                                        }
                                    ?>
                                </div>
                                <div class="meta-item entry-fee">
                                    <ion-icon name="ticket-outline"></ion-icon>
                                    <?php echo $tournament['entry_fee']; ?> Tickets
                                </div>
                                <div class="meta-item start-date">
                                    <ion-icon name="calendar-outline"></ion-icon>
                                    <?php echo date('M d, Y', strtotime($tournament['playing_start_date'])); ?>
                                </div>
                            </div>

                            <div class="tournament-info">
                                <div class="team-count">
                                    <ion-icon name="people-outline"></ion-icon>
                                    <?php echo $tournament['current_teams']; ?>/<?php echo $tournament['max_teams']; ?> Teams
                                </div>
                                
                                <a href="details.php?id=<?php echo $tournament['id']; ?>" class="details-btn">
                                    <ion-icon name="arrow-forward-outline"></ion-icon>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once '../../includes/footer.php'; ?>