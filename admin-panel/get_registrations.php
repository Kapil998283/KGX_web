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
    $stmt = $conn->prepare("SELECT name, mode FROM tournaments WHERE id = ?");
    $stmt->execute([$_GET['tournament_id']]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tournament) {
        echo '<div class="alert alert-danger">Tournament not found</div>';
        exit();
    }

    // Get registered teams with their members
    $stmt = $conn->prepare("
        SELECT 
            t.id as team_id,
            t.name as team_name,
            tr.status as registration_status,
            tr.registration_date,
            GROUP_CONCAT(
                CONCAT(u.username, ':', tm.role)
                ORDER BY 
                    CASE 
                        WHEN tm.role = 'captain' THEN 1 
                        ELSE 2 
                    END,
                    u.username
                SEPARATOR '|'
            ) as team_members
        FROM tournament_registrations tr
        INNER JOIN teams t ON tr.team_id = t.id
        INNER JOIN team_members tm ON t.id = tm.team_id
        INNER JOIN users u ON tm.user_id = u.id
        WHERE tr.tournament_id = ?
        AND tm.status = 'active'
        GROUP BY t.id, t.name, tr.status, tr.registration_date
        ORDER BY tr.registration_date DESC
    ");
    $stmt->execute([$_GET['tournament_id']]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div id="statusMessage" class="alert" style="display: none;"></div>

<h4 class="mb-3"><?php echo htmlspecialchars($tournament['name']); ?> - Registered Teams</h4>

<?php if (empty($registrations)): ?>
    <div class="alert alert-info">No teams have registered for this tournament yet.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Members</th>
                    <th>Registration Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrations as $reg): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reg['team_name']); ?></td>
                        <td>
                            <?php
                            $members = array_map(function($member) {
                                list($username, $role) = explode(':', $member);
                                $badge = $role === 'captain' ? 
                                    '<span class="badge bg-primary">Captain</span>' : 
                                    '<span class="badge bg-secondary">Member</span>';
                                return htmlspecialchars($username) . ' ' . $badge;
                            }, explode('|', $reg['team_members']));
                            echo implode('<br>', $members);
                            ?>
                        </td>
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
                                        onclick="updateRegistrationStatus('<?php echo $reg['team_id']; ?>', 'approved', <?php echo $_GET['tournament_id']; ?>)">
                                    Approve
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="updateRegistrationStatus('<?php echo $reg['team_id']; ?>', 'rejected', <?php echo $_GET['tournament_id']; ?>)">
                                    Reject
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function updateRegistrationStatus(teamId, status, tournamentId) {
        if (!confirm('Are you sure you want to ' + status + ' this registration?')) {
            return;
        }

        const formData = new FormData();
        formData.append('team_id', teamId);
        formData.append('tournament_id', tournamentId);
        formData.append('status', status);

        fetch('update_registration.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const messageDiv = document.getElementById('statusMessage');
            if (data.success) {
                messageDiv.className = 'alert alert-success';
                messageDiv.textContent = data.message;
                messageDiv.style.display = 'block';
                // Refresh the registrations list after 1 second
                setTimeout(() => {
                    viewRegistrations(tournamentId);
                }, 1000);
            } else {
                messageDiv.className = 'alert alert-danger';
                messageDiv.textContent = data.error || 'Failed to update registration status';
                messageDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const messageDiv = document.getElementById('statusMessage');
            messageDiv.className = 'alert alert-danger';
            messageDiv.textContent = 'Failed to update registration status';
            messageDiv.style.display = 'block';
        });
    }
    </script>
<?php endif; ?>

<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading registrations: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 