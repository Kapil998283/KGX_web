<?php
require_once '../../config/database.php';

function sendMatchNotification($user_id, $match_id, $type, $message) {
    $database = new Database();
    $db = $database->connect();
    
    try {
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
                ?, 
                ?, 
                ?, 
                'match',
                NOW()
            )
        ");
        $stmt->execute([$user_id, $type, $message, $match_id]);
        return true;
    } catch (Exception $e) {
        error_log("Error sending match notification: " . $e->getMessage());
        return false;
    }
}

function notifyMatchParticipants($match_id, $type, $message) {
    $database = new Database();
    $db = $database->connect();
    
    try {
        // Get all participants of the match
        $stmt = $db->prepare("
            SELECT DISTINCT user_id 
            FROM match_participants 
            WHERE match_id = ?
        ");
        $stmt->execute([$match_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Send notification to each participant
        foreach ($participants as $user_id) {
            sendMatchNotification($user_id, $match_id, $type, $message);
        }
        return true;
    } catch (Exception $e) {
        error_log("Error notifying match participants: " . $e->getMessage());
        return false;
    }
} 