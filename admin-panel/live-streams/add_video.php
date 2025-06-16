<?php
session_start();
require_once '../../config/database.php';

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get video categories
$categories_sql = "SELECT * FROM video_categories WHERE is_active = 1";
$categories_stmt = $db->prepare($categories_sql);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tournaments for reference
$tournaments_sql = "SELECT id, name FROM tournaments ORDER BY created_at DESC";
$tournaments_stmt = $db->prepare($tournaments_sql);
$tournaments_stmt->execute();
$tournaments = $tournaments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "INSERT INTO live_streams (
            tournament_id, stream_title, stream_link, streamer_name, 
            status, video_type, coin_reward, minimum_watch_duration, category_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Handle empty tournament_id
        $tournament_id = !empty($_POST['tournament_id']) ? $_POST['tournament_id'] : null;
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $tournament_id,
            $_POST['stream_title'],
            $_POST['stream_link'],
            $_POST['streamer_name'],
            'completed', // Default status for earning videos
            'earning',
            $_POST['coin_reward'],
            $_POST['minimum_watch_duration'],
            $_POST['category_id']
        ]);

        if($result) {
            $_SESSION['success_message'] = "Video added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding video.";
        }
    } catch(Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Earning Video - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Add Earning Video</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Videos
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form action="add_video.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="stream_title" class="form-label">Video Title</label>
                                    <input type="text" class="form-control" id="stream_title" name="stream_title" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tournament_id" class="form-label">Related Tournament (Optional)</label>
                                    <select class="form-select" id="tournament_id" name="tournament_id">
                                        <option value="">None</option>
                                        <?php foreach($tournaments as $tournament): ?>
                                        <option value="<?php echo $tournament['id']; ?>">
                                            <?php echo htmlspecialchars($tournament['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="streamer_name" class="form-label">Content Creator</label>
                                    <input type="text" class="form-control" id="streamer_name" name="streamer_name" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="stream_link" class="form-label">Video URL (YouTube/Twitch)</label>
                                    <input type="url" class="form-control" id="stream_link" name="stream_link" required>
                                    <small class="text-muted">For YouTube, use the embed URL format: https://www.youtube.com/embed/VIDEO_ID</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="coin_reward" class="form-label">Coin Reward</label>
                                    <input type="number" class="form-control" id="coin_reward" name="coin_reward" value="25" min="0" step="0.01" required>
                                    <small class="text-muted">Number of coins users will earn for watching the video (recommended: 25-35 coins)</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="minimum_watch_duration" class="form-label">Minimum Watch Duration (seconds)</label>
                                    <input type="number" class="form-control" id="minimum_watch_duration" name="minimum_watch_duration" value="300" min="1" required>
                                    <small class="text-muted">Minimum time users need to watch to earn coins (recommended: 300-600 seconds)</small>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Video
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 