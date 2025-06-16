<?php
// pages/dashboard/update_profile_image.php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

// Check if image path is provided
if (!isset($_POST['image_path']) || empty(trim($_POST['image_path']))) {
    $response['message'] = 'No image path provided.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$image_path = trim($_POST['image_path']);

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Update user's profile image in the database
$sql = "UPDATE users SET profile_image = :image_path WHERE id = :user_id";
$stmt = $conn->prepare($sql);

try {
    $stmt->execute([
        ':image_path' => $image_path,
        ':user_id' => $user_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Profile image updated successfully.';
        // Update session with new profile image
        $_SESSION['user_profile_image'] = $image_path;
    } else {
        // This could happen if the selected image was already the current one
        $response['success'] = true;
        $response['message'] = 'Profile image is already set to this image.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("PDO Error: " . $e->getMessage());
}

echo json_encode($response);
?> 