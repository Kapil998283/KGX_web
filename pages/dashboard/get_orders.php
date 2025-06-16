<?php
session_start();
require_once '../../includes/user-auth.php';
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get database connection
$conn = getDbConnection();

try {
    // Get recent redemption history
    $sql = "SELECT rh.*, ri.name as item_name, ri.coins_required 
            FROM redemption_history rh 
            JOIN redeemable_items ri ON rh.item_id = ri.id 
            WHERE rh.user_id = :user_id 
            ORDER BY rh.id DESC 
            LIMIT 8";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($orders);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    error_log("PDO Exception: " . $e->getMessage());
}
?> 