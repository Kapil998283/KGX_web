<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get tournament ID from request
$tournament_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tournament_id) {
    echo json_encode(['error' => 'Invalid tournament ID']);
    exit();
}

try {
    // Get tournament details
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournament_id]);
    $tournament = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tournament) {
        echo json_encode(['error' => 'Tournament not found']);
        exit();
    }
    
    // Format dates for HTML date inputs
    $tournament['registration_open_date'] = date('Y-m-d', strtotime($tournament['registration_open_date']));
    $tournament['registration_close_date'] = date('Y-m-d', strtotime($tournament['registration_close_date']));
    $tournament['playing_start_date'] = date('Y-m-d', strtotime($tournament['playing_start_date']));
    $tournament['finish_date'] = date('Y-m-d', strtotime($tournament['finish_date']));
    
    echo json_encode($tournament);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 