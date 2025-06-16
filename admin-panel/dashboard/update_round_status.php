<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get POST data
$round_id = isset($_POST['round_id']) ? (int)$_POST['round_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;

// Validate input
if (!$round_id || !$status || !$tournament_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Validate status value
$valid_statuses = ['upcoming', 'in_progress', 'completed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Update round status
    $stmt = $conn->prepare("
        UPDATE tournament_rounds 
        SET status = :status 
        WHERE id = :round_id AND tournament_id = :tournament_id
    ");
    
    $result = $stmt->execute([
        'status' => $status,
        'round_id' => $round_id,
        'tournament_id' => $tournament_id
    ]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 