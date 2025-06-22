<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get user's games including main game status
$sql = "SELECT game_name, game_username, game_uid, game_level, is_primary FROM user_games WHERE user_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$user_games = [];
while ($row = $stmt->fetch()) {
    $user_games[$row['game_name']] = [
        'game_username' => $row['game_username'],
        'game_uid' => $row['game_uid'],
        'game_level' => $row['game_level'],
        'is_primary' => $row['is_primary']
    ];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $game = $_POST['game_name'];
        $username = $_POST['game_username'];
        $uid = $_POST['game_uid'];
        $level = intval($_POST['game_level']);
        
        // Validate input
        if (empty($game) || empty($username) || empty($uid) || empty($level)) {
            throw new Exception('All fields are required');
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Check if this game exists for the user
            $stmt = $db->prepare("SELECT id FROM user_games WHERE user_id = ? AND game_name = ?");
            $stmt->execute([$_SESSION['user_id'], $game]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing game profile
                $stmt = $db->prepare("UPDATE user_games SET game_username = ?, game_uid = ?, game_level = ? WHERE user_id = ? AND game_name = ?");
                $stmt->execute([$username, $uid, $level, $_SESSION['user_id'], $game]);
            } else {
                // Insert new game profile
                $stmt = $db->prepare("INSERT INTO user_games (user_id, game_name, game_username, game_uid, game_level) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $game, $username, $uid, $level]);
            }
            
            // Commit transaction
            $db->commit();
            
            $response['success'] = true;
            $response['message'] = 'Game profile saved successfully!';
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Profile - KGX</title>
    <link rel="stylesheet" href="../../assets/css/game-profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Core styles */
        :root {
            --primary-color: #00c896;
            --secondary-color: #333;
            --border-color: #ddd;
            --error-color: #ff4444;
            --success-color: #00c851;
        }

        /* Game Profile Section */
        .game-profile-section {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .game-profile-container {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Header Styles */
        .page-header {
            margin-bottom: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: var(--primary-color);
        }

        .back-btn i {
            margin-right: 0.5rem;
        }

        /* Success Message */
        .success-message {
            background-color: var(--success-color);
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Game Cards */
        .game-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .game-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .game-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .game-card-inner {
            background: #fff;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
        }

        .game-image {
            width: 100%;
            height: 150px;
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
        }

        .game-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .game-card h3 {
            font-size: 1.25rem;
            margin: 0 0 1rem;
            color: var(--secondary-color);
        }

        .game-info {
            margin-top: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .game-username,
        .game-uid,
        .game-level {
            margin: 0;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .game-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .game-card:hover .game-overlay {
            opacity: 1;
        }

        .select-text {
            color: white;
            font-weight: 500;
            font-size: 1.1rem;
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0.5rem;
            line-height: 1;
        }

        .modal-close:hover {
            color: var(--error-color);
        }

        .modal-title {
            margin: 0 0 1.5rem;
            color: var(--secondary-color);
            font-size: 1.5rem;
        }

        .container h1 {
            color: var(--secondary-color);
            margin: 0 0 0.5rem;
            font-size: 2rem;
        }

        .subtitle {
            color: #666;
            margin: 0 0 2rem;
            font-size: 1.1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        /* Number Input Styles */
        .form-group input[type="number"] {
            -moz-appearance: textfield;
        }

        .form-group input[type="number"]::-webkit-inner-spin-button,
        .form-group input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Character Count */
        .character-count {
            font-size: 0.875rem;
            color: #666;
            text-align: right;
            margin-top: 0.25rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal.active {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s;
        }

        .modal.active .modal-content {
            transform: translateY(0);
        }

        /* Submit Button */
        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #00b085;
        }

        .submit-btn .arrow {
            margin-left: 0.5rem;
        }

        /* Primary Badge */
        .primary-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .game-profile-section {
                padding: 1rem;
            }

            .game-profile-container {
                padding: 1rem;
            }

            .game-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
        }

        /* Add styles for configured cards */
        .game-card.configured .game-card-inner {
            border-color: var(--primary-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background-color: var(--error-color);
            color: white;
        }
    </style>
</head>
<body>

<div class="game-profile-section">
    <div class="game-profile-container">
        <div class="page-header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="success-message" id="successMessage" style="display: none;">
            Game profile saved successfully!
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="container">
            <h1>YOUR GAME PROFILES</h1>
            <p class="subtitle">Add or update your game profiles - you can add multiple games!</p>

            <div class="game-cards">
                <?php
                $games = [
                    'PUBG' => [
                        'name' => 'PUBG',
                        'image' => 'pubg.png',
                        'username_pattern' => '2-16 characters',
                        'uid_pattern' => '8-10 digits'
                    ],
                    'BGMI' => [
                        'name' => 'BGMI',
                        'image' => 'bgmi.png',
                        'username_pattern' => '2-16 characters',
                        'uid_pattern' => '8-10 digits'
                    ],
                    'FREE FIRE' => [
                        'name' => 'Free Fire',
                        'image' => 'freefire.png',
                        'username_pattern' => '1-12 characters',
                        'uid_pattern' => '7-9 digits'
                    ],
                    'COD' => [
                        'name' => 'Call of Duty',
                        'image' => 'cod.png',
                        'username_pattern' => '3-20 characters',
                        'uid_pattern' => '6-8 digits'
                    ]
                ];

                foreach ($games as $key => $game): ?>
                    <div class="game-card <?php echo isset($user_games[$key]) ? 'configured' : ''; ?>" 
                         data-game="<?php echo $key; ?>"
                         data-username-pattern="<?php echo $game['username_pattern']; ?>"
                         data-uid-pattern="<?php echo $game['uid_pattern']; ?>">
                        <div class="game-card-inner">
                            <div class="game-image">
                                <img src="../../assets/images/games/<?php echo $game['image']; ?>" alt="<?php echo $game['name']; ?>">
                            </div>
                            <h3><?php echo $game['name']; ?></h3>
                            <div class="game-info">
                                <div class="info-row">
                                    <span class="info-label">Username:</span>
                                    <p class="game-username"><?php echo isset($user_games[$key]) ? htmlspecialchars($user_games[$key]['game_username']) : '-'; ?></p>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">UID:</span>
                                    <p class="game-uid"><?php echo isset($user_games[$key]) ? htmlspecialchars($user_games[$key]['game_uid']) : '-'; ?></p>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Level:</span>
                                    <p class="game-level"><?php echo isset($user_games[$key]) ? htmlspecialchars($user_games[$key]['game_level']) : '-'; ?></p>
                                </div>
                                <?php if (isset($user_games[$key]) && $user_games[$key]['is_primary']): ?>
                                    <span class="primary-badge">Main</span>
                                <?php endif; ?>
                            </div>
                            <div class="game-overlay">
                                <span class="select-text"><?php echo isset($user_games[$key]) ? 'Update Profile' : 'Add Profile'; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal" id="gameProfileModal">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h2 class="modal-title">Game Profile</h2>
        
        <form class="game-details-form" method="POST" id="gameProfileForm">
            <input type="hidden" name="selected_game" id="selected_game">
            
            <div class="form-group">
                <label for="game_username">In-Game Username</label>
                <input type="text" id="game_username" name="game_username" required>
                <div class="character-count">
                    <span id="username_count">0</span>/<span id="username_max">20</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="game_uid">Game UID</label>
                <input type="text" id="game_uid" name="game_uid" required>
                <div class="character-count">
                    <span id="uid_count">0</span>/<span id="uid_max">10</span>
                </div>
            </div>

            <div class="form-group">
                <label for="game_level">Game Level</label>
                <input type="number" id="game_level" name="game_level" min="1" max="100" required>
            </div>
            
            <button type="submit" class="submit-btn">
                <span id="submit_text">Add Profile</span>
                <span class="arrow">→</span>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gameCards = document.querySelectorAll('.game-card');
    const modal = document.getElementById('gameProfileModal');
    const form = document.querySelector('.game-details-form');
    const selectedGameInput = document.getElementById('selected_game');
    const usernamePattern = document.getElementById('username_pattern');
    const uidPattern = document.getElementById('uid_pattern');
    const gameUidInput = document.getElementById('game_uid');
    const gameUsernameInput = document.getElementById('game_username');
    const gameLevelInput = document.getElementById('game_level');
    const usernameCount = document.getElementById('username_count');
    const usernameMax = document.getElementById('username_max');
    const uidCount = document.getElementById('uid_count');
    const uidMax = document.getElementById('uid_max');
    const submitText = document.getElementById('submit_text');
    const modalClose = document.querySelector('.modal-close');

    // Store game profiles data
    const gameProfiles = <?php echo json_encode($user_games); ?>;

    // Close modal when clicking the close button or outside the modal
    modalClose.addEventListener('click', function() {
        modal.classList.remove('active');
    });

    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Only allow numbers in UID field
    gameUidInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
        
        // Get max length from the current game's requirements
        const maxLength = parseInt(uidMax.textContent);
        if (this.value.length > maxLength) {
            this.value = this.value.slice(0, maxLength);
        }
        
        uidCount.textContent = this.value.length;
    });

    // Update username character count and enforce limit
    gameUsernameInput.addEventListener('input', function(e) {
        // Get max length from the current game's requirements
        const maxLength = parseInt(usernameMax.textContent);
        if (this.value.length > maxLength) {
            this.value = this.value.slice(0, maxLength);
        }
        
        usernameCount.textContent = this.value.length;
    });

    // Add event listener for game level input
    gameLevelInput.addEventListener('input', function(e) {
        // Remove any non-numeric characters
        this.value = this.value.replace(/\D/g, '');
        
        // Ensure value is between 1 and 100
        let value = parseInt(this.value);
        if (value > 100) {
            this.value = '100';
        } else if (value < 1 && this.value !== '') {
            this.value = '1';
        }
    });

    gameCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            gameCards.forEach(c => c.classList.remove('selected'));
            
            // Add selected class to clicked card
            this.classList.add('selected');
            
            // Show modal
            modal.classList.add('active');
            
            // Get game name and update form
            const game = this.dataset.game;
            selectedGameInput.value = game;
            usernamePattern.textContent = this.dataset.usernamePattern;
            uidPattern.textContent = this.dataset.uidPattern;
            
            // Set input restrictions based on game
            switch(game) {
                case 'PUBG':
                case 'BGMI':
                    gameUidInput.maxLength = 10;
                    gameUidInput.minLength = 8;
                    gameUsernameInput.maxLength = 16;
                    gameUsernameInput.minLength = 2;
                    uidMax.textContent = '10';
                    usernameMax.textContent = '16';
                    break;
                case 'FREE FIRE':
                    gameUidInput.maxLength = 9;
                    gameUidInput.minLength = 7;
                    gameUsernameInput.maxLength = 12;
                    gameUsernameInput.minLength = 1;
                    uidMax.textContent = '9';
                    usernameMax.textContent = '12';
                    break;
                case 'COD':
                    gameUidInput.maxLength = 8;
                    gameUidInput.minLength = 6;
                    gameUsernameInput.maxLength = 20;
                    gameUsernameInput.minLength = 3;
                    uidMax.textContent = '8';
                    usernameMax.textContent = '20';
                    break;
            }

            // If game profile exists, populate form
            if (gameProfiles[game]) {
                gameUsernameInput.value = gameProfiles[game].game_username;
                gameUidInput.value = gameProfiles[game].game_uid;
                gameLevelInput.value = gameProfiles[game].game_level || 1;
                usernameCount.textContent = gameProfiles[game].game_username.length;
                uidCount.textContent = gameProfiles[game].game_uid.length;
            } else {
                gameUidInput.value = '';
                gameUsernameInput.value = '';
                gameLevelInput.value = '1';
                uidCount.textContent = '0';
                usernameCount.textContent = '0';
            }
        });
    });

    // Character count update function
    function updateCharCount(input, countSpan, maxSpan) {
        const currentLength = input.value.length;
        const maxLength = input.getAttribute('maxlength');
        countSpan.textContent = currentLength;
        maxSpan.textContent = maxLength;
    }

    // Success message handling
    function showSuccessMessage() {
        const successMessage = document.getElementById('successMessage');
        successMessage.style.display = 'block';
        successMessage.style.opacity = '1';
        
        // Hide after 3 seconds
        setTimeout(() => {
            successMessage.style.opacity = '0';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 300); // Wait for fade out animation
        }, 3000);
    }

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(this);
        
        // Add game name
        formData.append('game_name', selectedGameInput.value);
        
        // Send AJAX request
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage();
                modal.classList.remove('active');
                // Reload the page after a short delay
                setTimeout(() => {
                    location.reload();
                }, 3500); // Wait for success message to fade
            } else {
                alert(data.message || 'An error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the game profile');
        });
    });

    // Update character count on input
    usernameInput.addEventListener('input', () => updateCharCount(usernameInput, usernameCount, usernameMax));
    uidInput.addEventListener('input', () => updateCharCount(uidInput, uidCount, uidMax));

    // Initial character count update
    updateCharCount(usernameInput, usernameCount, usernameMax);
    updateCharCount(uidInput, uidCount, uidMax);
});
</script>

</body>
</html> 