<?php
require_once '../config/database.php';
require_once 'includes/admin-auth.php';

// Initialize response array
$response = ['success' => false, 'message' => ''];
$transaction_started = false;

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();
    
    // Enable PDO error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if user_games table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'user_games'");
    $table_exists = $stmt->rowCount() > 0;

    // Start transaction
    $conn->beginTransaction();
    $transaction_started = true;

    if ($table_exists) {
        // Drop existing triggers if they exist
        $conn->exec("DROP TRIGGER IF EXISTS before_user_games_insert");
        $conn->exec("DROP TRIGGER IF EXISTS before_user_games_update");

        // Backup existing data
        $stmt = $conn->query("SELECT * FROM user_games");
        $existing_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Drop the existing table
        $conn->exec("DROP TABLE IF EXISTS user_games");
    }
    
    // Create the table with new structure
    $conn->exec("
        CREATE TABLE user_games (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            game_name ENUM('PUBG', 'BGMI', 'FREE FIRE', 'COD') NOT NULL,
            game_username VARCHAR(50),
            game_uid VARCHAR(20),
            is_primary BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_game (user_id, game_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Restore the data if we had existing data
    if (isset($existing_data) && !empty($existing_data)) {
        $stmt = $conn->prepare("
            INSERT INTO user_games 
            (user_id, game_name, game_username, game_uid, is_primary, created_at, updated_at) 
            VALUES 
            (:user_id, :game_name, :game_username, :game_uid, :is_primary, :created_at, :updated_at)
        ");

        foreach ($existing_data as $row) {
            $stmt->execute([
                ':user_id' => $row['user_id'],
                ':game_name' => $row['game_name'],
                ':game_username' => $row['game_username'] ?? null,
                ':game_uid' => $row['game_uid'] ?? null,
                ':is_primary' => $row['is_primary'] ?? 0,
                ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    }

    // Commit transaction
    $conn->commit();
    $transaction_started = false;

    $response['success'] = true;
    $response['message'] = "User games table structure updated successfully!";

} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($transaction_started && isset($conn)) {
        try {
            $conn->rollBack();
        } catch (Exception $rollback_error) {
            // Ignore rollback errors
        }
    }
    $response['message'] = "Error: " . $e->getMessage();
}

// Send response
header('Content-Type: application/json');
echo json_encode($response);