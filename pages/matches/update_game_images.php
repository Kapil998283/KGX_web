<?php
require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Define the updates
$updates = [
    'BGMI' => '/KGX/assets/images/games/bgmi.png',
    'PUBG' => '/KGX/assets/images/games/pubg.png',
    'Free Fire' => '/KGX/assets/images/games/freefire.png',
    'Call of Duty Mobile' => '/KGX/assets/images/games/cod.png'
];

// First, delete any existing games
$db->query("DELETE FROM games");

// Then insert the games with correct image paths
foreach ($updates as $name => $image_url) {
    $stmt = $db->prepare("INSERT INTO games (name, image_url, status) VALUES (?, ?, 'active')");
    $stmt->execute([$name, $image_url]);
    echo "Added game: $name with image: $image_url<br>";
}

echo "<br>All game images have been updated successfully!<br>";
echo "<a href='index.php'>Return to Matches</a>";
?> 