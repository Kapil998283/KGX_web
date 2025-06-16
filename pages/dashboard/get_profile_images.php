<?php
// pages/dashboard/get_profile_images.php
session_start();
require_once '../../includes/user-auth.php';
header('Content-Type: application/json');

$response = ['success' => false, 'images' => [], 'message' => ''];

// Basic security check
if(!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

// Get database connection
$conn = getDbConnection();

try {
    // Check if profile_images table exists
    $sql = "SHOW TABLES LIKE 'profile_images'";
    $stmt = $conn->query($sql);
    if ($stmt->rowCount() === 0) {
        $response['message'] = 'Profile images feature not set up.';
        echo json_encode($response);
        exit();
    }

    // Fetch active profile images
    $sql = "SELECT id, image_path FROM profile_images WHERE is_active = 1 ORDER BY created_at DESC";
    $stmt = $conn->query($sql);
    
    $images = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $path = $row['image_path'];
        if (strpos($path, '/assets/') === 0) {
            $images[] = ['id' => $row['id'], 'path' => '/KGX' . $path];
        } elseif (filter_var($path, FILTER_VALIDATE_URL)) {
            $images[] = ['id' => $row['id'], 'path' => $path];
        }
    }
    
    $response['success'] = true;
    $response['images'] = $images;
} catch (PDOException $e) {
    $response['message'] = 'Error fetching profile images: ' . $e->getMessage();
    error_log("PDO Exception: " . $e->getMessage());
}

echo json_encode($response);
?> 