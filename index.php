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
        <a href="intro1.php" class="next-btn">Get Started</a>
    </div>

    <script>
        document.getElementById('preloader-video').addEventListener('ended', function() {
            this.currentTime = 0;
            this.play();
        });
    </script>
</body>
</html> 