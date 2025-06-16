<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['round_id'])) {
    echo json_encode(['error' => 'Round ID not provided']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    $stmt = $conn->prepare("SELECT room_code, room_password FROM tournament_rounds WHERE id = ?");
    $stmt->execute([$_GET['round_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($result ?: ['room_code' => '', 'room_password' => '']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?> 