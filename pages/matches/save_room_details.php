<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_POST['match_id']) || !isset($_POST['room_code']) || !isset($_POST['room_password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // Update room details
    $stmt = $db->prepare("
        UPDATE matches 
        SET room_code = ?, room_password = ?, room_details_added_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['room_code'],
        $_POST['room_password'],
        $_POST['match_id']
    ]);
    
    // Get match details
    $stmt = $db->prepare("
        SELECT m.id, m.match_type, g.name as game_name
        FROM matches m
        JOIN games g ON m.game_id = g.id
        WHERE m.id = ?
    ");
    $stmt->execute([$_POST['match_id']]);
    $matchInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all users participating in this match
    $stmt = $db->prepare("
        SELECT DISTINCT u.id
        FROM users u
        JOIN match_participants mp ON u.id = mp.user_id
        WHERE mp.match_id = ?
    ");
    $stmt->execute([$_POST['match_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Create notifications for participating users
    $notificationMessage = "Room details added for {$matchInfo['game_name']} {$matchInfo['match_type']} match";
    
    foreach ($users as $userId) {
        $stmt = $db->prepare("
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
                'match',
                NOW()
            )
        ");
        $stmt->execute([
            $userId, 
            $notificationMessage, 
            $_POST['match_id']
        ]);
    }
    
    $db->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Room details saved and notifications sent to ' . count($users) . ' users'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 