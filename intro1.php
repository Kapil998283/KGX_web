<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGX - Tournaments</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/intro.css">
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js" type="module"></script>
    <script src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js" nomodule></script>
</head>
<body>
    <div class="intro-container">
        <div class="intro-content">
            <h1 class="intro-title">Epic Tournaments</h1>
            <p class="intro-description">
                Join the most thrilling esports tournaments and compete for glory and amazing prizes!
            </p>

            <div class="feature-grid">
                <div class="feature-card">
                    <ion-icon name="trophy" class="feature-icon"></ion-icon>
                    <h3 class="feature-title">Weekly Tournaments</h3>
                    <p class="feature-text">Regular tournaments across multiple games with exciting prize pools.</p>
                </div>

                <div class="feature-card">
                    <ion-icon name="diamond" class="feature-icon"></ion-icon>
                    <h3 class="feature-title">Prize Pools</h3>
                    <p class="feature-text">Compete for substantial rewards and establish your gaming legacy.</p>
                </div>

                <div class="feature-card">
                    <ion-icon name="people" class="feature-icon"></ion-icon>
                    <h3 class="feature-title">Community Events</h3>
                    <p class="feature-text">Special tournaments and events to bring the gaming community together.</p>
                </div>
            </div>
        </div>

        <a href="home.php" class="skip-btn">Skip Intro</a>
        <a href="intro2.php" class="next-btn">Next <ion-icon name="arrow-forward-outline"></ion-icon></a>
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