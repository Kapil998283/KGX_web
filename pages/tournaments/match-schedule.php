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
