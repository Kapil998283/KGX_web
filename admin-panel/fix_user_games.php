<?php
require_once '../config/database.php';
require_once 'includes/admin-auth.php';

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();
    
    // Start transaction
    $conn->beginTransaction();

    // Drop existing triggers if they exist
    $conn->exec("DROP TRIGGER IF EXISTS before_user_games_insert");
    $conn->exec("DROP TRIGGER IF EXISTS before_user_games_update");

    // Backup existing data
    $stmt = $conn->query("SELECT * FROM user_games");
    $existing_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Drop and recreate the table
    $conn->exec("DROP TABLE IF EXISTS user_games");
    
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

    // Restore the data
    if (!empty($existing_data)) {
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
                ':game_username' => $row['game_username'],
                ':game_uid' => $row['game_uid'],
                ':is_primary' => $row['is_primary'],
                ':created_at' => $row['created_at'],
                ':updated_at' => $row['updated_at']
            ]);
        }
    }

    // Commit transaction
    $conn->commit();
    echo "User games table structure updated successfully!";

} catch (Exception $e) {
    // Rollback transaction if there was an error
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}