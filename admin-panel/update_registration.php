<?php
require_once 'includes/admin-auth.php';
require_once '../config/database.php';
require_once '../includes/tournament_notifications.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

// Debug log
error_log("Received POST data: " . print_r($_POST, true));

// Validate required parameters
if (empty($_POST['team_id']) || empty($_POST['tournament_id']) || empty($_POST['status'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Validate status
$allowed_statuses = ['approved', 'rejected'];
if (!in_array($_POST['status'], $allowed_statuses)) {
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Begin transaction
    $conn->beginTransaction();

    // First check if the registration exists and its current status
    $stmt = $conn->prepare("
        SELECT status 
        FROM tournament_registrations 
        WHERE team_id = ? AND tournament_id = ?
    ");
    $stmt->execute([$_POST['team_id'], $_POST['tournament_id']]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception("Registration not found");
    }

    if ($registration['status'] !== 'pending') {
        throw new Exception("Registration has already been " . $registration['status']);
    }

    // Update registration status
    $stmt = $conn->prepare("
        UPDATE tournament_registrations 
        SET status = ? 
        WHERE team_id = ? AND tournament_id = ?
    ");
    
    $success = $stmt->execute([
        $_POST['status'],
        $_POST['team_id'],
        $_POST['tournament_id']
    ]);

    if ($success && $_POST['status'] === 'approved') {
        // Get team members
        $stmt = $conn->prepare("
            SELECT user_id 
            FROM team_members 
            WHERE team_id = ? AND status = 'active'
        ");
        $stmt->execute([$_POST['team_id']]);
        $team_members = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Create tournament history records for all team members
        $stmt = $conn->prepare("
            INSERT INTO tournament_player_history (
                tournament_id,
                user_id,
                team_id,
                registration_date,
                status
            ) VALUES (?, ?, ?, NOW(), 'registered')
        ");

        foreach ($team_members as $user_id) {
            $stmt->execute([
                $_POST['tournament_id'],
                $user_id,
                $_POST['team_id']
            ]);
        }

        // Get tournament details for notification
        $stmt = $conn->prepare("SELECT name FROM tournaments WHERE id = ?");
        $stmt->execute([$_POST['tournament_id']]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

        // Send notifications
        $notifications = new TournamentNotifications($conn);
        $notifications->registrationStatus(
            $_POST['team_id'],
            $tournament['name'],
            'approved'
        );
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
?> 