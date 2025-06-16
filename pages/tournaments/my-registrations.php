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

<main>
    <section class="registrations-section" style="padding: 120px 0 60px;">
        <div class="container">
            <h2 class="section-title text-center mb-5">My Tournament Registrations</h2>
            
            <div class="row g-4">
                <?php if (empty($registrations)): ?>
                    <div class="col-12 text-center">
                        <div class="no-registrations">
                            <ion-icon name="trophy-outline" class="large-icon"></ion-icon>
                            <h3>No Tournament Registrations</h3>
                            <p>You haven't registered for any tournaments yet.</p>
                            <a href="index.php" class="btn btn-primary mt-3">Browse Tournaments</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($registrations as $reg): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="registration-card">
                                <div class="card-banner">
                                    <img src="<?php echo htmlspecialchars($reg['banner_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($reg['tournament_name']); ?>" 
                                         class="tournament-banner">
                                    
                                    <div class="tournament-meta">
                                        <div class="prize-pool">
                                            <ion-icon name="trophy-outline"></ion-icon>
                                            <span><?php 
                                                echo $reg['prize_currency'] === 'USD' ? '$' : '₹';
                                                echo number_format($reg['prize_pool'], 2); 
                                            ?></span>
                                        </div>
                                        <div class="registration-status <?php echo strtolower($reg['registration_status']); ?>">
                                            <?php echo ucfirst($reg['registration_status']); ?>
                                        </div>
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
                                            Registered: <?php echo date('M d, Y', strtotime($reg['registration_date'])); ?>
                                        </div>
                                        <div class="tournament-starts">
                                            Starts: <?php echo date('M d, Y', strtotime($reg['playing_start_date'])); ?>
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <?php if ($reg['registration_status'] === 'approved'): ?>
                                            <a href="/KGX/pages/tournaments/match-schedule.php?tournament_id=<?php echo $reg['tournament_id']; ?>&team_id=<?php echo $reg['team_id']; ?>" class="btn btn-primary">
                                                Match Schedule
                                            </a>
                                            <?php if ($reg['is_captain']): ?>
                                                <a href="../teams/yourteams.php?tab=tournament&team_id=<?php echo $reg['team_id']; ?>" 
                                                   class="btn btn-secondary">
                                                    Manage Team
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="/KGX/pages/tournaments/details.php?id=<?php echo $reg['tournament_id']; ?>" class="btn btn-primary">
                                                View Details
                                            </a>
                                            <?php if ($reg['registration_status'] === 'pending'): ?>
                                                <button class="btn btn-secondary" disabled>
                                                    Waiting for Approval
                                                </button>
                                            <?php elseif ($reg['registration_status'] === 'rejected'): ?>
                                                <a href="/KGX/pages/tournaments/register_<?php echo strtolower($reg['mode']); ?>.php?id=<?php echo $reg['tournament_id']; ?>" class="btn btn-secondary">
                                                    Register Again
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<style>
.registrations-section {
    background: var(--raisin-black-1);
    color: var(--white);
}

.registration-card {
    background: var(--raisin-black-2);
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.3s ease;
    height: 100%;
}

.registration-card:hover {
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

.prize-pool {
    display: flex;
    align-items: center;
    gap: 5px;
}

.registration-status {
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 0.9rem;
}

.registration-status.pending {
    background: var(--orange);
}

.registration-status.approved {
    background: var(--green);
}

.registration-status.rejected {
    background: var(--red);
}

.card-content {
    padding: 20px;
}

.tournament-title {
    font-size: 1.25rem;
    margin-bottom: 5px;
    color: var(--white);
}

.game-name {
    color: var(--quick-silver);
    margin-bottom: 15px;
}

.registration-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--quick-silver);
}

.tournament-dates {
    font-size: 0.9rem;
    color: var(--quick-silver);
    margin-bottom: 20px;
}

.registered-on {
    color: var(--orange);
    margin-bottom: 5px;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.card-actions .btn {
    flex: 1;
    text-align: center;
    padding: 8px 16px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--orange);
    color: var(--white);
}

.btn-secondary {
    background: var(--raisin-black-3);
    color: var(--white);
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.no-registrations {
    padding: 40px;
    text-align: center;
}

.no-registrations .large-icon {
    font-size: 48px;
    color: var(--quick-silver);
    margin-bottom: 20px;
}

.no-registrations h3 {
    color: var(--white);
    margin-bottom: 10px;
}

.no-registrations p {
    color: var(--quick-silver);
}

.badge {
    font-size: 0.8rem;
    padding: 3px 8px;
    margin-left: 5px;
}
</style>

<?php require_once '../../includes/footer.php'; ?> 