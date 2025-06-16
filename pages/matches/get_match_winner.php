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
    
    // First check if the match exists and is completed
    $checkStmt = $db->prepare("SELECT id, status FROM matches WHERE id = ?");
    $checkStmt->execute([$match_id]);
    $match = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match) {
        http_response_code(404);
        echo json_encode(['error' => 'Match not found']);
        exit;
    }
    
    if ($match['status'] !== 'completed') {
        http_response_code(400);
        echo json_encode(['error' => 'Match is not completed yet']);
        exit;
    }
    
    // Fetch winner information
    $stmt = $db->prepare("SELECT u.username, t.name as team_name,
                                COALESCE(uk.kills, 0) as kills,
                                COALESCE(uk.kills * m.coins_per_kill, 0) as coins_earned
                         FROM matches m
                         JOIN teams t ON m.winner_id = t.id
                         JOIN match_participants mp ON mp.match_id = m.id AND mp.team_id = t.id
                         JOIN users u ON mp.user_id = u.id
                         LEFT JOIN user_kills uk ON uk.match_id = m.id AND uk.user_id = u.id
                         WHERE m.id = ? AND m.status = 'completed'
                         LIMIT 1");
    
    $stmt->execute([$match_id]);
    $winner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the query results for debugging
    error_log("Winner query results: " . print_r($winner, true));
    
    echo json_encode(['winner' => $winner]);
} catch (Exception $e) {
    error_log("Error in get_match_winner.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} 