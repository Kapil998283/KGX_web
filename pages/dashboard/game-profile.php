<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Check if user already has a game profile
$stmt = $db->prepare("SELECT * FROM user_game WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$existing_profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_name = $_POST['game_name'];
    $game_username = $_POST['game_username'];
    $game_uid = $_POST['game_uid'];
    
    try {
        if ($existing_profile) {
            // Update existing profile
            $stmt = $db->prepare("UPDATE user_game SET game_name = ?, game_username = ?, game_uid = ? WHERE user_id = ?");
            $stmt->execute([$game_name, $game_username, $game_uid, $_SESSION['user_id']]);
        } else {
            // Create new profile
            $stmt = $db->prepare("INSERT INTO user_game (user_id, game_name, game_username, game_uid) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $game_name, $game_username, $game_uid]);
        }
        
        $_SESSION['success_message'] = "Game profile updated successfully!";
        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry error
            $error = "This game UID is already registered by another user.";
        } else {
            $error = "An error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Game - KGX</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/game-profile.css">
    <style>
        body {
            background-color: #0a0a0a;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .navbar {
            background-color: #000000;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .navbar img {
            height: 40px;
        }
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        .nav-links a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .nav-links a:hover {
            color: #4ecdc4;
        }
        .game-selection-title {
            text-align: center;
            padding: 3rem 0;
        }
        .game-selection-title h2 {
            color: #ffffff;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .game-selection-title p {
            color: #a0a0a0;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .game-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .game-card h3 {
            color: #ffffff;
            font-size: 1.2rem;
            margin-top: 1rem;
        }
        .game-card.selected {
            border-color: #4ecdc4;
            background: rgba(78, 205, 196, 0.1);
        }
        .game-details-form {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .form-group label {
            color: #ffffff;
            font-weight: 500;
        }
        .form-group input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .submit-btn {
            background: #4ecdc4;
            color: #ffffff;
            font-weight: 600;
        }
        .submit-btn:hover {
            background: #3dbdb4;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="../../home.php">
            <img src="../../assets/images/logo.jpg" alt="KGX Logo">
        </a>
        <div class="nav-links">
            <a href="../../home.php">HOME</a>
            <a href="../../pages/tournaments.php">TOURNAMENTS</a>
            <a href="../../pages/matches">MATCHES</a>
            <a href="../../pages/teams">TEAMS</a>
        </div>
    </nav>

    <main>
        <div class="game-profile-container">
            <div class="game-selection-title">
                <h2>Select Your Primary Game</h2>
                <p>Choose your primary game and provide your in-game details for tournament participation</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="game-cards">
                <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'PUBG' ? 'selected' : ''; ?>" data-game="PUBG">
                    <img src="../../assets/images/games/pubg.png" alt="PUBG">
                    <h3>PUBG</h3>
                </div>
                
                <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'BGMI' ? 'selected' : ''; ?>" data-game="BGMI">
                    <img src="../../assets/images/games/bgmi.png" alt="BGMI">
                    <h3>BGMI</h3>
                </div>
                
                <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'FREE FIRE' ? 'selected' : ''; ?>" data-game="FREE FIRE">
                    <img src="../../assets/images/games/freefire.png" alt="Free Fire">
                    <h3>Free Fire</h3>
                </div>
                
                <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'COD' ? 'selected' : ''; ?>" data-game="COD">
                    <img src="../../assets/images/games/cod.png" alt="COD">
                    <h3>Call of Duty</h3>
                </div>
            </div>

            <form class="game-details-form <?php echo $existing_profile ? 'active' : ''; ?>" method="POST">
                <input type="hidden" name="game_name" id="selected_game" value="<?php echo $existing_profile ? $existing_profile['game_name'] : ''; ?>">
                
                <div class="form-group">
                    <label for="game_username">In-Game Username</label>
                    <input type="text" id="game_username" name="game_username" value="<?php echo $existing_profile ? $existing_profile['game_username'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="game_uid">Game UID</label>
                    <input type="text" id="game_uid" name="game_uid" value="<?php echo $existing_profile ? $existing_profile['game_uid'] : ''; ?>" required>
                </div>
                
                <button type="submit" class="submit-btn">Save Game Profile</button>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const gameCards = document.querySelectorAll('.game-card');
        const form = document.querySelector('.game-details-form');
        const selectedGameInput = document.getElementById('selected_game');
        
        gameCards.forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                gameCards.forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Show form
                form.classList.add('active');
                
                // Update hidden input
                selectedGameInput.value = this.dataset.game;
            });
        });
    });
    </script>
</body>
</html> 