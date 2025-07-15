<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';
require_once '../../includes/team-auth.php';

// Add CSS link for tournaments
?>
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="./css/tournaments.css">
<?php

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Fetch active and upcoming tournaments
$stmt = $db->prepare("
    SELECT * FROM tournaments 
    WHERE status IN ('upcoming', 'ongoing')
    AND (
        (registration_phase = 'open' AND registration_close_date >= CURDATE())
        OR (registration_phase = 'closed' AND playing_start_date >= CURDATE())
        OR registration_phase = 'playing'
    )
    ORDER BY 
        CASE 
            WHEN registration_phase = 'open' THEN 1
            WHEN registration_phase = 'playing' THEN 2
            ELSE 3
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

<main>
    <section class="tournaments-section" style="padding: 120px 0 60px;">
        <div class="container">
            <div class="section-header d-flex justify-content-between align-items-center mb-5">
                <h2 class="section-title">Active Tournaments</h2>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my-registrations.php" class="btn btn-primary">
                        <ion-icon name="trophy-outline" class="me-2"></ion-icon>
                        My Registrations
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="row g-4">
                <?php foreach ($tournaments as $tournament): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="tournament-card">
                            <div class="card-banner">
                                <img src="<?php echo htmlspecialchars($tournament['banner_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($tournament['name']); ?>" 
                                     class="tournament-banner">
                                
                                <div class="tournament-meta">
                                    <div class="prize-pool">
                                        <ion-icon name="trophy-outline"></ion-icon>
                                        <span><?php 
                                            echo $tournament['prize_currency'] === 'USD' ? '$' : '₹';
                                            echo number_format($tournament['prize_pool'], 2); 
                                        ?></span>
                                    </div>
                                    <div class="entry-fee">
                                        <ion-icon name="ticket-outline"></ion-icon>
                                        <span><?php echo $tournament['entry_fee']; ?> Tickets</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-content">
                                <h3 class="tournament-title"><?php echo htmlspecialchars($tournament['name']); ?></h3>
                                <p class="game-name"><?php echo htmlspecialchars($tournament['game_name']); ?></p>
                                
                                <div class="tournament-info">
                                    <div class="info-item">
                                        <ion-icon name="people-outline"></ion-icon>
                                        <span><?php echo $tournament['current_teams']; ?>/<?php echo $tournament['max_teams']; ?> Teams</span>
                                    </div>
                                    <div class="info-item">
                                        <ion-icon name="game-controller-outline"></ion-icon>
                                        <span><?php echo htmlspecialchars($tournament['mode']); ?></span>
                                    </div>
                                </div>

                                <div class="tournament-dates">
                                    <?php if ($tournament['registration_phase'] === 'open'): ?>
                                        <div class="registration-ends">
                                            Registration Closes: <?php echo date('M d, Y', strtotime($tournament['registration_close_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="tournament-starts">
                                        Starts: <?php echo date('M d, Y', strtotime($tournament['playing_start_date'])); ?>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <a href="details.php?id=<?php echo $tournament['id']; ?>" class="btn btn-primary">
                                        View Details
                                    </a>
                                    
                                    <?php if ($tournament['registration_phase'] === 'open'): ?>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <?php
                                            // Check if user has already registered - different check for Solo mode
                                            if ($tournament['mode'] === 'Solo') {
                                                $stmt = $db->prepare("
                                                    SELECT tr.* 
                                                    FROM tournament_registrations tr
                                                    INNER JOIN teams t ON tr.team_id = t.id
                                                    INNER JOIN team_members tm ON t.id = tm.team_id
                                                    WHERE tr.tournament_id = ? 
                                                    AND tm.user_id = ?
                                                    AND tr.status IN ('pending', 'approved')
                                                ");
                                            } else {
                                                $stmt = $db->prepare("
                                                    SELECT tr.* 
                                                    FROM tournament_registrations tr
                                                    INNER JOIN teams t ON tr.team_id = t.id
                                                    INNER JOIN team_members tm ON t.id = tm.team_id
                                                    WHERE tr.tournament_id = ? 
                                                    AND tm.user_id = ?
                                                    AND tm.status = 'active'
                                                    AND tr.status IN ('pending', 'approved')
                                                ");
                                            }
                                            $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
                                            $existing_registration = $stmt->fetch();

                                            // Check if user has enough tickets
                                            $stmt = $db->prepare("SELECT tickets FROM user_tickets WHERE user_id = ?");
                                            $stmt->execute([$_SESSION['user_id']]);
                                            $user_tickets = $stmt->fetch();
                                            $has_enough_tickets = $user_tickets && $user_tickets['tickets'] >= $tournament['entry_fee'];

                                            // Check if tournament is full
                                            $spots_left = $tournament['max_teams'] - $tournament['current_teams'];

                                            // Check if user is team captain (only for non-solo tournaments)
                                            $can_register = true;
                                            if ($tournament['mode'] !== 'Solo') {
                                                $can_register = isTeamCaptain($db);
                                            }
                                            ?>

                                            <?php if (!$existing_registration && $spots_left > 0 && $has_enough_tickets): ?>
                                                <?php if ($tournament['mode'] === 'Solo'): ?>
                                                    <a href="<?php echo getRegistrationUrl($tournament); ?>" class="btn btn-secondary">
                                                        Register Now
                                                    </a>
                                                <?php elseif ($can_register): ?>
                                                    <a href="<?php echo getRegistrationUrl($tournament); ?>" class="btn btn-secondary">
                                                        Register Now
                                                    </a>
                                                <?php else: ?>
                                                    <?php
                                                    // Check if user is already a member of a team
                                                    $stmt = $db->prepare("
                                                        SELECT tm.role 
                                                        FROM team_members tm 
                                                        WHERE tm.user_id = ? 
                                                        AND tm.status = 'active'
                                                        LIMIT 1
                                                    ");
                                                    $stmt->execute([$_SESSION['user_id']]);
                                                    $teamRole = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    if ($teamRole && $teamRole['role'] === 'member'): ?>
                                                        <button class="btn btn-secondary" disabled>
                                                            Only Team Captain Can Register
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="../../pages/teams/create_team.php?redirect=tournament&id=<?php echo $tournament['id']; ?>"
                                                            class="btn btn-secondary">
                                                            <?php if ($tournament['mode'] === 'Duo'): ?>
                                                                Create Duo Team
                                                            <?php else: ?>
                                                                Create Squad Team
                                                            <?php endif; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif (!$has_enough_tickets): ?>
                                                <button class="btn btn-secondary" disabled>
                                                    Need <?php echo $tournament['entry_fee']; ?> Tickets
                                                </button>
                                            <?php elseif ($existing_registration): ?>
                                                <button class="btn btn-secondary" disabled>
                                                    Already Registered
                                                </button>
                                            <?php elseif ($spots_left <= 0): ?>
                                                <button class="btn btn-secondary" disabled>
                                                    Tournament Full
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="../../register/login.php" class="btn btn-secondary">
                                                Login to Register
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($tournaments)): ?>
                    <div class="col-12 text-center">
                        <div class="no-tournaments">
                            <ion-icon name="calendar-outline" class="large-icon"></ion-icon>
                            <h3>No Active Tournaments</h3>
                            <p>Check back later for upcoming tournaments!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once '../../includes/footer.php'; ?> 