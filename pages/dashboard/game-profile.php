<?php
require_once '../../config/database.php';
require_once '../../includes/user-auth.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Get all user's game profiles
$stmt = $db->prepare("SELECT id, user_id, game_name, game_username, game_uid, COALESCE(is_primary, 0) as is_primary FROM user_game WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$game_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create associative array for easy access
$user_games = [];
foreach ($game_profiles as $profile) {
    $user_games[$profile['game_name']] = $profile;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_name = $_POST['game_name'];
    $game_username = $_POST['game_username'];
    $game_uid = $_POST['game_uid'];
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // If this game is set as primary, unset other primary games
        if ($is_primary) {
            $stmt = $db->prepare("UPDATE user_game SET is_primary = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        // Check if profile for this game already exists
        $stmt = $db->prepare("SELECT id FROM user_game WHERE user_id = ? AND game_name = ?");
        $stmt->execute([$_SESSION['user_id'], $game_name]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing profile
            $stmt = $db->prepare("UPDATE user_game SET game_username = ?, game_uid = ?, is_primary = ? WHERE id = ?");
            $stmt->execute([$game_username, $game_uid, $is_primary, $existing['id']]);
        } else {
            // Insert new profile
            $stmt = $db->prepare("INSERT INTO user_game (user_id, game_name, game_username, game_uid, is_primary) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $game_name, $game_username, $game_uid, $is_primary]);
        }
        
        $db->commit();
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error saving game profile: " . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/game-profile.css">

<div class="game-profile-section">
    <div class="game-profile-container">
        <div class="game-selection-title">
            <h2>Your Game Profiles</h2>
            <p>Add or update your game profiles - you can add multiple games!</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Game profile saved successfully!</div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="game-cards">
            <div class="game-card <?php echo isset($user_games['PUBG']) ? 'configured' : ''; ?>" data-game="PUBG" data-username-pattern="2-16 characters" data-uid-pattern="8-10 digits">
                <div class="game-card-inner">
                    <div class="game-image">
                        <img src="../../assets/images/games/pubg.png" alt="PUBG">
                    </div>
                    <h3>PUBG</h3>
                    <?php if (isset($user_games['PUBG'])): ?>
                        <div class="game-info">
                            <p class="game-username"><?php echo htmlspecialchars($user_games['PUBG']['game_username']); ?></p>
                            <p class="game-uid">UID: <?php echo htmlspecialchars($user_games['PUBG']['game_uid']); ?></p>
                            <?php if ($user_games['PUBG']['is_primary']): ?>
                                <span class="primary-badge">Primary</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="game-overlay">
                        <span class="select-text"><?php echo isset($user_games['PUBG']) ? 'Update Profile' : 'Add Profile'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="game-card <?php echo isset($user_games['BGMI']) ? 'configured' : ''; ?>" data-game="BGMI" data-username-pattern="2-16 characters" data-uid-pattern="8-10 digits">
                <div class="game-card-inner">
                    <div class="game-image">
                        <img src="../../assets/images/games/bgmi.png" alt="BGMI">
                    </div>
                    <h3>BGMI</h3>
                    <?php if (isset($user_games['BGMI'])): ?>
                        <div class="game-info">
                            <p class="game-username"><?php echo htmlspecialchars($user_games['BGMI']['game_username']); ?></p>
                            <p class="game-uid">UID: <?php echo htmlspecialchars($user_games['BGMI']['game_uid']); ?></p>
                            <?php if ($user_games['BGMI']['is_primary']): ?>
                                <span class="primary-badge">Primary</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="game-overlay">
                        <span class="select-text"><?php echo isset($user_games['BGMI']) ? 'Update Profile' : 'Add Profile'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="game-card <?php echo isset($user_games['FREE FIRE']) ? 'configured' : ''; ?>" data-game="FREE FIRE" data-username-pattern="1-12 characters" data-uid-pattern="7-9 digits">
                <div class="game-card-inner">
                    <div class="game-image">
                        <img src="../../assets/images/games/freefire.png" alt="Free Fire">
                    </div>
                    <h3>Free Fire</h3>
                    <?php if (isset($user_games['FREE FIRE'])): ?>
                        <div class="game-info">
                            <p class="game-username"><?php echo htmlspecialchars($user_games['FREE FIRE']['game_username']); ?></p>
                            <p class="game-uid">UID: <?php echo htmlspecialchars($user_games['FREE FIRE']['game_uid']); ?></p>
                            <?php if ($user_games['FREE FIRE']['is_primary']): ?>
                                <span class="primary-badge">Primary</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="game-overlay">
                        <span class="select-text"><?php echo isset($user_games['FREE FIRE']) ? 'Update Profile' : 'Add Profile'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="game-card <?php echo isset($user_games['COD']) ? 'configured' : ''; ?>" data-game="COD" data-username-pattern="3-20 characters" data-uid-pattern="6-8 digits">
                <div class="game-card-inner">
                    <div class="game-image">
                        <img src="../../assets/images/games/cod.png" alt="COD">
                    </div>
                    <h3>Call of Duty</h3>
                    <?php if (isset($user_games['COD'])): ?>
                        <div class="game-info">
                            <p class="game-username"><?php echo htmlspecialchars($user_games['COD']['game_username']); ?></p>
                            <p class="game-uid">UID: <?php echo htmlspecialchars($user_games['COD']['game_uid']); ?></p>
                            <?php if ($user_games['COD']['is_primary']): ?>
                                <span class="primary-badge">Primary</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="game-overlay">
                        <span class="select-text"><?php echo isset($user_games['COD']) ? 'Update Profile' : 'Add Profile'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <form class="game-details-form" method="POST" id="gameProfileForm">
            <input type="hidden" name="game_name" id="selected_game" value="">
            
            <div class="form-group">
                <label for="game_username">
                    <span class="label-text">In-Game Username</span>
                    <span class="format-hint" id="username_pattern">Select a game first</span>
                </label>
                <div class="input-wrapper">
                    <input type="text" id="game_username" name="game_username" 
                        placeholder="Enter your in-game username" 
                        required>
                    <div class="character-count">
                        <span id="username_count">0</span>/<span id="username_max">16</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="game_uid">
                    <span class="label-text">Game UID</span>
                    <span class="format-hint" id="uid_pattern">Select a game first</span>
                </label>
                <div class="input-wrapper">
                    <input type="text" id="game_uid" name="game_uid" 
                        placeholder="Enter your game UID (numbers only)" 
                        pattern="\d*"
                        required>
                    <div class="character-count">
                        <span id="uid_count">0</span>/<span id="uid_max">10</span>
                    </div>
                </div>
            </div>

            <div class="form-group checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_primary" id="is_primary">
                    <span class="checkbox-text">Set as primary game</span>
                </label>
            </div>
            
            <button type="submit" class="submit-btn">
                <span class="btn-text" id="submit_text">Add Game Profile</span>
                <span class="btn-icon">→</span>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gameCards = document.querySelectorAll('.game-card');
    const form = document.querySelector('.game-details-form');
    const selectedGameInput = document.getElementById('selected_game');
    const usernamePattern = document.getElementById('username_pattern');
    const uidPattern = document.getElementById('uid_pattern');
    const gameUidInput = document.getElementById('game_uid');
    const gameUsernameInput = document.getElementById('game_username');
    const usernameCount = document.getElementById('username_count');
    const usernameMax = document.getElementById('username_max');
    const uidCount = document.getElementById('uid_count');
    const uidMax = document.getElementById('uid_max');
    const submitText = document.getElementById('submit_text');
    const isPrimaryCheckbox = document.getElementById('is_primary');

    // Store game profiles data
    const gameProfiles = <?php echo json_encode($user_games); ?>;

    // Only allow numbers in UID field
    gameUidInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
        uidCount.textContent = this.value.length;
    });

    // Update username character count
    gameUsernameInput.addEventListener('input', function(e) {
        usernameCount.textContent = this.value.length;
    });

    gameCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            gameCards.forEach(c => c.classList.remove('selected'));
            
            // Add selected class to clicked card
            this.classList.add('selected');
            
            // Show form with animation
            form.classList.add('active');
            
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
                isPrimaryCheckbox.checked = parseInt(gameProfiles[game].is_primary) === 1;
                submitText.textContent = 'Update Game Profile';
                usernameCount.textContent = gameProfiles[game].game_username.length;
                uidCount.textContent = gameProfiles[game].game_uid.length;
            } else {
                gameUidInput.value = '';
                gameUsernameInput.value = '';
                isPrimaryCheckbox.checked = false;
                submitText.textContent = 'Add Game Profile';
                uidCount.textContent = '0';
                usernameCount.textContent = '0';
            }
        });
    });

    // Form validation before submit
    form.addEventListener('submit', function(e) {
        const game = selectedGameInput.value;
        const uid = gameUidInput.value;
        const username = gameUsernameInput.value;
        let isValid = true;
        let errorMessage = '';

        switch(game) {
            case 'PUBG':
            case 'BGMI':
                if (uid.length < 8 || uid.length > 10) {
                    errorMessage = 'BGMI/PUBG UID must be between 8 and 10 digits';
                    isValid = false;
                }
                if (username.length < 2 || username.length > 16) {
                    errorMessage = 'BGMI/PUBG username must be between 2 and 16 characters';
                    isValid = false;
                }
                break;
            case 'FREE FIRE':
                if (uid.length < 7 || uid.length > 9) {
                    errorMessage = 'Free Fire UID must be between 7 and 9 digits';
                    isValid = false;
                }
                if (username.length < 1 || username.length > 12) {
                    errorMessage = 'Free Fire username must be between 1 and 12 characters';
                    isValid = false;
                }
                break;
            case 'COD':
                if (uid.length < 6 || uid.length > 8) {
                    errorMessage = 'COD UID must be between 6 and 8 digits';
                    isValid = false;
                }
                if (username.length < 3 || username.length > 20) {
                    errorMessage = 'COD username must be between 3 and 20 characters';
                    isValid = false;
                }
                break;
        }

        if (!isValid) {
            e.preventDefault();
            alert(errorMessage);
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?> 