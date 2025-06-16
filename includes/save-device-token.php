<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Token is required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    // Check if token already exists for this user
    $stmt = $conn->prepare("SELECT id FROM device_tokens WHERE user_id = ? AND token = ?");
    $stmt->execute([$_SESSION['user_id'], $data['token']]);
    
    if (!$stmt->fetch()) {
        // Token doesn't exist, insert it
        $stmt = $conn->prepare("INSERT INTO device_tokens (user_id, token, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $data['token']]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save device token']);
} 