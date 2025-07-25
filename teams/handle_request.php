<?php
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please login first';
    header("Location: /KGX/register/login.php");  // Fixed login path
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
$active_tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : 'requests';

// Debug logging
error_log("Processing request - Request ID: $request_id, Action: $action, Team ID: $team_id");

if (!$request_id || !in_array($action, ['approve', 'reject']) || !$team_id) {
    $_SESSION['error_message'] = 'Invalid request parameters';
    error_log("Invalid parameters - Request ID: $request_id, Action: $action, Team ID: $team_id");
    header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Get request details and verify captain
    $sql = "SELECT tjr.*, t.captain_id, t.max_members, t.name as team_name,
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as current_members,
            u.username as requester_username,
            t.id as team_id_check
            FROM team_join_requests tjr
            JOIN teams t ON tjr.team_id = t.id
            JOIN users u ON tjr.user_id = u.id
            WHERE tjr.id = :request_id AND tjr.status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['request_id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug logging
    error_log("Request details: " . print_r($request, true));
    error_log("Session user ID: " . $_SESSION['user_id']);
    error_log("Team ID from form: " . $team_id);
    error_log("Team ID from request: " . ($request ? $request['team_id_check'] : 'not found'));

    if (!$request) {
        $_SESSION['error_message'] = 'Request not found or already processed';
        error_log("Request not found or already processed - Request ID: $request_id");
        header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
        exit;
    }

    // Verify team ID matches
    if ($request['team_id_check'] != $team_id) {
        $_SESSION['error_message'] = 'Team ID mismatch';
        error_log("Team ID mismatch - Form: $team_id, DB: " . $request['team_id_check']);
        header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
        exit;
    }

    // Verify user is the team captain
    if ($request['captain_id'] != $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You are not authorized to handle this request';
        error_log("Unauthorized - User ID: {$_SESSION['user_id']}, Captain ID: {$request['captain_id']}");
        header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
        exit;
    }

    // Check if team is full when approving
    if ($action === 'approve' && $request['current_members'] >= $request['max_members']) {
        $_SESSION['error_message'] = 'Team is full';
        error_log("Team is full - Current: {$request['current_members']}, Max: {$request['max_members']}");
        header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
        exit;
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // Update request status
        $update_sql = "UPDATE team_join_requests 
                      SET status = :status 
                      WHERE id = :request_id 
                      AND status = 'pending'";  // Added status check
        $stmt = $conn->prepare($update_sql);
        $result = $stmt->execute([
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'request_id' => $request_id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update request status or request already processed');
        }

        if ($action === 'approve') {
            // Check if user is already a member
            $check_member_sql = "SELECT COUNT(*) FROM team_members 
                               WHERE team_id = :team_id AND user_id = :user_id";
            $stmt = $conn->prepare($check_member_sql);
            $stmt->execute([
                'team_id' => $team_id,
                'user_id' => $request['user_id']
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('User is already a member of this team');
            }

            // Add user to team
            $add_member_sql = "INSERT INTO team_members (team_id, user_id, role, status, joined_at) 
                             VALUES (:team_id, :user_id, 'member', 'active', NOW())";
            $stmt = $conn->prepare($add_member_sql);
            $result = $stmt->execute([
                'team_id' => $team_id,
                'user_id' => $request['user_id']
            ]);

            if (!$result) {
                throw new Exception('Failed to add user to team');
            }

            // Cancel any other pending requests from this user
            $cancel_other_requests_sql = "UPDATE team_join_requests 
                                        SET status = 'cancelled'
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
        
        header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in handle_request.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while processing the request';
    header("Location: yourteams.php?team_id=" . $team_id . "&tab=" . $active_tab);
    exit;
} 