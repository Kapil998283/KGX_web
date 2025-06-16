<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tournament ID is required']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tournament) {
        http_response_code(404);
        echo json_encode(['error' => 'Tournament not found']);
        exit();
    }
    
    // Format dates for HTML date inputs
    $tournament['registration_open_date'] = date('Y-m-d', strtotime($tournament['registration_open_date']));
    $tournament['registration_close_date'] = date('Y-m-d', strtotime($tournament['registration_close_date']));
    $tournament['playing_start_date'] = date('Y-m-d', strtotime($tournament['playing_start_date']));
    $tournament['finish_date'] = date('Y-m-d', strtotime($tournament['finish_date']));
    
    echo json_encode($tournament);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?> 