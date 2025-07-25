<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/user-auth.php';

// Get database connection
$conn = getDbConnection();

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if user has completed steps 1 and 2
if (!isset($_SESSION['registration_step']) || $_SESSION['registration_step'] < 3) {
    header("Location: multi-step-register.php");
    exit();
}

$error = '';
$success = '';

// Handle final registration submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'complete_registration') {
    $main_game = trim($_POST['main_game'] ?? '');
    $game_username = trim($_POST['game_username'] ?? '');
    $game_uid = trim($_POST['game_uid'] ?? '');
    $game_level = intval($_POST['game_level'] ?? 1);
    
    if (empty($main_game)) {
        $error = "Please select your main game";
    } elseif (empty($game_username)) {
        $error = "Please enter your game username";
    } elseif (empty($game_uid)) {
        $error = "Please enter your game UID";
    } elseif ($game_level < 1 || $game_level > 100) {
        $error = "Please enter a valid game level (1-100)";
    } else {
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Insert new user
            $sql = "INSERT INTO users (username, email, password, phone, role) VALUES (:username, :email, :password, :phone, 'user')";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":username", $_SESSION['registration_data']['username']);
            $stmt->bindParam(":email", $_SESSION['registration_data']['email']);
            $stmt->bindParam(":password", $_SESSION['registration_data']['password']);
            $stmt->bindParam(":phone", $_SESSION['registration_data']['phone']);
            
            if ($stmt->execute()) {
                $user_id = $conn->lastInsertId();
                
                // Add user's main game
                $sql = "INSERT INTO user_games (user_id, game_name, game_username, game_uid, game_level, is_primary) VALUES (:user_id, :game_name, :game_username, :game_uid, :game_level, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->bindParam(":game_name", $main_game);
                $stmt->bindParam(":game_username", $game_username);
                $stmt->bindParam(":game_uid", $game_uid);
                $stmt->bindParam(":game_level", $game_level);
                $stmt->execute();
                
                // Give new user 100 coins
                $sql = "INSERT INTO user_coins (user_id, coins) VALUES (:user_id, 100)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->execute();
                
                // Give new user 1 ticket
                $sql = "INSERT INTO user_tickets (user_id, tickets) VALUES (:user_id, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->execute();
                
                // Create welcome notification
                $welcome_message = "Welcome to KGX Gaming, " . $_SESSION['registration_data']['username'] . "! Your account has been successfully created. You've received 100 coins and 1 tournament ticket to get you started!";
                
                // Check if notifications table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
                if ($table_check->rowCount() > 0) {
                    $notification_sql = "INSERT INTO notifications (user_id, message, type) VALUES (:user_id, :message, 'welcome')";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $user_id);
                    $notification_stmt->bindParam(':message', $welcome_message);
                    $notification_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                // Set session variables to automatically log in the user
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $_SESSION['registration_data']['username'];
                $_SESSION['email'] = $_SESSION['registration_data']['email'];
                $_SESSION['role'] = 'user';
                
                // Clear registration data
                unset($_SESSION['registration_step']);
                unset($_SESSION['registration_data']);
                
                // Redirect to home page instead of login
                header("Location: ../index.php?welcome=1");
                exit();
            } else {
                throw new Exception("Error creating user account");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

$games = [
    'PUBG' => [
        'name' => 'PUBG',
        'image' => 'pubg.png',
        'username_pattern' => '2-16 characters',
        'uid_pattern' => '8-10 digits',
        'description' => 'PlayerUnknown\'s Battlegrounds'
    ],
    'BGMI' => [
        'name' => 'BGMI',
        'image' => 'bgmi.png',
        'username_pattern' => '2-16 characters',
        'uid_pattern' => '8-10 digits',
        'description' => 'Battlegrounds Mobile India'
    ],
    'FREE FIRE' => [
        'name' => 'Free Fire',
        'image' => 'freefire.png',
        'username_pattern' => '1-12 characters',
        'uid_pattern' => '7-9 digits',
        'description' => 'Garena Free Fire'
    ],
    'COD' => [
        'name' => 'Call of Duty',
        'image' => 'cod.png',
        'username_pattern' => '3-20 characters',
        'uid_pattern' => '6-8 digits',
        'description' => 'Call of Duty Mobile'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Selection - Step 3 | KGX Gaming</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../favicon.svg" type="image/svg+xml">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/multi-step-auth.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Ion Icons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <div class="auth-container multi-step game-selection">
            <!-- Progress Indicator -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 100%"></div>
                </div>
                <div class="step-indicators">
                    <div class="step-indicator completed">
                        <div class="step-number">
                            <ion-icon name="checkmark-outline"></ion-icon>
                        </div>
                        <div class="step-label">Account Info</div>
                    </div>
                    <div class="step-indicator completed">
                        <div class="step-number">
                            <ion-icon name="checkmark-outline"></ion-icon>
                        </div>
                        <div class="step-label">Phone Verify</div>
                    </div>
                    <div class="step-indicator active">
                        <div class="step-number">3</div>
                        <div class="step-label">Game Profile</div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="auth-header">
                <div class="logo">
                    <h1 class="brand-text">KGX</h1>
                    <span class="brand-tagline">GAMING XTREME</span>
                </div>
                <h2 class="auth-title">Choose Your Main Game</h2>
                <p class="auth-subtitle">Set up your gaming profile to join tournaments</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Game Selection -->
            <div class="game-selection-container">
                <div class="games-grid">
                    <?php foreach ($games as $key => $game): ?>
                        <div class="game-card" data-game="<?php echo $key; ?>" 
                             data-username-pattern="<?php echo $game['username_pattern']; ?>"
                             data-uid-pattern="<?php echo $game['uid_pattern']; ?>">
                            <div class="game-image">
                                <img src="../assets/images/games/<?php echo $game['image']; ?>" 
                                     alt="<?php echo $game['name']; ?>" 
                                     onerror="this.src='../assets/images/games/default-game.png'">
                            </div>
                            <div class="game-info">
                                <h3><?php echo $game['name']; ?></h3>
                                <p><?php echo $game['description']; ?></p>
                            </div>
                            <div class="game-select-indicator">
                                <ion-icon name="checkmark-circle-outline"></ion-icon>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Game Profile Form -->
            <form class="auth-form game-profile-form" method="POST" action="" style="display: none;">
                <input type="hidden" name="action" value="complete_registration">
                <input type="hidden" id="main_game" name="main_game">
                
                <div class="selected-game-display">
                    <div class="selected-game-icon">
                        <img id="selected-game-img" src="" alt="">
                    </div>
                    <div class="selected-game-info">
                        <h4 id="selected-game-name">Select a game</h4>
                        <p id="selected-game-desc">Choose your main game above</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="game_username">
                        <ion-icon name="person-outline"></ion-icon>
                        In-Game Username
                    </label>
                    <input type="text" id="game_username" name="game_username" required
                           placeholder="Enter your username in the game">
                    <div class="input-hint">
                        <span id="username-hint">Username format: varies by game</span>
                        <span class="char-count">
                            <span id="username-count">0</span>/<span id="username-max">16</span>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="game_uid">
                        <ion-icon name="id-card-outline"></ion-icon>
                        Game UID
                    </label>
                    <input type="text" id="game_uid" name="game_uid" required
                           placeholder="Enter your unique ID in the game">
                    <div class="input-hint">
                        <span id="uid-hint">UID format: varies by game</span>
                        <span class="char-count">
                            <span id="uid-count">0</span>/<span id="uid-max">10</span>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="game_level">
                        <ion-icon name="trophy-outline"></ion-icon>
                        Current Level
                    </label>
                    <input type="number" id="game_level" name="game_level" min="1" max="100" 
                           value="1" required placeholder="Enter your current level">
                    <div class="input-hint">Your current level or rank in the game (1-100)</div>
                </div>
                
                <button type="submit" class="auth-btn primary-btn complete-btn">
                    <span>Complete Registration</span>
                    <ion-icon name="rocket-outline"></ion-icon>
                </button>
            </form>
            
            <div class="auth-footer">
                <div class="step-navigation">
                    <a href="phone-verification.php" class="back-btn">
                        <ion-icon name="arrow-back-outline"></ion-icon>
                        Back
                    </a>
                </div>
                
                <div class="welcome-rewards">
                    <div class="reward-item">
                        <ion-icon name="diamond-outline"></ion-icon>
                        <span>100 Coins</span>
                    </div>
                    <div class="reward-item">
                        <ion-icon name="ticket-outline"></ion-icon>
                        <span>1 Tournament Ticket</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/multi-step-auth.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const gameCards = document.querySelectorAll('.game-card');
            const gameForm = document.querySelector('.game-profile-form');
            const mainGameInput = document.getElementById('main_game');
            const gameUsernameInput = document.getElementById('game_username');
            const gameUidInput = document.getElementById('game_uid');
            const usernameCount = document.getElementById('username-count');
            const usernameMax = document.getElementById('username-max');
            const uidCount = document.getElementById('uid-count');
            const uidMax = document.getElementById('uid-max');
            const usernameHint = document.getElementById('username-hint');
            const uidHint = document.getElementById('uid-hint');
            
            const games = <?php echo json_encode($games); ?>;
            
            // Game card selection
            gameCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    gameCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Get game data
                    const gameKey = this.dataset.game;
                    const game = games[gameKey];
                    
                    // Update form
                    mainGameInput.value = gameKey;
                    document.getElementById('selected-game-name').textContent = game.name;
                    document.getElementById('selected-game-desc').textContent = game.description;
                    document.getElementById('selected-game-img').src = '../assets/images/games/' + game.image;
                    document.getElementById('selected-game-img').alt = game.name;
                    
                    // Update input constraints
                    setGameConstraints(gameKey);
                    
                    // Show form
                    gameForm.style.display = 'block';
                    gameForm.scrollIntoView({ behavior: 'smooth' });
                });
            });
            
            function setGameConstraints(gameKey) {
                switch(gameKey) {
                    case 'PUBG':
                    case 'BGMI':
                        gameUidInput.maxLength = 10;
                        gameUidInput.minLength = 8;
                        gameUsernameInput.maxLength = 16;
                        gameUsernameInput.minLength = 2;
                        uidMax.textContent = '10';
                        usernameMax.textContent = '16';
                        usernameHint.textContent = 'Username: 2-16 characters';
                        uidHint.textContent = 'UID: 8-10 digits';
                        break;
                    case 'FREE FIRE':
                        gameUidInput.maxLength = 9;
                        gameUidInput.minLength = 7;
                        gameUsernameInput.maxLength = 12;
                        gameUsernameInput.minLength = 1;
                        uidMax.textContent = '9';
                        usernameMax.textContent = '12';
                        usernameHint.textContent = 'Username: 1-12 characters';
                        uidHint.textContent = 'UID: 7-9 digits';
                        break;
                    case 'COD':
                        gameUidInput.maxLength = 8;
                        gameUidInput.minLength = 6;
                        gameUsernameInput.maxLength = 20;
                        gameUsernameInput.minLength = 3;
                        uidMax.textContent = '8';
                        usernameMax.textContent = '20';
                        usernameHint.textContent = 'Username: 3-20 characters';
                        uidHint.textContent = 'UID: 6-8 digits';
                        break;
                }
            }
            
            // Character counting
            gameUsernameInput.addEventListener('input', function() {
                const maxLength = parseInt(usernameMax.textContent);
                if (this.value.length > maxLength) {
                    this.value = this.value.slice(0, maxLength);
                }
                usernameCount.textContent = this.value.length;
            });
            
            // UID input - numbers only
            gameUidInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                const maxLength = parseInt(uidMax.textContent);
                if (this.value.length > maxLength) {
                    this.value = this.value.slice(0, maxLength);
                }
                uidCount.textContent = this.value.length;
            });
            
            // Level input validation
            document.getElementById('game_level').addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                let value = parseInt(this.value);
                if (value > 100) {
                    this.value = '100';
                } else if (value < 1 && this.value !== '') {
                    this.value = '1';
                }
            });
        });
    </script>
</body>
</html>
