<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_round':
                    $stmt = $conn->prepare("
                        INSERT INTO tournament_rounds (
                            tournament_id, round_number, name, description,
                            start_time, end_time, players_count, qualifying_players,
                            round_format, map_name, special_rules, points_system
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $tournament_id,
                        $_POST['round_number'],
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['start_time'],
                        $_POST['end_time'],
                        $_POST['players_count'],
                        $_POST['qualifying_players'],
                        $_POST['round_format'],
                        $_POST['map_name'],
                        $_POST['special_rules'],
                        $_POST['points_system']
                    ]);
                    $_SESSION['success'] = "Round added successfully!";
                    break;

                case 'update_round':
                    $stmt = $conn->prepare("
                        UPDATE tournament_rounds SET
                            round_number = ?, name = ?, description = ?,
                            start_time = ?, end_time = ?, players_count = ?,
                            qualifying_players = ?, round_format = ?, map_name = ?,
                            special_rules = ?, points_system = ?
                        WHERE id = ? AND tournament_id = ?
                    ");
                    $stmt->execute([
                        $_POST['round_number'],
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['start_time'],
                        $_POST['end_time'],
                        $_POST['players_count'],
                        $_POST['qualifying_players'],
                        $_POST['round_format'],
                        $_POST['map_name'],
                        $_POST['special_rules'],
                        $_POST['points_system'],
                        $_POST['round_id'],
                        $tournament_id
                    ]);
                    $_SESSION['success'] = "Round updated successfully!";
                    break;

                case 'delete_round':
                    $stmt = $conn->prepare("DELETE FROM tournament_rounds WHERE id = ? AND tournament_id = ?");
                    $stmt->execute([$_POST['round_id'], $tournament_id]);
                    $_SESSION['success'] = "Round deleted successfully!";
                    break;
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: tournament-rounds.php?id=" . $tournament_id);
    exit();
}

// Get tournament rounds
$stmt = $conn->prepare("
    SELECT 
        r.*,
        COUNT(rt.id) as registered_teams
    FROM tournament_rounds r
    LEFT JOIN round_teams rt ON r.id = rt.round_id
    WHERE r.tournament_id = ?
    GROUP BY r.id
    ORDER BY r.round_number
");
$stmt->execute([$tournament_id]);
$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/admin-header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1>Tournament Rounds</h1>
                    <h5 class="text-muted"><?php echo htmlspecialchars($tournament['name']); ?> (<?php echo $tournament['game_name']; ?>)</h5>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoundModal">
                    <i class="bi bi-plus-circle"></i> Add Round
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
                            <th>Start Time</th>
                            <th>Format</th>
                            <th>Teams</th>
                            <th>Map</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rounds as $round): ?>
                            <tr>
                                <td><?php echo $round['round_number']; ?></td>
                                <td><?php echo htmlspecialchars($round['name']); ?></td>
                                <td><?php echo date('H:i', strtotime($round['start_time'])); ?></td>
                                <td><?php echo ucfirst($round['round_format']); ?></td>
                                <td>
                                    <?php echo $round['registered_teams']; ?> / <?php echo $round['teams_count']; ?>
                                    <br>
                                    <small class="text-muted">Qualifying: <?php echo $round['qualifying_teams']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($round['map_name']); ?></td>
                                <td>
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
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary me-1" onclick='editRound(<?php echo json_encode($round); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger me-1" onclick="deleteRound(<?php echo $round['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="manageRoomDetails(<?php echo $round['id']; ?>, '<?php echo htmlspecialchars($round['name']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Add Round Modal -->
<div class="modal fade" id="addRoundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Round</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRoundForm" method="POST" action="add_round.php">
                    <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Round Number</label>
                        <input type="number" class="form-control" name="round_number" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teams Count</label>
                        <input type="number" class="form-control" name="teams_count" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Qualifying Teams</label>
                        <input type="number" class="form-control" name="qualifying_teams" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Map</label>
                        <input type="text" class="form-control" name="map_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Points per Kill</label>
                        <input type="number" class="form-control" name="kill_points" value="2" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Qualification Bonus Points</label>
                        <input type="number" class="form-control" name="qualification_points" value="10" required min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Special Rules</label>
                        <textarea class="form-control" name="special_rules" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="addRoundForm" class="btn btn-primary">Add Round</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Round Modal -->
<div class="modal fade" id="editRoundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Round</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRoundForm" method="POST" action="update_round.php">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Round Number</label>
                        <input type="number" class="form-control" name="round_number" id="edit_round_number" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" id="edit_start_time" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teams Count</label>
                        <input type="number" class="form-control" name="teams_count" id="edit_teams_count" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Qualifying Teams</label>
                        <input type="number" class="form-control" name="qualifying_teams" id="edit_qualifying_teams" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Map</label>
                        <input type="text" class="form-control" name="map_name" id="edit_map_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Points per Kill</label>
                        <input type="number" class="form-control" name="kill_points" id="edit_kill_points" required min="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Qualification Bonus Points</label>
                        <input type="number" class="form-control" name="qualification_points" id="edit_qualification_points" required min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Special Rules</label>
                        <textarea class="form-control" name="special_rules" id="edit_special_rules" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="upcoming">Upcoming</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" form="editRoundForm" class="btn btn-primary">Update Round</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Room Details Modal -->
<div class="modal fade" id="roomDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Room Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="roomDetailsForm">
                    <input type="hidden" id="round_id" name="round_id">
                    <div class="mb-3">
                        <label for="room_code" class="form-label">Room Code</label>
                        <input type="text" class="form-control" id="room_code" name="room_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="room_password" class="form-label">Room Password</label>
                        <input type="text" class="form-control" id="room_password" name="room_password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveRoomDetails()">Save Room Details</button>
            </div>
        </div>
    </div>
</div>

<script>
function editRound(round) {
    document.getElementById('edit_id').value = round.id;
    document.getElementById('edit_round_number').value = round.round_number;
    document.getElementById('edit_name').value = round.name;
    document.getElementById('edit_description').value = round.description;
    document.getElementById('edit_start_time').value = round.start_time.substring(11, 16);
    document.getElementById('edit_teams_count').value = round.teams_count;
    document.getElementById('edit_qualifying_teams').value = round.qualifying_teams;
    document.getElementById('edit_map_name').value = round.map_name;
    document.getElementById('edit_kill_points').value = round.kill_points;
    document.getElementById('edit_qualification_points').value = round.qualification_points;
    document.getElementById('edit_special_rules').value = round.special_rules;
    document.getElementById('edit_status').value = round.status;

    new bootstrap.Modal(document.getElementById('editRoundModal')).show();
}

function deleteRound(roundId) {
    if (confirm('Are you sure you want to delete this round?')) {
        window.location.href = `delete_round.php?id=${roundId}&tournament_id=<?php echo $tournament_id; ?>`;
    }
}

function manageRoomDetails(roundId, roundName) {
    const modal = new bootstrap.Modal(document.getElementById('roomDetailsModal'));
    document.querySelector('#roomDetailsModal .modal-title').textContent = `Manage Room Details - ${roundName}`;
    document.getElementById('round_id').value = roundId;
    
    // Fetch existing room details
    fetch(`get_room_details.php?round_id=${roundId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('room_code').value = data.room_code || '';
            document.getElementById('room_password').value = data.room_password || '';
        });
    
    modal.show();
}

function saveRoomDetails() {
    const formData = new FormData(document.getElementById('roomDetailsForm'));
    
    fetch('save_room_details.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Room details saved successfully!');
            bootstrap.Modal.getInstance(document.getElementById('roomDetailsModal')).hide();
        } else {
            alert('Error saving room details: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error saving room details');
        console.error(error);
    });
}
</script>

<?php include 'includes/admin-footer.php'; ?> 