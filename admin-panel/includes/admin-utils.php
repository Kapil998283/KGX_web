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