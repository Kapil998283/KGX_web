<?php
require_once '../../includes/db_connect.php';
require_once '../../admin-panel/includes/admin_logger.php';

// Set timezone to match your server's timezone
date_default_timezone_set('Asia/Kolkata');

// Get all pending orders that should be completed
$sql = "SELECT rh.id, rh.redeemed_at, rh.delivery_time, ri.time_limit, ri.name as item_name,
        TIMESTAMPDIFF(MINUTE, rh.redeemed_at, NOW()) as minutes_passed
        FROM redemption_history rh
        JOIN redeemable_items ri ON rh.item_id = ri.id
        WHERE rh.status = 'pending'
        AND (
            (rh.delivery_time <= NOW() AND TIMESTAMPDIFF(MINUTE, rh.redeemed_at, NOW()) >= ri.time_limit)
            OR
            (TIMESTAMPDIFF(MINUTE, rh.redeemed_at, NOW()) >= ri.time_limit)
        )";

$result = mysqli_query($conn, $sql);

if (!$result) {
    log_admin_action($conn, $_SESSION['user_id'], 'error', 'Failed to fetch pending orders: ' . mysqli_error($conn));
    die(json_encode(['error' => 'Database error']));
}

$orders_to_update = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (!empty($orders_to_update)) {
    // Log the orders that will be updated
    foreach ($orders_to_update as $order) {
        log_admin_action($conn, $_SESSION['user_id'], 'info', 
            "Updating order status: Order ID {$order['id']}, " .
            "Item: {$order['item_name']}, " .
            "Minutes passed: {$order['minutes_passed']}, " .
            "Time limit: {$order['time_limit']}, " .
            "Delivery time: {$order['delivery_time']}, " .
            "Current time: " . date('Y-m-d H:i:s')
        );
    }

    // Update the status of these orders
    $update_sql = "UPDATE redemption_history 
                   SET status = 'completed' 
                   WHERE id IN (" . implode(',', array_column($orders_to_update, 'id')) . ")";
    
    if (mysqli_query($conn, $update_sql)) {
        log_admin_action($conn, $_SESSION['user_id'], 'success', 
            "Updated " . count($orders_to_update) . " orders to completed status"
        );
        echo json_encode(['success' => true, 'updated' => count($orders_to_update)]);
    } else {
        log_admin_action($conn, $_SESSION['user_id'], 'error', 
            'Failed to update order statuses: ' . mysqli_error($conn)
        );
        echo json_encode(['error' => 'Failed to update orders']);
    }
} else {
    // Log that no orders needed updating
    log_admin_action($conn, $_SESSION['user_id'], 'info', 'No orders needed status update');
    echo json_encode(['success' => true, 'updated' => 0]);
}
?> 