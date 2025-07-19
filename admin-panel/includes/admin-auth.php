<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

// Initialize database connection if not already initialized
function getDbConnection() {
    static $conn = null;
    if ($conn === null) {
        $database = new Database();
        $conn = $database->connect();
    }
    return $conn;
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header('Location: ../login.php');
    exit();
}

// Function to check if user has required role
function checkAdminRole($required_role) {
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== $required_role) {
        header('Location: ../dashboard/index.php');
        exit();
    }
}

// Function to log admin activity
function logAdminAction($action, $description) {
    $db = getDbConnection();
    $stmt = $db->prepare("INSERT INTO admin_activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['admin_id'], $action, $description, $_SERVER['REMOTE_ADDR']]);
}

// Function to get admin user data
function getAdminUser($admin_id) {
    $conn = getDbConnection();
    
    $sql = "SELECT * FROM admin_users WHERE id = :admin_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':admin_id', $admin_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
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
?> 