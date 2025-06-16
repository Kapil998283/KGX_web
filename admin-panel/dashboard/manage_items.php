<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Handle item deletion
if (isset($_POST['delete_item'])) {
    $item_id = $_POST['item_id'];
    $sql = "DELETE FROM redeemable_items WHERE id = :item_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['item_id' => $item_id]);
}

// Get all items
$sql = "SELECT * FROM redeemable_items ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items</title>
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
                            <a class="nav-link text-white" href="admin_dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="add_item.php">
                                <i class="bi bi-plus-circle"></i> Add Item
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="manage_items.php">
                                <i class="bi bi-list"></i> Manage Items
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="redemption_history.php">
                                <i class="bi bi-clock-history"></i> Redemption History
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Items</h1>
                    <a href="add_item.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Item
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Coin Cost</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Unlimited</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td>
                                            <?php if ($item['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="Item Image" style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="bi bi-image text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td><?php echo $item['coin_cost']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['stock'] < 5 && !$item['is_unlimited'] ? 'danger' : 'success'; ?>">
                                                <?php echo $item['is_unlimited'] ? 'Unlimited' : $item['stock']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['is_unlimited'] ? 'info' : 'secondary'; ?>">
                                                <?php echo $item['is_unlimited'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (!$item['is_unlimited']): ?>
                                                <a href="update_stock.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-box"></i>
                                                </a>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="delete_item" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
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