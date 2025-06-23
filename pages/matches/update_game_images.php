<?php
require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

try {
    $db->beginTransaction();

    $game_paths = [
        'BGMI' => '../../assets/images/games/bgmi.png',
        'PUBG' => '../../assets/images/games/pubg.png',
        'Free Fire' => '../../assets/images/games/freefire.png',
        'COD' => '../../assets/images/games/cod.png'
    ];

    foreach ($game_paths as $game_name => $image_path) {
        $stmt = $db->prepare("UPDATE games SET image_url = ? WHERE name = ?");
        $stmt->execute([$image_path, $game_name]);
    }

    $db->commit();
    echo "Successfully updated game image paths.";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error updating game image paths: " . $e->getMessage();
}
?> 