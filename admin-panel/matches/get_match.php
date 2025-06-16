<?php
require_once '../includes/admin-auth.php';
require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get match ID from URL
$match_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($match_id > 0) {
    // Fetch match details
    $stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($match) {
        // Format date and time
        $match['match_date'] = date('Y-m-d', strtotime($match['match_date']));
        $match['match_time'] = date('H:i', strtotime($match['match_date']));
        
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($match);
        exit;
    }
}

// If we get here, something went wrong
http_response_code(404);
echo json_encode(['error' => 'Match not found']); 