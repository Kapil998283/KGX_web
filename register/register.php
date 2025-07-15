<?php
session_start();
require_once '../config/database.php';
require_once '../includes/user-auth.php';

// Get database connection
$db = new Database();
$conn = $db->connect();

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_registration'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $main_game = trim($_POST['main_game'] ?? '');
    $phone = trim($_POST['full_phone'] ?? '');
    $auto_login = isset($_POST['auto_login']) ? true : false;
    
    // Validate inputs
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($main_game)) $errors[] = "Main game selection is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (!isset($_POST['terms'])) $errors[] = "You must agree to the Terms & Conditions";

    // Check email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->beginTransaction();
        
        try {
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
            
            if ($auto_login) {
                // Log the user in automatically
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'user';
                
                // Redirect to home page
                header("Location: ../home.php");
                exit();
            } else {
                // Redirect to login page with success message
                $_SESSION['registration_success'] = "Registration successful! Please login to continue.";
                header("Location: ../pages/login.php");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = $e->getMessage();
        }
    }
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
    <link rel="stylesheet" href="../assets/css/register/multi-step.css">
    
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
    /* Add these styles to your existing CSS */
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

    .checkbox-wrapper a {
        color: #00ff00;
        text-decoration: none;
    }

    .checkbox-wrapper a:hover {
        text-decoration: underline;
    }
    </style>
</head>
<body>
    <div class="auth-container">
        <form id="registrationForm" class="multi-step-form" method="POST" action="">
            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-step active" data-step="1"></div>
                <div class="progress-step" data-step="2"></div>
                <div class="progress-step" data-step="3"></div>
                <div class="progress-step" data-step="4"></div>
            </div>

            <!-- Step 1: Username & Email -->
            <div class="form-step active" data-step="1">
                <h2>Create Your Account</h2>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                    <div class="error-message"></div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-next">Next</button>
                </div>
            </div>

            <!-- Step 2: Game & Password -->
            <div class="form-step" data-step="2">
                <h2>Game & Security</h2>
                <div class="form-group">
                    <label>Select Your Main Game</label>
                    <div class="game-options">
                        <div class="game-option" data-game="PUBG">
                            <img src="../assets/images/games/pubg.png" alt="PUBG">
                            <span>PUBG</span>
                        </div>
                        <div class="game-option" data-game="BGMI">
                            <img src="../assets/images/games/bgmi.png" alt="BGMI">
                            <span>BGMI</span>
                        </div>
                        <div class="game-option" data-game="FREE FIRE">
                            <img src="../assets/images/games/freefire.png" alt="Free Fire">
                            <span>Free Fire</span>
                        </div>
                        <div class="game-option" data-game="COD">
                            <img src="../assets/images/games/cod.jpg" alt="Call of Duty Mobile">
                            <span>COD Mobile</span>
                        </div>
                    </div>
                    <input type="hidden" id="main_game" name="main_game" required>
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-strength">
                        <div class="strength-meter"></div>
                    </div>
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <div class="error-message"></div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-prev">Previous</button>
                    <button type="button" class="btn btn-next">Next</button>
                </div>
            </div>

            <!-- Step 3: Phone -->
            <div class="form-step" data-step="3">
                <h2>Contact Information</h2>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                    <input type="hidden" id="full_phone" name="full_phone">
                    <div class="error-message"></div>
                    <div class="phone-hint">Enter your phone number with country code</div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-prev">Previous</button>
                    <button type="button" class="btn btn-next">Next</button>
                </div>
            </div>

            <!-- Step 4: Review & Submit -->
            <div class="form-step" data-step="4">
                <h2>Complete Registration</h2>
                <div class="review-details">
                    <p>Please review your information:</p>
                    <div class="review-item">
                        <strong>Username:</strong> <span id="review-username"></span>
                    </div>
                    <div class="review-item">
                        <strong>Email:</strong> <span id="review-email"></span>
                    </div>
                    <div class="review-item">
                        <strong>Main Game:</strong> <span id="review-game"></span>
                    </div>
                    <div class="review-item">
                        <strong>Phone:</strong> <span id="review-phone"></span>
                    </div>
                </div>
                <div class="form-group checkbox-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the Terms & Conditions and Privacy Policy</label>
                    </div>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="auto_login" name="auto_login" checked>
                        <label for="auto_login">Sign me in automatically after registration</label>
                    </div>
                    <div class="error-message"></div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-prev">Previous</button>
                    <button type="submit" name="submit_registration" class="btn btn-submit">Complete Registration</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize variables
            let currentStep = 1;
            const form = document.getElementById('registrationForm');
            const steps = document.querySelectorAll('.form-step');
            const progressSteps = document.querySelectorAll('.progress-step');
            
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

            // Game selection
            const gameOptions = document.querySelectorAll('.game-option');
            const gameInput = document.getElementById('main_game');
            
            gameOptions.forEach(option => {
                option.addEventListener('click', function() {
                    gameOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    gameInput.value = this.dataset.game;
                    hideError(gameInput);
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

            // Navigation between steps
            document.querySelectorAll('.btn-next').forEach(button => {
                button.addEventListener('click', function() {
                    if (validateStep(currentStep)) {
                        if (currentStep < 4) {
                            currentStep++;
                            updateStep();
                        }
                    }
                });
            });

            document.querySelectorAll('.btn-prev').forEach(button => {
                button.addEventListener('click', () => {
                    if (currentStep > 1) {
                        currentStep--;
                        updateStep();
                    }
                });
            });

            function updateStep() {
                steps.forEach(step => step.classList.remove('active'));
                progressSteps.forEach(step => step.classList.remove('active', 'completed'));
                
                document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active');
                
                for (let i = 1; i <= 4; i++) {
                    const step = document.querySelector(`.progress-step[data-step="${i}"]`);
                    if (i < currentStep) {
                        step.classList.add('completed');
                    } else if (i === currentStep) {
                        step.classList.add('active');
                    }
                }

                if (currentStep === 4) {
                    updateReviewDetails();
                }
            }

            function validateStep(step) {
                const currentStepElement = document.querySelector(`.form-step[data-step="${step}"]`);
                let isValid = true;

                // Step-specific validation
                switch(step) {
                    case 1:
                        // Username and Email validation
                        const username = document.getElementById('username').value;
                        const email = document.getElementById('email').value;
                        
                        if (!username) {
                            showError(document.getElementById('username'), 'Username is required');
                            isValid = false;
                        }
                        
                        if (!email) {
                            showError(document.getElementById('email'), 'Email is required');
                            isValid = false;
                        } else if (!isValidEmail(email)) {
                            showError(document.getElementById('email'), 'Please enter a valid email address');
                            isValid = false;
                        }
                        break;

                    case 2:
                        // Game and Password validation
                        const password = document.getElementById('password').value;
                        const confirmPassword = document.getElementById('confirm_password').value;
                        const game = document.getElementById('main_game').value;

                        if (!game) {
                            showError(gameInput, 'Please select your main game');
                            isValid = false;
                        }

                        if (!password) {
                            showError(document.getElementById('password'), 'Password is required');
                            isValid = false;
                        } else if (password.length < 8) {
                            showError(document.getElementById('password'), 'Password must be at least 8 characters long');
                            isValid = false;
                        }

                        if (!confirmPassword) {
                            showError(document.getElementById('confirm_password'), 'Please confirm your password');
                            isValid = false;
                        } else if (password !== confirmPassword) {
                            showError(document.getElementById('confirm_password'), 'Passwords do not match');
                            isValid = false;
                        }
                        break;

                    case 3:
                        // Phone validation
                        const phoneNumber = phoneInput.value.trim();
                        if (!phoneNumber) {
                            showError(phoneInput, 'Phone number is required');
                            isValid = false;
                        } else {
                            // Update the full phone number
                            const fullNumber = iti.getNumber();
                            if (!fullNumber) {
                                showError(phoneInput, 'Please enter a valid phone number');
                                isValid = false;
                            } else {
                                fullPhoneInput.value = fullNumber;
                                hideError(phoneInput);
                            }
                        }
                        break;

                    case 4:
                        // Terms validation
                        const termsCheckbox = document.getElementById('terms');
                        if (!termsCheckbox.checked) {
                            showError(termsCheckbox, 'You must agree to the Terms & Conditions');
                            isValid = false;
                        } else {
                            hideError(termsCheckbox);
                        }
                        break;
                }

                return isValid;
            }

            function showError(input, message) {
                const errorElement = input.parentElement.querySelector('.error-message');
                if (errorElement) {
                    errorElement.textContent = message;
                    errorElement.style.display = 'block';
                    input.parentElement.classList.add('error');
                }
            }

            function hideError(input) {
                const errorElement = input.parentElement.querySelector('.error-message');
                if (errorElement) {
                    errorElement.style.display = 'none';
                    input.parentElement.classList.remove('error');
                }
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function updateReviewDetails() {
                document.getElementById('review-username').textContent = document.getElementById('username').value;
                document.getElementById('review-email').textContent = document.getElementById('email').value;
                document.getElementById('review-game').textContent = document.getElementById('main_game').value;
                document.getElementById('review-phone').textContent = iti.getNumber();
            }

            // Form submission
            form.addEventListener('submit', function(e) {
                if (!validateStep(currentStep)) {
                    e.preventDefault();
                }
            });

            // Phone input event listeners
            phoneInput.addEventListener('input', function() {
                hideError(phoneInput);
            });

            phoneInput.addEventListener('blur', function() {
                if (phoneInput.value.trim()) {
                    if (!iti.isValidNumber()) {
                        showError(phoneInput, 'Please enter a valid phone number');
                    } else {
                        hideError(phoneInput);
                        fullPhoneInput.value = iti.getNumber();
                    }
                }
            });
        });
    </script>
</body>
</html> 