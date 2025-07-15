<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/user-auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$errors = [];
$debug_log = [];
$username = '';
$email = '';
$main_game = '';
$phone = '';

// Get database connection
try {
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    $debug_log[] = "Database connection successful";
} catch (Exception $e) {
    $errors[] = "Database Error: " . $e->getMessage();
    $debug_log[] = "Database Error: " . $e->getMessage();
}

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_registration'])) {
    $debug_log[] = "Form submitted";
    
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $main_game = trim($_POST['main_game'] ?? '');
    $phone = trim($_POST['full_phone'] ?? '');
    $auto_login = isset($_POST['auto_login']) ? true : false;
    
    $debug_log[] = "Form data received: Username: $username, Email: $email, Game: $main_game, Phone: $phone";
    
    // Validate inputs
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($confirm_password)) $errors[] = "Password confirmation is required";
    if (empty($main_game)) $errors[] = "Main game selection is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (!isset($_POST['terms'])) $errors[] = "You must agree to the Terms & Conditions";

    // Additional validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    if (!preg_match("/^\+[1-9]\d{6,14}$/", $phone)) {
        $errors[] = "Please enter a valid phone number with country code";
    }
    
    $debug_log[] = empty($errors) ? "Validation passed" : "Validation errors: " . implode(", ", $errors);
    
    if (empty($errors)) {
        // Start transaction
        try {
            $conn->beginTransaction();
            $debug_log[] = "Transaction started";
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Email already exists");
            }
            
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Username already exists");
            }

            // Check if phone exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Phone number already exists");
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, phone, created_at) VALUES (?, ?, ?, 'user', ?, NOW())");
            if (!$stmt->execute([$username, $email, $hashed_password, $phone])) {
                throw new Exception("Failed to create user account");
            }
            
            $user_id = $conn->lastInsertId();
            if (!$user_id) {
                throw new Exception("Failed to get new user ID");
            }
            
            // Add user's main game
            $stmt = $conn->prepare("INSERT INTO user_games (user_id, game_name, is_primary) VALUES (?, ?, 1)");
            if (!$stmt->execute([$user_id, $main_game])) {
                throw new Exception("Failed to set main game");
            }
            
            // Give new user 100 coins
            $stmt = $conn->prepare("INSERT INTO user_coins (user_id, coins) VALUES (?, 100)");
            if (!$stmt->execute([$user_id])) {
                throw new Exception("Failed to set initial coins");
            }
            
            // Give new user 1 ticket
            $stmt = $conn->prepare("INSERT INTO user_tickets (user_id, tickets) VALUES (?, 1)");
            if (!$stmt->execute([$user_id])) {
                throw new Exception("Failed to set initial tickets");
            }
            
            // Commit transaction
            $conn->commit();
            $debug_log[] = "Transaction committed successfully";
            
            if ($auto_login) {
                // Log the user in automatically
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'user';
                
                $debug_log[] = "Auto login successful";
                header("Location: ../home.php");
                exit();
            } else {
                // Redirect to login page with success message
                $_SESSION['registration_success'] = "Registration successful! Please login to continue.";
                $debug_log[] = "Registration successful, redirecting to login";
                header("Location: ../pages/login.php");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $debug_log[] = "Error occurred: " . $e->getMessage();
            $debug_log[] = "Transaction rolled back";
            $errors[] = $e->getMessage();
        }
    }
}

// For debugging - add this at the bottom of the page
if (!empty($debug_log)) {
    echo "<!-- Debug Log:\n";
    echo implode("\n", $debug_log);
    echo "\n-->";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - KGX Esports</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../favicon.svg" type="image/svg+xml">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    
    <!-- International Telephone Input CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Ion Icons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
    .iti {
        width: 100%;
    }
    .iti__flag {
        background-image: url("https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/img/flags.png");
    }
    @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
        .iti__flag {
            background-image: url("https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/img/flags@2x.png");
        }
    }
    .checkbox-group {
        margin: 20px 0;
    }
    .checkbox-wrapper {
        display: flex;
        align-items: center;
        margin: 10px 0;
    }
    .checkbox-wrapper input[type="checkbox"] {
        margin-right: 10px;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .checkbox-wrapper label {
        font-size: 14px;
        cursor: pointer;
        color: #fff;
    }
    .error-message {
        color: #ff3333;
        font-size: 14px;
        margin-top: 5px;
    }
    .php-errors {
        background: rgba(255, 51, 51, 0.1);
        border: 1px solid #ff3333;
        padding: 10px;
        margin-bottom: 20px;
        color: #ff3333;
        border-radius: 4px;
    }
    .game-options {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-top: 10px;
    }
    .game-option {
        border: 2px solid #333;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .game-option.selected {
        border-color: #00ff00;
        background: rgba(0, 255, 0, 0.1);
    }
    .game-option img {
        width: 100%;
        max-width: 100px;
        height: auto;
        margin-bottom: 10px;
    }
    .game-option span {
        display: block;
        color: #fff;
    }
    .password-strength {
        height: 4px;
        background: #333;
        margin-top: 5px;
        border-radius: 2px;
        overflow: hidden;
    }
    .strength-meter {
        height: 100%;
        width: 0;
        transition: all 0.3s ease;
    }
    .strength-meter.weak { width: 33%; background: #ff3333; }
    .strength-meter.medium { width: 66%; background: #ffa500; }
    .strength-meter.strong { width: 100%; background: #00ff00; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 class="auth-title">Create Account</h1>
        
        <?php if(!empty($errors)): ?>
            <div class="php-errors">
                <?php foreach($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form class="auth-form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <!-- Step 1: Username & Email -->
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            
            <!-- Step 2: Game & Password -->
            <div class="form-group">
                <label>Select Your Main Game</label>
                <div class="game-options">
                    <div class="game-option <?php echo $main_game === 'PUBG' ? 'selected' : ''; ?>" data-game="PUBG">
                        <img src="../assets/images/games/pubg.png" alt="PUBG">
                        <span>PUBG</span>
                    </div>
                    <div class="game-option <?php echo $main_game === 'BGMI' ? 'selected' : ''; ?>" data-game="BGMI">
                        <img src="../assets/images/games/bgmi.png" alt="BGMI">
                        <span>BGMI</span>
                    </div>
                    <div class="game-option <?php echo $main_game === 'FREE FIRE' ? 'selected' : ''; ?>" data-game="FREE FIRE">
                        <img src="../assets/images/games/freefire.png" alt="Free Fire">
                        <span>Free Fire</span>
                    </div>
                    <div class="game-option <?php echo $main_game === 'COD' ? 'selected' : ''; ?>" data-game="COD">
                        <img src="../assets/images/games/cod.jpg" alt="Call of Duty Mobile">
                        <span>COD Mobile</span>
                    </div>
                </div>
                <input type="hidden" id="main_game" name="main_game" required value="<?php echo htmlspecialchars($main_game); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required>
                    <ion-icon name="eye-outline" class="toggle-password"></ion-icon>
                </div>
                <div class="password-strength">
                    <div class="strength-meter"></div>
                </div>
                <small class="password-hint">Password must be at least 8 characters long</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-toggle">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <ion-icon name="eye-outline" class="toggle-password"></ion-icon>
                </div>
            </div>
            
            <!-- Step 3: Phone -->
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required>
                <input type="hidden" id="full_phone" name="full_phone" value="<?php echo htmlspecialchars($phone); ?>">
                <small class="phone-hint">Select country code and enter your phone number</small>
            </div>
            
            <!-- Step 4: Terms & Submit -->
            <div class="checkbox-group">
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the Terms & Conditions and Privacy Policy</label>
                </div>
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="auto_login" name="auto_login" checked>
                    <label for="auto_login">Sign me in automatically after registration</label>
                </div>
            </div>
            
            <button type="submit" name="submit_registration" class="auth-btn">Register</button>
        </form>
        
        <div class="auth-links">
            <p>Already have an account? <a href="../pages/login.php">Sign in</a></p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize phone input
            const phoneInput = document.querySelector("#phone");
            const fullPhoneInput = document.querySelector("#full_phone");
            const iti = window.intlTelInput(phoneInput, {
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
                separateDialCode: true,
                initialCountry: "in",
                preferredCountries: ['in', 'us', 'gb'],
                formatOnDisplay: true
            });

            // Set initial phone value if exists
            if (fullPhoneInput.value) {
                iti.setNumber(fullPhoneInput.value);
            }

            // Game selection
            const gameOptions = document.querySelectorAll('.game-option');
            const gameInput = document.getElementById('main_game');
            
            gameOptions.forEach(option => {
                option.addEventListener('click', function() {
                    gameOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    gameInput.value = this.dataset.game;
                });
            });

            // Password strength checker
            const passwordInput = document.getElementById('password');
            const strengthMeter = document.querySelector('.strength-meter');
            
            passwordInput.addEventListener('input', function() {
                const strength = checkPasswordStrength(this.value);
                updateStrengthMeter(strength);
            });

            function checkPasswordStrength(password) {
                if (password.length < 8) return 'weak';
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                const strength = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecial]
                    .filter(Boolean).length;
                
                if (strength < 2) return 'weak';
                if (strength < 4) return 'medium';
                return 'strong';
            }

            function updateStrengthMeter(strength) {
                strengthMeter.className = 'strength-meter ' + strength;
            }

            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const passwordInput = this.parentElement.querySelector('input');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.name = type === 'password' ? 'eye-outline' : 'eye-off-outline';
                });
            });

            // Form submission
            document.querySelector('.auth-form').addEventListener('submit', function(e) {
                const phoneNumber = phoneInput.value.trim();
                if (phoneNumber && !iti.isValidNumber()) {
                    e.preventDefault();
                    alert('Please enter a valid phone number');
                    return;
                }
                fullPhoneInput.value = iti.getNumber();
            });
        });
    </script>
</body>
</html> 