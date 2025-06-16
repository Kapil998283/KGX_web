<?php
require_once '../config/database.php';
require_once 'check_admin.php';

header('Content-Type: application/json');

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get game_id from query parameter
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

if ($game_id > 0) {
    // Fetch maps for the selected game
    $stmt = $db->prepare("SELECT map_name FROM game_maps WHERE game_id = ? AND status = 'active' ORDER BY map_name");
    $stmt->execute([$game_id]);
    $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($maps);
} else {
    echo json_encode([]);
}
?> 