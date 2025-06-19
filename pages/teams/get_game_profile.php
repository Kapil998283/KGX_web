<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Initialize response array
$response = ['success' => false, 'message' => '', 'game_profile' => null];

try {
    // Get user ID from query parameter
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID');
    }

    // Initialize database connection
    $database = new Database();
    $conn = $database->connect();

    // Get user's primary game profile
    $sql = "SELECT game_name, game_username, game_uid, is_primary 
            FROM user_games 
            WHERE user_id = :user_id AND is_primary = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $game_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no primary game found, get any game profile
    if (!$game_profile) {
        $sql = "SELECT game_name, game_username, game_uid, is_primary 
                FROM user_games 
                WHERE user_id = :user_id 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $game_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($game_profile) {
        $response['success'] = true;
        $response['game_profile'] = $game_profile;
    } else {
        $response['message'] = 'No game profile found';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 