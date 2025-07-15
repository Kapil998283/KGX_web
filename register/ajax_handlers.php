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

    case 'send_otp':
        $phone = trim($_POST['phone'] ?? '');
        if (empty($phone)) {
            echo json_encode(['error' => 'Phone number is required']);
            exit;
        }

        // For development: Use a fixed OTP
        $otp = '1234'; // Fixed test OTP
        
        // Store OTP in session with timestamp
        $_SESSION['registration_otp'] = [
            'code' => $otp,
            'phone' => $phone,
            'timestamp' => time(),
            'attempts' => 0
        ];

        // Return success with the test OTP
        echo json_encode([
            'success' => true,
            'message' => '[DEVELOPMENT MODE] Your OTP is: 1234',
            'debug_otp' => $otp,
            'is_dev' => true
        ]);
        break;

    case 'verify_otp':
        $entered_otp = trim($_POST['otp'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($entered_otp) || empty($phone)) {
            echo json_encode(['error' => 'OTP and phone number are required']);
            exit;
        }

        $otp_data = $_SESSION['registration_otp'] ?? null;
        
        if (!$otp_data) {
            echo json_encode(['error' => 'No OTP was sent. Please request a new OTP']);
            exit;
        }

        // Check if OTP is expired (5 minutes)
        if (time() - $otp_data['timestamp'] > 300) {
            unset($_SESSION['registration_otp']);
            echo json_encode(['error' => 'OTP has expired. Please request a new OTP']);
            exit;
        }

        // Check if too many attempts
        if ($otp_data['attempts'] >= 3) {
            unset($_SESSION['registration_otp']);
            echo json_encode(['error' => 'Too many failed attempts. Please request a new OTP']);
            exit;
        }

        // Verify OTP
        if ($entered_otp === $otp_data['code'] && $phone === $otp_data['phone']) {
            $_SESSION['phone_verified'] = true;
            unset($_SESSION['registration_otp']);
            echo json_encode(['success' => true]);
        } else {
            $_SESSION['registration_otp']['attempts']++;
            echo json_encode([
                'error' => 'Invalid OTP',
                'attempts_left' => 3 - $_SESSION['registration_otp']['attempts']
            ]);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
} 