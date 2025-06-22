<?php
session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get user's current coins
$coins_sql = "SELECT coins FROM user_coins WHERE user_id = ?";
$coins_stmt = $db->prepare($coins_sql);
$coins_stmt->execute([$_SESSION['user_id']]);
$user_coins = $coins_stmt->fetch(PDO::FETCH_ASSOC);
$current_coins = $user_coins ? $user_coins['coins'] : 0;

// Get user's watch history with detailed information
$history_sql = "
    SELECT 
        vwh.*,
        ls.stream_title,
        ls.streamer_name,
        ls.video_type,
        ls.stream_link,
        ls.coin_reward,
        t.name as tournament_name,
        tr.name as round_name,
        tr.round_number,
        vc.name as category_name,
        sr.coins_earned as stream_coins_earned
    FROM video_watch_history vwh
    JOIN live_streams ls ON vwh.video_id = ls.id
    LEFT JOIN tournaments t ON ls.tournament_id = t.id
    LEFT JOIN tournament_rounds tr ON ls.round_id = tr.id
    LEFT JOIN video_categories vc ON ls.category_id = vc.id
    LEFT JOIN stream_rewards sr ON sr.stream_id = ls.id AND sr.user_id = vwh.user_id
    WHERE vwh.user_id = ?
    ORDER BY vwh.watched_at DESC";

$history_stmt = $db->prepare($history_sql);
$history_stmt->execute([$_SESSION['user_id']]);
$watch_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total earnings
$total_earnings = 0;
foreach ($watch_history as $history) {
    $total_earnings += ($history['stream_coins_earned'] ?? $history['coins_earned']);
}

// Function to get YouTube thumbnail
function getYoutubeThumbnail($url) {
    $video_id = '';
    if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id)) {
        $video_id = $id[1];
    } else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id)) {
        $video_id = $id[1];
    } else if (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id)) {
        $video_id = $id[1];
    } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id)) {
        $video_id = $id[1];
    }
    return $video_id ? "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg" : null;
}

// Function to format duration
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . " seconds";
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . " minutes";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . " hours " . $minutes . " minutes";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch History - Earn Coins</title>
    <link rel="stylesheet" href="../ui/assets/css/style.css">
    <link rel="stylesheet" href="css/history.css">
</head>
<body>
    <div class="history-container">
        <div class="history-header">
            <div class="header-content">
                <h1>Watch History</h1>
                <p>Track your watched content and earnings</p>
            </div>
            <div class="total-earnings">
                <div class="amount">
                    <ion-icon name="wallet"></ion-icon>
                    <?php echo number_format($total_earnings, 2); ?>
                </div>
                <div class="label">Total Coins Earned</div>
            </div>
        </div>

        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">
                <ion-icon name="grid-outline"></ion-icon>
                All Content
            </button>
            <button class="filter-btn" data-filter="tournament">
                <ion-icon name="trophy-outline"></ion-icon>
                Tournament Streams
            </button>
            <button class="filter-btn" data-filter="earning">
                <ion-icon name="play-circle-outline"></ion-icon>
                Content Videos
            </button>
        </div>

        <?php if (empty($watch_history)): ?>
        <div class="no-history">
            <ion-icon name="videocam-off-outline"></ion-icon>
            <h2>No Watch History</h2>
            <p>You haven't watched any content yet. Start watching to earn coins!</p>
            <a href="index.php" class="btn">
                <ion-icon name="play-circle-outline"></ion-icon>
                Browse Content
            </a>
        </div>
        <?php else: ?>
        <div class="history-table">
            <div class="table-header">
                <span>Thumbnail</span>
                <span>Content Details</span>
                <span>Duration</span>
                <span>Date Watched</span>
                <span>Coins Earned</span>
            </div>
            <?php foreach ($watch_history as $item): 
                $thumbnail = getYoutubeThumbnail($item['stream_link']) ?? '../ui/assets/images/video-placeholder.jpg';
                $coins_earned = $item['stream_coins_earned'] ?? $item['coins_earned'];
            ?>
            <div class="history-item" data-type="<?php echo $item['video_type']; ?>">
                <div class="history-thumbnail">
                    <img src="<?php echo $thumbnail; ?>" alt="Video Thumbnail">
                </div>
                <div class="content-info">
                    <h3><?php 
                        if ($item['video_type'] == 'tournament') {
                            echo htmlspecialchars($item['tournament_name'] . ' - Round ' . $item['round_number'] . ': ' . $item['round_name']);
                        } else {
                            echo htmlspecialchars($item['stream_title']); 
                        }
                    ?></h3>
                    <div class="content-meta">
                        <div class="meta-item">
                            <ion-icon name="person-outline"></ion-icon>
                            <span><?php echo htmlspecialchars($item['streamer_name']); ?></span>
                        </div>
                        <?php if ($item['category_name']): ?>
                        <div class="meta-item">
                            <ion-icon name="folder-outline"></ion-icon>
                            <span><?php echo htmlspecialchars($item['category_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="type-badge <?php echo $item['video_type']; ?>">
                            <?php echo $item['video_type'] == 'tournament' ? 'Tournament' : 'Content'; ?>
                        </div>
                    </div>
                </div>
                <div class="watch-duration">
                    <ion-icon name="time-outline"></ion-icon>
                    <?php echo formatDuration($item['watch_duration']); ?>
                </div>
                <div class="watch-date">
                    <ion-icon name="calendar-outline"></ion-icon>
                    <?php echo date('M d, Y - h:i A', strtotime($item['watched_at'])); ?>
                </div>
                <div class="coins-earned">
                    <ion-icon name="wallet-outline"></ion-icon>
                    <?php echo number_format($coins_earned, 2); ?> Coins
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const historyItems = document.querySelectorAll('.history-item');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Update active button
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // Filter items
                    const filter = btn.dataset.filter;
                    historyItems.forEach(item => {
                        if (filter === 'all' || item.dataset.type === filter) {
                            item.style.display = 'grid';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html> 