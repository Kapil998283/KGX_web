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

    if (!$success) {
        throw new Exception("Failed to update registration status");
    }

    // If status is approved, update tournament's current_teams count
    if ($_POST['status'] === 'approved') {
        // First check if we haven't exceeded max_teams
        $stmt = $conn->prepare("
            SELECT current_teams, max_teams 
            FROM tournaments 
            WHERE id = ?
        ");
        $stmt->execute([$_POST['tournament_id']]);
        $tournament = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tournament['current_teams'] >= $tournament['max_teams']) {
            throw new Exception("Tournament has reached maximum team capacity");
        }

        // Update current_teams count
        $stmt = $conn->prepare("
            UPDATE tournaments 
            SET current_teams = current_teams + 1 
            WHERE id = ?
        ");
        $stmt->execute([$_POST['tournament_id']]);
    }

    // Get team name for logging
    $stmt = $conn->prepare("SELECT name FROM teams WHERE id = ?");
    $stmt->execute([$_POST['team_id']]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        throw new Exception("Team not found");
    }

    // Try to log the action, but don't fail if table doesn't exist
    try {
        $action = $_POST['status'] === 'approved' ? 'approve_registration' : 'reject_registration';
        $message = ucfirst($_POST['status']) . ' team "' . $team['name'] . '" for tournament #' . $_POST['tournament_id'];
        
        // Insert into admin_logs table
        $stmt = $conn->prepare("
            INSERT INTO admin_logs (admin_id, action, details, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $message
        ]);
    } catch (Exception $e) {
        // Ignore logging errors
        error_log("Failed to log admin action: " . $e->getMessage());
    }

    // Send notification
    $notifications = new TournamentNotifications($conn);
    $notifications->registrationStatus(
        $_POST['team_id'],
        $_POST['tournament_id'],
        $_POST['status']
    );

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration ' . $_POST['status'] . ' successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Error in update_registration.php: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 