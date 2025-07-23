<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user-auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate POST data
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;

if (!$request_id || !in_array($action, ['approve', 'reject']) || !$team_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Check if user is team captain and request exists
    $stmt = $conn->prepare("
        SELECT r.*, t.captain_id, t.max_members, 
        (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
        FROM team_join_requests r
        JOIN teams t ON r.team_id = t.id
        WHERE r.id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found or already processed');
    }
    
    if ($request['captain_id'] != $_SESSION['user_id']) {
        throw new Exception('You are not authorized to handle this request');
    }
    
    if ($action === 'approve' && $request['member_count'] >= $request['max_members']) {
        throw new Exception('Team is full');
    }
    
    // Update request status
    $stmt = $conn->prepare("
        UPDATE team_join_requests 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$action === 'approve' ? 'approved' : 'rejected', $request_id]);
    
    if ($action === 'approve') {
        // Add member to team
        $stmt = $conn->prepare("
            INSERT INTO team_members (team_id, user_id, role, joined_at) 
            VALUES (?, ?, 'member', NOW())
        ");
        $stmt->execute([$team_id, $request['user_id']]);
        
        // Cancel other pending requests from this user
        $stmt = $conn->prepare("
            UPDATE team_join_requests 
            SET status = 'cancelled' 
            WHERE user_id = ? AND status = 'pending' AND id != ?
        ");
        $stmt->execute([$request['user_id'], $request_id]);
    }
    
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 'Request approved successfully' : 'Request rejected successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 