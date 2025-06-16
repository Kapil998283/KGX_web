<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_POST['round_id']) || !isset($_POST['room_code']) || !isset($_POST['room_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update room details
    $stmt = $conn->prepare("
        UPDATE tournament_rounds 
        SET room_code = ?, room_password = ?, room_details_added_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['room_code'],
        $_POST['room_password'],
        $_POST['round_id']
    ]);
    
    // Get round and tournament details
    $stmt = $conn->prepare("
        SELECT tr.name as round_name, t.id as tournament_id, t.name as tournament_name
        FROM tournament_rounds tr
        JOIN tournaments t ON tr.tournament_id = t.id
        WHERE tr.id = ?
    ");
    $stmt->execute([$_POST['round_id']]);
    $roundInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all users from teams participating in this specific round
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id
        FROM users u
        JOIN team_members tm ON u.id = tm.user_id
        JOIN teams t ON tm.team_id = t.id
        JOIN round_teams rt ON t.id = rt.team_id
        WHERE rt.round_id = ? 
        AND tm.status = 'active'
        AND rt.status IN ('selected', 'qualified')
    ");
    $stmt->execute([$_POST['round_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Create notifications for users in participating teams
    $notificationMessage = "Room details added for {$roundInfo['round_name']} in tournament {$roundInfo['tournament_name']}";
    
    foreach ($users as $userId) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                user_id, 
                type, 
                message, 
                related_id, 
                related_type,
                created_at
            ) VALUES (
                ?, 
                'room_details', 
                ?, 
                ?, 
                'tournament_round',
                NOW()
            )
        ");
        $stmt->execute([
            $userId, 
            $notificationMessage, 
            $_POST['round_id']
        ]);
    }
    
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Room details saved and notifications sent to ' . count($users) . ' users'
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 