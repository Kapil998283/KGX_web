<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';
require_once '../includes/tournament_notifications.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get tournament ID from URL
$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get tournament details
$stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tournament) {
    $_SESSION['error'] = "Tournament not found!";
    header("Location: tournaments.php");
    exit();
}

// Default placement points based on game type
$default_placement_points = [
    'PUBG' => [
        1 => 15, // 1st place: 15 points
        2 => 12, // 2nd place: 12 points
        3 => 10, // 3rd place: 10 points
        4 => 8,  // 4th place: 8 points
        5 => 6,  // 5th place: 6 points
        6 => 4,  // 6th place: 4 points
        7 => 2,  // 7th place: 2 points
        8 => 1   // 8th place: 1 point
    ],
    'BGMI' => [
        1 => 15,
        2 => 12,
        3 => 10,
        4 => 8,
        5 => 6,
        6 => 4,
        7 => 2,
        8 => 1
    ],
    'Free Fire' => [
        1 => 12,
        2 => 9,
        3 => 8,
        4 => 7,
        5 => 6,
        6 => 5,
        7 => 4,
        8 => 3,
        9 => 2,
        10 => 1
    ],
    'Call of Duty Mobile' => [
        1 => 15,
        2 => 12,
        3 => 10,
        4 => 8,
        5 => 6
    ]
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_day':
                    // Add new tournament day
                    $stmt = $conn->prepare("
                        INSERT INTO tournament_days (tournament_id, day_number, date)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $tournament_id,
                        $_POST['day_number'],
                        $_POST['date']
                    ]);
                    $day_id = $conn->lastInsertId();

                    // Add rounds for this day
                    $total_teams = (int)$_POST['total_teams'];
                    $rounds_count = (int)$_POST['rounds_count'];
                    $teams_per_round = ceil($total_teams / $rounds_count);

                    // Get placement points for this game
                    $placement_points = isset($default_placement_points[$tournament['game_name']]) 
                        ? json_encode($default_placement_points[$tournament['game_name']])
                        : json_encode($default_placement_points['PUBG']); // Default to PUBG points

                    for ($i = 1; $i <= $rounds_count; $i++) {
                        $stmt = $conn->prepare("
                            INSERT INTO tournament_rounds (
                                tournament_id, day_id, round_number, name,
                                start_time, teams_count, qualifying_teams,
                                round_format, map_name, kill_points,
                                placement_points, qualification_points
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )
                        ");
                        $stmt->execute([
                            $tournament_id,
                            $day_id,
                            $i,
                            "Round " . $i,
                            $_POST['start_time_' . $i],
                            $teams_per_round,
                            $_POST['qualifying_teams'],
                            'points',
                            $_POST['map_name_' . $i],
                            $_POST['kill_points'],
                            $placement_points,
                            $_POST['qualification_points']
                        ]);
                    }
                    $_SESSION['success'] = "Tournament day and rounds added successfully!";
                    break;

                case 'update_results':
                    $round_id = $_POST['round_id'];
                    $team_results = $_POST['team_results'];

                    // Get round details
                    $stmt = $conn->prepare("
                        SELECT tr.name as round_name, t.name as tournament_name
                        FROM tournament_rounds tr
                        JOIN tournaments t ON tr.tournament_id = t.id
                        WHERE tr.id = ?
                    ");
                    $stmt->execute([$round_id]);
                    $round = $stmt->fetch(PDO::FETCH_ASSOC);

                    $notifications = new TournamentNotifications($conn);

                    foreach ($team_results as $team_id => $result) {
                        // Calculate points
                        $kill_points = $result['kills'] * $_POST['kill_points'];
                        
                        // Get placement points from the round settings
                        $stmt = $conn->prepare("SELECT placement_points FROM tournament_rounds WHERE id = ?");
                        $stmt->execute([$round_id]);
                        $roundSettings = $stmt->fetch(PDO::FETCH_ASSOC);
                        $placement_points_array = json_decode($roundSettings['placement_points'], true);
                        
                        // Calculate placement points
                        $placement_points = isset($placement_points_array[$result['placement']]) 
                            ? $placement_points_array[$result['placement']] 
                            : 0;

                        // Calculate bonus points (qualification points if team qualifies)
                        $bonus_points = ($result['status'] === 'qualified') ? $_POST['qualification_points'] : 0;
                        
                        // Calculate total points
                        $total_points = $kill_points + $placement_points + $bonus_points;

                        // Update team's total score
                        $stmt = $conn->prepare("
                            UPDATE teams 
                            SET total_score = total_score + ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$total_points, $team_id]);

                        $stmt = $conn->prepare("
                            UPDATE round_teams 
                            SET placement = ?,
                                kills = ?,
                                kill_points = ?,
                                placement_points = ?,
                                bonus_points = ?,
                                total_points = ?,
                                status = ?
                            WHERE round_id = ? AND team_id = ?
                        ");
                        $stmt->execute([
                            $result['placement'],
                            $result['kills'],
                            $kill_points,
                            $placement_points,
                            $bonus_points,
                            $total_points,
                            $result['status'],
                            $round_id,
                            $team_id
                        ]);

                        // Send notifications
                        $notifications->roundResults(
                            $team_id,
                            $round['tournament_name'],
                            $round['round_name'],
                            $result['placement'],
                            $result['kills'],
                            $total_points
                        );

                        // Send qualification/elimination notification
                        if ($result['status'] === 'qualified') {
                            $notifications->teamQualified(
                                $team_id,
                                $round['tournament_name'],
                                $round['round_name']
                            );
                        } elseif ($result['status'] === 'eliminated') {
                            $notifications->teamEliminated(
                                $team_id,
                                $round['tournament_name'],
                                $round['round_name']
                            );
                        }
                    }
                    $_SESSION['success'] = "Round results updated successfully!";
                    break;
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: tournament-schedule.php?id=" . $tournament_id);
    exit();
}

// Get tournament rounds with all necessary information
$stmt = $conn->prepare("
    SELECT 
        r.*,
        COUNT(rt.id) as registered_teams,
        GROUP_CONCAT(
            JSON_OBJECT(
                'team_id', rt.team_id,
                'placement', rt.placement,
                'kills', rt.kills,
                'total_points', rt.total_points,
                'status', rt.status
            )
        ) as teams_data
    FROM tournament_rounds r
    LEFT JOIN round_teams rt ON r.id = rt.round_id
    WHERE r.tournament_id = ?
    GROUP BY r.id
    ORDER BY r.round_number
");
$stmt->execute([$tournament_id]);
$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the latest day number
$stmt = $conn->prepare("
    SELECT MAX(day_number) as last_day
    FROM tournament_days
    WHERE tournament_id = ?
");
$stmt->execute([$tournament_id]);
$lastDay = $stmt->fetch(PDO::FETCH_ASSOC);
$nextDayNumber = ($lastDay['last_day'] ?? 0) + 1;

// Get total qualified teams from previous day
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT rt.team_id) as qualified_teams
    FROM tournament_days td
    JOIN tournament_rounds tr ON td.id = tr.day_id
    JOIN round_teams rt ON tr.id = rt.round_id
    WHERE td.tournament_id = ? 
    AND td.day_number = ?
    AND rt.status = 'qualified'
");
$stmt->execute([$tournament_id, $nextDayNumber - 1]);
$qualifiedTeams = $stmt->fetch(PDO::FETCH_ASSOC);
$totalTeams = $qualifiedTeams['qualified_teams'] ?? 0;

// If it's day 1, get total registered teams
if ($nextDayNumber === 1) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_teams
        FROM tournament_registrations
        WHERE tournament_id = ? AND status = 'approved'
    ");
    $stmt->execute([$tournament_id]);
    $registeredTeams = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTeams = $registeredTeams['total_teams'];
}

// Get all registered teams for team selection
$stmt = $conn->prepare("
    SELECT t.*, tr.status as registration_status
    FROM teams t
    INNER JOIN tournament_registrations tr ON t.id = tr.team_id
    WHERE tr.tournament_id = ? AND tr.status = 'approved'
    ORDER BY t.name
");
$stmt->execute([$tournament_id]);
$allTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teams for each round
$roundTeams = [];
if ($rounds) {
    foreach ($rounds as $round) {
        $stmt = $conn->prepare("
            SELECT t.*, rt.status
            FROM teams t
            INNER JOIN round_teams rt ON t.id = rt.team_id
            WHERE rt.round_id = ?
            ORDER BY t.name
        ");
        $stmt->execute([$round['id']]);
        $roundTeams[$round['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include 'includes/admin-header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1>Tournament Schedule</h1>
                    <h5 class="text-muted"><?php echo htmlspecialchars($tournament['name'] ?? ''); ?> (<?php echo htmlspecialchars($tournament['game_name'] ?? ''); ?>)</h5>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDayModal">
                    <i class="bi bi-plus-circle"></i> Add Tournament Day
                </button>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Round</th>
                            <th>Name</th>
                            <th>Time</th>
                            <th>Map</th>
                            <th>Teams</th>
                            <th>Points System</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rounds): foreach ($rounds as $round): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($round['round_number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($round['name'] ?? ''); ?></td>
                                <td><?php echo $round['start_time'] ? date('H:i', strtotime($round['start_time'])) : ''; ?></td>
                                <td><?php echo htmlspecialchars($round['map_name'] ?? ''); ?></td>
                                <td>
                                    <?php if (isset($round['teams_count'])): ?>
                                        <?php echo (int)$round['registered_teams']; ?> / <?php echo (int)$round['teams_count']; ?>
                                        <br>
                                        <small class="text-muted">Qualifying: <?php echo (int)$round['qualifying_teams']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($round['kill_points'])): ?>
                                        Kill: <?php echo (int)$round['kill_points']; ?> pts
                                        <br>
                                        Position: 
                                        <?php 
                                        $placement_points = json_decode($round['placement_points'] ?? '{}', true);
                                        echo isset($placement_points[1]) ? "1st: {$placement_points[1]} pts" : '';
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($round['status'])): ?>
                                        <span class="badge bg-<?php 
                                            echo match($round['status']) {
                                                'upcoming' => 'primary',
                                                'in_progress' => 'success',
                                                'completed' => 'secondary',
                                                default => 'info'
                                            };
                                        ?>">
                                            <?php echo ucfirst($round['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($round['id'])): ?>
                                        <button class="btn btn-sm btn-primary" onclick="assignTeams(<?php echo htmlspecialchars(json_encode($round)); ?>)">
                                            <i class="bi bi-people"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="updateResults(<?php echo htmlspecialchars(json_encode($round)); ?>)">
                                            <i class="bi bi-trophy"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info" onclick="updateStatus(<?php echo $round['id']; ?>, '<?php echo htmlspecialchars($round['status']); ?>')">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Add Day Modal -->
<div class="modal fade" id="addDayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Tournament Day <?php echo $nextDayNumber; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addDayForm" method="POST">
                    <input type="hidden" name="action" value="add_day">
                    <input type="hidden" name="day_number" value="<?php echo $nextDayNumber; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Total Teams</label>
                            <input type="number" class="form-control" name="total_teams" value="<?php echo $totalTeams; ?>" readonly>
                            <small class="text-muted">
                                <?php if ($nextDayNumber === 1): ?>
                                    Total registered teams
                                <?php else: ?>
                                    Teams qualified from Day <?php echo $nextDayNumber - 1; ?>
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Number of Rounds</label>
                            <input type="number" class="form-control" name="rounds_count" required min="1" onchange="generateRoundInputs(this.value, <?php echo $totalTeams; ?>)">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Qualifying Teams per Round</label>
                            <input type="number" class="form-control" name="qualifying_teams" required min="1">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Points per Kill</label>
                            <input type="number" class="form-control" name="kill_points" value="2" required min="1">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Qualification Bonus Points</label>
                            <input type="number" class="form-control" name="qualification_points" value="10" required min="0">
                        </div>

                        <div id="roundsContainer" class="col-12">
                            <!-- Round inputs will be generated here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addDayForm" class="btn btn-primary">Add Day</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Round Teams Modal -->
<div class="modal fade" id="editRoundTeamsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Round Teams</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRoundTeamsForm" method="POST" action="update_round_teams.php">
                    <input type="hidden" name="round_id" id="edit_round_id">
                    <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">

                    <div class="mb-3">
                        <label class="form-label">Select Teams for this Round</label>
                        <div class="row" id="teamSelectionContainer">
                            <!-- Teams will be loaded here -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editRoundTeamsForm" class="btn btn-primary">Save Teams</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Results Modal -->
<div class="modal fade" id="updateResultsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Round Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateResultsForm" method="POST">
                    <input type="hidden" name="action" value="update_results">
                    <input type="hidden" name="round_id" id="results_round_id">
                    <input type="hidden" name="kill_points" id="results_kill_points">
                    <input type="hidden" name="qualification_points" id="results_qualification_points">

                    <div class="alert alert-info">
                        <strong>Note:</strong>
                        <ul>
                            <li>Enter placement (1st = 1, 2nd = 2, etc.)</li>
                            <li>Enter total kills for each team</li>
                            <li>Select status:
                                <ul>
                                    <li><strong>Selected:</strong> Team is in the round</li>
                                    <li><strong>Eliminated:</strong> Team did not qualify</li>
                                    <li><strong>Qualified:</strong> Team moves to next round</li>
                                </ul>
                            </li>
                        </ul>
                    </div>

                    <div id="resultsContainer">
                        <!-- Team results will be loaded here -->
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="updateResultsForm" class="btn btn-primary">Save Results</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Round Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" name="round_id" id="status_round_id">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="status_select">
                            <option value="upcoming">Upcoming</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveStatus()">Save Status</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function generateRoundInputs(count, totalTeams) {
    const container = document.getElementById('roundsContainer');
    container.innerHTML = '';
    const teamsPerRound = Math.ceil(totalTeams / count);

    for (let i = 1; i <= count; i++) {
        container.innerHTML += `
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <h5>Round ${i}</h5>
                    <small class="text-muted">Recommended teams: ${teamsPerRound}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Time</label>
                    <input type="time" class="form-control" name="start_time_${i}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Map</label>
                    <input type="text" class="form-control" name="map_name_${i}" required>
                </div>
            </div>
        `;
    }
}

function assignTeams(round) {
    if (!round) return;
    
    document.getElementById('edit_round_id').value = round.id;

    // Load teams for selection
    fetch(`get_available_teams.php?round_id=${round.id}&tournament_id=<?php echo $tournament_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            const container = document.getElementById('teamSelectionContainer');
            container.innerHTML = data.teams.map(team => `
                <div class="col-md-4 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                            name="selected_teams[]" 
                            value="${team.id}" 
                            id="team_${team.id}"
                            ${team.is_selected ? 'checked' : ''}>
                        <label class="form-check-label" for="team_${team.id}">
                            ${team.name}
                        </label>
                    </div>
                </div>
            `).join('');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load teams');
        });

    // Show the modal
    new bootstrap.Modal(document.getElementById('editRoundTeamsModal')).show();
}

// Add form submission handler
document.getElementById('editRoundTeamsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_round_teams.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }
        
        // Close the modal
        bootstrap.Modal.getInstance(document.getElementById('editRoundTeamsModal')).hide();
        
        // Show success message and reload the page
        alert(data.message);
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update teams');
    });
});

function updateResults(round) {
    if (!round) return;
    
    document.getElementById('results_round_id').value = round.id;
    document.getElementById('results_kill_points').value = round.kill_points;
    document.getElementById('results_qualification_points').value = round.qualification_points;

    // Load existing results
    fetch(`get_round_results.php?round_id=${round.id}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('resultsContainer');
            container.innerHTML = data.map((team, index) => `
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">${team.name}</label>
                        <input type="hidden" name="team_results[${team.id}][team_id]" value="${team.id}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Placement</label>
                        <input type="number" class="form-control" name="team_results[${team.id}][placement]" value="${team.placement || ''}" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kills</label>
                        <input type="number" class="form-control" name="team_results[${team.id}][kills]" value="${team.kills || 0}" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="team_results[${team.id}][status]">
                            <option value="selected" ${team.status === 'selected' ? 'selected' : ''}>Selected</option>
                            <option value="eliminated" ${team.status === 'eliminated' ? 'selected' : ''}>Eliminated</option>
                            <option value="qualified" ${team.status === 'qualified' ? 'selected' : ''}>Qualified</option>
                        </select>
                    </div>
                </div>
            `).join('');
        });

    new bootstrap.Modal(document.getElementById('updateResultsModal')).show();
}

function updateStatus(roundId, currentStatus) {
    document.getElementById('status_round_id').value = roundId;
    document.getElementById('status_select').value = currentStatus;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

function saveStatus() {
    const roundId = document.getElementById('status_round_id').value;
    const status = document.getElementById('status_select').value;

    fetch('update_round_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            round_id: roundId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload(); // Reload to show updated status
        } else {
            alert('Error updating status: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update status. Please try again.');
    });
}
</script>

<?php include 'includes/admin-footer.php'; ?> 