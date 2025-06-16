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

// Get tournaments
$tournaments_sql = "SELECT id, name FROM tournaments WHERE status != 'completed' ORDER BY created_at DESC";
$tournaments_stmt = $db->prepare($tournaments_sql);
$tournaments_stmt->execute();
$tournaments = $tournaments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rounds for AJAX
$rounds = [];
if(isset($_GET['tournament_id'])) {
    $rounds_sql = "SELECT id, name, round_number FROM tournament_rounds WHERE tournament_id = ?";
    $rounds_stmt = $db->prepare($rounds_sql);
    $rounds_stmt->execute([$_GET['tournament_id']]);
    $rounds = $rounds_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(isset($_GET['ajax'])) {
        echo json_encode($rounds);
        exit();
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if(empty($_POST['tournament_id'])) {
            throw new Exception("Tournament selection is required for streams.");
        }

        $sql = "INSERT INTO live_streams (
            tournament_id, round_id, stream_title, stream_link, streamer_name, 
            status, video_type, coin_reward, minimum_watch_duration
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $_POST['tournament_id'],
            $_POST['round_id'],
            $_POST['stream_title'],
            $_POST['stream_link'],
            $_POST['streamer_name'],
            'live',
            'tournament',
            $_POST['coin_reward'],
            $_POST['minimum_watch_duration']
        ]);

        if($result) {
            $_SESSION['success_message'] = "Tournament stream added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding stream.";
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
    <title>Add Tournament Stream - Admin Dashboard</title>
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
                    <h1 class="h2">Add Tournament Stream</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Videos
                    </a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form action="add_stream.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tournament_id" class="form-label">Tournament</label>
                                    <select class="form-select" id="tournament_id" name="tournament_id" required>
                                        <option value="">Select Tournament</option>
                                        <?php foreach($tournaments as $tournament): ?>
                                        <option value="<?php echo $tournament['id']; ?>">
                                            <?php echo htmlspecialchars($tournament['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="round_id" class="form-label">Tournament Round</label>
                                    <select class="form-select" id="round_id" name="round_id" required>
                                        <option value="">Select Round</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="stream_title" class="form-label">Stream Title</label>
                                    <input type="text" class="form-control" id="stream_title" name="stream_title" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="stream_link" class="form-label">Stream URL (YouTube/Twitch)</label>
                                    <input type="url" class="form-control" id="stream_link" name="stream_link" required>
                                    <small class="text-muted">For YouTube, use the embed URL format: https://www.youtube.com/embed/VIDEO_ID</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="streamer_name" class="form-label">Streamer Name</label>
                                    <input type="text" class="form-control" id="streamer_name" name="streamer_name" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="coin_reward" class="form-label">Coins Per Minute</label>
                                    <input type="number" class="form-control" id="coin_reward" name="coin_reward" value="1" min="0" step="0.01" required>
                                    <small class="text-muted">Number of coins users will earn per minute of watching</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="minimum_watch_duration" class="form-label">Minimum Watch Time (minutes)</label>
                                    <input type="number" class="form-control" id="minimum_watch_duration" name="minimum_watch_duration" value="5" min="1" required>
                                    <small class="text-muted">Minimum time in minutes before users start earning coins</small>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Stream
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
    <script>
        $(document).ready(function() {
            // Load rounds when tournament is selected
            $('#tournament_id').change(function() {
                const tournamentId = $(this).val();
                const roundSelect = $('#round_id');
                
                roundSelect.html('<option value="">Select Round</option>');
                
                if(tournamentId) {
                    $.get('add_stream.php', {
                        tournament_id: tournamentId,
                        ajax: true
                    }, function(data) {
                        const rounds = JSON.parse(data);
                        rounds.forEach(function(round) {
                            roundSelect.append(
                                `<option value="${round.id}">Round ${round.round_number} - ${round.name}</option>`
                            );
                        });
                    });
                }
            });
        });
    </script>
</body>
</html> 