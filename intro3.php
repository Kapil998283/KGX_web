<?php
session_start();
// Redirect logged-in users to home page
if(isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGX - Teams</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/intro.css">
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js" type="module"></script>
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js" nomodule></script>
</head>
<body>
    <div class="intro-container">
        <div class="intro-content">
            <h1 class="intro-title">Epic Teams</h1>
            <p class="intro-description">
                Build your dream team, compete together, and rise to the top of the leaderboards!
            </p>

            <div class="feature-grid">
                <div class="feature-card">
                    <ion-icon name="people" class="feature-icon"></ion-icon>
                    <h3 class="feature-title">Team Management</h3>
                    <p class="feature-text">Create or join teams, manage members, and coordinate your strategies.</p>
                </div>

                <div class="feature-card">
                    <ion-icon name="trending-up" class="feature-icon"></ion-icon>
                    <h3 class="feature-title">Team Rankings</h3>
                    <p class="feature-text">Compete in tournaments to improve your team's ranking and reputation.</p>
                </div>

                <div class="feature-card">
                    <ion-icon name="chatbubbles" class="feature-icon"></ion-icon>
                    <h3 class="feature-title">Team Communication</h3>
                    <p class="feature-text">Built-in tools to coordinate with your teammates and plan your strategies.</p>
                </div>
            </div>
        </div>

        <a href="home.php" class="skip-btn">Skip Intro</a>
        <a href="home.php" class="next-btn">Get Started <ion-icon name="arrow-forward-outline"></ion-icon></a>
    </div>

    <canvas class="particles"></canvas>

    <script>
        // Particle animation
        const canvas = document.querySelector('.particles');
        const ctx = canvas.getContext('2d');
        
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        
        const particles = [];
        const particleCount = 100;
        
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2;
                this.speedX = Math.random() * 2 - 1;
                this.speedY = Math.random() * 2 - 1;
            }
            
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                
                if (this.x > canvas.width) this.x = 0;
                if (this.x < 0) this.x = canvas.width;
                if (this.y > canvas.height) this.y = 0;
                if (this.y < 0) this.y = canvas.height;
            }
            
            draw() {
                ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
        function init() {
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Particle());
            }
        }
        
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });
            requestAnimationFrame(animate);
        }
        
        init();
        animate();
        
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    </script>
</body>
</html> 


kapil
