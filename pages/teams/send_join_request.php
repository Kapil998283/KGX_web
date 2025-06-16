<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

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

$team_id = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;

if (!$team_id) {
    $_SESSION['error_message'] = 'Invalid team ID';
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Check if user is already in a team or has pending requests
    $check_sql = "SELECT 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM team_members 
                            WHERE user_id = :user_id AND team_id = :team_id
                        ) THEN 'already_member'
                        WHEN EXISTS (
                            SELECT 1 FROM team_members 
                            WHERE user_id = :user_id
                        ) THEN 'in_other_team'
                        WHEN EXISTS (
                            SELECT 1 FROM team_join_requests 
                            WHERE user_id = :user_id AND status = 'pending'
                        ) THEN 'has_pending'
                        ELSE 'can_join'
                    END as status";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'team_id' => $team_id
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    switch ($result['status']) {
        case 'already_member':
            $_SESSION['error_message'] = 'You are already a member of this team';
            header("Location: index.php");
            exit;
        
        case 'in_other_team':
            $_SESSION['error_message'] = 'You are already a member of another team. Leave that team first to join a new one.';
            header("Location: index.php");
            exit;
            
        case 'has_pending':
            $_SESSION['error_message'] = 'You already have a pending join request';
            header("Location: index.php");
            exit;
    }

    // Check if team is full
    $team_sql = "SELECT t.max_members, 
                 (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as current_members
                 FROM teams t WHERE t.id = :team_id";
    $stmt = $conn->prepare($team_sql);
    $stmt->execute(['team_id' => $team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($team['current_members'] >= $team['max_members']) {
        $_SESSION['error_message'] = 'This team is full';
        header("Location: index.php");
        exit;
    }

    // Create join request
    $sql = "INSERT INTO team_join_requests (team_id, user_id, status, created_at) 
            VALUES (:team_id, :user_id, 'pending', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'team_id' => $team_id,
        'user_id' => $_SESSION['user_id']
    ]);

    $_SESSION['success_message'] = 'Join request sent successfully';
    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Error in send_join_request.php: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while sending the join request';
    header("Location: index.php");
    exit;
} 