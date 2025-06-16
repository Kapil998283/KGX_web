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

// Check if item ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_items.php");
    exit();
}

$item_id = (int)$_GET['id'];
$success = '';
$error = '';

// Get item details
$sql = "SELECT * FROM redeemable_items WHERE id = :item_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':item_id', $item_id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header("Location: manage_items.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $coin_cost = $_POST['coin_cost'];
    $stock = $_POST['stock'];
    $is_unlimited = isset($_POST['is_unlimited']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;
    $image_url = $_POST['image_url'];
    
    $sql = "UPDATE redeemable_items SET 
            name = :name, 
            description = :description, 
            coin_cost = :coin_cost, 
            stock = :stock, 
            is_unlimited = :is_unlimited,
            is_active = :is_active, 
            requires_approval = :requires_approval,
            image_url = :image_url 
            WHERE id = :item_id";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':coin_cost', $coin_cost);
    $stmt->bindParam(':stock', $stock);
    $stmt->bindParam(':is_unlimited', $is_unlimited);
    $stmt->bindParam(':is_active', $is_active);
    $stmt->bindParam(':requires_approval', $requires_approval);
    $stmt->bindParam(':image_url', $image_url);
    $stmt->bindParam(':item_id', $item_id);
    
    if ($stmt->execute()) {
        header('Location: manage_items.php?success=1');
        exit;
    } else {
        $error = "Error updating item: " . implode(', ', $stmt->errorInfo());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - <?php echo htmlspecialchars($item['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            margin-top: 10px;
        }
    </style>
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
                    <h1 class="h2">Edit Item: <?php echo htmlspecialchars($item['name']); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="manage_items.php" class="btn btn-secondary">Back to Items</a>
                    </div>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Item Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="coin_cost" class="form-label">Coin Cost</label>
                                        <input type="number" class="form-control" id="coin_cost" name="coin_cost" value="<?php echo $item['coin_cost']; ?>" required min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="stock" class="form-label">Stock</label>
                                        <input type="number" class="form-control" id="stock" name="stock" value="<?php echo $item['stock']; ?>" required min="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="image_url" class="form-label">Image URL</label>
                                        <input type="url" class="form-control" id="image_url" name="image_url" value="<?php echo htmlspecialchars($item['image_url']); ?>" placeholder="https://example.com/image.jpg">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="Current Image" class="preview-image">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="is_unlimited" name="is_unlimited" <?php echo $item['is_unlimited'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_unlimited">Unlimited Redemption</label>
                                        </div>
                                        <small class="text-muted">If checked, this item can be redeemed an unlimited number of times.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo $item['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="requires_approval" name="requires_approval" <?php echo $item['requires_approval'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="requires_approval">Request Need</label>
                                        </div>
                                        <small class="text-muted">If checked, admin approval will be required to complete the redemption.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Item</button>
                                <a href="manage_items.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 