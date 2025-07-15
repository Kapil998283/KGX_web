<?php
session_start();
require_once '../includes/user-auth.php';
require_once '../config/config.php';

header('Content-Type: application/json');

// Get database connection
$conn = getDbConnection();

// Handle different AJAX actions
$action = $_POST['action'] ?? '';

switch($action) {
    case 'check_username':
        $username = trim($_POST['username'] ?? '');
        if (empty($username)) {
            echo json_encode(['error' => 'Username is required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        echo json_encode(['exists' => $stmt->rowCount() > 0]);
        break;

    case 'check_email':
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            echo json_encode(['error' => 'Email is required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        echo json_encode(['exists' => $stmt->rowCount() > 0]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
} 