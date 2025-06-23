<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'User not logged in']));
}

// Initialize database connection
$db = new Database();
$conn = $db->connect();

// Function to generate unique order ID
function generateOrderId() {
    return 'KGX' . time() . rand(1000, 9999);
}

// Function to create order in database
function createOrder($conn, $userId, $orderId, $amount, $coins, $tickets) {
    try {
        $sql = "INSERT INTO shop_orders (user_id, order_id, amount, coins, tickets, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$userId, $orderId, $amount, $coins, $tickets]);
    } catch (PDOException $e) {
        error_log("Error creating order: " . $e->getMessage());
        return false;
    }
}

// Function to update user balance
function updateUserBalance($conn, $userId, $coins, $tickets) {
    try {
        $conn->beginTransaction();

        // Update coins
        $sql = "INSERT INTO user_coins (user_id, coins) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE coins = coins + VALUES(coins)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $coins]);

        // Update tickets
        $sql = "INSERT INTO user_tickets (user_id, tickets) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE tickets = tickets + VALUES(tickets)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $tickets]);

        $conn->commit();
        return true;
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error updating balance: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($data['package']) || !isset($data['amount']) || !isset($data['coins']) || !isset($data['tickets'])) {
        die(json_encode(['status' => 'error', 'message' => 'Invalid input']));
    }

    $userId = $_SESSION['user_id'];
    $orderId = generateOrderId();
    $amount = $data['amount'];
    $coins = $data['coins'];
    $tickets = $data['tickets'];

    // Create order in database
    if (!createOrder($conn, $userId, $orderId, $amount, $coins, $tickets)) {
        die(json_encode(['status' => 'error', 'message' => 'Failed to create order']));
    }

    // TODO: Implement Cashfree API integration here
    // This is where we'll add the Cashfree payment initialization code
    // For now, return a placeholder response

    $response = [
        'status' => 'success',
        'message' => 'Order created successfully',
        'order_id' => $orderId,
        'amount' => $amount,
        // Add more payment gateway specific details here
    ];

    echo json_encode($response);
    exit;
}

// Handle payment callback/webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    // TODO: Implement Cashfree webhook handling
    // Verify payment signature
    // Update order status
    // Credit coins/tickets to user if payment successful
    
    $orderId = $_POST['order_id'];
    // Add verification logic here once Cashfree is integrated
    
    exit;
}
?> 