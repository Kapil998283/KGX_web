<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Handle team status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $team_id = (int)$_POST['team_id'];
    
    switch ($_POST['action']) {
        case 'deactivate':
            $sql = "UPDATE teams SET is_active = 0 WHERE id = :team_id";
            $message = "Team deactivated successfully";
            break;
        case 'activate':
            $sql = "UPDATE teams SET is_active = 1 WHERE id = :team_id";
            $message = "Team activated successfully";
            break;
        case 'delete':
            $sql = "DELETE FROM teams WHERE id = :team_id";
            $message = "Team deleted successfully";
            break;
        default:
            $_SESSION['error_message'] = "Invalid action";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute(['team_id' => $team_id]);
        $_SESSION['success_message'] = $message;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get all teams with their details
$sql = "SELECT t.*, u.username as captain_name, 
        COUNT(tm.id) as member_count,
        tb.image_path as banner_path
        FROM teams t
        LEFT JOIN users u ON t.captain_id = u.id
        LEFT JOIN team_members tm ON t.id = tm.team_id
        LEFT JOIN team_banners tb ON t.banner_id = tb.id";

// Add search condition if search parameter exists
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $sql .= " WHERE t.name LIKE :search";
}

$sql .= " GROUP BY t.id ORDER BY t.created_at DESC";

$stmt = $conn->prepare($sql);

// Bind search parameter if it exists
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $stmt->bindParam(':search', $searchTerm);
}

$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../users/index.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../profile.php">
                                <i class="bi bi-person-circle"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="./admin_dashboard.php">
                                <i class="bi bi-gift"></i>ADD Redeemable Item
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="./hero-settings.php">
                                <i class="bi bi-gift"></i>Edit Hero
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="./team-management.php">
                                <i class="bi bi-gift"></i>Teams management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Team Management</h1>
                </div>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label class="visually-hidden" for="searchTeam">Search Team</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchTeam" name="search" 
                                           placeholder="Search team name..." 
                                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button class="btn btn-primary" type="submit">Search</button>
                                    <?php if(isset($_GET['search'])): ?>
                                        <a href="team-management.php" class="btn btn-secondary">Clear</a>
                                    <?php endif; ?>
                                </div>
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo htmlspecialchars($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Team Name</th>
                                        <th>Logo</th>
                                        <th>Banner</th>
                                        <th>Captain</th>
                                        <th>Members</th>
                                        <th>Language</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Total Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teams as $team): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($team['id']); ?></td>
                                            <td><?php echo htmlspecialchars($team['name']); ?></td>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                                                     alt="Team Logo" 
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                            </td>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($team['banner_path']); ?>" 
                                                     alt="Team Banner" 
                                                     style="width: 100px; height: 50px; object-fit: cover;">
                                            </td>
                                            <td><?php echo htmlspecialchars($team['captain_name']); ?></td>
                                            <td><?php echo htmlspecialchars($team['member_count']); ?>/<?php echo htmlspecialchars($team['max_members']); ?></td>
                                            <td><?php echo htmlspecialchars($team['language']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $team['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $team['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($team['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo number_format($team['total_score']); ?> pts
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="team-details.php?id=<?php echo $team['id']; ?>" 
                                                       class="btn btn-info btn-sm">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <?php if ($team['is_active']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                            <input type="hidden" name="action" value="deactivate">
                                                            <button type="submit" class="btn btn-warning btn-sm" 
                                                                    onclick="return confirm('Are you sure you want to deactivate this team?')">
                                                                <i class="bi bi-x-circle"></i> Deactivate
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                            <input type="hidden" name="action" value="activate">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="bi bi-check-circle"></i> Activate
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to delete this team? This action cannot be undone.')">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
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
</body>
</html> 