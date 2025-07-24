<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';
require_once '../../includes/team-auth.php';
require_once '../../includes/tournament-status.php';
?>
<link rel="stylesheet" href="../../assets/css/tournament/index.css">
<?php

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Update tournament statuses
updateTournamentStatus($db);

// Get active tab from URL parameter
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Build the query based on active tab
$where_clause = "";

switch ($active_tab) {
    case 'playing':
        $where_clause = "WHERE status = 'in_progress'";
        break;
    case 'upcoming':
        $where_clause = "WHERE status IN ('announced', 'registration_open', 'registration_closed')";
        break;
    case 'finished':
        $where_clause = "WHERE status IN ('completed', 'archived')";
        break;
    default: // 'all'
        $where_clause = "WHERE status != 'cancelled' AND status != 'draft'";
}

// Fetch tournaments based on filter
$stmt = $db->prepare("
    SELECT *, 
    CASE 
        WHEN status = 'in_progress' THEN 1
        WHEN status = 'registration_open' THEN 2
        WHEN status = 'registration_closed' THEN 3
        WHEN status = 'announced' THEN 4
        WHEN status = 'completed' THEN 5
        WHEN status = 'archived' THEN 6
        ELSE 7
    END as sort_order
    FROM tournaments 
    {$where_clause}
    ORDER BY sort_order ASC, playing_start_date ASC
");

$stmt->execute();
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<section class="tournaments-section">
    <div class="container">
        <h1 class="tournaments-title">Tournaments</h1>
        
        <div class="tournament-tabs">
            <div class="tabs-group">
                <a href="?tab=all" class="tab-btn <?php echo $active_tab === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?tab=upcoming" class="tab-btn <?php echo $active_tab === 'upcoming' ? 'active' : ''; ?>">Upcoming</a>
                <a href="?tab=playing" class="tab-btn <?php echo $active_tab === 'playing' ? 'active' : ''; ?>">Playing</a>
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
                            
                            <?php 
                                $status_info = getTournamentDisplayStatus($tournament);
                                echo '<div class="tournament-status ' . $status_info['class'] . '">';
                                echo '<ion-icon name="' . $status_info['icon'] . '"></ion-icon>';
                                echo $status_info['status'];
                                echo '</div>';

                                if ($status_info['date_label'] && $status_info['date_value']) {
                                    echo '<div class="date-info">';
                                    echo '<small>' . $status_info['date_label'] . ': ' . $status_info['date_value'] . '</small>';
                                    echo '</div>';
                                }
                            ?>
                        </div>

                        <div class="card-content">
                            <h2 class="tournament-name"><?php echo htmlspecialchars($tournament['name']); ?></h2>
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
                                    <?php if (isTournamentRegistrationOpen($tournament)): ?>
                                        <span class="register-now">Register Now</span>
                                    <?php endif; ?>
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