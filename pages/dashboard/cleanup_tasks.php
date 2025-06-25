<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    exit('Not authorized');
}

$database = new Database();
$conn = $database->connect();

// Find duplicate daily login tasks
$find_duplicates_sql = "SELECT name, COUNT(*) as count 
                       FROM streak_tasks 
                       WHERE name = 'Daily Login' 
                       GROUP BY name 
                       HAVING count > 1";
$find_stmt = $conn->prepare($find_duplicates_sql);
$find_stmt->execute();
$duplicates = $find_stmt->fetch(PDO::FETCH_ASSOC);

if ($duplicates && $duplicates['count'] > 1) {
    try {
        $conn->beginTransaction();

        // Keep only one daily login task (the one with the lowest ID)
        $cleanup_sql = "DELETE st1 FROM streak_tasks st1
                       INNER JOIN streak_tasks st2
                       WHERE st1.name = 'Daily Login'
                       AND st2.name = 'Daily Login'
                       AND st1.id > st2.id";
        $cleanup_stmt = $conn->prepare($cleanup_sql);
        $cleanup_stmt->execute();

        $conn->commit();
        echo "Cleaned up duplicate tasks";
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error cleaning up: " . $e->getMessage();
    }
} else {
    echo "No duplicates found";
} 