<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Fetch user's tournament registrations (both solo and team)
$stmt = $db->prepare("
    (
        -- Solo registrations
        SELECT 
            t.id as tournament_id,
            t.name as tournament_name,
            t.game_name,
            t.mode,
            t.banner_image,
            t.playing_start_date,
            t.prize_pool,
            t.prize_currency,
            t.status,
            t.phase,
            NULL as team_id,
            NULL as team_name,
            tr.registration_date,
            tr.status as registration_status,
            0 as is_captain,
            'solo' as registration_type
        FROM tournament_registrations tr
        INNER JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.user_id = ? AND tr.team_id IS NULL
    )
    UNION ALL
    (
        -- Team registrations (as captain or member)
        SELECT 
            t.id as tournament_id,
            t.name as tournament_name,
            t.game_name,
            t.mode,
            t.banner_image,
            t.playing_start_date,
            t.prize_pool,
            t.prize_currency,
            t.status,
            t.phase,
            tm.team_id,
            team.name as team_name,
            tr.registration_date,
            tr.status as registration_status,
            CASE 
                WHEN tm.role = 'captain' THEN 1
                ELSE 0
            END as is_captain,
            'team' as registration_type
        FROM tournament_registrations tr
        INNER JOIN tournaments t ON tr.tournament_id = t.id
        INNER JOIN teams team ON tr.team_id = team.id
        INNER JOIN team_members tm ON team.id = tm.team_id
        WHERE tm.user_id = ? AND tm.status = 'active' AND tr.team_id IS NOT NULL
    )
    ORDER BY registration_date DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../../assets/css/tournament/registrations.css">

<main>
    <section class="registrations-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">My Tournament Registrations</h1>
                <div class="title-underline"></div>
            </div>
            
            <?php if (empty($registrations)): ?>
                <div class="no-registrations">
                    <div class="no-registrations-content">
                        <ion-icon name="trophy-outline" class="large-icon"></ion-icon>
                        <h3>No Tournament Registrations</h3>
                        <p>You haven't registered for any tournaments yet.</p>
                        <a href="index.php" class="browse-btn">
                            <ion-icon name="search-outline"></ion-icon>
                            Browse Tournaments
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
                                     class="tournament-banner"
                                     onerror="this.src='../../assets/images/default-tournament.jpg'">
                                
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
                                
                                <div class="tournament-meta">
                                    <div class="meta-item">
                                        <ion-icon name="game-controller-outline"></ion-icon>
                                        <span><?php echo htmlspecialchars($reg['game_name']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <ion-icon name="people-outline"></ion-icon>
                                        <span><?php echo htmlspecialchars($reg['mode']); ?></span>
                                    </div>
                                    <div class="meta-item prize">
                                        <ion-icon name="trophy-outline"></ion-icon>
                                        <span><?php 
                                            echo $reg['prize_currency'] === 'USD' ? '$' : '₹';
                                            echo number_format($reg['prize_pool'], 2); 
                                        ?></span>
                                    </div>
                                </div>

                                <?php if ($reg['registration_type'] === 'solo'): ?>
                                    <div class="team-info">
                                        <div class="team-name">
                                            <ion-icon name="person-outline"></ion-icon>
                                            <span>Solo Player</span>
                                            <span class="badge solo">Individual</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="team-info">
                                        <div class="team-name">
                                            <ion-icon name="shield-outline"></ion-icon>
                                            <span><?php echo htmlspecialchars($reg['team_name']); ?></span>
                                            <?php if ($reg['is_captain']): ?>
                                                <span class="badge captain">Team Captain</span>
                                            <?php else: ?>
                                                <span class="badge member">Team Member</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="tournament-dates">
                                    <div class="date-item">
                                        <ion-icon name="calendar-outline"></ion-icon>
                                        <span>Registered: <?php echo date('M d, Y', strtotime($reg['registration_date'])); ?></span>
                                    </div>
                                    <div class="date-item">
                                        <ion-icon name="time-outline"></ion-icon>
                                        <span>Starts: <?php echo date('M d, Y', strtotime($reg['playing_start_date'])); ?></span>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <?php if ($reg['registration_status'] === 'approved'): ?>
                                        <?php if ($reg['registration_type'] === 'solo'): ?>
                                            <a href="match-schedule.php?tournament_id=<?php echo $reg['tournament_id']; ?>&user_id=<?php echo $_SESSION['user_id']; ?>" 
                                               class="action-btn primary">
                                                <ion-icon name="calendar-outline"></ion-icon>
                                                Match Schedule
                                            </a>
                                        <?php else: ?>
                                            <a href="match-schedule.php?tournament_id=<?php echo $reg['tournament_id']; ?>&team_id=<?php echo $reg['team_id']; ?>" 
                                               class="action-btn primary">
                                                <ion-icon name="calendar-outline"></ion-icon>
                                                Match Schedule
                                            </a>
                                            <?php if ($reg['is_captain']): ?>
                                                <a href="../teams/yourteams.php?tab=tournament&team_id=<?php echo $reg['team_id']; ?>" 
                                                   class="action-btn secondary">
                                                    <ion-icon name="settings-outline"></ion-icon>
                                                    Manage Team
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="details.php?id=<?php echo $reg['tournament_id']; ?>" 
                                           class="action-btn primary">
                                            <ion-icon name="information-circle-outline"></ion-icon>
                                            View Details
                                        </a>
                                        <?php if ($reg['registration_status'] === 'pending'): ?>
                                            <button class="action-btn secondary" disabled>
                                                <ion-icon name="hourglass-outline"></ion-icon>
                                                Awaiting Approval
                                            </button>
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

<?php require_once '../../includes/footer.php'; ?> 