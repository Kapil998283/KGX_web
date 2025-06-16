<?php
session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

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
        tr.round_number,
        vc.name as category_name
    FROM live_streams ls
    LEFT JOIN tournaments t ON ls.tournament_id = t.id
    LEFT JOIN tournament_rounds tr ON ls.round_id = tr.id
    LEFT JOIN video_categories vc ON ls.category_id = vc.id
    WHERE ls.id = ?";

$stream_stmt = $db->prepare($stream_sql);
$stream_stmt->execute([$_GET['stream_id']]);
$stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);

if (!$stream) {
    header("Location: index.php");
    exit();
}

// Check if user has already earned coins for this stream
$reward_sql = "SELECT * FROM stream_rewards WHERE user_id = ? AND stream_id = ?";
$reward_stmt = $db->prepare($reward_sql);
$reward_stmt->execute([$_SESSION['user_id'], $_GET['stream_id']]);
$existing_reward = $reward_stmt->fetch(PDO::FETCH_ASSOC);

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

// If this is a live stream and it's no longer live, prevent earning coins
$can_earn = true;
if ($stream['video_type'] === 'tournament' && $stream['status'] === 'completed') {
    $can_earn = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($stream['stream_title']); ?> - Watch & Earn</title>
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

                    <?php if ($stream['category_name']): ?>
                    <div class="meta-item">
                        <ion-icon name="folder-outline"></ion-icon>
                        <span><?php echo htmlspecialchars($stream['category_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="meta-item">
                        <ion-icon name="person-outline"></ion-icon>
                        <span><?php echo htmlspecialchars($stream['streamer_name']); ?></span>
                    </div>

                    <div class="meta-item">
                        <ion-icon name="time-outline"></ion-icon>
                        <span>Added: <?php echo date('M d, Y', strtotime($stream['created_at'] ?? $stream['start_time'])); ?></span>
                    </div>
                </div>

                <?php if (!$existing_reward && $can_earn): ?>
                <div class="reward-info">
                    <div class="reward-text">
                        <ion-icon name="wallet-outline"></ion-icon>
                        <div class="reward-details">
                            <div class="amount"><?php echo number_format($stream['coin_reward'], 2); ?> Coins</div>
                            <div class="condition">Watch for <?php echo ceil($stream['minimum_watch_duration'] / 60); ?> minutes to earn</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$existing_reward && $can_earn): ?>
        <div class="progress-wrapper" id="progressWrapper">
            <div class="progress-info">
                <div class="progress-label">Watch Progress</div>
                <div class="progress-time">
                    <span id="currentTime">0:00</span> / 
                    <span id="requiredTime"><?php echo gmdate("i:s", $stream['minimum_watch_duration']); ?></span>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($existing_reward): ?>
        <div class="reward-earned" style="display: block;">
            <ion-icon name="checkmark-circle"></ion-icon>
            <h2>Reward Already Claimed!</h2>
            <p>You've already earned coins for watching this content.</p>
            <div class="coins">+<?php echo number_format($existing_reward['coins_earned'], 2); ?> Coins</div>
            <a href="index.php" class="back-btn">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Back to Videos
            </a>
        </div>
        <?php elseif (!$can_earn): ?>
        <div class="reward-earned" style="display: block;">
            <ion-icon name="alert-circle"></ion-icon>
            <h2>Stream Ended</h2>
            <p>This live stream has ended and is no longer eligible for coin rewards.</p>
            <a href="index.php" class="back-btn">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Back to Videos
            </a>
        </div>
        <?php else: ?>
        <div class="reward-earned" id="rewardEarned">
            <ion-icon name="trophy"></ion-icon>
            <h2>Congratulations!</h2>
            <p>You've successfully completed watching the required duration.</p>
            <div class="coins">+<?php echo number_format($stream['coin_reward'], 2); ?> Coins</div>
            <a href="index.php" class="back-btn">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Back to Videos
            </a>
        </div>
        <?php endif; ?>
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
        var requiredDuration = <?php echo $stream['minimum_watch_duration']; ?>;
        var rewardClaimed = false;
        var isPlaying = false;
        var lastUpdateTime = 0;
        var playerReady = false;

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
            playerReady = true;
            // Make sure the player is visible
            document.getElementById('videoPlayerContainer').style.display = 'block';
        }

        function onPlayerError(event) {
            console.error("Player Error:", event.data);
            showVideoError();
        }

        function showVideoError() {
            document.getElementById('videoPlayerContainer').innerHTML = `
                <div style="padding: 20px; text-align: center; background: #f8d7da; color: #721c24; border-radius: 8px;">
                    <h3>Video Playback Error</h3>
                    <p>Sorry, we couldn't load the video. Please try again later.</p>
                    <a href="index.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;">Back to Videos</a>
                </div>
            `;
        }

        function onPlayerStateChange(event) {
            console.log("Player State Change:", event.data);
            if (event.data == YT.PlayerState.PLAYING) {
                if (!watchStartTime) {
                    watchStartTime = Math.floor(Date.now() / 1000);
                    lastUpdateTime = watchStartTime;
                }
                isPlaying = true;
                document.getElementById('progressWrapper').style.display = 'block';
                updateProgress();
            } else if (event.data == YT.PlayerState.PAUSED || event.data == YT.PlayerState.ENDED) {
                isPlaying = false;
                // If video is paused or ended, update the duration before stopping
                if (lastUpdateTime > 0) {
                    watchDuration += Math.floor(Date.now() / 1000) - lastUpdateTime;
                }
            }
        }

        function updateProgress() {
            if (!rewardClaimed && isPlaying) {
                var currentTime = Math.floor(Date.now() / 1000);
                
                // Only update duration if video is playing
                if (lastUpdateTime > 0) {
                    watchDuration += currentTime - lastUpdateTime;
                }
                lastUpdateTime = currentTime;
                
                var progress = (watchDuration / requiredDuration) * 100;
                progress = Math.min(progress, 100);
                
                document.getElementById('progressFill').style.width = progress + '%';
                document.getElementById('currentTime').textContent = formatTime(watchDuration);
                
                if (watchDuration >= requiredDuration && !rewardClaimed) {
                    claimReward();
                } else if (isPlaying) {
                    setTimeout(updateProgress, 1000);
                }
            }
        }

        function formatTime(seconds) {
            return new Date(seconds * 1000).toISOString().substr(14, 5);
        }

        function claimReward() {
            rewardClaimed = true;
            
            // Send AJAX request to claim reward
            fetch('claim_reward.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'stream_id=<?php echo $stream['id']; ?>&watch_duration=' + watchDuration
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('progressWrapper').style.display = 'none';
                    document.getElementById('rewardEarned').style.display = 'block';
                }
            });
        }
    </script>

    <?php require_once '../includes/footer.php'; ?>
</body>
</html> 