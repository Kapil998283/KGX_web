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

// Get all videos with more details
$videos_sql = "SELECT ls.*, t.name as tournament_name, vc.name as category_name,
               (SELECT COUNT(*) FROM video_watch_history WHERE video_id = ls.id) as total_views
               FROM live_streams ls
               LEFT JOIN tournaments t ON ls.tournament_id = t.id
               LEFT JOIN video_categories vc ON ls.category_id = vc.id
               ORDER BY ls.created_at DESC";
$videos_stmt = $db->prepare($videos_sql);
$videos_stmt->execute();
$videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/admin.css">
    <!-- Add SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Video Management</h1>
                    <div>
                        <a href="add_stream.php" class="btn btn-outline-primary me-2">
                            <i class="bi bi-broadcast"></i> Add Tournament Stream
                        </a>
                        <a href="add_video.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Earning Video
                        </a>
                    </div>
                </div>

                <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); endif; ?>

                <!-- Videos Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-camera-video"></i>
                        All Videos
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="videosTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Category/Tournament</th>
                                        <th>Creator</th>
                                        <th>Views</th>
                                        <th>Coins</th>
                                        <th>Upload Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($videos as $video): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($video['stream_title']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $video['video_type'] === 'earning' ? 'success' : 'primary'; ?>">
                                                <?php echo ucfirst($video['video_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if($video['video_type'] === 'earning') {
                                                echo htmlspecialchars($video['category_name'] ?? 'Uncategorized');
                                            } else {
                                                echo htmlspecialchars($video['tournament_name'] ?? 'N/A');
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($video['streamer_name']); ?></td>
                                        <td><?php echo number_format($video['total_views']); ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo number_format($video['coin_reward'], 2); ?> coins
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($video['created_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $video['status'] === 'live' ? 'success' : ($video['status'] === 'scheduled' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($video['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_video.php?id=<?php echo $video['id']; ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger delete-video" 
                                                        data-id="<?php echo $video['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($video['stream_title']); ?>"
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Handle video deletion
            $('.delete-video').click(function() {
                const videoId = $(this).data('id');
                const videoTitle = $(this).data('title');
                
                Swal.fire({
                    title: 'Delete Video?',
                    html: `Are you sure you want to delete <strong>${videoTitle}</strong>?<br>This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'delete_video.php',
                            method: 'POST',
                            data: { id: videoId },
                            dataType: 'json',
                            success: function(response) {
                                if(response.success) {
                                    Swal.fire({
                                        title: 'Deleted!',
                                        text: 'The video has been deleted.',
                                        icon: 'success'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: response.message || 'Failed to delete the video.',
                                        icon: 'error'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'An error occurred while deleting the video.',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html> 