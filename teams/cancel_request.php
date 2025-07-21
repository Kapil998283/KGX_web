<?php
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please login first';
    header("Location: /KGX/pages/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header("Location: index.php");
    exit;
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if (!$request_id) {
    $_SESSION['error_message'] = 'Invalid request ID';
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Verify the request belongs to the user and is pending
    $check_sql = "SELECT id FROM team_join_requests 
                  WHERE id = :request_id 
                  AND user_id = :user_id 
                  AND status = 'pending'";
    $stmt = $conn->prepare($check_sql);
    $stmt->execute([
        'request_id' => $request_id,
        'user_id' => $_SESSION['user_id']
    ]);

    if (!$stmt->fetch()) {
        $_SESSION['error_message'] = 'Request not found or already processed';
        header("Location: index.php");
        exit;
    }

    // Delete the request
    $delete_sql = "DELETE FROM team_join_requests WHERE id = :request_id";
    $stmt = $conn->prepare($delete_sql);
    $stmt->execute(['request_id' => $request_id]);

    $_SESSION['success_message'] = 'Join request cancelled successfully';
    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Error in cancel_request.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while cancelling the request';
    header("Location: index.php");
    exit;
} 