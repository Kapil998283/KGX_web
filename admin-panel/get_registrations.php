<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

if (!isset($_GET['tournament_id'])) {
    echo '<div class="alert alert-danger">Tournament ID is required</div>';
    exit();
}

try {
    // Get tournament details
    $stmt = $conn->prepare("SELECT name, mode, game_name FROM tournaments WHERE id = ?");
    $stmt->execute([$_GET['tournament_id']]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournament) {
        echo '<div class="alert alert-danger">Tournament not found</div>';
        exit();
    }

    $game_name = $tournament['game_name'];
    $mode = $tournament['mode'];

    if ($mode === 'Solo') {
        // Show registered players with game details
        $stmt = $conn->prepare("
            SELECT u.username, tr.registration_date, tr.status as registration_status, ug.game_username, ug.game_uid, ug.game_level, u.id as user_id
            FROM tournament_registrations tr
            INNER JOIN users u ON tr.user_id = u.id
            LEFT JOIN user_games ug ON ug.user_id = u.id AND ug.game_name = ?
            WHERE tr.tournament_id = ?
            ORDER BY tr.registration_date DESC
        ");
        $stmt->execute([$game_name, $_GET['tournament_id']]);
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div id="statusMessage" class="alert" style="display: none;"></div>
        <h4 class="mb-3"><?php echo htmlspecialchars($tournament['name']); ?> - Registered Players</h4>
        <?php if (empty($registrations)): ?>
            <div class="alert alert-info">No players have registered for this tournament yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>In-Game Username</th>
                            <th>UID</th>
                            <th>Level</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reg['username']); ?></td>
                            <td><?php echo htmlspecialchars($reg['game_username']); ?></td>
                            <td><?php echo htmlspecialchars($reg['game_uid']); ?></td>
                            <td><?php echo htmlspecialchars($reg['game_level']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($reg['registration_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $reg['registration_status'] === 'pending' ? 'warning' : 
                                        ($reg['registration_status'] === 'approved' ? 'success' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($reg['registration_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reg['registration_status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="updateRegistrationStatus('<?php echo $reg['user_id']; ?>', 'approved', <?php echo $_GET['tournament_id']; ?>, 'solo')">
                                        Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="updateRegistrationStatus('<?php echo $reg['user_id']; ?>', 'rejected', <?php echo $_GET['tournament_id']; ?>, 'solo')">
                                        Reject
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
    } else {
        // Show teams and their members with game details
        $stmt = $conn->prepare("
            SELECT t.id as team_id, t.name as team_name, tr.status as registration_status, tr.registration_date
            FROM tournament_registrations tr
            INNER JOIN teams t ON tr.team_id = t.id
            WHERE tr.tournament_id = ?
            ORDER BY tr.registration_date DESC
        ");
        $stmt->execute([$_GET['tournament_id']]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div id="statusMessage" class="alert" style="display: none;"></div>
        <h4 class="mb-3"><?php echo htmlspecialchars($tournament['name']); ?> - Registered Teams</h4>
        <?php if (empty($teams)): ?>
            <div class="alert alert-info">No teams have registered for this tournament yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Team Name</th>
                            <th>Members & Game Details</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                            <td>
                                <?php
                                // Get team members and their game details
                                $stmt2 = $conn->prepare("
                                    SELECT u.username, tm.role, ug.game_username, ug.game_uid, ug.game_level
                                    FROM team_members tm
                                    INNER JOIN users u ON tm.user_id = u.id
                                    LEFT JOIN user_games ug ON ug.user_id = u.id AND ug.game_name = ?
                                    WHERE tm.team_id = ? AND tm.status = 'active'
                                    ORDER BY FIELD(tm.role, 'captain', 'member'), u.username
                                ");
                                $stmt2->execute([$game_name, $team['team_id']]);
                                $members = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($members as $member): ?>
                                    <div style="margin-bottom: 8px;">
                                        <strong><?php echo htmlspecialchars($member['username']); ?></strong> (<?php echo htmlspecialchars($member['role']); ?>)<br>
                                        <span style="font-size: 90%; color: #555;">In-Game: <?php echo htmlspecialchars($member['game_username']); ?> | UID: <?php echo htmlspecialchars($member['game_uid']); ?> | Level: <?php echo htmlspecialchars($member['game_level']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($team['registration_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $team['registration_status'] === 'pending' ? 'warning' : 
                                        ($team['registration_status'] === 'approved' ? 'success' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($team['registration_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($team['registration_status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="updateRegistrationStatus('<?php echo $team['team_id']; ?>', 'approved', <?php echo $_GET['tournament_id']; ?>, 'team')">
                                        Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="updateRegistrationStatus('<?php echo $team['team_id']; ?>', 'rejected', <?php echo $_GET['tournament_id']; ?>, 'team')">
                                        Reject
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
    }
?>

<!-- JavaScript function is now handled by the parent window (tournaments.php) -->

<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading registrations: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 