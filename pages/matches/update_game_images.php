<?php
require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Define the updates
$updates = [
    'BGMI' => '/assets/images/games/bgmi.png',
    'PUBG' => '/assets/images/games/pubg.png',
    'Free Fire' => '/assets/images/games/freefire.png',
    'Call of Duty Mobile' => '/assets/images/games/cod.png'
];

try {
    // Start transaction
    $db->beginTransaction();

    // Update existing records
    foreach ($updates as $name => $image_url) {
        $stmt = $db->prepare("UPDATE games SET image_url = ? WHERE name = ?");
        $result = $stmt->execute([$image_url, $name]);
        
        // If the game doesn't exist, insert it
        if ($stmt->rowCount() === 0) {
            $stmt = $db->prepare("INSERT INTO games (name, image_url, status) VALUES (?, ?, 'active')");
            $stmt->execute([$name, $image_url]);
            echo "Added new game: $name with image: $image_url<br>";
        } else {
            echo "Updated game: $name with image: $image_url<br>";
        }
    }

    // Commit transaction
    $db->commit();
    echo "<br>All game images have been updated successfully!<br>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "<br>";
}

// Show current games in database
$stmt = $db->query("SELECT * FROM games");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<br><strong>Current games in database:</strong><br>";
echo "<pre>";
print_r($games);
echo "</pre>";

echo "<br><a href='index.php'>Return to Matches</a>";
?> 