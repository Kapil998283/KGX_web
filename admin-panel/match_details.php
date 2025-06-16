<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';
include 'includes/admin-header.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get match ID and referring page
$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$referrer = isset($_SERVER['HTTP_REFERER']) ? basename($_SERVER['HTTP_REFERER']) : 'matches.php';

// Determine game type from referrer
$game_type = 'BGMI'; // default
if (strpos($referrer, 'pubg.php') !== false) {
    $game_type = 'PUBG';
} elseif (strpos($referrer, 'freefire.php') !== false) {
    $game_type = 'Free Fire';
} elseif (strpos($referrer, 'cod.php') !== false) {
    $game_type = 'Call of Duty';
}

// Fetch match details with game information
$stmt = $db->prepare("SELECT m.*, g.name as game_name, g.image_url as game_image,
                            t1.name as team1_name, t1.logo as team1_logo,
                            t2.name as team2_name, t2.logo as team2_logo,
                            tour.name as tournament_name,
                            (SELECT COUNT(*) FROM match_participants WHERE match_id = m.id) as current_participants
                     FROM matches m 
                     JOIN games g ON m.game_id = g.id 
                     LEFT JOIN teams t1 ON m.team1_id = t1.id
                     LEFT JOIN teams t2 ON m.team2_id = t2.id
                     LEFT JOIN tournaments tour ON m.tournament_id = tour.id
                     WHERE m.id = ?");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    header("Location: $referrer");
    exit();
}

// Fetch participants for this match
$stmt = $db->prepare("SELECT mp.id, mp.match_id, mp.user_id, mp.team_id, mp.join_date, mp.status,
                            u.username, u.profile_image, u.email
                     FROM match_participants mp 
                     JOIN users u ON mp.user_id = u.id 
                     WHERE mp.match_id = ?
                     ORDER BY mp.join_date ASC");
$stmt->execute([$match_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all teams for dropdown
$stmt = $db->query("SELECT id, name FROM teams ORDER BY name");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tournaments for dropdown
$stmt = $db->query("SELECT id, name FROM tournaments ORDER BY name");
$tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_match':
                try {
                    $db->beginTransaction();
                    
                    $tournament_id = $_POST['tournament_id'] ?: null;
                    $team1_id = $_POST['team1_id'] ?: null;
                    $team2_id = $_POST['team2_id'] ?: null;
                    $match_type = $_POST['match_type'];
                    $entry_type = $_POST['entry_type'];
                    $entry_fee = $_POST['entry_fee'];
                    $prize_pool = $_POST['prize_pool'];
                    $max_participants = $_POST['max_participants'];
                    $status = $_POST['status'];
                    $map_name = $_POST['map_name'];
                    $match_date = $_POST['match_date'] . ' ' . $_POST['match_time'];
                    
                    $stmt = $db->prepare("UPDATE matches 
                                        SET tournament_id = ?, team1_id = ?, team2_id = ?,
                                            match_type = ?, entry_type = ?, entry_fee = ?,
                                            prize_pool = ?, max_participants = ?, status = ?,
                                            map_name = ?, match_date = ?
                                        WHERE id = ?");
                    $stmt->execute([
                        $tournament_id, $team1_id, $team2_id, $match_type, $entry_type,
                        $entry_fee, $prize_pool, $max_participants, $status, $map_name,
                        $match_date, $match_id
                    ]);
                    
                    $db->commit();
                    header("Location: match_details.php?id=$match_id&updated=1");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error updating match: " . $e->getMessage();
                }
                break;

            case 'remove_participant':
                try {
                    $db->beginTransaction();
                    
                    $participant_id = $_POST['participant_id'];
                    
                    // Delete participant
                    $stmt = $db->prepare("DELETE FROM match_participants WHERE id = ? AND match_id = ?");
                    $stmt->execute([$participant_id, $match_id]);
                    
                    $db->commit();
                    header("Location: match_details.php?id=$match_id&removed=1");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error removing participant: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get game-specific match types
function getMatchTypes($game_type) {
    switch ($game_type) {
        case 'PUBG':
            return ['Solo' => 'Solo', 'Duo' => 'Duo', 'Squad' => 'Squad', 'TDM' => 'Team Deathmatch'];
        case 'Free Fire':
            return ['Solo' => 'Solo', 'Duo' => 'Duo', 'Squad' => 'Squad', 'Clash' => 'Clash Squad'];
        case 'Call of Duty':
            return ['MP' => 'Multiplayer', 'BR' => 'Battle Royale', 'ZM' => 'Zombies'];
        default: // BGMI
            return ['Classic' => 'Classic', 'TDM' => 'Team Deathmatch', 'Arena' => 'Arena'];
    }
}

// Get game-specific maps
function getMaps($game_type) {
    switch ($game_type) {
        case 'PUBG':
            return ['Erangel' => 'Erangel', 'Miramar' => 'Miramar', 'Sanhok' => 'Sanhok', 'Vikendi' => 'Vikendi'];
        case 'Free Fire':
            return ['Bermuda' => 'Bermuda', 'Purgatory' => 'Purgatory', 'Kalahari' => 'Kalahari'];
        case 'Call of Duty':
            return ['Isolated' => 'Isolated', 'Blackout' => 'Blackout', 'Alcatraz' => 'Alcatraz'];
        default: // BGMI
            return ['Erangel' => 'Erangel', 'Miramar' => 'Miramar', 'Sanhok' => 'Sanhok', 'Livik' => 'Livik'];
    }
}

$match_types = getMatchTypes($game_type);
$maps = getMaps($game_type);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Details - <?= htmlspecialchars($match['game_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Success Messages -->
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Match details have been successfully updated.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['removed'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Participant has been successfully removed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Back Button and Title -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h3 mb-0">
                    <img src="../<?= htmlspecialchars($match['game_image']) ?>" alt="<?= htmlspecialchars($match['game_name']) ?>" class="game-icon me-2">
                    <?= htmlspecialchars($match['game_name']) ?> Match Details
                </h2>
                <a href="<?= htmlspecialchars($referrer) ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Matches
                </a>
            </div>

            <!-- Match Details Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Match Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_match">
                        
                        <div class="row g-3">
                            <!-- Tournament Selection -->
                            <div class="col-md-6">
                                <label for="tournament_id" class="form-label">Tournament</label>
                                <select class="form-select" id="tournament_id" name="tournament_id">
                                    <option value="">No Tournament</option>
                                    <?php foreach ($tournaments as $tournament): ?>
                                        <option value="<?= $tournament['id'] ?>" <?= $match['tournament_id'] == $tournament['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tournament['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Match Type -->
                            <div class="col-md-6">
                                <label for="match_type" class="form-label">Match Type</label>
                                <select class="form-select" id="match_type" name="match_type" required>
                                    <?php foreach ($match_types as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $match['match_type'] === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Team Selections -->
                            <div class="col-md-6">
                                <label for="team1_id" class="form-label">Team 1</label>
                                <select class="form-select" id="team1_id" name="team1_id">
                                    <option value="">No Team</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>" <?= $match['team1_id'] == $team['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($team['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="team2_id" class="form-label">Team 2</label>
                                <select class="form-select" id="team2_id" name="team2_id">
                                    <option value="">No Team</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>" <?= $match['team2_id'] == $team['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($team['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Map Selection -->
                            <div class="col-md-6">
                                <label for="map_name" class="form-label">Map</label>
                                <select class="form-select" id="map_name" name="map_name" required>
                                    <?php foreach ($maps as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $match['map_name'] === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Entry Type and Fee -->
                            <div class="col-md-6">
                                <label for="entry_type" class="form-label">Entry Type</label>
                                <select class="form-select" id="entry_type" name="entry_type" required onchange="toggleEntryFee()">
                                    <option value="free" <?= $match['entry_type'] === 'free' ? 'selected' : '' ?>>Free</option>
                                    <option value="coins" <?= $match['entry_type'] === 'coins' ? 'selected' : '' ?>>Coins</option>
                                    <option value="diamonds" <?= $match['entry_type'] === 'diamonds' ? 'selected' : '' ?>>Diamonds</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="entryFeeContainer">
                                <label for="entry_fee" class="form-label">Entry Fee</label>
                                <input type="number" class="form-control" id="entry_fee" name="entry_fee" 
                                       value="<?= htmlspecialchars($match['entry_fee']) ?>" min="0">
                            </div>

                            <!-- Prize Pool -->
                            <div class="col-md-6">
                                <label for="prize_pool" class="form-label">Prize Pool</label>
                                <input type="number" class="form-control" id="prize_pool" name="prize_pool" 
                                       value="<?= htmlspecialchars($match['prize_pool']) ?>" required min="0">
                            </div>

                            <!-- Max Participants -->
                            <div class="col-md-6">
                                <label for="max_participants" class="form-label">Max Participants</label>
                                <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                       value="<?= htmlspecialchars($match['max_participants']) ?>" required min="2">
                            </div>

                            <!-- Date and Time -->
                            <div class="col-md-6">
                                <label for="match_date" class="form-label">Match Date</label>
                                <input type="date" class="form-control" id="match_date" name="match_date" 
                                       value="<?= date('Y-m-d', strtotime($match['match_date'])) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="match_time" class="form-label">Match Time</label>
                                <input type="time" class="form-control" id="match_time" name="match_time" 
                                       value="<?= date('H:i', strtotime($match['match_date'])) ?>" required>
                            </div>

                            <!-- Status -->
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="upcoming" <?= $match['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                    <option value="in_progress" <?= $match['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $match['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $match['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Participants Card -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Participants</h5>
                    <span class="badge bg-light text-dark">
                        <?= count($participants) ?> / <?= $match['max_participants'] ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($participants)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                            <p class="mt-2">No participants have joined this match yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Player</th>
                                        <th>Email</th>
                                        <th>Joined At</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $index => $participant): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="../<?= htmlspecialchars($participant['profile_image']) ?>" 
                                                         alt="" class="participant-avatar me-2">
                                                    <?= htmlspecialchars($participant['username']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($participant['email']) ?></td>
                                            <td><?= date('M j, Y g:i A', strtotime($participant['join_date'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $participant['status'] === 'joined' ? 'success' : 
                                                    ($participant['status'] === 'disqualified' ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($participant['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to remove this participant?');">
                                                    <input type="hidden" name="action" value="remove_participant">
                                                    <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.game-icon {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 8px;
}

.participant-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

.table > :not(caption) > * > * {
    padding: 0.75rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
}

.alert {
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .container-fluid {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .table-responsive {
        margin-bottom: 1rem;
    }
}
</style>

<script>
function toggleEntryFee() {
    const entryType = document.getElementById('entry_type').value;
    const entryFeeContainer = document.getElementById('entryFeeContainer');
    const entryFeeInput = document.getElementById('entry_fee');
    
    if (entryType === 'free') {
        entryFeeContainer.style.display = 'none';
        entryFeeInput.value = '0';
        entryFeeInput.required = false;
    } else {
        entryFeeContainer.style.display = 'block';
        entryFeeInput.required = true;
    }
}

// Initialize entry fee visibility
document.addEventListener('DOMContentLoaded', function() {
    toggleEntryFee();
});

// Form validation
(function () {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php include 'includes/admin-footer.php'; ?>
