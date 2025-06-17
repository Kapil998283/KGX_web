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
    $stmt = $db->prepare("SELECT 
                            u.username, 
                            t.name as team_name,
                            mp.position,
                            COALESCE(uk.kills, 0) as kills,
                            COALESCE(uk.kills * m.coins_per_kill + 
                                CASE 
                                    WHEN mp.position = 1 THEN m.prize_pool * 0.5
                                    WHEN mp.position = 2 THEN m.prize_pool * 0.3
                                    WHEN mp.position = 3 THEN m.prize_pool * 0.2
                                    ELSE 0
                                END, 0) as coins_earned
                         FROM match_participants mp
                         JOIN users u ON mp.user_id = u.id
                         JOIN matches m ON m.id = mp.match_id
                         LEFT JOIN teams t ON mp.team_id = t.id
                         LEFT JOIN user_kills uk ON uk.match_id = m.id AND uk.user_id = u.id
                         WHERE mp.match_id = ? 
                         AND m.status = 'completed'
                         AND mp.position IS NOT NULL
                         ORDER BY mp.position ASC");
    
    $stmt->execute([$match_id]);
    $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the query results for debugging
    error_log("Winners query results: " . print_r($winners, true));
    
    echo json_encode(['winners' => $winners]);
} catch (Exception $e) {
    error_log("Error in get_match_winner.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} 