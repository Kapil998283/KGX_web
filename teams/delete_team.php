<?php
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get POST data
$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;

if (!$team_id) {
    echo json_encode(['success' => false, 'message' => 'Team ID is required']);
    exit();
}

// Verify if user is the captain
$user_id = $_SESSION['user_id'];
$captain_check_sql = "SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id AND role = 'captain'";
$stmt = $conn->prepare($captain_check_sql);
$stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Delete team members first (due to foreign key constraint)
    $delete_members_sql = "DELETE FROM team_members WHERE team_id = :team_id";
    $stmt = $conn->prepare($delete_members_sql);
    $stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete team
    $delete_team_sql = "DELETE FROM teams WHERE id = :team_id";
    $stmt = $conn->prepare($delete_team_sql);
    $stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Error deleting team');
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 