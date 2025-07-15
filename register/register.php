<?php
session_start();
require_once '../includes/user-auth.php';

// Get database connection
$conn = getDbConnection();

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if phone is verified
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_SESSION['phone_verified'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Phone number not verified']);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_registration'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $main_game = trim($_POST['main_game'] ?? '');
    $phone = trim($_POST['full_phone'] ?? '');
    
    // Validate inputs
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($main_game)) $errors[] = "Main game selection is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    
    if (empty($errors)) {
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("Email already exists");
            }
            
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("Username already exists");
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $sql = "INSERT INTO users (username, email, password, role, phone) VALUES (:username, :email, :password, 'user', :phone)";
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
                
                // Commit transaction
                $conn->commit();
                
                // Log the user in
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'user';
                
                // Clear phone verification
                unset($_SESSION['phone_verified']);
                
                // Redirect to home page
                header("Location: ../index.php");
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

            <!-- Step 3: Phone & OTP -->
            <div class="form-step" data-step="3">
                <h2>Phone Verification</h2>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                    <input type="hidden" id="full_phone" name="full_phone">
                    <div class="error-message"></div>
                    <div class="success-message" style="display: none;"></div>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-send-otp" id="sendOtp">Send OTP</button>
                    <div class="spinner"></div>
                </div>
                <div class="form-group otp-section" style="display: none;">
                    <label>Enter OTP</label>
                    <div class="otp-input-group">
                        <input type="text" class="otp-input" maxlength="1" data-index="1">
                        <input type="text" class="otp-input" maxlength="1" data-index="2">
                        <input type="text" class="otp-input" maxlength="1" data-index="3">
                        <input type="text" class="otp-input" maxlength="1" data-index="4">
                    </div>
                    <div class="error-message"></div>
                    <div class="resend-timer" style="display: none;">
                        Resend OTP in <span class="timer">300</span> seconds
                    </div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-prev">Previous</button>
                    <button type="button" class="btn btn-next" id="verifyOtp" disabled>Verify & Continue</button>
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
                <div class="form-group">
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
            let otpTimer;
            let isPhoneVerified = false;
            
            // Initialize phone input with improved options
            const phoneInput = document.querySelector("#phone");
            const fullPhoneInput = document.querySelector("#full_phone");
            const iti = window.intlTelInput(phoneInput, {
                utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
                separateDialCode: true,
                initialCountry: "auto",
                geoIpLookup: function(callback) {
                    fetch("https://ipapi.co/json")
                    .then(res => res.json())
                    .then(data => callback(data.country_code))
                    .catch(() => callback("in")); // Default to India if geolocation fails
                },
                // Enable search and dropdown
                dropdownContainer: document.body,
                formatOnDisplay: true,
                autoPlaceholder: "aggressive",
                preferredCountries: ['in', 'us', 'gb', 'ca', 'au'],
                localizedCountries: { 'in': 'India', 'us': 'United States', 'gb': 'United Kingdom' },
                customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
                    return "Enter " + selectedCountryPlaceholder;
                }
            });

            // Add custom styling to the country dropdown
            const countryList = document.querySelector('.iti__country-list');
            if (countryList) {
                countryList.style.maxHeight = '300px';
                countryList.style.overflowY = 'auto';
            }

            // AJAX validation for username
            const usernameInput = document.getElementById('username');
            let usernameTimeout;
            
            usernameInput.addEventListener('input', function() {
                clearTimeout(usernameTimeout);
                usernameTimeout = setTimeout(() => {
                    if (this.value.length >= 3) {
                        validateUsername(this.value);
                    }
                }, 500);
            });

            function validateUsername(username) {
                fetch('ajax_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=check_username&username=${encodeURIComponent(username)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        showError(usernameInput, 'Username already exists');
                    } else {
                        hideError(usernameInput);
                    }
                });
            }

            // AJAX validation for email
            const emailInput = document.getElementById('email');
            let emailTimeout;
            
            emailInput.addEventListener('input', function() {
                clearTimeout(emailTimeout);
                emailTimeout = setTimeout(() => {
                    if (isValidEmail(this.value)) {
                        validateEmail(this.value);
                    }
                }, 500);
            });

            function validateEmail(email) {
                fetch('ajax_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=check_email&email=${encodeURIComponent(email)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        showError(emailInput, 'Email already exists');
                    } else {
                        hideError(emailInput);
                    }
                });
            }

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

            // OTP handling
            const sendOtpBtn = document.getElementById('sendOtp');
            const verifyOtpBtn = document.getElementById('verifyOtp');
            const otpSection = document.querySelector('.otp-section');
            const otpInputs = document.querySelectorAll('.otp-input');
            const resendTimer = document.querySelector('.resend-timer');
            const timerSpan = document.querySelector('.timer');
            
            sendOtpBtn.addEventListener('click', function() {
                if (!iti.isValidNumber()) {
                    showError(phoneInput, 'Please enter a valid phone number');
                    return;
                }

                const spinner = document.querySelector('.spinner');
                const successMessage = phoneInput.parentElement.querySelector('.success-message');
                spinner.style.display = 'inline-block';
                sendOtpBtn.disabled = true;

                fetch('ajax_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_otp&phone=${encodeURIComponent(iti.getNumber())}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        otpSection.style.display = 'block';
                        startResendTimer();
                        
                        // Show development mode message
                        if (data.is_dev) {
                            successMessage.textContent = data.message;
                            successMessage.style.display = 'block';
                            // Auto-fill OTP for development
                            const otp = data.debug_otp.split('');
                            otpInputs.forEach((input, index) => {
                                input.value = otp[index];
                            });
                            verifyOtp(); // Auto-verify in dev mode
                        }
                    } else {
                        showError(phoneInput, data.error);
                    }
                })
                .finally(() => {
                    spinner.style.display = 'none';
                    sendOtpBtn.disabled = false;
                });
            });

            function startResendTimer() {
                let timeLeft = 300; // 5 minutes
                resendTimer.style.display = 'block';
                sendOtpBtn.disabled = true;

                clearInterval(otpTimer);
                otpTimer = setInterval(() => {
                    timeLeft--;
                    timerSpan.textContent = timeLeft;

                    if (timeLeft <= 0) {
                        clearInterval(otpTimer);
                        resendTimer.style.display = 'none';
                        sendOtpBtn.disabled = false;
                    }
                }, 1000);
            }

            // OTP input handling
            otpInputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value) {
                        const next = document.querySelector(`.otp-input[data-index="${parseInt(this.dataset.index) + 1}"]`);
                        if (next) next.focus();
                    }

                    // Check if all OTP inputs are filled
                    const allFilled = Array.from(otpInputs).every(input => input.value.length === 1);
                    if (allFilled) {
                        verifyOtp();
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value) {
                        const prev = document.querySelector(`.otp-input[data-index="${parseInt(this.dataset.index) - 1}"]`);
                        if (prev) prev.focus();
                    }
                });
            });

            function verifyOtp() {
                const otp = Array.from(otpInputs).map(input => input.value).join('');
                const phone = iti.getNumber();

                fetch('ajax_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_otp&otp=${encodeURIComponent(otp)}&phone=${encodeURIComponent(phone)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        isPhoneVerified = true;
                        verifyOtpBtn.disabled = false;
                        fullPhoneInput.value = phone;
                        hideError(document.querySelector('.otp-section'));
                    } else {
                        showError(document.querySelector('.otp-section'), data.error);
                        if (data.attempts_left) {
                            showError(document.querySelector('.otp-section'), 
                                `Invalid OTP. ${data.attempts_left} attempts remaining`);
                        }
                    }
                });
            }

            // Navigation between steps
            document.querySelectorAll('.btn-next').forEach(button => {
                button.addEventListener('click', () => {
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
                const inputs = currentStepElement.querySelectorAll('input[required]');
                let isValid = true;

                inputs.forEach(input => {
                    if (!input.value) {
                        showError(input, 'This field is required');
                        isValid = false;
                    } else {
                        hideError(input);
                        
                        // Additional validation based on input type
                        switch(input.id) {
                            case 'email':
                                if (!isValidEmail(input.value)) {
                                    showError(input, 'Please enter a valid email address');
                                    isValid = false;
                                }
                                break;
                            case 'password':
                                if (input.value.length < 8) {
                                    showError(input, 'Password must be at least 8 characters long');
                                    isValid = false;
                                }
                                break;
                            case 'confirm_password':
                                if (input.value !== passwordInput.value) {
                                    showError(input, 'Passwords do not match');
                                    isValid = false;
                                }
                                break;
                            case 'phone':
                                if (!iti.isValidNumber()) {
                                    showError(input, 'Please enter a valid phone number');
                                    isValid = false;
                                }
                                break;
                        }
                    }
                });

                // Additional step-specific validation
                if (step === 2 && !gameInput.value) {
                    showError(gameInput, 'Please select your main game');
                    isValid = false;
                }

                if (step === 3 && !isPhoneVerified) {
                    showError(phoneInput, 'Please verify your phone number');
                    isValid = false;
                }

                return isValid;
            }

            function showError(input, message) {
                const errorElement = input.parentElement.querySelector('.error-message');
                errorElement.textContent = message;
                errorElement.style.display = 'block';
                input.parentElement.classList.add('error');
            }

            function hideError(input) {
                const errorElement = input.parentElement.querySelector('.error-message');
                errorElement.style.display = 'none';
                input.parentElement.classList.remove('error');
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
        });
    </script>
</body>
</html> 