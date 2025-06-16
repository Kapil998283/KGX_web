<?php
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['match_id'])) {
    echo json_encode(['error' => 'Match ID not provided']);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    $stmt = $db->prepare("SELECT room_code, room_password FROM matches WHERE id = ?");
    $stmt->execute([$_GET['match_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($result ?: ['room_code' => '', 'room_password' => '']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 