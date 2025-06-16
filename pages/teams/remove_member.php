<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please login first';
    header("Location: /KGX/pages/auth/login.php");
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header("Location: yourteams.php");
    exit;
}

// Get POST data
$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;

if (!$member_id || !$team_id) {
    $_SESSION['error_message'] = 'Invalid request parameters';
    header("Location: yourteams.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Start transaction
    $conn->beginTransaction();

    // Get team and captain information
    $team_info_sql = "SELECT t.name as team_name, u.username as captain_name 
                      FROM teams t 
                      JOIN users u ON t.captain_id = u.id 
                      WHERE t.id = :team_id";
    $stmt = $conn->prepare($team_info_sql);
    $stmt->execute(['team_id' => $team_id]);
    $team_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify that the logged-in user is the team captain
    $check_captain_sql = "SELECT 1 FROM team_members 
                         WHERE team_id = :team_id 
                         AND user_id = :user_id 
                         AND role = 'captain'";
    $stmt = $conn->prepare($check_captain_sql);
    $stmt->execute([
        'team_id' => $team_id,
        'user_id' => $_SESSION['user_id']
    ]);

    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = 'You are not authorized to remove members from this team';
        header("Location: yourteams.php?tab=players&team_id=" . $team_id);
        exit;
    }

    // Verify that the member to be removed is not the captain
    $check_member_sql = "SELECT role FROM team_members 
                        WHERE team_id = :team_id 
                        AND user_id = :member_id";
    $stmt = $conn->prepare($check_member_sql);
    $stmt->execute([
        'team_id' => $team_id,
        'member_id' => $member_id
    ]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        $_SESSION['error_message'] = 'Member not found in the team';
        header("Location: yourteams.php?tab=players&team_id=" . $team_id);
        exit;
    }

    if ($member['role'] === 'captain') {
        $_SESSION['error_message'] = 'Cannot remove the team captain';
        header("Location: yourteams.php?tab=players&team_id=" . $team_id);
        exit;
    }

    // Remove the member
    $delete_sql = "DELETE FROM team_members 
                   WHERE team_id = :team_id 
                   AND user_id = :member_id 
                   AND role != 'captain'";
    $stmt = $conn->prepare($delete_sql);
    $stmt->execute([
        'team_id' => $team_id,
        'member_id' => $member_id
    ]);

    if ($stmt->rowCount() > 0) {
        // Create notification for the removed member
        $notification_sql = "INSERT INTO notifications (user_id, type, message, created_at, is_read) 
                           VALUES (:user_id, 'team_removal', :message, NOW(), 0)";
        $stmt = $conn->prepare($notification_sql);
        $stmt->execute([
            'user_id' => $member_id,
            'message' => "You have been removed from team '" . $team_info['team_name'] . "' by captain " . $team_info['captain_name']
        ]);

        $conn->commit();
        $_SESSION['success_message'] = 'Member removed successfully';
    } else {
        $conn->rollBack();
        $_SESSION['error_message'] = 'Failed to remove member';
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error removing member: " . $e->getMessage());
    $_SESSION['error_message'] = 'Database error while removing member';
}

header("Location: yourteams.php?tab=players&team_id=" . $team_id);
exit; 