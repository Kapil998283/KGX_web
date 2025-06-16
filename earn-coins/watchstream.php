<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if stream_id is provided
if (!isset($_GET['stream_id'])) {
    header("Location: index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get stream details
$stream_sql = "
    SELECT 
        ls.*,
        t.name as tournament_name,
        tr.name as round_name,
        tr.round_number
    FROM live_streams ls
    LEFT JOIN tournaments t ON ls.tournament_id = t.id
    LEFT JOIN tournament_rounds tr ON ls.round_id = tr.id
    WHERE ls.id = ? AND ls.video_type = 'tournament' AND ls.status = 'live'";

$stream_stmt = $db->prepare($stream_sql);
$stream_stmt->execute([$_GET['stream_id']]);
$stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);

if (!$stream) {
    header("Location: index.php");
    exit();
}

// Include header after all redirects
require_once '../includes/header.php';

// Function to get YouTube video ID and thumbnail
function getYoutubeInfo($url) {
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
    
    return [
        'video_id' => $video_id,
        'thumbnail' => $video_id ? "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg" : null,
        'embed_url' => $video_id ? "https://www.youtube.com/embed/{$video_id}" : null
    ];
}

$youtube_info = getYoutubeInfo($stream['stream_link']);

// If no video ID was found, redirect back
if (empty($youtube_info['video_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($stream['stream_title']); ?> - Live Stream</title>
    <link rel="stylesheet" href="css/watch.css">
    <style>
        .video-player {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            background: #000;
        }
        .video-player iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <div class="watch-container">
        <div class="video-wrapper">
            <div class="video-player">
                <div id="videoPlayerContainer"></div>
            </div>
            <div class="video-info">
                <h1 class="video-title"><?php echo htmlspecialchars($stream['stream_title']); ?></h1>
                <div class="video-meta">
                    <?php if ($stream['tournament_name']): ?>
                    <div class="meta-item">
                        <ion-icon name="trophy-outline"></ion-icon>
                        <span><?php echo htmlspecialchars($stream['tournament_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($stream['round_name']): ?>
                    <div class="meta-item">
                        <ion-icon name="flag-outline"></ion-icon>
                        <span>Round <?php echo $stream['round_number']; ?>: <?php echo htmlspecialchars($stream['round_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="meta-item">
                        <ion-icon name="person-outline"></ion-icon>
                        <span><?php echo htmlspecialchars($stream['streamer_name']); ?></span>
                    </div>

                    <div class="meta-item live-indicator">
                        <ion-icon name="radio-outline"></ion-icon>
                        <span>LIVE</span>
                    </div>
                </div>

                <div class="reward-info">
                    <div class="reward-text">
                        <ion-icon name="wallet-outline"></ion-icon>
                        <div class="reward-details">
                            <div class="amount"><span id="earnedCoins">0</span> Coins Earned</div>
                            <div class="condition">Earn <?php echo number_format($stream['coin_reward'], 2); ?> coins per minute of watching</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="progress-wrapper" id="progressWrapper">
            <div class="progress-info">
                <div class="progress-label">Watch Time</div>
                <div class="progress-time">
                    <span id="watchTime">0:00</span>
                </div>
            </div>
            <div class="coins-earned">
                <div class="coins-label">Coins earned this session</div>
                <div class="coins-amount" id="sessionCoins">0.00</div>
            </div>
        </div>
    </div>

    <script>
        // YouTube Player API
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

        var player;
        var watchStartTime = 0;
        var watchDuration = 0;
        var isPlaying = false;
        var lastUpdateTime = 0;
        var coinsPerMinute = <?php echo $stream['coin_reward']; ?>;
        var earnedCoins = 0;
        var lastCoinUpdate = 0;

        function onYouTubeIframeAPIReady() {
            console.log("YouTube API Ready");
            try {
                player = new YT.Player('videoPlayerContainer', {
                    videoId: '<?php echo $youtube_info['video_id']; ?>',
                    playerVars: {
                        'autoplay': 0,
                        'controls': 1,
                        'rel': 0,
                        'modestbranding': 1,
                        'origin': window.location.origin
                    },
                    events: {
                        'onReady': onPlayerReady,
                        'onStateChange': onPlayerStateChange,
                        'onError': onPlayerError
                    }
                });
            } catch (error) {
                console.error("Error initializing YouTube player:", error);
                showVideoError();
            }
        }

        function onPlayerReady(event) {
            console.log("Player Ready");
            document.getElementById('videoPlayerContainer').style.display = 'block';
            document.getElementById('progressWrapper').style.display = 'block';
        }

        function onPlayerError(event) {
            console.error("Player Error:", event.data);
            showVideoError();
        }

        function showVideoError() {
            document.getElementById('videoPlayerContainer').innerHTML = `
                <div class="video-error">
                    <h3>Video Playback Error</h3>
                    <p>Sorry, we couldn't load the stream. Please try again later.</p>
                    <a href="index.php" class="error-btn">Back to Videos</a>
                </div>
            `;
        }

        function onPlayerStateChange(event) {
            console.log("Player State Change:", event.data);
            if (event.data == YT.PlayerState.PLAYING) {
                if (!watchStartTime) {
                    watchStartTime = Math.floor(Date.now() / 1000);
                    lastUpdateTime = watchStartTime;
                    lastCoinUpdate = watchStartTime;
                }
                isPlaying = true;
                updateProgress();
            } else if (event.data == YT.PlayerState.PAUSED || event.data == YT.PlayerState.ENDED) {
                isPlaying = false;
                if (lastUpdateTime > 0) {
                    watchDuration += Math.floor(Date.now() / 1000) - lastUpdateTime;
                }
            }
        }

        function updateProgress() {
            if (isPlaying) {
                var currentTime = Math.floor(Date.now() / 1000);
                
                if (lastUpdateTime > 0) {
                    watchDuration += currentTime - lastUpdateTime;
                }
                lastUpdateTime = currentTime;

                // Update watch time display
                document.getElementById('watchTime').textContent = formatTime(watchDuration);

                // Calculate and update coins (every minute)
                var minutesWatched = Math.floor(watchDuration / 60);
                var newCoins = (minutesWatched * coinsPerMinute).toFixed(2);
                if (newCoins > earnedCoins) {
                    earnedCoins = newCoins;
                    updateCoins(earnedCoins);
                }

                // Check if stream is still live
                checkStreamStatus();

                if (isPlaying) {
                    setTimeout(updateProgress, 1000);
                }
            }
        }

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = seconds % 60;
            
            if (hours > 0) {
                return `${hours}:${padNumber(minutes)}:${padNumber(remainingSeconds)}`;
            }
            return `${minutes}:${padNumber(remainingSeconds)}`;
        }

        function padNumber(number) {
            return number.toString().padStart(2, '0');
        }

        function updateCoins(amount) {
            document.getElementById('earnedCoins').textContent = amount;
            document.getElementById('sessionCoins').textContent = amount;

            // Send update to server
            fetch('update_stream_coins.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `stream_id=<?php echo $stream['id']; ?>&coins=${amount}&watch_duration=${watchDuration}`
            });
        }

        function checkStreamStatus() {
            // Check stream status every minute
            if (Math.floor(Date.now() / 1000) - lastCoinUpdate >= 60) {
                fetch('check_stream_status.php?stream_id=<?php echo $stream['id']; ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.is_live) {
                            location.reload(); // Reload page if stream has ended
                        }
                    });
                lastCoinUpdate = Math.floor(Date.now() / 1000);
            }
        }
    </script>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html> 