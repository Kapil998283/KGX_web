<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Get tournament_id and either team_id or user_id from URL
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Must have tournament_id and either team_id or user_id
if (!$tournament_id || (!$team_id && !$user_id)) {
    header("Location: my-registrations.php");
    exit();
}

// Determine if this is a solo or team tournament
$is_solo = !empty($user_id);

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get tournament details
$tournament_sql = "SELECT name, game_name, mode, playing_start_date FROM tournaments WHERE id = ?";
$tournament_stmt = $db->prepare($tournament_sql);
$tournament_stmt->execute([$tournament_id]);
$tournament = $tournament_stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    header("Location: my-registrations.php");
    exit();
}

// Get tournament days and their rounds
$days_sql = "
    SELECT 
        td.*,
        tr.id as round_id,
        tr.round_number,
        tr.name as round_name,
        tr.teams_count,
        tr.qualifying_teams,
        tr.kill_points,
        tr.qualification_points,
        tr.map_name,
        tr.start_time,
        tr.placement_points,
        tr.status,
        tr.room_code,
        tr.room_password
    FROM tournament_days td
    LEFT JOIN tournament_rounds tr ON td.id = tr.day_id
    WHERE td.tournament_id = ?
    ORDER BY td.day_number, tr.round_number";

$days_stmt = $db->prepare($days_sql);
$days_stmt->execute([$tournament_id]);
$rounds_data = $days_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize rounds by days
$days = [];
foreach ($rounds_data as $row) {
    $day_number = $row['day_number'];
    if (!isset($days[$day_number])) {
        $days[$day_number] = [
            'date' => $row['date'],
            'rounds' => []
        ];
    }
    if ($row['round_id']) {
        $days[$day_number]['rounds'][] = [
            'round_number' => $row['round_number'],
            'name' => $row['round_name'],
            'teams_count' => $row['teams_count'],
            'qualifying_teams' => $row['qualifying_teams'],
            'kill_points' => $row['kill_points'],
            'qualification_points' => $row['qualification_points'],
            'map_name' => $row['map_name'],
            'start_time' => $row['start_time'],
            'placement_points' => json_decode($row['placement_points'], true),
            'round_id' => $row['round_id'],
            'status' => $row['status'],
            'room_code' => $row['room_code'],
            'room_password' => $row['room_password']
        ];
    }
}

// Get total registered teams for the tournament
$total_teams_sql = "SELECT COUNT(*) as count FROM tournament_registrations WHERE tournament_id = ? AND status = 'approved'";
$total_teams_stmt = $db->prepare($total_teams_sql);
$total_teams_stmt->execute([$tournament_id]);
$total_teams = $total_teams_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<main>
    <section class="tournament-schedule-section">
        <div class="container">
            <div class="tournament-header mb-5">
                <div class="game-banner">
                    <div class="game-overlay"></div>
                    <h2 class="tournament-title"><?php echo htmlspecialchars($tournament['name']); ?></h2>
                    <div class="game-name">
                        <ion-icon name="game-controller"></ion-icon>
                        <span><?php echo htmlspecialchars($tournament['game_name']); ?></span>
                    </div>
                </div>
            </div>

            <?php if (empty($days)): ?>
                <div class="no-schedule">
                    <ion-icon name="calendar-outline" class="large-icon"></ion-icon>
                    <h3>Tournament Schedule Not Available</h3>
                    <p>The tournament schedule will be announced soon.</p>
                </div>
            <?php else: ?>
                <div class="tournament-progress">
                    <div class="progress-bar">
                        <?php foreach ($days as $day_number => $day): ?>
                            <div class="progress-step <?php echo $day_number == 1 ? 'active' : ''; ?>">
                                <div class="step-number">Day <?php echo $day_number; ?></div>
                                <div class="step-date"><?php echo date('M d', strtotime($day['date'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php foreach ($days as $day_number => $day): ?>
                    <div class="day-section mb-5">
                        <h3 class="day-title">
                            Day <?php echo $day_number; ?> - <?php echo date('M d, Y', strtotime($day['date'])); ?>
                        </h3>
                        
                        <?php foreach ($day['rounds'] as $round): ?>
                            <div class="round-card">
                                <div class="round-header">
                                    <div class="round-info">
                                        <h4><?php echo htmlspecialchars($round['name']); ?></h4>
                                        <span class="round-status <?php echo $round['status']; ?>">
                                            <?php echo ucfirst($round['status'] ?? 'Upcoming'); ?>
                                        </span>
                                    </div>
                                    <div class="round-timing">
                                        <ion-icon name="time-outline"></ion-icon>
                                        <span class="time"><?php echo date('h:i A', strtotime($round['start_time'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="round-details">
                                    <div class="detail-item">
                                        <ion-icon name="people-outline"></ion-icon>
                                        <span>Teams: <?php echo $round['teams_count']; ?></span>
                                    </div>
                                    <div class="detail-item qualifying">
                                        <ion-icon name="trophy-outline"></ion-icon>
                                        <span>Qualifying: <?php echo $round['qualifying_teams']; ?> teams</span>
                                    </div>
                                    <div class="detail-item">
                                        <ion-icon name="map-outline"></ion-icon>
                                        <span>Map: <?php echo htmlspecialchars($round['map_name']); ?></span>
                                    </div>
                                    <?php if ($round['room_code'] && $round['room_password']): ?>
                                    <div class="detail-item room-details">
                                        <ion-icon name="key-outline"></ion-icon>
                                        <span>
                                            Room: <?php echo htmlspecialchars($round['room_code']); ?> 
                                            (Pass: <?php echo htmlspecialchars($round['room_password']); ?>)
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="points-system">
                                    <h5>Points System</h5>
                                    <div class="points-details">
                                        <div class="point-item">
                                            <span class="label">Kill Points:</span>
                                            <span class="value"><?php echo $round['kill_points']; ?> per kill</span>
                                        </div>
                                        <div class="point-item">
                                            <span class="label">Qualification Bonus:</span>
                                            <span class="value"><?php echo $round['qualification_points']; ?> points</span>
                                        </div>
                                        <?php if ($round['placement_points']): ?>
                                            <div class="placement-points">
                                                <span class="label">Placement Points:</span>
                                                <div class="placement-list">
                                                    <?php foreach ($round['placement_points'] as $place => $points): ?>
                                                        <span class="placement">#<?php echo $place; ?>: <?php echo $points; ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="round-actions">
                                    <button class="btn-view-teams" onclick="viewTeams(<?php echo $round['round_id']; ?>)">
                                        <ion-icon name="people"></ion-icon> View Teams
                                    </button>
                                    <?php if ($round['status'] === 'completed'): ?>
                                        <button class="btn-qualified" onclick="viewQualifiedTeams(<?php echo $round['round_id']; ?>)">
                                            <ion-icon name="trophy"></ion-icon> View Qualified Teams
                                        </button>
                                    <?php endif; ?>
                                    <?php
                                    // Check if there's a live stream for this round
                                    $stream_sql = "SELECT * FROM live_streams WHERE round_id = ? AND status = 'live' ORDER BY created_at DESC LIMIT 1";
                                    $stream_stmt = $db->prepare($stream_sql);
                                    $stream_stmt->execute([$round['round_id']]);
                                    $stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if($stream): ?>
                                        <button class="btn-live-match" onclick="window.location.href='/KGX/earn-coins/watchstream.php?stream_id=<?php echo $stream['id']; ?>'">
                                            <ion-icon name="videocam"></ion-icon> Live Match
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Regular Teams Modal -->
<div class="modal fade" id="teamsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Teams in Round</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="teamsModalBody">
                <!-- Teams will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Qualified Teams Modal -->
<div class="modal fade" id="qualifiedTeamsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Qualified Teams</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="qualifiedTeamsModalBody">
                <!-- Qualified teams will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.tournament-schedule-section {
    background: var(--raisin-black-1);
    color: var(--white);
}

.tournament-header {
    text-align: center;
    margin-bottom: 3rem !important;
}

.game-banner {
    position: relative;
    background: linear-gradient(145deg, var(--raisin-black-2), var(--raisin-black-3));
    border-radius: 20px;
    padding: 3rem 2rem;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.game-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('/KGX/assets/images/pattern.png') repeat;
    opacity: 0.1;
    animation: moveBackground 20s linear infinite;
}

.game-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255, 69, 0, 0.2), rgba(0, 0, 0, 0.4));
}

.tournament-title {
    position: relative;
    font-size: 3rem;
    margin-bottom: 1.5rem;
    color: var(--white);
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    font-weight: 700;
}

.game-name {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 69, 0, 0.9);
    padding: 12px 30px;
    border-radius: 50px;
    color: var(--white);
    font-size: 1.4rem;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(255, 69, 0, 0.3);
    transition: all 0.3s ease;
}

.game-name:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 69, 0, 0.4);
}

.game-name ion-icon {
    font-size: 1.8rem;
}

@keyframes moveBackground {
    from {
        background-position: 0 0;
    }
    to {
        background-position: 100% 100%;
    }
}

@media (max-width: 768px) {
    .tournament-title {
        font-size: 2rem;
    }

    .game-name {
        font-size: 1.2rem;
        padding: 10px 20px;
    }

    .game-banner {
        padding: 2rem 1rem;
    }
}

.day-title {
    color: var(--white);
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--orange);
}

.round-card {
    background: var(--raisin-black-2);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 1.5rem;
    transition: transform 0.3s ease;
}

.round-card:hover {
    transform: translateY(-2px);
}

.round-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.round-header h4 {
    color: var(--orange);
    margin: 0;
}

.time {
    color: var(--quick-silver);
    font-size: 0.9rem;
}

.round-details {
    display: flex;
    gap: 20px;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--quick-silver);
}

.detail-item.qualifying {
    color: var(--green);
}

.detail-item.room-details {
    color: var(--orange);
    background: rgba(255, 69, 0, 0.1);
    padding: 8px 12px;
    border-radius: 5px;
    border: 1px solid rgba(255, 69, 0, 0.2);
}

.detail-item.room-details ion-icon {
    font-size: 1.2rem;
    margin-right: 5px;
}

.detail-item ion-icon {
    font-size: 1.2rem;
}

.points-system {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    padding: 15px;
}

.points-system h5 {
    color: var(--white);
    margin-bottom: 1rem;
}

.points-details {
    display: grid;
    gap: 10px;
}

.point-item {
    display: flex;
    justify-content: space-between;
    color: var(--quick-silver);
}

.placement-points {
    margin-top: 10px;
}

.placement-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}

.placement {
    background: rgba(255, 255, 255, 0.1);
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 0.9rem;
}

.no-schedule {
    text-align: center;
    padding: 40px;
}

.no-schedule .large-icon {
    font-size: 48px;
    color: var(--quick-silver);
    margin-bottom: 20px;
}

.no-schedule h3 {
    color: var(--white);
    margin-bottom: 10px;
}

.no-schedule p {
    color: var(--quick-silver);
}

@media (max-width: 768px) {
    .tournament-meta {
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .round-details {
        flex-direction: column;
        gap: 10px;
    }

    .points-details {
        grid-template-columns: 1fr;
    }
}

.tournament-progress {
    margin-bottom: 2rem;
}

.progress-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    position: relative;
}

.progress-bar::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--quick-silver);
    z-index: 1;
}

.progress-step {
    position: relative;
    z-index: 2;
    background: var(--raisin-black-2);
    padding: 10px;
    border-radius: 10px;
    text-align: center;
    min-width: 100px;
}

.progress-step.active {
    background: var(--orange);
}

.step-number {
    font-weight: bold;
    color: var(--white);
}

.step-date {
    font-size: 0.8rem;
    color: var(--quick-silver);
}

.day-teams {
    font-size: 1rem;
    color: var(--quick-silver);
    margin-left: 15px;
}

.round-status {
    font-size: 0.8rem;
    padding: 3px 8px;
    border-radius: 15px;
    margin-left: 10px;
}

.round-status.upcoming {
    background: var(--blue);
    color: var(--white);
}

.round-status.in_progress {
    background: var(--orange);
    color: var(--white);
}

.round-status.completed {
    background: var(--green);
    color: var(--white);
}

.round-timing {
    display: flex;
    align-items: center;
    gap: 5px;
}

.round-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-view-teams, .btn-qualified, .btn-live-match {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-view-teams {
    background: var(--blue);
    color: var(--white);
}

.btn-qualified {
    background: var(--green);
    color: var(--white);
}

.btn-live-match {
    background: var(--orange);
    color: var(--white);
}

.btn-view-teams:hover, .btn-qualified:hover, .btn-live-match:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

/* Modal Styles */
.modal-content {
    background: linear-gradient(145deg, var(--raisin-black-1), var(--raisin-black-2));
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.modal-header {
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
}

.modal-header .modal-title {
    color: var(--orange);
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-header .btn-close {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.modal-header .btn-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem;
    background: rgba(0, 0, 0, 0.1);
}

/* Enhanced Team Grid */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    padding: 0.5rem;
}

/* Enhanced Team Cards */
.team-card {
    background: linear-gradient(145deg, var(--raisin-black-2), var(--raisin-black-1));
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.team-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--blue), var(--orange));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.team-card:hover::before {
    opacity: 1;
}

.team-card.qualified {
    border: none;
    background: linear-gradient(145deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.2));
}

.team-card.qualified::before {
    background: linear-gradient(90deg, var(--green), var(--blue));
}

/* Enhanced Team Header */
.team-header {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative;
}

.team-avatar {
    width: 80px;
    height: 80px;
    border-radius: 15px;
    overflow: hidden;
    border: 3px solid var(--orange);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.team-card:hover .team-avatar {
    transform: scale(1.05);
}

.team-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.3s ease;
}

.team-card:hover .team-avatar img {
    transform: scale(1.1);
}

.team-main-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.team-name {
    color: var(--white);
    font-size: 1.4rem;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

/* Enhanced Team Stats */
.team-stats {
    display: flex;
    gap: 1rem;
    margin-top: 0.8rem;
    flex-wrap: wrap;
}

.stat {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    color: var(--white);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
}

.stat:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.stat i {
    color: var(--orange);
}

/* Enhanced Team Details */
.team-details {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    padding: 1.2rem;
    margin-top: 1rem;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    color: var(--quick-silver);
    margin-bottom: 0.8rem;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.detail-row:hover {
    background: rgba(255, 255, 255, 0.05);
}

.detail-row i {
    color: var(--orange);
    width: 20px;
    font-size: 1.1rem;
}

.team-members {
    margin-top: 1.2rem;
    background: rgba(255, 255, 255, 0.05);
    padding: 1rem;
    border-radius: 8px;
}

.team-members small {
    color: var(--orange);
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.team-members p {
    color: var(--white);
    font-size: 0.95rem;
    margin: 0;
    line-height: 1.6;
}

/* Status Badge Styles */
.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.bg-success {
    background: linear-gradient(45deg, #28a745, #20c997);
}

.bg-danger {
    background: linear-gradient(45deg, #dc3545, #f86);
}

.bg-primary {
    background: linear-gradient(45deg, #007bff, #00bfff);
}

.bg-secondary {
    background: linear-gradient(45deg, #6c757d, #868e96);
}

/* Modal Footer */
.modal-footer {
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.2rem;
}

.btn-secondary {
    background: linear-gradient(145deg, var(--raisin-black-2), var(--raisin-black-1));
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--white);
    padding: 0.8rem 1.5rem;
    border-radius: 25px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: linear-gradient(145deg, var(--raisin-black-1), var(--raisin-black-2));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Scrollbar Styles */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--orange);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--blue);
}

/* Responsive Design */
@media (max-width: 768px) {
    .team-grid {
        grid-template-columns: 1fr;
    }

    .team-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1rem;
    }

    .team-avatar {
        width: 100px;
        height: 100px;
    }

    .team-stats {
        justify-content: center;
    }

    .detail-row {
        justify-content: center;
    }

    .team-members {
        text-align: center;
    }
}

/* Animation for Modal Opening */
.modal.fade .modal-dialog {
    transform: scale(0.95);
    opacity: 0;
    transition: all 0.3s ease;
}

.modal.show .modal-dialog {
    transform: scale(1);
    opacity: 1;
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function viewTeams(roundId) {
    fetch(`get_round_teams.php?round_id=${roundId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            const modalBody = document.getElementById('teamsModalBody');
            modalBody.innerHTML = `
                <div class="team-grid">
                    ${data.teams.map(team => `
                        <div class="team-card">
                            <div class="team-header">
                                <div class="team-avatar">
                                    <img src="${team.logo || '/KGX/assets/images/character-2.png'}" 
                                         alt="${team.team_name}"
                                         onerror="this.src='/KGX/assets/images/character-2.png'">
                                </div>
                                <div class="team-main-info">
                                    <h4 class="team-name">${team.team_name}</h4>
                                    <span class="badge bg-${getStatusColor(team.status)}">${team.status}</span>
                                </div>
                            </div>
                            <div class="team-details">
                                <div class="detail-row">
                                    <i class="fas fa-user-shield"></i>
                                    <span>Captain: ${team.captain_name}</span>
                                </div>
                                <div class="detail-row">
                                    <i class="fas fa-users"></i>
                                    <span>${team.member_count} Members</span>
                                </div>
                                <div class="team-members">
                                    <small>Team Members:</small>
                                    <p>${team.members || 'No members'}</p>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            const teamsModal = new bootstrap.Modal(document.getElementById('teamsModal'));
            teamsModal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'Failed to load teams. Please try again.');
        });
}

function viewQualifiedTeams(roundId) {
    fetch(`get_qualified_teams.php?round_id=${roundId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            const modalBody = document.getElementById('qualifiedTeamsModalBody');
            modalBody.innerHTML = `
                <div class="team-grid">
                    ${data.teams.map(team => `
                        <div class="team-card qualified">
                            <div class="team-header">
                                <div class="team-avatar">
                                    <img src="${team.logo || '/KGX/assets/images/character-2.png'}" 
                                         alt="${team.team_name}"
                                         onerror="this.src='/KGX/assets/images/character-2.png'">
                                </div>
                                <div class="team-main-info">
                                    <h4 class="team-name">${team.team_name}</h4>
                                    <div class="team-stats">
                                        <span class="stat">
                                            <i class="fas fa-trophy"></i>
                                            ${team.total_points} pts
                                        </span>
                                        <span class="stat">
                                            <i class="fas fa-crosshairs"></i>
                                            ${team.kills} kills
                                        </span>
                                        <span class="stat">
                                            <i class="fas fa-medal"></i>
                                            #${team.placement}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="team-details">
                                <div class="detail-row">
                                    <i class="fas fa-user-shield"></i>
                                    <span>Captain: ${team.captain_name}</span>
                                </div>
                                <div class="detail-row">
                                    <i class="fas fa-users"></i>
                                    <span>${team.member_count} Members</span>
                                </div>
                                <div class="team-members">
                                    <small>Team Members:</small>
                                    <p>${team.members || 'No members'}</p>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            const qualifiedTeamsModal = new bootstrap.Modal(document.getElementById('qualifiedTeamsModal'));
            qualifiedTeamsModal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'Failed to load qualified teams. Please try again.');
        });
}

function getStatusColor(status) {
    switch (status) {
        case 'qualified':
            return 'success';
        case 'eliminated':
            return 'danger';
        case 'selected':
            return 'primary';
        default:
            return 'secondary';
    }
}
</script>

<?php require_once '../../includes/header.php'; ?>

<link rel="stylesheet" href="../../assets/css/tournament/match-schedule.css">
