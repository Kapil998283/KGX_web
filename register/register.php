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
    <title>Register - KGX Esports</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        .field-feedback {
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .field-feedback.success {
            display: block;
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .field-feedback.error {
            display: block;
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s ease;
        }
        
        .input-wrapper input.validating {
            border-color: #ffd700;
        }
        
        .input-wrapper input.valid {
            border-color: #28a745;
        }
        
        .input-wrapper input.invalid {
            border-color: #dc3545;
        }
        
        .spinner {
            display: none;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h2>Create Account</h2>
                <p>Join KGX Esports Community</p>
            </div>
            
            <div class="welcome-bonus">
                <h3>Welcome Bonus!</h3>
                <p>Get 100 coins + 1 ticket on signup</p>
            </div>

            <form id="registerForm" method="POST" action="process_register.php">
                <div class="input-wrapper">
                    <input type="text" name="username" id="username" placeholder="Username" required>
                    <div class="spinner" id="usernameSpinner"></div>
                    <div class="field-feedback" id="usernameFeedback"></div>
                </div>

                <div class="input-wrapper">
                    <input type="email" name="email" id="email" placeholder="Email" required>
                    <div class="spinner" id="emailSpinner"></div>
                    <div class="field-feedback" id="emailFeedback"></div>
                </div>

                <div class="input-wrapper">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <div class="field-feedback" id="passwordFeedback"></div>
                </div>

                <div class="input-wrapper">
                    <input type="tel" name="phone" id="phone" placeholder="Phone Number" required>
                    <div class="field-feedback" id="phoneFeedback"></div>
                </div>

                <div class="game-selection">
                    <label>Select your main game:</label>
                    <select name="main_game" required>
                        <option value="pubg">PUBG</option>
                        <option value="bgmi">BGMI</option>
                        <option value="freefire">Free Fire</option>
                        <option value="cod">Call of Duty Mobile</option>
                    </select>
                </div>

                <button type="submit" id="submitBtn">Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        let usernameTimeout = null;
        let emailTimeout = null;
        const debounceTime = 500; // milliseconds

        function validateField(field, value) {
            const spinner = document.getElementById(`${field}Spinner`);
            const feedback = document.getElementById(`${field}Feedback`);
            const input = document.getElementById(field);
            
            // Show spinner and set validating state
            spinner.style.display = 'block';
            input.classList.add('validating');
            input.classList.remove('valid', 'invalid');
            
            fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `field=${field}&value=${encodeURIComponent(value)}`
            })
            .then(response => response.json())
            .then(data => {
                spinner.style.display = 'none';
                input.classList.remove('validating');
                
                if (data.error) {
                    feedback.textContent = 'Error checking availability';
                    feedback.className = 'field-feedback error';
                    input.classList.add('invalid');
                } else {
                    feedback.textContent = data.message;
                    feedback.className = `field-feedback ${data.available ? 'success' : 'error'}`;
                    input.classList.add(data.available ? 'valid' : 'invalid');
                }
                feedback.style.display = 'block';
            })
            .catch(error => {
                spinner.style.display = 'none';
                input.classList.remove('validating');
                feedback.textContent = 'Error checking availability';
                feedback.className = 'field-feedback error';
                input.classList.add('invalid');
            });
        }

        document.getElementById('username').addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (value.length < 3) {
                document.getElementById('usernameFeedback').textContent = 'Username must be at least 3 characters';
                document.getElementById('usernameFeedback').className = 'field-feedback error';
                e.target.classList.add('invalid');
                return;
            }
            
            clearTimeout(usernameTimeout);
            usernameTimeout = setTimeout(() => validateField('username', value), debounceTime);
        });

        document.getElementById('email').addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (!value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                document.getElementById('emailFeedback').textContent = 'Please enter a valid email address';
                document.getElementById('emailFeedback').className = 'field-feedback error';
                e.target.classList.add('invalid');
                return;
            }
            
            clearTimeout(emailTimeout);
            emailTimeout = setTimeout(() => validateField('email', value), debounceTime);
        });

        // Password validation
        document.getElementById('password').addEventListener('input', (e) => {
            const value = e.target.value;
            const feedback = document.getElementById('passwordFeedback');
            const input = e.target;
            
            if (value.length < 8) {
                feedback.textContent = 'Password must be at least 8 characters';
                feedback.className = 'field-feedback error';
                input.classList.add('invalid');
                input.classList.remove('valid');
            } else if (!value.match(/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/)) {
                feedback.textContent = 'Password must contain both letters and numbers';
                feedback.className = 'field-feedback error';
                input.classList.add('invalid');
                input.classList.remove('valid');
            } else {
                feedback.textContent = 'Password is valid';
                feedback.className = 'field-feedback success';
                input.classList.add('valid');
                input.classList.remove('invalid');
            }
            feedback.style.display = 'block';
        });

        // Phone number validation
        document.getElementById('phone').addEventListener('input', (e) => {
            const value = e.target.value.trim();
            const feedback = document.getElementById('phoneFeedback');
            const input = e.target;
            
            if (!value.match(/^\+?[\d\s-]{10,}$/)) {
                feedback.textContent = 'Please enter a valid phone number';
                feedback.className = 'field-feedback error';
                input.classList.add('invalid');
                input.classList.remove('valid');
            } else {
                feedback.textContent = 'Phone number is valid';
                feedback.className = 'field-feedback success';
                input.classList.add('valid');
                input.classList.remove('invalid');
            }
            feedback.style.display = 'block';
        });

        // Form submission
        document.getElementById('registerForm').addEventListener('submit', (e) => {
            const inputs = e.target.querySelectorAll('input');
            let hasInvalid = false;
            
            inputs.forEach(input => {
                if (input.classList.contains('invalid')) {
                    hasInvalid = true;
                }
            });
            
            if (hasInvalid) {
                e.preventDefault();
                alert('Please fix the errors in the form before submitting.');
            }
        });
    </script>
</body>
</html> 