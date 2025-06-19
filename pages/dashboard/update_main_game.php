<?php
session_start();
require_once '../../config/database.php';

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Get the game name from POST data
    $game_name = $_POST['game_name'] ?? '';
    if (empty($game_name)) {
        throw new Exception('Game name is required');
    }

    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();
    
    // Enable PDO error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Start transaction
    $conn->beginTransaction();

    try {
        // First, check if the user has this game
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_games WHERE user_id = :user_id AND game_name = :game_name");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':game_name', $game_name);
        $stmt->execute();
        $has_game = $stmt->fetchColumn() > 0;

        // If user doesn't have this game, add it
        if (!$has_game) {
            $stmt = $conn->prepare("INSERT INTO user_games (user_id, game_name) VALUES (:user_id, :game_name)");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':game_name', $game_name);
            $stmt->execute();
        }

        // Set all games to non-primary
        $stmt = $conn->prepare("UPDATE user_games SET is_primary = 0 WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();

        // Set the selected game as primary
        $stmt = $conn->prepare("UPDATE user_games SET is_primary = 1 WHERE user_id = :user_id AND game_name = :game_name");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':game_name', $game_name);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        $response['message'] = 'Main game updated successfully';

    } catch (PDOException $e) {
        // Log the specific database error
        $response['error_details'] = $e->getMessage();
        throw new Exception('Database error occurred: ' . $e->getMessage());
    }

} catch (Exception $e) {
    // Rollback transaction if there was an error
    if (isset($conn)) {
        $conn->rollBack();
    }
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 