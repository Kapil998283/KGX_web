<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to KGX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background: #000;
        }
        .preloader {
            width: 100%;
            height: 100%;
            position: relative;
        }
        #preloader-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }
        .next-btn {
            position: absolute;
            bottom: 30px;
            right: 30px;
            padding: 12px 30px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            border-radius: 25px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
            text-decoration: none;
            backdrop-filter: blur(5px);
        }
        .next-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="preloader">
        <video id="preloader-video" autoplay muted>
            <source src="assets/images/kgx_preloader.mp4" type="video/mp4">
        </video>
    </div>

    <script>
        // Function to redirect based on session
        function redirectUser() {
            <?php if(isset($_SESSION['user_id'])): ?>
                window.location.href = 'home.php';
            <?php else: ?>
                window.location.href = 'intro1.php';
            <?php endif; ?>
        }

        // Play video and set up automatic redirect
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.getElementById('preloader-video');
            
            // Set timeout for 4 seconds
            setTimeout(redirectUser, 4000);

            // If video ends before 4 seconds, redirect immediately
            video.addEventListener('ended', function() {
                redirectUser();
            });

            // Loop video if it's shorter than 4 seconds
            video.addEventListener('ended', function() {
                if(video.currentTime < 4) {
                    video.currentTime = 0;
                    video.play();
                }
            });
        });
    </script>
</body>
</html> 