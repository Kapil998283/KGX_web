<?php
session_start();
require_once '../../includes/user-auth.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if image ID is provided
if (!isset($_POST['image_id'])) {
    echo json_encode(['success' => false, 'message' => 'Image ID not provided']);
    exit;
}

$image_id = $_POST['image_id'];
$user_id = $_SESSION['user_id'];

// Get database connection
$conn = getDbConnection();

try {
    // Get image path before deletion
    $stmt = $conn->prepare("SELECT image_path FROM profile_images WHERE id = :image_id");
    $stmt->bindParam(':image_id', $image_id);
    $stmt->execute();
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Image not found']);
        exit;
    }

    // Delete the image file
    $file_path = '../../' . $image['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM profile_images WHERE id = :image_id");
    $stmt->bindParam(':image_id', $image_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete image']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("PDO Exception: " . $e->getMessage());
}
?> 