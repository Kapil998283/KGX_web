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

$error = '';
$success = '';
$username = '';
$email = '';
$main_game = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['full_phone'] ?? ''); // Using the full phone number with country code
    $main_game = trim($_POST['main_game'] ?? ''); // Add main game field
    
    // Enhanced validation with specific error messages
    if (empty($username)) {
        $error = "Username is required";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long";
    } elseif (strlen($username) > 20) {
        $error = "Username cannot exceed 20 characters";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Username can only contain letters, numbers, and underscores";
    } elseif (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (empty($password)) {
        $error = "Password is required";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (empty($phone)) {
        $error = "Phone number is required";
    } elseif (!preg_match("/^\+[1-9]\d{6,14}$/", $phone)) {
        $error = "Please enter a valid phone number with country code";
    } elseif (empty($main_game)) {
        $error = "Please select your main game";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Email already exists";
        } else {
            // Check if username already exists
            $sql = "SELECT id FROM users WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = "Username already exists";
            } else {
                // Check if phone exists
                $sql = "SELECT id FROM users WHERE phone = :phone";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(":phone", $phone);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = "Phone number already registered";
                } else {
                    // Start transaction
                    $conn->beginTransaction();
                    
                    try {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new user with additional fields
                        $sql = "INSERT INTO users (username, email, password, role, phone, created_at) VALUES (:username, :email, :password, 'user', :phone, NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(":username", $username);
                        $stmt->bindParam(":email", $email);
                        $stmt->bindParam(":password", $hashed_password);
                        $stmt->bindParam(":phone", $phone);
                        
                        if ($stmt->execute()) {
                            $user_id = $conn->lastInsertId();
                            
                            // Add user's main game
                            $sql = "INSERT INTO user_games (user_id, game_name, is_primary) VALUES (:user_id, :game_name, 1)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bindParam(":user_id", $user_id);
                            $stmt->bindParam(":game_name", $main_game);
                            $stmt->execute();
                            
                            // Give new user 100 coins as welcome bonus
                            $sql = "INSERT INTO user_coins (user_id, coins) VALUES (:user_id, 100)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bindParam(":user_id", $user_id);
                            $stmt->execute();
                            
                            // Give new user 1 ticket as welcome bonus
                            $sql = "INSERT INTO user_tickets (user_id, tickets) VALUES (:user_id, 1)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bindParam(":user_id", $user_id);
                            $stmt->execute();
                            
                            // Commit transaction
                            $conn->commit();
                            
                            // Set success message and redirect
                            $_SESSION['registration_success'] = "Welcome to KGX Esports! You've received 100 coins and 1 ticket as a welcome bonus.";
                            
                            // Auto login the user
                            $_SESSION['user_id'] = $user_id;
                            $_SESSION['username'] = $username;
                            $_SESSION['email'] = $email;
                            $_SESSION['role'] = 'user';
                            
                            header("Location: ../home.php?welcome=1");
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
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join KGX Esports - Create Your Gaming Account</title>
    
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
    .auth-container {
        max-width: 500px;
        margin: 2rem auto;
        padding: 2rem;
        background: rgba(0, 0, 0, 0.8);
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(0, 255, 0, 0.1);
    }
    .auth-title {
        color: #fff;
        text-align: center;
        margin-bottom: 2rem;
        font-size: 2rem;
        text-transform: uppercase;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        color: #fff;
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid #333;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        border-color: #00ff00;
        box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
    }
    .password-toggle {
        position: relative;
    }
    .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #fff;
    }
    .game-options {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 0.5rem;
    }
    .game-option {
        border: 2px solid #333;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .game-option:hover {
        border-color: #00ff00;
        transform: translateY(-2px);
    }
    .game-option.selected {
        border-color: #00ff00;
        background: rgba(0, 255, 0, 0.1);
    }
    .game-option img {
        width: 100%;
        max-width: 80px;
        height: auto;
        margin-bottom: 0.5rem;
    }
    .error-message {
        background: rgba(255, 0, 0, 0.1);
        border: 1px solid #ff3333;
        color: #ff3333;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .success-message {
        background: rgba(0, 255, 0, 0.1);
        border: 1px solid #00ff00;
        color: #00ff00;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .auth-btn {
        width: 100%;
        padding: 1rem;
        background: #00ff00;
        color: #000;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .auth-btn:hover {
        background: #00cc00;
        transform: translateY(-2px);
    }
    .auth-links {
        text-align: center;
        margin-top: 1.5rem;
    }
    .auth-links a {
        color: #00ff00;
        text-decoration: none;
    }
    .auth-links a:hover {
        text-decoration: underline;
    }
    .password-strength {
        height: 4px;
        background: #333;
        margin-top: 0.5rem;
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
    .phone-hint, .password-hint {
        color: #999;
        font-size: 0.85rem;
        margin-top: 0.5rem;
    }
    .welcome-bonus {
        background: rgba(0, 255, 0, 0.1);
        border: 1px solid #00ff00;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    .welcome-bonus h3 {
        color: #00ff00;
        margin-bottom: 0.5rem;
    }
    .welcome-bonus p {
        color: #fff;
        margin: 0;
    }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 class="auth-title">Join KGX Esports</h1>
        
        <div class="welcome-bonus">
            <h3>Welcome Bonus!</h3>
            <p>Get 100 coins and 1 ticket when you join</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form class="auth-form" method="POST" action="" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       value="<?php echo htmlspecialchars($username); ?>"
                       pattern="[a-zA-Z0-9_]+" 
                       minlength="3" 
                       maxlength="20"
                       title="Username can only contain letters, numbers, and underscores">
                <small class="phone-hint">3-20 characters, letters, numbers, and underscores only</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required 
                       value="<?php echo htmlspecialchars($email); ?>">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-control" required>
                <input type="hidden" id="full_phone" name="full_phone" value="<?php echo htmlspecialchars($phone); ?>">
                <small class="phone-hint">Select country code and enter your phone number</small>
                <div id="phone-error" class="error-message" style="display: none;"></div>
            </div>
            
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
                    <input type="password" id="password" name="password" class="form-control" required>
                    <ion-icon name="eye-outline" class="toggle-password"></ion-icon>
                </div>
                <div class="password-strength">
                    <div class="strength-meter"></div>
                </div>
                <small class="password-hint">Minimum 8 characters with uppercase, lowercase, and numbers</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-toggle">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    <ion-icon name="eye-outline" class="toggle-password"></ion-icon>
                </div>
            </div>
            
            <button type="submit" class="auth-btn">Create Account</button>
        </form>
        
        <div class="auth-links">
            <p>Already have an account? <a href="../pages/login.php">Sign in</a></p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize phone input
            const phoneInput = document.querySelector("#phone");
            const phoneError = document.querySelector("#phone-error");
            const fullPhoneInput = document.querySelector("#full_phone");
            
            const iti = window.intlTelInput(phoneInput, {
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
                separateDialCode: true,
                initialCountry: "auto",
                geoIpLookup: function(callback) {
                    fetch("https://ipapi.co/json")
                    .then(function(res) { return res.json(); })
                    .then(function(data) { callback(data.country_code); })
                    .catch(function() { callback("in"); });
                },
                preferredCountries: ["in", "us", "gb"],
                nationalMode: true,
                formatOnDisplay: true,
                autoPlaceholder: "polite"
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
                const hasNumbers = /[0-9]/.test(password);
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

            // Real-time validation
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const confirmPasswordInput = document.getElementById('confirm_password');

            usernameInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            });

            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });

            // Form submission
            document.querySelector('.auth-form').addEventListener('submit', function(e) {
                const phoneNumber = phoneInput.value.trim();
                if (phoneNumber && !iti.isValidNumber()) {
                    e.preventDefault();
                    phoneError.style.display = 'block';
                    phoneError.textContent = 'Please enter a valid phone number';
                    return;
                }
                fullPhoneInput.value = iti.getNumber();
            });

            // Show password requirements on focus
            passwordInput.addEventListener('focus', function() {
                document.querySelector('.password-hint').style.color = '#00ff00';
            });

            passwordInput.addEventListener('blur', function() {
                document.querySelector('.password-hint').style.color = '#999';
            });
        });
    </script>
</body>
</html> 