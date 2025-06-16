<?php
// Include the Database class
require_once __DIR__ . '/../config/database.php';

// Use the Database class to connect to the database
$database = new Database();
$conn = $database->connect();

// Set timezone
date_default_timezone_set('Asia/Kolkata');
?> 