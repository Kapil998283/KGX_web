<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Fetch user's tournament registrations (both as captain and member)
$stmt = $db->prepare("
    SELECT 
        t.id as tournament_id,
        t.name as tournament_name,
        t.game_name,
        t.mode,
        t.banner_image,
        t.playing_start_date,
        t.prize_pool,
        t.prize_currency,
        t.registration_phase,
        tm.team_id,
        team.name as team_name,
        tr.registration_date,
        tr.status as registration_status,
        CASE 
            WHEN tm.role = 'captain' THEN 1
            ELSE 0
        END as is_captain
    FROM tournament_registrations tr
    INNER JOIN tournaments t ON tr.tournament_id = t.id
    INNER JOIN teams team ON tr.team_id = team.id
    INNER JOIN team_members tm ON team.id = tm.team_id
    WHERE tm.user_id = ? AND tm.status = 'active'
    ORDER BY tr.registration_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../../assets/css/tournament/registrations.css">

<main>
    <section class="registrations-section">
        <div class="container">
            <h1 class="section-title">My Tournament Registrations</h1>
            <div class="title-underline"></div>
            
            <?php if (empty($registrations)): ?>
                <div class="no-registrations">
                    <div class="no-registrations-content">
                        <ion-icon name="trophy-outline" class="large-icon"></ion-icon>
                        <h3>No Tournament Registrations</h3>
                        <p>You haven't registered for any tournaments yet.</p>
                        <a href="index.php" class="browse-btn">
                            BROWSE TOURNAMENTS
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="registrations-grid">
                    <?php foreach ($registrations as $reg): ?>
                        <div class="registration-card">
                            <div class="card-banner">
                                <img src="<?php echo htmlspecialchars($reg['banner_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($reg['tournament_name']); ?>" 
                                     class="tournament-banner">
                                
                                <div class="registration-status <?php echo strtolower($reg['registration_status']); ?>">
                                    <ion-icon name="<?php 
                                        echo $reg['registration_status'] === 'approved' ? 'checkmark-circle-outline' : 
                                            ($reg['registration_status'] === 'pending' ? 'time-outline' : 'close-circle-outline'); 
                                    ?>"></ion-icon>
                                    <?php echo ucfirst($reg['registration_status']); ?>
                                </div>
                            </div>

                            <div class="card-content">
                                <h3 class="tournament-title">
                                    <?php echo htmlspecialchars($reg['tournament_name']); ?>
                                </h3>
                                <p class="game-name"><?php echo htmlspecialchars($reg['game_name']); ?></p>
                                
                                <div class="registration-info">
                                    <div class="info-item">
                                        <ion-icon name="people-outline"></ion-icon>
                                        <span><?php echo htmlspecialchars($reg['team_name']); ?></span>
                                        <?php if ($reg['is_captain']): ?>
                                            <span class="badge bg-primary">Captain</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Member</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="info-item">
                                        <ion-icon name="game-controller-outline"></ion-icon>
                                        <span><?php echo htmlspecialchars($reg['mode']); ?></span>
                                    </div>
                                </div>

                                <div class="tournament-dates">
                                    <div class="registered-on">
                                        <ion-icon name="calendar-outline"></ion-icon>
                                        Registered: <?php echo date('M d, Y', strtotime($reg['registration_date'])); ?>
                                    </div>
                                    <div class="tournament-starts">
                                        <ion-icon name="time-outline"></ion-icon>
                                        Starts: <?php echo date('M d, Y', strtotime($reg['playing_start_date'])); ?>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <?php if ($reg['registration_status'] === 'approved'): ?>
                                        <a href="match-schedule.php?tournament_id=<?php echo $reg['tournament_id']; ?>&team_id=<?php echo $reg['team_id']; ?>" class="btn btn-primary">
                                            <ion-icon name="calendar-outline"></ion-icon>
                                            Match Schedule
                                        </a>
                                        <?php if ($reg['is_captain']): ?>
                                            <a href="../teams/yourteams.php?tab=tournament&team_id=<?php echo $reg['team_id']; ?>" 
                                               class="btn btn-secondary">
                                                <ion-icon name="settings-outline"></ion-icon>
                                                Manage Team
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="details.php?id=<?php echo $reg['tournament_id']; ?>" class="btn btn-primary">
                                            <ion-icon name="information-circle-outline"></ion-icon>
                                            View Details
                                        </a>
                                        <?php if ($reg['registration_status'] === 'pending'): ?>
                                            <button class="btn btn-secondary" disabled>
                                                <ion-icon name="hourglass-outline"></ion-icon>
                                                Waiting for Approval
                                            </button>
                                        <?php elseif ($reg['registration_status'] === 'rejected'): ?>
                                            <a href="register_<?php echo strtolower($reg['mode']); ?>.php?id=<?php echo $reg['tournament_id']; ?>" class="btn btn-secondary">
                                                <ion-icon name="refresh-outline"></ion-icon>
                                                Register Again
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<style>
/* Additional styles specific to this page */
.title-underline {
    width: 60px;
    height: 4px;
    background: var(--neon-green);
    margin: -1rem auto 3rem;
    border-radius: 2px;
}

.no-registrations {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}

.no-registrations-content {
    text-align: center;
    max-width: 400px;
    padding: 3rem;
}

.browse-btn {
    display: inline-block;
    background: var(--neon-green);
    color: var(--raisin-black-1);
    padding: 1rem 2rem;
    border-radius: 100px;
    font-weight: 600;
    text-decoration: none;
    margin-top: 2rem;
    transition: var(--card-transition);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.browse-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--neon-green-glow);
}

.large-icon {
    font-size: 4rem;
    color: var(--neon-green);
    margin-bottom: 1.5rem;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .no-registrations {
        min-height: 300px;
    }

    .no-registrations-content {
        padding: 2rem 1rem;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?> 