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

// Get video details
if(!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$video_sql = "SELECT ls.*, t.name as tournament_name, tr.name as round_name, tr.round_number
              FROM live_streams ls
              LEFT JOIN tournaments t ON ls.tournament_id = t.id
              LEFT JOIN tournament_rounds tr ON ls.round_id = tr.id
              WHERE ls.id = ?";
$video_stmt = $db->prepare($video_sql);
$video_stmt->execute([$_GET['id']]);
$video = $video_stmt->fetch(PDO::FETCH_ASSOC);

if(!$video) {
    header("Location: index.php");
    exit();
}

// Get video categories
$categories_sql = "SELECT * FROM video_categories WHERE is_active = 1";
$categories_stmt = $db->prepare($categories_sql);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tournaments
$tournaments_sql = "SELECT id, name FROM tournaments ORDER BY created_at DESC";
$tournaments_stmt = $db->prepare($tournaments_sql);
$tournaments_stmt->execute();
$tournaments = $tournaments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $update_sql = "UPDATE live_streams SET 
            stream_title = ?,
            stream_link = ?,
            streamer_name = ?,
            status = ?,
            coin_reward = ?,
            minimum_watch_duration = ?,
            category_id = ?,
            tournament_id = ?
            WHERE id = ?";
        
        $stmt = $db->prepare($update_sql);
        $result = $stmt->execute([
            $_POST['stream_title'],
            $_POST['stream_link'],
            $_POST['streamer_name'],
            $_POST['status'],
            $_POST['coin_reward'],
            $_POST['minimum_watch_duration'],
            $_POST['category_id'] ?: null,
            $_POST['tournament_id'] ?: null,
            $_GET['id']
        ]);

        if($result) {
            $_SESSION['success_message'] = "Video updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating video.";
        }
        
        header("Location: index.php");
        exit();
    } catch(Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video - Admin Dashboard</title>
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
                    <h1 class="h2">Edit Video</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Videos
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form action="edit_video.php?id=<?php echo $_GET['id']; ?>" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="stream_title" class="form-label">Video Title</label>
                                    <input type="text" class="form-control" id="stream_title" name="stream_title" 
                                           value="<?php echo htmlspecialchars($video['stream_title']); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="streamer_name" class="form-label">Content Creator</label>
                                    <input type="text" class="form-control" id="streamer_name" name="streamer_name" 
                                           value="<?php echo htmlspecialchars($video['streamer_name']); ?>" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="stream_link" class="form-label">Video URL</label>
                                    <input type="url" class="form-control" id="stream_link" name="stream_link" 
                                           value="<?php echo htmlspecialchars($video['stream_link']); ?>" required>
                                    <small class="text-muted">For YouTube, use the embed URL format: https://www.youtube.com/embed/VIDEO_ID</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $video['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tournament_id" class="form-label">Tournament (Optional)</label>
                                    <select class="form-select" id="tournament_id" name="tournament_id">
                                        <option value="">None</option>
                                        <?php foreach($tournaments as $tournament): ?>
                                        <option value="<?php echo $tournament['id']; ?>"
                                                <?php echo $video['tournament_id'] == $tournament['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tournament['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="coin_reward" class="form-label">Coin Reward</label>
                                    <input type="number" class="form-control" id="coin_reward" name="coin_reward" 
                                           value="<?php echo $video['coin_reward']; ?>" min="0" step="0.01" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="minimum_watch_duration" class="form-label">Minimum Watch Duration (seconds)</label>
                                    <input type="number" class="form-control" id="minimum_watch_duration" name="minimum_watch_duration" 
                                           value="<?php echo $video['minimum_watch_duration']; ?>" min="1" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="scheduled" <?php echo $video['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="live" <?php echo $video['status'] == 'live' ? 'selected' : ''; ?>>Live</option>
                                        <option value="completed" <?php echo $video['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $video['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Changes
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
</body>
</html>