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
$conn = $database->connect();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $coin_cost = $_POST['coin_cost'];
    $stock = $_POST['stock'];
    $image_url = $_POST['image_url'];
    $is_unlimited = isset($_POST['is_unlimited']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;

    try {
        $sql = "INSERT INTO redeemable_items (name, description, coin_cost, stock, image_url, is_unlimited, is_active, requires_approval) 
                VALUES (:name, :description, :coin_cost, :stock, :image_url, :is_unlimited, :is_active, :requires_approval)";
        $stmt = $conn->prepare($sql);
        
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':coin_cost' => $coin_cost,
            ':stock' => $stock,
            ':image_url' => $image_url,
            ':is_unlimited' => $is_unlimited,
            ':is_active' => $is_active,
            ':requires_approval' => $requires_approval
        ]);

        if ($stmt->rowCount() > 0) {
            echo '<div class="alert alert-success">Item added successfully!</div>';
        } else {
            echo '<div class="alert alert-danger">Failed to add item.</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        error_log("PDO Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Add New Item</h2>
    <form method="POST" action="">
        <div class="mb-3">
            <label for="name" class="form-label">Item Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"></textarea>
        </div>
        <div class="mb-3">
            <label for="coin_cost" class="form-label">Coin Cost</label>
            <input type="number" class="form-control" id="coin_cost" name="coin_cost" required>
        </div>
        <div class="mb-3">
            <label for="stock" class="form-label">Stock</label>
            <input type="number" class="form-control" id="stock" name="stock" value="0" required>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_unlimited" name="is_unlimited">
                <label class="form-check-label" for="is_unlimited">Unlimited Redemption</label>
            </div>
            <small class="text-muted">If checked, this item can be redeemed an unlimited number of times.</small>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="requires_approval" name="requires_approval">
                <label class="form-check-label" for="requires_approval">Request Need</label>
            </div>
            <small class="text-muted">If checked, admin approval will be required to complete the redemption.</small>
        </div>
        <div class="mb-3">
            <label for="image_url" class="form-label">Image URL</label>
            <input type="text" class="form-control" id="image_url" name="image_url">
        </div>
        <button type="submit" class="btn btn-primary">Add Item</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 