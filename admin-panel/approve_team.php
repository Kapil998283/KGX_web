<?php
require_once '../includes/admin-auth.php';
require_once '../config/database.php';
require_once '../includes/notification-helper.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['registration_id'])) {
        throw new Exception('Registration ID is required');
    }

    $database = new Database();
    $conn = $database->connect();

    // Get registration details
    $stmt = $conn->prepare("
        SELECT tr.*, t.name as team_name, tour.name as tournament_name 
        FROM tournament_registrations tr
        JOIN teams t ON tr.team_id = t.id
        JOIN tournaments tour ON tr.tournament_id = tour.id
        WHERE tr.id = ?
    ");
    $stmt->execute([$data['registration_id']]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registration not found');
    }

    // Update registration status
    $stmt = $conn->prepare("UPDATE tournament_registrations SET status = 'approved' WHERE id = ?");
    $result = $stmt->execute([$data['registration_id']]);

    if ($result) {
        // Send notification to team members
        NotificationHelper::sendToTeam(
            $registration['team_id'],
            "Tournament Registration Approved!",
            "Your team {$registration['team_name']} has been approved for {$registration['tournament_name']}!",
            "/KGX/pages/tournaments/details.php?id={$registration['tournament_id']}"
        );

        echo json_encode(['success' => true, 'message' => 'Team approved successfully']);
    } else {
        throw new Exception('Failed to approve team');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 