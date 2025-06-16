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
$db = $database->connect();

// Get total users count
$sql = "SELECT COUNT(*) as total_users FROM users WHERE role = 'user'";
$stmt = $db->prepare($sql);
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// Get total coins in circulation
$sql = "SELECT SUM(coins) as total_coins FROM user_coins";
$stmt = $db->prepare($sql);
$stmt->execute();
$total_coins = $stmt->fetch(PDO::FETCH_ASSOC)['total_coins'] ?? 0;

// Get total tickets in circulation
$sql = "SELECT SUM(tickets) as total_tickets FROM user_tickets";
$stmt = $db->prepare($sql);
$stmt->execute();
$total_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['total_tickets'] ?? 0;

// Get total teams count
$teams_sql = "SELECT COUNT(*) as total_teams FROM teams";
$stmt = $db->query($teams_sql);
$total_teams = $stmt->fetch(PDO::FETCH_ASSOC)['total_teams'];

// Get recent users
$sql = "SELECT id, username, email, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($sql);
$stmt->execute();
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../users/index.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../matches/bgmi.php">
                                <i class="bi bi-person-circle"></i> Matches
                            </a>
                        </li>
                        <li class="nav-item">
                                <a class="nav-link text-white" href="../tournaments.php">
                                <i class="bi bi-person-circle"></i> Tournaments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../live-streams/index.php">
                                <i class="bi bi-broadcast"></i> Live Streams
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
                            <a class="nav-link text-white" href="./team-management.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Users</h6>
                                        <h2 class="mb-0"><?php echo number_format($total_users); ?></h2>
                                    </div>
                                    <i class="bi bi-people display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Coins</h6>
                                        <h2 class="mb-0"><?php echo number_format($total_coins); ?></h2>
                                    </div>
                                    <i class="bi bi-coin display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Tickets</h6>
                                        <h2 class="mb-0"><?php echo number_format($total_tickets); ?></h2>
                                    </div>
                                    <i class="bi bi-ticket-perforated display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-warning text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase">Total Teams</h6>
                                        <h2 class="mb-0"><?php echo number_format($total_teams); ?></h2>
                                    </div>
                                    <i class="bi bi-people-fill display-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Users Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-table me-1"></i>
                        Recent Users
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <a href="../users/view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <a href="../users/edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
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
    <script src="../js/admin.js"></script>
</body>
</html> 