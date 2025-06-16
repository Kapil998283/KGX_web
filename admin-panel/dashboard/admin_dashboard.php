<?php
session_start();
require_once '../includes/admin-auth.php';

// Get database connection
$conn = getDbConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get total items count
$sql = "SELECT COUNT(*) as total_items FROM redeemable_items";
$stmt = $conn->query($sql);
$total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total_items'];

// Get total redemptions
$sql = "SELECT COUNT(*) as total_redemptions FROM redemption_history";
$stmt = $conn->query($sql);
$total_redemptions = $stmt->fetch(PDO::FETCH_ASSOC)['total_redemptions'];

// Get recent redemptions
$sql = "SELECT rh.*, ri.name as item_name, u.username 
        FROM redemption_history rh
        JOIN redeemable_items ri ON rh.item_id = ri.id
        JOIN users u ON rh.user_id = u.id
        ORDER BY rh.redeemed_at DESC
        LIMIT 5";
$recent_redemptions = $conn->query($sql);

// Get low stock items
$sql = "SELECT * FROM redeemable_items WHERE stock < 5 AND is_unlimited = 0 ORDER BY stock ASC";
$low_stock_items = $conn->query($sql);
$low_stock_count = $low_stock_items->rowCount();

// Get pending redemption requests
$sql = "SELECT COUNT(*) as pending_requests 
        FROM redemption_history rh 
        JOIN redeemable_items ri ON rh.item_id = ri.id 
        WHERE rh.status = 'pending' AND ri.requires_approval = 1";
$stmt = $conn->query($sql);
$pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_requests'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="./index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="add_item.php">
                                <i class="bi bi-plus-circle"></i> Add Item
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="manage_items.php">
                                <i class="bi bi-list"></i> Manage Items
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="redemption_request.php">
                                <i class="bi bi-clock-history"></i> Redemption Request
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Redeem Dashboard</h1>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h2 class="card-text"><?php echo $total_items; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Redemptions</h5>
                                <h2 class="card-text"><?php echo $total_redemptions; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <h2 class="card-text"><?php echo $low_stock_count; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Requested Redemptions</h5>
                                <h2 class="card-text"><?php echo $pending_requests; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Redemptions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Redemptions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Item</th>
                                        <th>Coins Spent</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($redemption = $recent_redemptions->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($redemption['username']); ?></td>
                                        <td><?php echo htmlspecialchars($redemption['item_name']); ?></td>
                                        <td><?php echo $redemption['coins_spent']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $redemption['status'] == 'completed' ? 'success' : ($redemption['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($redemption['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($redemption['redeemed_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Items -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Low Stock Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Current Stock</th>
                                        <th>Coin Cost</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($item = $low_stock_items->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>
                                            <span class="badge bg-danger"><?php echo $item['stock']; ?></span>
                                        </td>
                                        <td><?php echo $item['coin_cost']; ?></td>
                                        <td>
                                            <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <a href="update_stock.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success">Update Stock</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
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