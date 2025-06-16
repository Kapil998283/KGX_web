<?php
require_once '../../config/database.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get match ID from request
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

if (!$match_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid match ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // First check if the match exists
    $checkStmt = $db->prepare("SELECT id FROM matches WHERE id = ?");
    $checkStmt->execute([$match_id]);
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Match not found']);
        exit;
    }
    
    // Fetch participants with their kills and coins earned
    $stmt = $db->prepare("SELECT u.username, 
                                COALESCE(uk.kills, 0) as kills,
                                COALESCE(uk.kills * m.coins_per_kill, 0) as coins_earned
                         FROM match_participants mp
                         JOIN users u ON mp.user_id = u.id
                         JOIN matches m ON mp.match_id = m.id
                         LEFT JOIN user_kills uk ON uk.match_id = mp.match_id AND uk.user_id = mp.user_id
                         WHERE mp.match_id = ?
                         ORDER BY uk.kills DESC, u.username ASC");
    
    $stmt->execute([$match_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the query results for debugging
    error_log("Participants query results: " . print_r($participants, true));
    
    echo json_encode($participants);
} catch (Exception $e) {
    error_log("Error in get_match_participants.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} 