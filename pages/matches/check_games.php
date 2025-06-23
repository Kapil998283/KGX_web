<?php
require_once '../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Check current games in the database
$stmt = $db->query("SELECT * FROM games");
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($games);
echo "</pre>";
?> 