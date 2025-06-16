<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['round_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Round ID is required']);
    exit();
}

$round_id = (int)$_GET['round_id'];

try {
    $database = new Database();
    $conn = $database->connect();

    // Get teams for this round with their results
    $stmt = $conn->prepare("
        SELECT 
            t.id,
            t.name,
            rt.placement,
            rt.kills,
            rt.kill_points,
            rt.placement_points,
            rt.bonus_points,
            rt.total_points,
            rt.status
        FROM teams t
        INNER JOIN tournament_registrations tr ON t.id = tr.team_id
        INNER JOIN tournament_rounds r ON tr.tournament_id = r.tournament_id
        LEFT JOIN round_teams rt ON t.id = rt.team_id AND r.id = rt.round_id
        WHERE r.id = ? AND tr.status = 'approved'
        ORDER BY 
            CASE 
                WHEN rt.placement IS NULL THEN 1
                ELSE 0
            END,
            rt.placement ASC,
            t.name ASC
    ");
    $stmt->execute([$round_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($teams);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 