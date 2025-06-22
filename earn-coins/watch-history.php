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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: var(--surface-2);
            padding: 1.5rem;
            border-radius: 10px;
        }

        .earnings-summary {
            text-align: right;
        }

        .total-earned {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--color-success);
        }

        .history-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            background: var(--surface-3);
            color: var(--text-1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
        }

        .history-card {
            display: flex;
            gap: 1.5rem;
            background: var(--surface-2);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }

        .history-card:hover {
            transform: translateY(-2px);
        }

        .history-thumbnail {
            width: 200px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
        }

        .history-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .history-details {
            flex: 1;
        }

        .history-title {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .history-meta {
            display: flex;
            gap: 2rem;
            color: var(--text-2);
            margin-bottom: 0.5rem;
        }

        .history-stats {
            display: flex;
            gap: 2rem;
            align-items: center;
            margin-top: 1rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-item ion-icon {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .no-history {
            text-align: center;
            padding: 3rem;
            background: var(--surface-2);
            border-radius: 10px;
        }

        .no-history ion-icon {
            font-size: 3rem;
            color: var(--text-2);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="earn-coins-container">
        <div class="history-header">
            <div>
                <h1>Watch History</h1>
                <p>Track your watched content and earnings</p>
            </div>
            <div class="earnings-summary">
                <div>Total Coins Earned</div>
                <div class="total-earned"><?php echo number_format($total_earnings, 2); ?></div>
            </div>
        </div>

        <div class="history-filters">
            <button class="filter-btn active" data-filter="all">All Content</button>
            <button class="filter-btn" data-filter="tournament">Tournament Streams</button>
            <button class="filter-btn" data-filter="earning">Content Videos</button>
        </div>

        <?php if (empty($watch_history)): ?>
        <div class="no-history">
            <ion-icon name="videocam-off-outline"></ion-icon>
            <h2>No Watch History</h2>
            <p>You haven't watched any content yet. Start watching to earn coins!</p>
            <a href="index.php" class="btn btn-primary mt-3">Browse Content</a>
        </div>
        <?php else: ?>
        <div class="history-list">
            <?php foreach ($watch_history as $item): 
                $thumbnail = getYoutubeThumbnail($item['stream_link']) ?? '../ui/assets/images/video-placeholder.jpg';
                $coins_earned = $item['stream_coins_earned'] ?? $item['coins_earned'];
            ?>
            <div class="history-card" data-type="<?php echo $item['video_type']; ?>">
                <div class="history-thumbnail">
                    <img src="<?php echo $thumbnail; ?>" alt="Video Thumbnail">
                </div>
                <div class="history-details">
                    <h3 class="history-title">
                        <?php 
                        if ($item['video_type'] == 'tournament') {
                            echo htmlspecialchars($item['tournament_name'] . ' - Round ' . $item['round_number'] . ': ' . $item['round_name']);
                        } else {
                            echo htmlspecialchars($item['stream_title']); 
                        }
                        ?>
                    </h3>
                    <div class="history-meta">
                        <div>
                            <ion-icon name="person-outline"></ion-icon>
                            <?php echo htmlspecialchars($item['streamer_name']); ?>
                        </div>
                        <?php if ($item['category_name']): ?>
                        <div>
                            <ion-icon name="folder-outline"></ion-icon>
                            <?php echo htmlspecialchars($item['category_name']); ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <ion-icon name="time-outline"></ion-icon>
                            <?php echo date('M d, Y - h:i A', strtotime($item['watched_at'])); ?>
                        </div>
                    </div>
                    <div class="history-stats">
                        <div class="stat-item">
                            <ion-icon name="time"></ion-icon>
                            Watched: <?php echo formatDuration($item['watch_duration']); ?>
                        </div>
                        <div class="stat-item">
                            <ion-icon name="wallet"></ion-icon>
                            Earned: <?php echo number_format($coins_earned, 2); ?> Coins
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const historyCards = document.querySelectorAll('.history-card');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Update active button
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // Filter cards
                    const filter = btn.dataset.filter;
                    historyCards.forEach(card => {
                        if (filter === 'all' || card.dataset.type === filter) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html> 