<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$item_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get item details
$sql = "SELECT * FROM redeemable_items WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $item_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$item = mysqli_fetch_assoc($result);

if (!$item) {
    header("Location: manage_items.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_stock = $_POST['stock'];
    $is_unlimited = isset($_POST['is_unlimited']) ? 1 : 0;
    
    $sql = "UPDATE redeemable_items SET stock = ?, is_unlimited = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $new_stock, $is_unlimited, $item_id);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: manage_items.php?success=1");
        exit();
    } else {
        $error = "Failed to update stock";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stock - <?php echo htmlspecialchars($item['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Update Stock</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Item Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($item['name']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="text" class="form-control" value="<?php echo $item['stock']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="stock" class="form-label">New Stock Quantity</label>
                                <input type="number" class="form-control" id="stock" name="stock" value="<?php echo $item['stock']; ?>" required min="0">
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_unlimited" name="is_unlimited" <?php echo $item['is_unlimited'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_unlimited">Unlimited Redemption</label>
                                </div>
                                <small class="text-muted">If checked, this item can be redeemed an unlimited number of times.</small>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Stock</button>
                                <a href="manage_items.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 