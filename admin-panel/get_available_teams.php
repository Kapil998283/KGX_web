<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();

    // Get parameters
    $round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : 0;
    $tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;

    if (!$round_id || !$tournament_id) {
        throw new Exception('Invalid round or tournament ID');
    }

    // Get round details
    $stmt = $conn->prepare("
        SELECT r.*, td.day_number 
        FROM tournament_rounds r
        JOIN tournament_days td ON r.day_id = td.id
        WHERE r.id = ?
    ");
    $stmt->execute([$round_id]);
    $round = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$round) {
        throw new Exception('Round not found');
    }

    // For day 1, get all registered teams
    if ($round['day_number'] == 1) {
        $stmt = $conn->prepare("
            SELECT 
                t.*,
                CASE WHEN rt.team_id IS NOT NULL THEN 1 ELSE 0 END as is_selected
            FROM teams t
            INNER JOIN tournament_registrations tr ON t.id = tr.team_id
            LEFT JOIN round_teams rt ON t.id = rt.team_id AND rt.round_id = ?
            WHERE tr.tournament_id = ? AND tr.status = 'approved'
            ORDER BY t.name
        ");
        $stmt->execute([$round_id, $tournament_id]);
    } 
    // For other days, get teams that qualified from previous day
    else {
        $previousDayNumber = $round['day_number'] - 1;
        
        $stmt = $conn->prepare("
            SELECT 
                t.*,
                CASE WHEN current_rt.team_id IS NOT NULL THEN 1 ELSE 0 END as is_selected
            FROM teams t
            INNER JOIN round_teams prev_rt ON t.id = prev_rt.team_id
            INNER JOIN tournament_rounds prev_r ON prev_rt.round_id = prev_r.id
            INNER JOIN tournament_days prev_td ON prev_r.day_id = prev_td.id
            LEFT JOIN round_teams current_rt ON t.id = current_rt.team_id AND current_rt.round_id = ?
            WHERE prev_td.tournament_id = ?
            AND prev_td.day_number = ?
            AND prev_rt.status = 'qualified'
            GROUP BY t.id
            ORDER BY t.name
        ");
        $stmt->execute([$round_id, $tournament_id, $previousDayNumber]);
    }

    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return success response
    echo json_encode([
        'success' => true,
        'teams' => $teams
    ]);

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 