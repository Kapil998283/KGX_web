<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$success_message = '';
$error_message = '';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Check if profile_images table exists
$table_exists = false;
$stmt = $db->query("SHOW TABLES LIKE 'profile_images'");
if ($stmt->rowCount() > 0) {
    $table_exists = true;
}

// Handle setting default image
if (isset($_POST['set_default']) && $table_exists) {
    $image_id = (int)$_POST['image_id'];
    
    try {
        $db->beginTransaction();
        
        // First, set all images to not default
        $sql = "UPDATE profile_images SET is_default = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        // Then set the selected image as default
        $sql = "UPDATE profile_images SET is_default = 1 WHERE id = :image_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['image_id' => $image_id]);
        
        $db->commit();
        $success_message = "Default profile image set successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = "Error setting default image: " . $e->getMessage();
    }
}

// Handle URL-based image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_url_image']) && isset($_POST['image_url'])) {
    if (!$table_exists) {
        $error_message = "Cannot add images: The profile_images table doesn't exist. Please run the setup script first.";
    } else {
        $image_url = trim($_POST['image_url']);
        
        // Validate URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            $error_message = "Invalid URL format. Please enter a valid URL.";
        } else {
            // Check if URL is already in database
            $sql = "SELECT id FROM profile_images WHERE image_path = :image_path";
            $stmt = $db->prepare($sql);
            $stmt->execute(['image_path' => $image_url]);
            $result = $stmt->rowCount();
            
            if ($result > 0) {
                $error_message = "This image URL is already in the database.";
            } else {
                // Add to database
                $sql = "INSERT INTO profile_images (image_path, is_active) VALUES (:image_path, 1)";
                $stmt = $db->prepare($sql);
                $stmt->execute(['image_path' => $image_url]);
                
                if ($stmt->rowCount() > 0) {
                    $success_message = "Profile image URL added successfully!";
                } else {
                    $error_message = "Error saving image URL to database.";
                }
            }
        }
    }
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    if (!$table_exists) {
        $error_message = "Cannot upload images: The profile_images table doesn't exist. Please run the setup script first.";
    } else {
        $file = $_FILES['profile_image'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_error = $file['error'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        
        if ($file_error === 0) {
            if (in_array($file_ext, $allowed)) {
                // Create unique filename
                $new_file_name = uniqid('profile_') . '.' . $file_ext;
                $upload_path = '../assets/images/profile/' . $new_file_name;
                
                // Create directory if it doesn't exist
                if (!file_exists('../assets/images/profile/')) {
                    mkdir('../assets/images/profile/', 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Add to database
                    $image_path = '/assets/images/profile/' . $new_file_name;
                    $sql = "INSERT INTO profile_images (image_path) VALUES (:image_path)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['image_path' => $image_path]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success_message = "Profile image uploaded successfully!";
                    } else {
                        $error_message = "Error saving image to database.";
                        // Delete uploaded file if database insert fails
                        unlink($upload_path);
                    }
                } else {
                    $error_message = "Error uploading file.";
                }
            } else {
                $error_message = "Invalid file type. Allowed types: jpg, jpeg, png, gif";
            }
        } else {
            $error_message = "Error uploading file.";
        }
    }
}

// Handle image deletion
if (isset($_POST['delete_image']) && $table_exists) {
    $image_id = (int)$_POST['image_id'];
    
    // Get image path before deletion
    $sql = "SELECT image_path FROM profile_images WHERE id = :image_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['image_id' => $image_id]);
    $result = $stmt->rowCount();
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result > 0) {
        // Delete from database
        $sql = "DELETE FROM profile_images WHERE id = :image_id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['image_id' => $image_id]);
        
        // Only delete file if it's a local file (starts with /assets/)
        if (strpos($image['image_path'], '/assets/') === 0) {
            $file_path = '..' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $success_message = "Image deleted successfully!";
    } else {
        $error_message = "Image not found.";
    }
}

// Handle image activation/deactivation
if (isset($_POST['toggle_status']) && $table_exists) {
    $image_id = (int)$_POST['image_id'];
    $new_status = (int)$_POST['new_status'];
    
    $sql = "UPDATE profile_images SET is_active = :new_status WHERE id = :image_id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['new_status' => $new_status, 'image_id' => $image_id]);
    
    if ($stmt->rowCount() > 0) {
        $success_message = "Image status updated successfully!";
    } else {
        $error_message = "Error updating image status.";
    }
}

// Get all profile images
$profile_images = [];
if ($table_exists) {
    $sql = "SELECT * FROM profile_images ORDER BY is_default DESC, created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $profile_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Images Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        
        .nav-link {
            color: #fff;
            padding: 0.5rem 1rem;
            margin: 0.2rem 0;
            border-radius: 0.25rem;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-link.active {
            background-color: #0d6efd;
        }
        
        .profile-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .image-card {
            transition: transform 0.2s;
            position: relative;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
        }
        
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        
        .upload-area i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .setup-alert {
            background-color: #fff3cd;
            border-color: #ffecb5;
            color: #664d03;
        }
        
        .url-image {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }
        
        .default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard/index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="users/index.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active text-white" href="profile.php">
                                <i class="bi bi-person-circle"></i> Profile Images
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">Profile Images Management</h1>
                
                <?php if (!$table_exists): ?>
                    <div class="alert setup-alert alert-dismissible fade show" role="alert">
                        <strong>Setup Required!</strong> The profile_images table doesn't exist. 
                        <a href="../database/setup_profile_images.php" class="alert-link">Click here to run the setup script</a>.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($table_exists): ?>
                    <!-- Upload Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Profile Images</h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-3" id="uploadTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="file-tab" data-bs-toggle="tab" data-bs-target="#file-upload" type="button" role="tab" aria-controls="file-upload" aria-selected="true">File Upload</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="url-tab" data-bs-toggle="tab" data-bs-target="#url-upload" type="button" role="tab" aria-controls="url-upload" aria-selected="false">Image URL</button>
                                </li>
                            </ul>
                            <div class="tab-content" id="uploadTabsContent">
                                <!-- File Upload Tab -->
                                <div class="tab-pane fade show active" id="file-upload" role="tabpanel" aria-labelledby="file-tab">
                                    <form action="" method="post" enctype="multipart/form-data">
                                        <div class="upload-area">
                                            <i class="bi bi-cloud-upload"></i>
                                            <h5>Click to Upload</h5>
                                            <p class="text-muted">or drag and drop</p>
                                            <input type="file" id="profile_image" name="profile_image" class="d-none" accept="image/*">
                                        </div>
                                    </form>
                                </div>
                                <!-- URL Upload Tab -->
                                <div class="tab-pane fade" id="url-upload" role="tabpanel" aria-labelledby="url-tab">
                                    <form action="" method="post">
                                        <div class="mb-3">
                                            <label for="image_url" class="form-label">Image URL</label>
                                            <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" required>
                                            <div class="form-text">Enter the direct URL to an image (jpg, jpeg, png, gif)</div>
                                        </div>
                                        <button type="submit" name="add_url_image" class="btn btn-primary">Add Image</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Images Grid -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Available Profile Images</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($profile_images)): ?>
                                <div class="alert alert-info">
                                    No profile images available. Upload some images to get started.
                                </div>
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
                                    <?php foreach ($profile_images as $image): ?>
                                        <div class="col">
                                            <div class="card h-100 image-card">
                                                <?php if (strpos($image['image_path'], '/assets/') === 0): ?>
                                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" class="card-img-top profile-image mx-auto mt-3" alt="Profile Image">
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" class="card-img-top profile-image mx-auto mt-3" alt="Profile Image">
                                                <?php endif; ?>
                                                
                                                <?php if ($image['is_default']): ?>
                                                    <div class="default-badge">Default</div>
                                                <?php endif; ?>
                                                
                                                <div class="card-body text-center">
                                                    <p class="card-text">
                                                        <small class="text-muted">Added: <?php echo date('M d, Y', strtotime($image['created_at'])); ?></small>
                                                    </p>
                                                    <div class="btn-group" role="group">
                                                        <form action="" method="post" class="d-inline">
                                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo $image['is_active'] ? '0' : '1'; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $image['is_active'] ? 'btn-success' : 'btn-warning'; ?>">
                                                                <?php echo $image['is_active'] ? 'Active' : 'Inactive'; ?>
                                                            </button>
                                                        </form>
                                                        <?php if (!$image['is_default']): ?>
                                                            <form action="" method="post" class="d-inline">
                                                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                                <button type="submit" name="set_default" class="btn btn-sm btn-info">Set Default</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form action="" method="post" class="d-inline">
                                                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                            <button type="submit" name="delete_image" class="btn btn-sm btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make the file upload area clickable
        document.querySelector('.upload-area').addEventListener('click', function() {
            document.getElementById('profile_image').click();
        });
        
        // Submit the form when a file is selected
        document.getElementById('profile_image').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html> 