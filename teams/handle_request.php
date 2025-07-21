<?php
require_once '../config/database.php';
require_once '../includes/user-auth.php';

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
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'approve' or 'reject'
$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;

if (!$request_id || !in_array($action, ['approve', 'reject']) || !$team_id) {
    $_SESSION['error_message'] = 'Invalid request parameters';
    header("Location: yourteams.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Get request details and verify captain
    $sql = "SELECT tjr.*, t.captain_id, t.max_members, t.name as team_name,
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members,
            u.username as requester_username
            FROM team_join_requests tjr
            JOIN teams t ON tjr.team_id = t.id
            JOIN users u ON tjr.user_id = u.id
            WHERE tjr.id = :request_id AND tjr.status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['request_id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = 'Request not found or already processed';
        header("Location: yourteams.php?team_id=" . $team_id);
        exit;
    }

    // Verify user is the team captain
    if ($request['captain_id'] != $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You are not authorized to handle this request';
        header("Location: yourteams.php?team_id=" . $team_id);
        exit;
    }

    // Check if team is full when approving
    if ($action === 'approve' && $request['current_members'] >= $request['max_members']) {
        $_SESSION['error_message'] = 'Team is full';
        header("Location: yourteams.php?team_id=" . $team_id);
        exit;
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // Update request status
        $update_sql = "UPDATE team_join_requests SET status = :status, updated_at = NOW() 
                      WHERE id = :request_id";
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'request_id' => $request_id
        ]);

        if ($action === 'approve') {
            // Add user to team
            $add_member_sql = "INSERT INTO team_members (team_id, user_id, role, joined_at) 
                             VALUES (:team_id, :user_id, 'member', NOW())";
            $stmt = $conn->prepare($add_member_sql);
            $stmt->execute([
                'team_id' => $team_id,
                'user_id' => $request['user_id']
            ]);

            // Cancel any other pending requests from this user
            $cancel_other_requests_sql = "UPDATE team_join_requests 
                                        SET status = 'cancelled', updated_at = NOW()
                                        WHERE user_id = :user_id 
                                        AND status = 'pending' 
                                        AND id != :request_id";
            $stmt = $conn->prepare($cancel_other_requests_sql);
            $stmt->execute([
                'user_id' => $request['user_id'],
                'request_id' => $request_id
            ]);
        }

        // Create notification for the requesting user
        $notification_sql = "INSERT INTO notifications (user_id, type, message, created_at, is_read) 
                           VALUES (:user_id, :type, :message, NOW(), 0)";
        $stmt = $conn->prepare($notification_sql);
        $stmt->execute([
            'user_id' => $request['user_id'],
            'type' => $action === 'approve' ? 'request_approved' : 'request_rejected',
            'message' => $action === 'approve' 
                ? "Your request to join team '" . $request['team_name'] . "' has been approved!"
                : "Your request to join team '" . $request['team_name'] . "' has been rejected."
        ]);

        $conn->commit();
        $_SESSION['success_message'] = $action === 'approve' 
            ? "Successfully approved " . $request['requester_username'] . "'s request to join the team!"
            : "Request from " . $request['requester_username'] . " has been rejected.";
        
        header("Location: yourteams.php?team_id=" . $team_id);
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in handle_request.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while processing the request';
    header("Location: yourteams.php?team_id=" . $team_id);
    exit;
} 