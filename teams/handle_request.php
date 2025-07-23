<?php
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Please login first');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Get POST data and validate
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);

// Log received data for debugging
error_log("Received request - ID: $request_id, Action: $action, Team ID: $team_id");

if (!$request_id || !in_array($action, ['approve', 'reject']) || !$team_id) {
    sendJsonResponse(false, 'Invalid request parameters');
}

try {
    $database = new Database();
    $conn = $database->connect();

    if (!$conn) {
        error_log("Database connection failed");
        sendJsonResponse(false, 'Database connection failed');
    }

    // Get request details and verify captain
    $sql = "SELECT tjr.*, t.captain_id, t.max_members, t.name as team_name,
            (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members,
            u.username as requester_username, tjr.user_id
            FROM team_join_requests tjr
            JOIN teams t ON tjr.team_id = t.id
            JOIN users u ON tjr.user_id = u.id
            WHERE tjr.id = :request_id AND tjr.status = 'pending'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        sendJsonResponse(false, 'Failed to prepare database query');
    }

    $stmt->execute(['request_id' => $request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log request data for debugging
    error_log("Request data: " . print_r($request, true));
    error_log("Session user ID: " . $_SESSION['user_id']);

    if (!$request) {
        sendJsonResponse(false, 'Request not found or already processed');
    }

    // Verify user is the team captain
    if ($request['captain_id'] != $_SESSION['user_id']) {
        sendJsonResponse(false, 'You are not authorized to handle this request');
    }

    // Check if team is full when approving
    if ($action === 'approve' && $request['current_members'] >= $request['max_members']) {
        sendJsonResponse(false, 'Team is full');
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
            // Check if user is already a member
            $check_member_sql = "SELECT COUNT(*) as count FROM team_members 
                               WHERE team_id = :team_id AND user_id = :user_id";
            $stmt = $conn->prepare($check_member_sql);
            $stmt->execute([
                'team_id' => $team_id,
                'user_id' => $request['user_id']
            ]);
            $member_check = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($member_check['count'] == 0) {
                // Add user to team
                $add_member_sql = "INSERT INTO team_members (team_id, user_id, role, joined_at) 
                                 VALUES (:team_id, :user_id, 'member', NOW())";
                $stmt = $conn->prepare($add_member_sql);
                $stmt->execute([
                    'team_id' => $team_id,
                    'user_id' => $request['user_id']
                ]);
            }

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
        
        $successMessage = $action === 'approve' 
            ? "Successfully approved " . $request['requester_username'] . "'s request to join the team!"
            : "Request from " . $request['requester_username'] . " has been rejected.";
        
        sendJsonResponse(true, $successMessage);

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Transaction error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in handle_request.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse(false, 'An error occurred while processing the request: ' . $e->getMessage());
} 