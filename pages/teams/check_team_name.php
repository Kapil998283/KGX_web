<?php
require_once '../../config/database.php';
session_start();

header('Content-Type: text/plain');

if (!isset($_GET['name'])) {
    echo 'Team name is required';
    exit;
}

$name = trim($_GET['name']);

if (strlen($name) < 3) {
    echo 'Team name must be at least 3 characters long';
    exit;
}

if (strlen($name) > 50) {
    echo 'Team name cannot exceed 50 characters';
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    $check_sql = "SELECT COUNT(*) FROM teams WHERE LOWER(name) = LOWER(:name) AND is_active = 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute(['name' => $name]);
    
    if ($check_stmt->fetchColumn() > 0) {
        echo 'taken';
    } else {
        echo 'available';
    }
} catch (Exception $e) {
    echo 'error';
}
?> 