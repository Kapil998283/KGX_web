<?php
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// Check if team_id is provided
if (!isset($_POST['team_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Team ID is required']);
    exit();
}

$database = new Database();
$conn = $database->connect();

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if team exists and is not full
    $sql = "SELECT t.*, COUNT(tm.user_id) as current_members 
            FROM teams t 
            LEFT JOIN team_members tm ON t.id = tm.team_id 
            WHERE t.id = :team_id AND t.is_active = 1 
            GROUP BY t.id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['team_id' => $_POST['team_id']]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        throw new Exception('Team not found or inactive');
    }

    if ($team['current_members'] >= $team['max_members']) {
        throw new Exception('Team is full');
    }

    // Check if user is already a member or has a pending request
    $sql = "SELECT * FROM team_members 
            WHERE team_id = :team_id AND user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'team_id' => $_POST['team_id'],
        'user_id' => $_SESSION['user_id']
    ]);
    $existing_member = $stmt->fetch();

    if ($existing_member) {
        throw new Exception('You are already a member of this team');
    }

    $sql = "SELECT * FROM team_join_requests 
            WHERE team_id = :team_id AND user_id = :user_id AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'team_id' => $_POST['team_id'],
        'user_id' => $_SESSION['user_id']
    ]);
    $pending_request = $stmt->fetch();

    if ($pending_request) {
        throw new Exception('You already have a pending request for this team');
    }

    // Create join request
    $sql = "INSERT INTO team_join_requests (team_id, user_id) VALUES (:team_id, :user_id)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'team_id' => $_POST['team_id'],
        'user_id' => $_SESSION['user_id']
    ]);

    // Create notification for team captain
    $sql = "INSERT INTO notifications (user_id, message, type) 
            VALUES (:captain_id, :message, 'team_request')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'captain_id' => $team['captain_id'],
        'message' => "New join request for team: " . $team['name']
    ]);

    // Commit transaction
    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Join request sent successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 