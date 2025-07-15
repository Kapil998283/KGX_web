<?php
require_once '../config/database.php';
header('Content-Type: application/json');

if (!isset($_POST['field']) || !isset($_POST['value'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$field = $_POST['field'];
$value = trim($_POST['value']);

// Validate the field parameter
if (!in_array($field, ['username', 'email'])) {
    echo json_encode(['error' => 'Invalid field parameter']);
    exit;
}

try {
    $pdo = getConnection();
    
    $sql = "SELECT COUNT(*) as count FROM users WHERE $field = :value";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['value' => $value]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $isAvailable = $result['count'] == 0;
    
    echo json_encode([
        'available' => $isAvailable,
        'message' => $isAvailable ? 
            ($field === 'username' ? 'Username is available' : 'Email is available') : 
            ($field === 'username' ? 'Username is already taken' : 'Email is already registered')
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error occurred']);
}
?> 