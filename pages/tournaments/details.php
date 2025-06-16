<?php
ob_start(); // Start output buffering at the very beginning
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Function to get the correct registration URL based on tournament mode
function getRegistrationUrl($tournament) {
    switch ($tournament['mode']) {
        case 'Solo':
            return "/KGX/pages/tournaments/register_solo.php?id=" . $tournament['id'];
        case 'Duo':
            return "/KGX/pages/tournaments/register_duo.php?id=" . $tournament['id'];
        case 'Squad':
            return "/KGX/pages/tournaments/register_squad.php?id=" . $tournament['id'];
        default:
            return "/KGX/pages/tournaments/details.php?id=" . $tournament['id'];
    }
}

// Check if tournament ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Fetch tournament details
$stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$_GET['id']]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

// If tournament doesn't exist, redirect
if (!$tournament) {
    header('Location: index.php');
    exit();
}

// Fetch registered teams count
$stmt = $db->prepare("SELECT COUNT(*) as team_count FROM tournament_registrations WHERE tournament_id = ? AND status = 'approved'");
$stmt->execute([$tournament['id']]);
$team_count = $stmt->fetch(PDO::FETCH_ASSOC)['team_count'];

// Update current teams count if different
if ($team_count != $tournament['current_teams']) {
    $stmt = $db->prepare("UPDATE tournaments SET current_teams = ? WHERE id = ?");
    $stmt->execute([$team_count, $tournament['id']]);
    $tournament['current_teams'] = $team_count;
}
?>

<main>
    <section class="tournament-details" style="padding: 120px 0 60px;">
        <div class="container">
            <div class="tournament-header">
                <div class="banner-container">
                    <img src="<?php echo htmlspecialchars($tournament['banner_image']); ?>" 
                         alt="<?php echo htmlspecialchars($tournament['name']); ?>" 
                         class="tournament-banner">
                </div>
                
                <div class="tournament-info">
                    <h1 class="tournament-title"><?php echo htmlspecialchars($tournament['name']); ?></h1>
                    <div class="meta-info">
                        <div class="meta-item">
                            <ion-icon name="game-controller"></ion-icon>
                            <span><?php echo htmlspecialchars($tournament['game_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <ion-icon name="trophy"></ion-icon>
                            <span>Prize Pool: <?php 
                                echo $tournament['prize_currency'] === 'USD' ? '$' : '₹';
                                echo number_format($tournament['prize_pool'], 2); 
                            ?></span>
                        </div>
                        <div class="meta-item">
                            <ion-icon name="ticket"></ion-icon>
                            <span>Entry Fee: <?php echo $tournament['entry_fee']; ?> Tickets</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tournament-content">
                <div class="row">
                    <div class="col-lg-7 col-md-6">
                        <div class="content-section">
                            <h2>About the Tournament</h2>
                            <p><?php echo nl2br(htmlspecialchars($tournament['description'])); ?></p>
                        </div>

                        <div class="content-section">
                            <h2>Tournament Rules</h2>
                            <div class="rules-content">
                                <?php echo nl2br(htmlspecialchars($tournament['rules'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 col-md-6">
                        <div class="tournament-sidebar">
                            <div class="sidebar-flex-container">
                                <div class="sidebar-section tournament-stats">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="people-outline"></ion-icon>
                                        </div>
                                        <div class="stat-info">
                                            <span class="stat-value"><?php echo $tournament['current_teams']; ?>/<?php echo $tournament['max_teams']; ?></span>
                                            <span class="stat-label">Teams Registered</span>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="trophy-outline"></ion-icon>
                                        </div>
                                        <div class="stat-info">
                                            <span class="stat-value"><?php echo htmlspecialchars($tournament['format']); ?></span>
                                            <span class="stat-label">Tournament Format</span>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="game-controller-outline"></ion-icon>
                                        </div>
                                        <div class="stat-info">
                                            <span class="stat-value"><?php echo htmlspecialchars($tournament['mode']); ?></span>
                                            <span class="stat-label">Game Mode</span>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <ion-icon name="medal-outline"></ion-icon>
                                        </div>
                                        <div class="stat-info">
                                            <span class="stat-value"><?php echo htmlspecialchars($tournament['match_type']); ?></span>
                                            <span class="stat-label">Match Type</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="sidebar-flex-row">
                                    <div class="sidebar-section timeline-section">
                                        <h3>Tournament Timeline</h3>
                                        <div class="timeline">
                                            <?php
                                            $current_time = time();
                                            $dates = [
                                                ['date' => $tournament['registration_open_date'], 'label' => 'Registration Opens', 'icon' => 'calendar-outline'],
                                                ['date' => $tournament['registration_close_date'], 'label' => 'Registration Closes', 'icon' => 'time-outline'],
                                                ['date' => $tournament['playing_start_date'], 'label' => 'Tournament Starts', 'icon' => 'flag-outline'],
                                                ['date' => $tournament['finish_date'], 'label' => 'Tournament Ends', 'icon' => 'trophy-outline']
                                            ];

                                            foreach ($dates as $index => $date_info):
                                                $date_timestamp = strtotime($date_info['date']);
                                                $is_past = $current_time > $date_timestamp;
                                                $is_current = $current_time >= $date_timestamp && 
                                                            ($index == count($dates) - 1 || $current_time < strtotime($dates[$index + 1]['date']));
                                            ?>
                                            <div class="timeline-item <?php echo $is_past ? 'completed' : ($is_current ? 'current' : ''); ?>">
                                                <div class="timeline-icon">
                                                    <ion-icon name="<?php echo $date_info['icon']; ?>"></ion-icon>
                                                    <?php if ($is_past): ?>
                                                    <div class="check-icon">
                                                        <ion-icon name="checkmark-outline"></ion-icon>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="timeline-content">
                                                    <h4><?php echo $date_info['label']; ?></h4>
                                                    <p><?php echo date('M d, Y', $date_timestamp); ?></p>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="sidebar-section rules-section">
                                        <h3>Rules & Guidelines</h3>
                                        <div class="tournament-rules">
                                            <?php if (!empty($tournament['rules'])): ?>
                                                <div class="rules-list">
                                                    <?php 
                                                    $rules = explode("\n", $tournament['rules']);
                                                    foreach ($rules as $index => $rule): 
                                                        if (trim($rule)):
                                                    ?>
                                                        <div class="rule-item">
                                                            <span class="rule-number"><?php echo $index + 1; ?></span>
                                                            <span class="rule-text"><?php echo htmlspecialchars(trim($rule)); ?></span>
                                                        </div>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="no-rules">No specific rules have been set for this tournament.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="sidebar-section">
                                    <h3>Description</h3>
                                    <div class="tournament-description">
                                        <?php echo nl2br(htmlspecialchars($tournament['description'] ?? 'No description available.')); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($tournament['registration_phase'] === 'open' && strtotime($tournament['registration_close_date']) >= time()): ?>
                                <div class="registration-section">
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <?php
                                        // Check if user has already registered
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
                                        $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
                                        $existing_registration = $stmt->fetch();

                                        // Get rejected registration if exists
                                        $stmt = $db->prepare("
                                            SELECT tr.* 
                                            FROM tournament_registrations tr
                                            INNER JOIN teams t ON tr.team_id = t.id
                                            INNER JOIN team_members tm ON t.id = tm.team_id
                                            WHERE tr.tournament_id = ? 
                                            AND tm.user_id = ?
                                            AND tm.status = 'active'
                                            AND tr.status = 'rejected'
                                            ORDER BY tr.registration_date DESC
                                            LIMIT 1
                                        ");
                                        $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
                                        $rejected_registration = $stmt->fetch();

                                        // Check if user has enough tickets
                                        $stmt = $db->prepare("SELECT tickets FROM user_tickets WHERE user_id = ?");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $user_tickets = $stmt->fetch();
                                        $has_enough_tickets = $user_tickets && $user_tickets['tickets'] >= $tournament['entry_fee'];

                                        // Check if tournament is full
                                        $spots_left = $tournament['max_teams'] - $tournament['current_teams'];
                                        ?>

                                        <?php if ($existing_registration): ?>
                                            <div class="alert alert-info">
                                                <h4 class="alert-heading">Already Registered!</h4>
                                                <p>You are already registered for this tournament.</p>
                                                <a href="my-registrations.php" class="btn btn-primary btn-lg w-100">View My Registrations</a>
                                            </div>
                                        <?php elseif ($rejected_registration): ?>
                                            <div class="alert alert-warning">
                                                <h4 class="alert-heading">Previous Registration Rejected</h4>
                                                <p>Your previous registration was rejected. You can try registering again.</p>
                                                <?php if ($has_enough_tickets && $spots_left > 0): ?>
                                                    <a href="<?php echo getRegistrationUrl($tournament); ?>" class="btn btn-primary btn-lg w-100">
                                                        Register Again
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($spots_left <= 0): ?>
                                            <div class="alert alert-warning">
                                                <h4 class="alert-heading">Tournament Full</h4>
                                                <p>This tournament has reached its maximum capacity.</p>
                                            </div>
                                        <?php elseif (!$has_enough_tickets): ?>
                                            <div class="alert alert-warning">
                                                <h4 class="alert-heading">Insufficient Tickets</h4>
                                                <p>You need <?php echo $tournament['entry_fee']; ?> tickets to register.</p>
                                                <a href="../shop/tickets.php" class="btn btn-primary btn-lg w-100">Get Tickets</a>
                                            </div>
                                        <?php else: ?>
                                            <div class="registration-box">
                                                <h4>Ready to Join?</h4>
                                                <div class="registration-info mb-3">
                                                    <div class="info-row">
                                                        <span class="info-label">Mode:</span>
                                                        <span class="info-value"><?php echo htmlspecialchars($tournament['mode']); ?></span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Entry Fee:</span>
                                                        <span class="info-value"><?php echo $tournament['entry_fee']; ?> Tickets</span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Your Tickets:</span>
                                                        <span class="info-value"><?php echo $user_tickets['tickets']; ?> Available</span>
                                                    </div>
                                                    <div class="info-row">
                                                        <span class="info-label">Spots Left:</span>
                                                        <span class="info-value"><?php echo $spots_left; ?></span>
                                                    </div>
                                                </div>
                                                <a href="<?php echo getRegistrationUrl($tournament); ?>" class="btn btn-primary btn-lg w-100">
                                                    Register Now
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <h4 class="alert-heading">Login Required</h4>
                                            <p>Please login to register for this tournament.</p>
                                            <a href="../auth/login.php" class="btn btn-primary btn-lg w-100">Login to Register</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($tournament['registration_phase'] === 'closed' && strtotime($tournament['registration_open_date']) > time()): ?>
                                <div class="registration-section">
                                    <div class="alert alert-info">
                                        <h4 class="alert-heading">Coming Soon</h4>
                                        <p>Registration opens on <?php echo date('M d, Y', strtotime($tournament['registration_open_date'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
.tournament-details {
    background: var(--raisin-black-1);
    color: var(--white);
}

.tournament-header {
    margin-bottom: 40px;
}

.banner-container {
    height: 300px;
    overflow: hidden;
    border-radius: 15px;
    margin-bottom: 20px;
}

.tournament-banner {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tournament-title {
    font-size: 2.5rem;
    margin-bottom: 20px;
    color: var(--white);
}

.meta-info {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--quick-silver);
}

.meta-item ion-icon {
    font-size: 1.2rem;
    color: var(--orange);
}

.content-section {
    background: var(--raisin-black-2);
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.content-section h2 {
    color: var(--white);
    margin-bottom: 20px;
    font-size: 1.5rem;
}

.rules-content {
    white-space: pre-line;
    color: var(--quick-silver);
}

.tournament-sidebar {
    position: sticky;
    top: 100px;
    width: 100%;
    max-width: 100%;
}

.sidebar-flex-container {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.sidebar-flex-row {
    display: flex;
    gap: 25px;
    flex-direction: column;
}

.sidebar-section {
    background: var(--raisin-black-2);
    padding: 30px;
    border-radius: 15px;
    width: 100%;
    height: 100%;
}

.timeline-section, .rules-section {
    flex: 1;
    min-width: 0;
}

.sidebar-section h3 {
    color: var(--white);
    margin-bottom: 15px;
    font-size: 1.2rem;
}

.details-list, .dates-list {
    list-style: none;
    padding: 0;
}

.details-list li, .dates-list li {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--quick-silver);
}

.label {
    color: var(--quick-silver);
}

.value {
    color: var(--white);
    font-weight: 500;
}

.registration-section {
    margin-top: 20px;
    padding: 20px;
    background: var(--raisin-black-2);
    border-radius: 10px;
}

.registration-box {
    background: var(--raisin-black-3);
    padding: 20px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.registration-box h4 {
    color: var(--white);
    margin-bottom: 20px;
    text-align: center;
}

.registration-info {
    background: rgba(0, 0, 0, 0.2);
    padding: 15px;
    border-radius: 8px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--quick-silver);
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-label {
    font-weight: 500;
}

.info-value {
    color: var(--white);
}

.btn-primary {
    background: var(--orange);
    border: none;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.alert {
    background: var(--raisin-black-3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--white);
    padding: 20px;
    border-radius: 10px;
}

.alert-heading {
    color: var(--white);
    margin-bottom: 15px;
}

.alert-info {
    border-left: 4px solid var(--blue);
}

.alert-warning {
    border-left: 4px solid var(--orange);
}

.btn-link {
    color: var(--orange);
    text-decoration: none;
}

.btn-link:hover {
    text-decoration: underline;
}

.btn-lg {
    margin-top: 15px;
    padding: 12px 24px;
    font-size: 1.1rem;
}

@media (min-width: 1200px) {
    .sidebar-flex-row {
        flex-direction: row;
    }

    .timeline-section, .rules-section {
        width: calc(50% - 12.5px);
        min-height: 400px;
        overflow-y: auto;
    }
}

@media (min-width: 992px) {
    .tournament-sidebar {
        padding-left: 20px;
    }
    
    .stat-card {
        padding: 25px;
    }
}

@media (max-width: 991px) {
    .tournament-sidebar {
        position: static;
        margin-top: 30px;
        padding: 0;
    }
    
    .tournament-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .sidebar-flex-row {
        flex-direction: column;
    }
}

@media (max-width: 767px) {
    .tournament-stats {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .sidebar-section {
        padding: 20px;
    }
}

.tournament-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.stat-card {
    background: var(--raisin-black-3);
    padding: 20px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
    min-width: 0; /* Prevent overflow on small screens */
}

.stat-icon {
    width: 40px;
    height: 40px;
    background: var(--orange);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon ion-icon {
    font-size: 1.5rem;
    color: var(--white);
}

.stat-info {
    flex-grow: 1;
}

.stat-value {
    display: block;
    color: var(--white);
    font-size: 1.1rem;
    font-weight: 600;
    line-height: 1.2;
}

.stat-label {
    display: block;
    color: var(--quick-silver);
    font-size: 0.85rem;
}

/* Timeline */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    left: 15px;
    height: 100%;
    width: 2px;
    background: var(--raisin-black-4);
}

.timeline-item {
    position: relative;
    padding-left: 45px;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: 0;
    width: 32px;
    height: 32px;
    background: var(--raisin-black-3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.timeline-icon ion-icon {
    font-size: 1.2rem;
    color: var(--quick-silver);
}

.timeline-item.completed .timeline-icon {
    background: var(--orange);
}

.timeline-item.completed .timeline-icon ion-icon {
    color: var(--white);
}

.timeline-item.current .timeline-icon {
    background: var(--orange);
    box-shadow: 0 0 0 4px rgba(255, 70, 85, 0.2);
}

.timeline-content h4 {
    color: var(--white);
    font-size: 1rem;
    margin-bottom: 5px;
}

.timeline-content p {
    color: var(--quick-silver);
    font-size: 0.9rem;
    margin: 0;
}

.check-icon {
    position: absolute;
    top: -4px;
    right: -4px;
    width: 16px;
    height: 16px;
    background: var(--green);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.check-icon ion-icon {
    font-size: 0.7rem;
    color: var(--white);
}

/* Description */
.tournament-description {
    color: var(--quick-silver);
    line-height: 1.6;
    font-size: 0.95rem;
}

/* Rules */
.rules-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.rule-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.rule-number {
    width: 24px;
    height: 24px;
    background: var(--raisin-black-3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--orange);
    font-size: 0.85rem;
    font-weight: 600;
    flex-shrink: 0;
}

.rule-text {
    color: var(--quick-silver);
    font-size: 0.95rem;
    line-height: 1.5;
}

.no-rules {
    color: var(--quick-silver);
    font-style: italic;
    text-align: center;
    margin: 20px 0;
}

/* Scrollbar styling for the sections */
.timeline-section::-webkit-scrollbar,
.rules-section::-webkit-scrollbar {
    width: 8px;
}

.timeline-section::-webkit-scrollbar-track,
.rules-section::-webkit-scrollbar-track {
    background: var(--raisin-black-1);
    border-radius: 4px;
}

.timeline-section::-webkit-scrollbar-thumb,
.rules-section::-webkit-scrollbar-thumb {
    background: var(--raisin-black-4);
    border-radius: 4px;
}

.timeline-section::-webkit-scrollbar-thumb:hover,
.rules-section::-webkit-scrollbar-thumb:hover {
    background: var(--orange);
}
</style>

<?php require_once '../../includes/footer.php'; ?>

<?php ob_end_flush(); // End output buffering and send output ?> 