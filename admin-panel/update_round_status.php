<?php
require_once '../includes/admin-auth.php';
require_once '../config/database.php';
require_once '../includes/notification-helper.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['round_id']) || !isset($data['status'])) {
        throw new Exception('Missing required parameters');
    }

    $round_id = (int)$data['round_id'];
    $status = $data['status'];

    // Validate status
    $valid_statuses = ['upcoming', 'in_progress', 'completed'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status value');
    }

    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();

    // Start transaction
    $conn->beginTransaction();

    // Get round and tournament details
    $stmt = $conn->prepare("
        SELECT r.*, t.id as tournament_id, t.name as tournament_name 
        FROM tournament_rounds r 
        JOIN tournaments t ON r.tournament_id = t.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$round_id]);
    $round = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$round) {
        throw new Exception('Round not found');
    }

    // Update round status
    $stmt = $conn->prepare("UPDATE tournament_rounds SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $round_id]);

    if ($result) {
        // Send notifications based on status
        switch ($status) {
            case 'in_progress':
                NotificationHelper::sendToTournamentParticipants(
                    $round['tournament_id'],
                    "Round Starting Now!",
                    "Round {$round['round_number']} of {$round['tournament_name']} is starting now!",
                    "/KGX/pages/tournaments/match-schedule.php?tournament_id={$round['tournament_id']}"
                );
                break;

            case 'completed':
                // Update tournament history for all players
                updateTournamentPlayerHistory($round_id, $conn);

                // Send completion notification
                NotificationHelper::sendToTournamentParticipants(
                    $round['tournament_id'],
                    "Round Completed",
                    "Round {$round['round_number']} of {$round['tournament_name']} has been completed. Check the results!",
                    "/KGX/pages/tournaments/match-schedule.php?tournament_id={$round['tournament_id']}"
                );

                // Check if tournament is completed
                checkAndUpdateTournamentStatus($round['tournament_id'], $conn);
                break;
        }

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Round status updated successfully']);
    } else {
        throw new Exception('Failed to update round status');
    }

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 