<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Read and execute the SQL file
    $sql = file_get_contents('includes/tournament_rounds.sql');
    $conn->exec($sql);
    
    echo "Tables created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 