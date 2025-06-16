<?php
require_once '../../config/database.php';
require_once '../includes/admin-utils.php';

// Initialize database connection
$database = new Database();
$conn = $database->connect();

// Get current hero settings
$sql = "SELECT * FROM hero_settings WHERE is_active = 1 LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$hero_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subtitle = $_POST['subtitle'] ?? '';
    $title = $_POST['title'] ?? '';
    $primary_btn_text = $_POST['primary_btn_text'] ?? '';
    $primary_btn_icon = $_POST['primary_btn_icon'] ?? '';
    $secondary_btn_text = $_POST['secondary_btn_text'] ?? '';
    $secondary_btn_icon = $_POST['secondary_btn_icon'] ?? '';
    $secondary_btn_url = $_POST['secondary_btn_url'] ?? '';
    
    if (!isset($error_message)) {
        // Update hero settings
        $update_sql = "UPDATE hero_settings SET 
                      subtitle = :subtitle,
                      title = :title,
                      primary_btn_text = :primary_btn_text,
                      primary_btn_icon = :primary_btn_icon,
                      secondary_btn_text = :secondary_btn_text,
                      secondary_btn_icon = :secondary_btn_icon,
                      secondary_btn_url = :secondary_btn_url,
                      updated_by = :updated_by,
                      updated_at = NOW()
                      WHERE is_active = 1";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bindParam(':subtitle', $subtitle);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':primary_btn_text', $primary_btn_text);
        $stmt->bindParam(':primary_btn_icon', $primary_btn_icon);
        $stmt->bindParam(':secondary_btn_text', $secondary_btn_text);
        $stmt->bindParam(':secondary_btn_icon', $secondary_btn_icon);
        $stmt->bindParam(':secondary_btn_url', $secondary_btn_url);
        $stmt->bindParam(':updated_by', $_SESSION['admin_id']);
        
        if ($stmt->execute()) {
            // Log the action
            logAdminAction($conn, $_SESSION['admin_id'], 'update_hero_settings', 'Updated hero section settings');
            
            // Redirect to prevent form resubmission
            header('Location: hero-settings.php?success=1');
            exit();
        } else {
            $error_message = "Database error: " . $stmt->errorInfo()[2];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hero Section - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Hero Section</h1>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">Hero settings updated successfully!</div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="subtitle" class="form-label">Subtitle</label>
                                <input type="text" class="form-control" id="subtitle" name="subtitle" 
                                       value="<?php echo htmlspecialchars($hero_settings['subtitle'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($hero_settings['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="primary_btn_text" class="form-label">Primary Button Text</label>
                                <input type="text" class="form-control" id="primary_btn_text" name="primary_btn_text" 
                                       value="<?php echo htmlspecialchars($hero_settings['primary_btn_text'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="primary_btn_icon" class="form-label">Primary Button Icon</label>
                                <input type="text" class="form-control" id="primary_btn_icon" name="primary_btn_icon" 
                                       value="<?php echo htmlspecialchars($hero_settings['primary_btn_icon'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="secondary_btn_text" class="form-label">Secondary Button Text</label>
                                <input type="text" class="form-control" id="secondary_btn_text" name="secondary_btn_text" 
                                       value="<?php echo htmlspecialchars($hero_settings['secondary_btn_text'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="secondary_btn_icon" class="form-label">Secondary Button Icon</label>
                                <input type="text" class="form-control" id="secondary_btn_icon" name="secondary_btn_icon" 
                                       value="<?php echo htmlspecialchars($hero_settings['secondary_btn_icon'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="secondary_btn_url" class="form-label">Secondary Button URL</label>
                                <input type="text" class="form-control" id="secondary_btn_url" name="secondary_btn_url" 
                                       value="<?php echo htmlspecialchars($hero_settings['secondary_btn_url'] ?? ''); ?>" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 