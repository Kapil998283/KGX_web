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

// Initialize session variables for multi-step form
if (!isset($_SESSION['registration_step'])) {
    $_SESSION['registration_step'] = 1;
    $_SESSION['registration_data'] = [];
}

$error = '';
$success = '';

// Handle form submission for Step 1
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['step']) && $_POST['step'] == '1') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
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
                // Store data in session and proceed to next step
                $_SESSION['registration_data']['username'] = $username;
                $_SESSION['registration_data']['email'] = $email;
                $_SESSION['registration_data']['password'] = password_hash($password, PASSWORD_DEFAULT);
                $_SESSION['registration_step'] = 2;
                
                header("Location: phone-verification.php");
                exit();
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
    <title>Create Account - Step 1 | KGX Gaming</title>
    
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
        <div class="auth-container multi-step">
            <!-- Progress Indicator -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 33.33%"></div>
                </div>
                <div class="step-indicators">
                    <div class="step-indicator active">
                        <div class="step-number">1</div>
                        <div class="step-label">Account Info</div>
                    </div>
                    <div class="step-indicator">
                        <div class="step-number">2</div>
                        <div class="step-label">Phone Verify</div>
                    </div>
                    <div class="step-indicator">
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
                <h2 class="auth-title">Create Your Account</h2>
                <p class="auth-subtitle">Join the ultimate gaming community</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" method="POST" action="">
                <input type="hidden" name="step" value="1">
                
                <div class="form-group">
                    <label for="username">
                        <ion-icon name="person-outline"></ion-icon>
                        Username
                    </label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_SESSION['registration_data']['username'] ?? ''); ?>"
                           placeholder="Enter your gaming username">
                    <div class="input-hint">This will be your display name in tournaments</div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <ion-icon name="mail-outline"></ion-icon>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_SESSION['registration_data']['email'] ?? ''); ?>"
                           placeholder="Enter your email address">
                    <div class="input-hint">We'll use this to send you tournament updates</div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <ion-icon name="lock-closed-outline"></ion-icon>
                        Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required
                               placeholder="Create a strong password">
                        <button type="button" class="password-toggle" data-target="password">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter">
                            <div class="strength-fill"></div>
                        </div>
                        <div class="strength-text">Password Strength</div>
                    </div>
                    <div class="input-hint">At least 8 characters with numbers and letters</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        <ion-icon name="lock-closed-outline"></ion-icon>
                        Confirm Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="confirm_password" name="confirm_password" required
                               placeholder="Confirm your password">
                        <button type="button" class="password-toggle" data-target="confirm_password">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                    </div>
                    <div class="password-match">
                        <ion-icon name="checkmark-circle-outline" class="match-icon"></ion-icon>
                        <span class="match-text">Passwords match</span>
                    </div>
                </div>
                
                <button type="submit" class="auth-btn primary-btn">
                    <span>Continue to Phone Verification</span>
                    <ion-icon name="arrow-forward-outline"></ion-icon>
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="login.php" class="auth-link">Sign In</a></p>
                <div class="social-login">
                    <p class="social-title">Or continue with</p>
                    <div class="social-buttons">
                        <button class="social-btn google-btn">
                            <ion-icon name="logo-google"></ion-icon>
                        </button>
                        <button class="social-btn discord-btn">
                            <ion-icon name="logo-discord"></ion-icon>
                        </button>
                        <button class="social-btn steam-btn">
                            <ion-icon name="game-controller-outline"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/multi-step-auth.js"></script>
</body>
</html>
