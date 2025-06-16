<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();

    // Get POST data
    $round_id = isset($_POST['round_id']) ? (int)$_POST['round_id'] : 0;
    $tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
    $selected_teams = isset($_POST['selected_teams']) ? $_POST['selected_teams'] : [];

    // Validate input
    if (!$round_id || !$tournament_id) {
        throw new Exception('Invalid round or tournament ID');
    }

    // Start transaction
    $conn->beginTransaction();

    // Get round details to check teams limit
    $stmt = $conn->prepare("SELECT teams_count FROM tournament_rounds WHERE id = ?");
    $stmt->execute([$round_id]);
    $round = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$round) {
        throw new Exception('Round not found');
    }

    // Check if number of selected teams exceeds the limit
    if (count($selected_teams) > $round['teams_count']) {
        throw new Exception('Too many teams selected. Maximum allowed: ' . $round['teams_count']);
    }

    // Remove existing teams from this round
    $stmt = $conn->prepare("DELETE FROM round_teams WHERE round_id = ?");
    $stmt->execute([$round_id]);

    // Add selected teams to the round
    if (!empty($selected_teams)) {
        $stmt = $conn->prepare("
            INSERT INTO round_teams (round_id, team_id, status) 
            VALUES (?, ?, 'selected')
        ");

        foreach ($selected_teams as $team_id) {
            $stmt->execute([$round_id, $team_id]);
        }
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Teams updated successfully',
        'teams_count' => count($selected_teams)
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 