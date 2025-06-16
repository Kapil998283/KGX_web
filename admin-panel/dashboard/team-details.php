<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /KGX/admin-panel/index.php');
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get team ID from URL
$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$team_id) {
    header('Location: team-management.php');
    exit();
}

// Fetch team details
$team_sql = "SELECT t.*, 
             u.username as captain_username,
             u.email as captain_email,
             u.created_at as captain_join_date
             FROM teams t
             LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.role = 'captain'
             LEFT JOIN users u ON tm.user_id = u.id
             WHERE t.id = :team_id";
$stmt = $conn->prepare($team_sql);
$stmt->execute(['team_id' => $team_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: team-management.php');
    exit();
}

// Fetch team members
$members_sql = "SELECT tm.*, u.username, u.email, u.created_at as join_date
                FROM team_members tm
                JOIN users u ON tm.user_id = u.id
                WHERE tm.team_id = :team_id
                ORDER BY tm.role";
$stmt = $conn->prepare($members_sql);
$stmt->execute(['team_id' => $team_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle score update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_score'])) {
    $new_score = (int)$_POST['total_score'];
    try {
        $update_sql = "UPDATE teams SET total_score = :score WHERE id = :team_id";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([
            'score' => $new_score,
            'team_id' => $team_id
        ]);
        $_SESSION['success_message'] = "Team score updated successfully!";
        header("Location: team-details.php?id=" . $team_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating score: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Details - <?php echo htmlspecialchars($team['name']); ?></title>
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Team Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="team-management.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Teams
                        </a>
                    </div>
                </div>

                <!-- Team Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Team Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                     alt="Team Logo" 
                                     class="img-fluid rounded-circle mb-2" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                                <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php echo $team['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $team['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </p>
                                        <p><strong>Language:</strong> <?php echo htmlspecialchars($team['language']); ?></p>
                                        <p><strong>Created At:</strong> <?php echo date('F j, Y', strtotime($team['created_at'])); ?></p>
                                        <p>
                                            <strong>Total Score:</strong>
                                            <span class="badge bg-primary" style="font-size: 1em;">
                                                <?php echo number_format($team['total_score']); ?> pts
                                            </span>
                                            <button type="button" class="btn btn-sm btn-warning ms-2" 
                                                    data-bs-toggle="modal" data-bs-target="#updateScoreModal">
                                                <i class="bi bi-pencil"></i> Edit Score
                                            </button>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Captain:</strong> <?php echo htmlspecialchars($team['captain_username']); ?></p>
                                        <p><strong>Captain Email:</strong> <?php echo htmlspecialchars($team['captain_email']); ?></p>
                                        <p><strong>Captain Since:</strong> <?php echo date('F j, Y', strtotime($team['captain_join_date'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Members -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Team Members</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Join Date</th>
                                        <th>Member Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['username']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $member['role'] === 'captain' ? 'primary' : 'secondary'; ?>">
                                                <?php echo ucfirst($member['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('F j, Y', strtotime($member['join_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $member['role'] === 'captain' ? 'success' : 'info'; ?>">
                                                <?php echo $member['role'] === 'captain' ? 'Leader' : 'Member'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Update Score Modal -->
    <div class="modal fade" id="updateScoreModal" tabindex="-1" aria-labelledby="updateScoreModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateScoreModalLabel">Update Team Score</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="total_score" class="form-label">Total Score</label>
                            <input type="number" class="form-control" id="total_score" name="total_score" 
                                   value="<?php echo $team['total_score']; ?>" required min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_score" class="btn btn-primary">Update Score</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add success/error message display -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert">
            <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert">
            <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</body>
</html> 