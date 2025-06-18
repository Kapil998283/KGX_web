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
    
    // Validate based on game
    $valid = true;
    $error = "";
    
    // Username validation
    switch($game_name) {
        case 'PUBG':
        case 'BGMI':
            if(strlen($game_username) < 2 || strlen($game_username) > 16) {
                $valid = false;
                $error = "Username must be 2-16 characters for PUBG/BGMI";
            }
            if(!preg_match('/^\d{8,10}$/', $game_uid)) {
                $valid = false;
                $error = "PUBG/BGMI UID must be 8-10 digits";
            }
            break;
        case 'FREE FIRE':
            if(strlen($game_username) < 1 || strlen($game_username) > 12) {
                $valid = false;
                $error = "Username must be 1-12 characters for Free Fire";
            }
            if(!preg_match('/^\d{7,9}$/', $game_uid)) {
                $valid = false;
                $error = "Free Fire UID must be 7-9 digits";
            }
            break;
        case 'COD':
            if(strlen($game_username) < 3 || strlen($game_username) > 20) {
                $valid = false;
                $error = "Username must be 3-20 characters for COD Mobile";
            }
            if(!preg_match('/^\d{6,8}$/', $game_uid)) {
                $valid = false;
                $error = "COD Mobile UID must be 6-8 digits";
            }
            break;
    }
    
    if($valid) {
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
            
            $_SESSION['success_message'] = "Game profile saved successfully! You can now participate in tournaments for " . $game_name;
            
            // Update user's session with game info
            $_SESSION['user_game'] = [
                'game_name' => $game_name,
                'game_username' => $game_username,
                'game_uid' => $game_uid
            ];
            
            // Redirect to dashboard with success message
            header("Location: dashboard.php?game_profile=success");
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry error
                $error = "This game UID is already registered by another user.";
            } else {
                $error = "An error occurred. Please try again.";
            }
        }
    }
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/game-profile.css">

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
            <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'PUBG' ? 'selected' : ''; ?>" data-game="PUBG" data-username-pattern="2-16 characters" data-uid-pattern="8-10 digits">
                <img src="../../assets/images/games/pubg.png" alt="PUBG">
                <h3>PUBG</h3>
            </div>
            
            <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'BGMI' ? 'selected' : ''; ?>" data-game="BGMI" data-username-pattern="2-16 characters" data-uid-pattern="8-10 digits">
                <img src="../../assets/images/games/bgmi.png" alt="BGMI">
                <h3>BGMI</h3>
            </div>
            
            <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'FREE FIRE' ? 'selected' : ''; ?>" data-game="FREE FIRE" data-username-pattern="1-12 characters" data-uid-pattern="7-9 digits">
                <img src="../../assets/images/games/freefire.png" alt="Free Fire">
                <h3>Free Fire</h3>
            </div>
            
            <div class="game-card <?php echo $existing_profile && $existing_profile['game_name'] === 'COD' ? 'selected' : ''; ?>" data-game="COD" data-username-pattern="3-20 characters" data-uid-pattern="6-8 digits">
                <img src="../../assets/images/games/cod.png" alt="COD">
                <h3>Call of Duty</h3>
            </div>
        </div>

        <form class="game-details-form <?php echo $existing_profile ? 'active' : ''; ?>" method="POST" id="gameProfileForm">
            <input type="hidden" name="game_name" id="selected_game" value="<?php echo $existing_profile ? $existing_profile['game_name'] : ''; ?>">
            
            <div class="form-group">
                <label for="game_username">In-Game Username</label>
                <input type="text" id="game_username" name="game_username" 
                    value="<?php echo $existing_profile ? $existing_profile['game_username'] : ''; ?>" 
                    placeholder="Enter your in-game username" 
                    required>
                <small class="help-text">Username format: <span id="username_pattern">Select a game first</span></small>
            </div>
            
            <div class="form-group">
                <label for="game_uid">Game UID</label>
                <input type="text" id="game_uid" name="game_uid" 
                    value="<?php echo $existing_profile ? $existing_profile['game_uid'] : ''; ?>" 
                    placeholder="Enter your game UID (numbers only)" 
                    pattern="\d*"
                    required>
                <small class="help-text">UID format: <span id="uid_pattern">Select a game first</span></small>
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
    const usernamePattern = document.getElementById('username_pattern');
    const uidPattern = document.getElementById('uid_pattern');
    const gameUidInput = document.getElementById('game_uid');
    
    // Only allow numbers in UID field
    gameUidInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
    });
    
    gameCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            gameCards.forEach(c => c.classList.remove('selected'));
            
            // Add selected class to clicked card
            this.classList.add('selected');
            
            // Show form
            form.classList.add('active');
            
            // Update hidden input and patterns
            selectedGameInput.value = this.dataset.game;
            usernamePattern.textContent = this.dataset.usernamePattern;
            uidPattern.textContent = this.dataset.uidPattern;
            
            // Update input patterns based on game
            switch(this.dataset.game) {
                case 'PUBG':
                case 'BGMI':
                    gameUidInput.pattern = '\\d{8,10}';
                    break;
                case 'FREE FIRE':
                    gameUidInput.pattern = '\\d{7,9}';
                    break;
                case 'COD':
                    gameUidInput.pattern = '\\d{6,8}';
                    break;
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?> 