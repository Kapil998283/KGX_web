<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Get round ID from request
$round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : 0;

if (!$round_id) {
    echo json_encode(['error' => 'Invalid round ID']);
    exit();
}

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();

    // Get teams selected for this round
    $stmt = $conn->prepare("
        SELECT 
            t.name as team_name,
            t.logo,
            u.username as captain_name,
            (
                SELECT GROUP_CONCAT(u2.username)
                FROM team_members tm2
                JOIN users u2 ON tm2.user_id = u2.id
                WHERE tm2.team_id = t.id
                AND tm2.status = 'active'
            ) as members,
            rt.status,
            (
                SELECT COUNT(*)
                FROM team_members tm3
                WHERE tm3.team_id = t.id
                AND tm3.status = 'active'
            ) as member_count
        FROM round_teams rt
        JOIN teams t ON rt.team_id = t.id
        LEFT JOIN users u ON t.captain_id = u.id
        WHERE rt.round_id = ?
        ORDER BY t.name
    ");
    
    $stmt->execute([$round_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['teams' => $teams]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 