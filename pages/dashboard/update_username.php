<?php
session_start();
require_once '../../includes/user-auth.php';
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if it's a POST request
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the new username
$new_username = trim($_POST['username'] ?? '');

// Validate username
if(empty($new_username)) {
    echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
    exit();
}

// Get database connection
$conn = getDbConnection();

try {
    // Check if username already exists
    $sql = "SELECT id FROM users WHERE username = :username AND id != :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $new_username);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();

    if($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit();
    }

    // Update username
    $sql = "UPDATE users SET username = :username WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $new_username);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);

    if($stmt->execute()) {
        $_SESSION['username'] = $new_username;
        echo json_encode(['success' => true, 'message' => 'Username updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating username']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    error_log("PDO Exception: " . $e->getMessage());
}
?> 