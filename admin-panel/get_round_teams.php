<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : 0;

if (!$round_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Round ID is required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Get teams assigned to this round
    $stmt = $conn->prepare("
        SELECT t.id, t.name, rt.status, rt.points, rt.rank
        FROM teams t
        INNER JOIN round_teams rt ON t.id = rt.team_id
        WHERE rt.round_id = ?
        ORDER BY rt.rank, t.name
    ");
    $stmt->execute([$round_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($teams);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 