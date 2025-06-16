<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Fetch active and upcoming tournaments with registration count
$sql = "
    SELECT 
        t.*,
        COALESCE(reg_count.registered_teams, 0) as registered_teams
    FROM tournaments t
    LEFT JOIN (
        SELECT 
            tournament_id, 
            COUNT(*) as registered_teams
        FROM tournament_registrations 
        WHERE status = 'approved'
        GROUP BY tournament_id
    ) reg_count ON t.id = reg_count.tournament_id
    WHERE t.status IN ('upcoming', 'ongoing', 'active')
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
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get the correct registration URL based on tournament mode
function getRegistrationUrl($tournament) {
    switch ($tournament['mode']) {
        case 'Solo':
            return "../pages/tournaments/register_solo.php?id=" . $tournament['id'];
        case 'Duo':
            return "../pages/tournaments/register_duo.php?id=" . $tournament['id'];
        case 'Squad':
            return "../pages/tournaments/register_squad.php?id=" . $tournament['id'];
        default:
            return "../pages/tournaments/details.php?id=" . $tournament['id'];
    }
}
?>

<main>
    <section class="tournaments-section" style="padding: 120px 0 60px;">
        <div class="container">
            <h2 class="section-title text-center mb-5">Available Tournaments</h2>
            
            <div class="row g-4">
                <?php if (!empty($tournaments)): ?>
                    <?php foreach ($tournaments as $tournament): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="tournament-card">
                                <div class="card-banner">
                                    <img src="<?php echo htmlspecialchars($tournament['banner_image'] ?? '../assets/images/tournament-default.jpg'); ?>" 
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
                                    <div class="mb-2">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($tournament['game_name']); ?></span>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($tournament['mode']); ?></span>
                                        <span class="badge bg-<?php echo $tournament['registration_phase'] === 'open' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($tournament['registration_phase']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="tournament-info">
                                        <div class="info-item">
                                            <ion-icon name="people-outline"></ion-icon>
                                            <span><?php echo $tournament['registered_teams']; ?>/<?php echo $tournament['max_teams']; ?> Teams</span>
                                        </div>
                                        <div class="info-item">
                                            <ion-icon name="calendar-outline"></ion-icon>
                                            <span><?php echo date('M d, Y', strtotime($tournament['playing_start_date'])); ?></span>
                                        </div>
                                    </div>

                                    <?php if ($tournament['registration_phase'] === 'open'): ?>
                                        <div class="registration-ends">
                                            Registration Closes: <?php echo date('M d, Y', strtotime($tournament['registration_close_date'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-actions">
                                        <a href="../pages/tournaments/details.php?id=<?php echo $tournament['id']; ?>" class="btn btn-primary">
                                            View Details
                                        </a>
                                        
                                        <?php if ($tournament['registration_phase'] === 'open'): ?>
                                            <?php if (isset($_SESSION['user_id'])): ?>
                                                <?php
                                                // Check if user has already registered
                                                $stmt = $conn->prepare("
                                                    SELECT tr.* 
                                                    FROM tournament_registrations tr
                                                    INNER JOIN teams t ON tr.team_id = t.id
                                                    INNER JOIN team_members tm ON t.id = tm.team_id
                                                    WHERE tr.tournament_id = ? 
                                                    AND tm.user_id = ?
                                                    AND tm.status = 'active'
                                                ");
                                                $stmt->execute([$tournament['id'], $_SESSION['user_id']]);
                                                $existing_registration = $stmt->fetch();

                                                // Check if user has enough tickets
                                                $stmt = $conn->prepare("SELECT tickets FROM user_tickets WHERE user_id = ?");
                                                $stmt->execute([$_SESSION['user_id']]);
                                                $user_tickets = $stmt->fetch();
                                                $has_enough_tickets = $user_tickets && $user_tickets['tickets'] >= $tournament['entry_fee'];

                                                // Check if tournament is full
                                                $spots_left = $tournament['max_teams'] - $tournament['registered_teams'];
                                                ?>

                                                <?php if (!$existing_registration && $spots_left > 0 && $has_enough_tickets): ?>
                                                    <a href="<?php echo getRegistrationUrl($tournament); ?>" class="btn btn-success">
                                                        Register Now
                                                    </a>
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
                                                <a href="../pages/auth/login.php" class="btn btn-secondary">
                                                    Login to Register
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
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

<style>
.tournaments-section {
    background: var(--raisin-black-1);
    color: var(--white);
}

.tournament-card {
    background: rgba(20, 20, 20, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.3s ease;
    height: 100%;
}

.tournament-card:hover {
    transform: translateY(-5px);
}

.card-banner {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.tournament-banner {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tournament-meta {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    padding: 10px;
    background: rgba(0, 0, 0, 0.7);
    color: var(--white);
}

.prize-pool, .entry-fee {
    display: flex;
    align-items: center;
    gap: 5px;
}

.card-content {
    padding: 20px;
}

.tournament-title {
    font-size: 1.25rem;
    margin-bottom: 10px;
    color: var(--white);
}

.badge {
    margin-right: 5px;
    padding: 5px 10px;
    border-radius: 15px;
}

.tournament-info {
    display: flex;
    justify-content: space-between;
    margin: 15px 0;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--quick-silver);
}

.registration-ends {
    color: var(--orange);
    margin: 10px 0;
    font-size: 0.9rem;
}

.card-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn {
    flex: 1;
    text-align: center;
    padding: 8px 16px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-primary {
    background: var(--orange);
    color: var(--white);
}

.btn-success {
    background: #28a745;
    color: var(--white);
}

.btn-secondary {
    background: var(--raisin-black-3);
    color: var(--white);
}

.btn:not(:disabled):hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.no-tournaments {
    padding: 40px;
    text-align: center;
}

.no-tournaments .large-icon {
    font-size: 48px;
    color: var(--quick-silver);
    margin-bottom: 20px;
}

.no-tournaments h3 {
    color: var(--white);
    margin-bottom: 10px;
}

.no-tournaments p {
    color: var(--quick-silver);
}

.alert {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--white);
}
</style>

<?php require_once '../includes/footer.php'; ?> 