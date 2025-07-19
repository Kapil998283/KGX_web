<?php
/**
 * Logs an admin action to the database
 * 
 * @param PDO $db Database connection
 * @param int $admin_id Admin user ID
 * @param string $action Description of the action performed
 * @param string $details Additional details about the action
 * @return bool True if logging was successful, false otherwise
 */
function logAdminAction($db, $admin_id, $action, $details = '') {
    try {
        $sql = "INSERT INTO admin_logs (admin_id, action, details, created_at) 
                VALUES (:admin_id, :action, :details, NOW())";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'admin_id' => $admin_id,
            'action' => $action,
            'details' => $details
        ]);
    } catch (PDOException $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
} 

/**
 * Updates tournament status automatically based on dates
 */
function updateTournamentStatus($conn, $tournamentId = null) {
    try {
        $now = new DateTime();
        $where = $tournamentId ? "WHERE id = ?" : "";
        
        // Don't update cancelled tournaments
        $sql = "UPDATE tournaments 
                SET status = CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    WHEN CURDATE() < registration_open_date THEN 'upcoming'
                    WHEN CURDATE() BETWEEN registration_open_date AND registration_close_date THEN 'registration'
                    WHEN CURDATE() BETWEEN playing_start_date AND finish_date THEN 'ongoing'
                    WHEN CURDATE() > finish_date THEN 'completed'
                    ELSE status
                END,
                registration_phase = CASE
                    WHEN status = 'cancelled' THEN 'closed'
                    WHEN CURDATE() < registration_open_date THEN 'closed'
                    WHEN CURDATE() BETWEEN registration_open_date AND registration_close_date THEN 'open'
                    WHEN CURDATE() > registration_close_date THEN 'closed'
                    ELSE registration_phase
                END
                $where";

        $stmt = $conn->prepare($sql);
        if ($tournamentId) {
            $stmt->execute([$tournamentId]);
        } else {
            $stmt->execute();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating tournament status: " . $e->getMessage());
        return false;
    }
}

/**
 * Cancel a tournament
 */
function cancelTournament($conn, $tournamentId) {
    try {
        $stmt = $conn->prepare("UPDATE tournaments SET status = 'cancelled', registration_phase = 'closed' WHERE id = ?");
        $stmt->execute([$tournamentId]);
        
        // Log the cancellation
        logAdminAction('cancel_tournament', "Cancelled tournament ID: $tournamentId");
        
        return true;
    } catch (Exception $e) {
        error_log("Error cancelling tournament: " . $e->getMessage());
        return false;
    }
} 