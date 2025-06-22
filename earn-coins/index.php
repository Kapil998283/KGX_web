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

// Get active tournament streams
$tournament_streams_sql = "
    SELECT 
        ls.*,
        t.name as tournament_name,
        tr.name as round_name,
        tr.round_number
    FROM live_streams ls
    JOIN tournaments t ON ls.tournament_id = t.id
    JOIN tournament_rounds tr ON ls.round_id = tr.id
    WHERE ls.status = 'live'
    AND ls.video_type = 'tournament'
    ORDER BY ls.created_at DESC";

$tournament_streams_stmt = $db->prepare($tournament_streams_sql);
$tournament_streams_stmt->execute();
$tournament_streams = $tournament_streams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get content creator videos
$videos_sql = "
    SELECT 
        ls.*,
        vc.name as category_name
    FROM live_streams ls
    LEFT JOIN video_categories vc ON ls.category_id = vc.id
    WHERE ls.video_type = 'earning'
    AND ls.status = 'completed'
    ORDER BY ls.start_time DESC";

$videos_stmt = $db->prepare($videos_sql);
$videos_stmt->execute();
$videos = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's watched streams
$watched_sql = "SELECT stream_id FROM stream_rewards WHERE user_id = ?";
$watched_stmt = $db->prepare($watched_sql);
$watched_stmt->execute([$_SESSION['user_id']]);
$watched_streams = array_column($watched_stmt->fetchAll(PDO::FETCH_ASSOC), 'stream_id');

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earn Coins - Watch Content</title>
    <link rel="stylesheet" href="../ui/assets/css/style.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Override any conflicting styles from main CSS */
        :root {
            --orange: hsla(140, 100%, 50%, 0.985);
        }
        
        /* Ensure the earn-coins container is properly positioned */
        .earn-coins-container {
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="earn-coins-container">
        <div class="coins-header">
            <div class="header-content">
                <h1>Earn Coins</h1>
                <p>Watch tournaments and content to earn coins!</p>
            </div>
            <div class="coins-actions">
                <a href="watch-history.php" class="history-link">
                    <ion-icon name="time-outline"></ion-icon>
                    Watch History
                </a>
                <div class="coins-balance">
                    <ion-icon name="wallet"></ion-icon>
                    <div class="balance-info">
                        <div class="amount"><?php echo number_format($current_coins, 2); ?></div>
                        <div class="label">Your Coins</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-tabs">
            <button class="tab-btn active" data-tab="videos">
                <ion-icon name="play-circle-outline"></ion-icon>
                Content Videos
            </button>
            <button class="tab-btn" data-tab="tournaments">
                <ion-icon name="trophy-outline"></ion-icon>
                Tournament Streams
            </button>
        </div>

        <!-- Content Videos Tab -->
        <div class="tab-content active" id="videos-tab">
            <?php if (empty($videos)): ?>
            <div class="no-content">
                <ion-icon name="film-outline"></ion-icon>
                <h2>No Content Videos Available</h2>
                <p>Check back later for new content!</p>
            </div>
            <?php else: ?>
            <div class="streams-grid">
                <?php foreach ($videos as $video): 
                    $is_watched = in_array($video['id'], $watched_streams);
                    $thumbnail = getYoutubeThumbnail($video['stream_link']) ?? '../ui/assets/images/video-placeholder.jpg';
                ?>
                <div class="stream-card">
                    <div class="stream-thumbnail">
                        <img src="<?php echo $thumbnail; ?>" alt="Video Thumbnail">
                        <?php if ($video['category_name']): ?>
                        <div class="category-tag">
                            <?php echo htmlspecialchars($video['category_name']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stream-info">
                        <h3 class="stream-title"><?php echo htmlspecialchars($video['stream_title']); ?></h3>
                        <div class="stream-meta">
                            <div>By: <?php echo htmlspecialchars($video['streamer_name']); ?></div>
                            <div>Added: <?php echo date('M d, Y', strtotime($video['created_at'] ?? $video['start_time'])); ?></div>
                        </div>
                        <div class="stream-reward">
                            <div class="reward-amount">
                                <ion-icon name="wallet-outline"></ion-icon>
                                Earn <?php echo number_format($video['coin_reward'] ?? 50, 2); ?> Coins
                            </div>
                            <?php if ($is_watched): ?>
                                <span class="watch-btn watched">
                                    <ion-icon name="checkmark-circle"></ion-icon>
                                    Earned
                                </span>
                            <?php else: ?>
                                <a href="watch.php?stream_id=<?php echo $video['id']; ?>" class="watch-btn available">
                                    <ion-icon name="play"></ion-icon>
                                    Watch Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tournament Streams Tab -->
        <div class="tab-content" id="tournaments-tab">
            <?php if (empty($tournament_streams)): ?>
            <div class="no-content">
                <ion-icon name="videocam-outline"></ion-icon>
                <h2>No Tournament Streams Available</h2>
                <p>Check back later for live tournament streams!</p>
            </div>
            <?php else: ?>
            <div class="streams-grid">
                <?php foreach ($tournament_streams as $stream): 
                    $is_watched = in_array($stream['id'], $watched_streams);
                    $thumbnail = getYoutubeThumbnail($stream['stream_link']) ?? '../ui/assets/images/stream-placeholder.jpg';
                ?>
                <div class="stream-card">
                    <div class="stream-thumbnail">
                        <img src="<?php echo $thumbnail; ?>" alt="Stream Thumbnail">
                        <div class="stream-status live">LIVE NOW</div>
                    </div>
                    <div class="stream-info">
                        <h3 class="stream-title"><?php echo htmlspecialchars($stream['tournament_name']); ?></h3>
                        <div class="stream-meta">
                            <div>Round <?php echo $stream['round_number']; ?>: <?php echo htmlspecialchars($stream['round_name']); ?></div>
                            <div>Added: <?php echo date('M d, Y - h:i A', strtotime($stream['created_at'])); ?></div>
                        </div>
                        <div class="stream-reward">
                            <div class="reward-amount">
                                <ion-icon name="wallet-outline"></ion-icon>
                                Earn <?php echo number_format($stream['coin_reward'] ?? 50, 2); ?> Coins/min
                            </div>
                            <?php if ($is_watched): ?>
                                <span class="watch-btn watched">
                                    <ion-icon name="checkmark-circle"></ion-icon>
                                    Earned
                                </span>
                            <?php else: ?>
                                <a href="watchstream.php?stream_id=<?php echo $stream['id']; ?>" class="watch-btn available">
                                    <ion-icon name="play"></ion-icon>
                                    Watch Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));

                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    document.getElementById(`${tab.dataset.tab}-tab`).classList.add('active');
                });
            });
        });
    </script>

    <?php require_once '../includes/footer.php'; ?>

    <style>
        .coins-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .history-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--surface-3);
            border-radius: 5px;
            color: var(--text-1);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .history-link:hover {
            background: var(--surface-4);
            transform: translateY(-2px);
        }

        .history-link ion-icon {
            font-size: 1.2rem;
        }
    </style>
</body>
</html> 