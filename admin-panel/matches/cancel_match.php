<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['match_id'])) {
        throw new Exception('Match ID is required');
    }

    $match_id = $data['match_id'];
    
    // Initialize database connection
    $database = new Database();
    $db = $database->connect();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get match details
    $stmt = $db->prepare("SELECT entry_fee, entry_type FROM matches WHERE id = ? AND status = 'upcoming'");
    $stmt->execute([$match_id]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match) {
        throw new Exception('Match not found or cannot be cancelled');
    }
    
    // Get all joined users
    $stmt = $db->prepare("SELECT user_id FROM match_participants WHERE match_id = ?");
    $stmt->execute([$match_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Refund each user
    foreach ($participants as $participant) {
        if ($match['entry_type'] === 'coins') {
            // Refund coins
            $stmt = $db->prepare("UPDATE user_coins SET coins = coins + ? WHERE user_id = ?");
            $stmt->execute([$match['entry_fee'], $participant['user_id']]);
            $currency_type = 'coins';
        } elseif ($match['entry_type'] === 'tickets') {
            // Refund tickets
            $stmt = $db->prepare("UPDATE user_tickets SET tickets = tickets + ? WHERE user_id = ?");
            $stmt->execute([$match['entry_fee'], $participant['user_id']]);
            $currency_type = 'tickets';
        }
        
        // Add refund transaction record
        $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, type, description, currency_type) VALUES (?, ?, 'refund', ?, ?)");
        $stmt->execute([
            $participant['user_id'],
            $match['entry_fee'],
            "Refund for cancelled match #" . $match_id,
            $currency_type
        ]);

        // Add notification for the user
        $stmt = $db->prepare("INSERT INTO notifications (user_id, type, message, related_id, related_type, created_at) 
                            VALUES (?, 'match_cancelled', ?, ?, 'match', NOW())");
        $stmt->execute([
            $participant['user_id'],
            "Match #" . $match_id . " cancelled: Refunded " . $match['entry_fee'] . " " . $match['entry_type'],
            $match_id
        ]);
    }
    
    // Update match status to cancelled
    $stmt = $db->prepare("UPDATE matches SET 
                         status = 'cancelled',
                         cancelled_at = NOW(),
                         cancellation_reason = 'Cancelled by admin'
                         WHERE id = ?");
    $stmt->execute([$match_id]);
    
    // Remove participants
    $stmt = $db->prepare("DELETE FROM match_participants WHERE match_id = ?");
    $stmt->execute([$match_id]);
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Match cancelled and all users refunded successfully'
    ]);

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 