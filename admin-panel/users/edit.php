<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if user ID is provided
if(!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int)$_GET['id'];
$success = '';
$error = '';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $coins = (int)$_POST['coins'];
    $tickets = (int)$_POST['tickets'];
    
    try {
        $db->beginTransaction();
        
        // Update coins
        $sql = "INSERT INTO user_coins (user_id, coins) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE coins = VALUES(coins)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $coins]);
        
        // Update tickets
        $sql = "INSERT INTO user_tickets (user_id, tickets) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE tickets = VALUES(tickets)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $tickets]);
        
        $db->commit();
        $success = "User resources updated successfully";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error updating user resources: " . $e->getMessage();
    }
}

// Get user data
$sql = "SELECT u.*, 
        COALESCE(uc.coins, 0) as coins,
        COALESCE(ut.tickets, 0) as tickets
        FROM users u 
        LEFT JOIN user_coins uc ON u.id = uc.user_id
        LEFT JOIN user_tickets ut ON u.id = ut.user_id
        WHERE u.id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Dashboard</title>
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
                            <a class="nav-link text-white" href="../dashboard/index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="index.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../transactions/index.php">
                                <i class="bi bi-currency-exchange"></i> Transactions
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
                    <h1 class="h2">Edit User: <?php echo htmlspecialchars($user['username']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-secondary">Back to Users</a>
                    </div>
                </div>

                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- User Edit Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Coins</label>
                                    <input type="number" class="form-control" name="coins" value="<?php echo $user['coins']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tickets</label>
                                    <input type="number" class="form-control" name="tickets" value="<?php echo $user['tickets']; ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Registration Date</label>
                                    <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($user['created_at'])); ?>" readonly>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Update User</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 