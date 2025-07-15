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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['full_phone'] ?? ''); // Using the full phone number with country code
    $main_game = trim($_POST['main_game'] ?? ''); // Add main game field
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($phone) || empty($main_game)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!preg_match("/^\+[1-9]\d{6,14}$/", $phone)) {
        $error = "Please enter a valid phone number with country code";
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
                // Start transaction
                $conn->beginTransaction();
                
                try {
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
                        
                        $success = "Registration successful! Please login to continue.";
                        // Remove the session setting and redirect to login
                        header("Location: login.php?success=1");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Esports Tournament Platform</title>
    
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
    /* Style for the search dropdown */
    .iti__country-list {
        max-height: 300px;
    }
    .iti__search-container {
        padding: 5px 10px;
        position: sticky;
        top: 0;
        background-color: #fff;
        z-index: 1;
    }
    .iti__search-input {
        width: 100%;
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 4px;
        margin-bottom: 5px;
    }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1 class="auth-title">Create Account</h1>
        
        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" required class="form-control" maxlength="10" pattern="[0-9]{10}">
                <input type="hidden" id="full_phone" name="full_phone">
                <small class="phone-hint">Select country code and enter your 10-digit phone number</small>
                <div id="phone-error" class="error-message" style="display: none;"></div>
            </div>
            
            <div class="form-group">
                <label for="main_game">Select Your Main Game</label>
                <select id="main_game" name="main_game" required class="form-control">
                    <option value="">Select a game</option>
                    <option value="PUBG">PUBG</option>
                    <option value="BGMI">BGMI</option>
                    <option value="FREE FIRE">Free Fire</option>
                    <option value="COD">Call of Duty Mobile</option>
                </select>
                <small class="game-hint">This will be set as your main game profile</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required>
                    <ion-icon name="eye-outline" class="toggle-password"></ion-icon>
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
            
            <button type="submit" class="auth-btn">Register</button>
        </form>
        
        <div class="auth-links">
            <p>Already have an account? <a href="login.php">Sign in</a></p>
        </div>
        
        <div class="social-login">
            <p class="social-login-title">Or register with</p>
            <div class="social-buttons">
                <button class="social-btn">
                    <ion-icon name="logo-google"></ion-icon>
                </button>
                <button class="social-btn">
                    <ion-icon name="logo-facebook"></ion-icon>
                </button>
                <button class="social-btn">
                    <ion-icon name="logo-twitter"></ion-icon>
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
    <script>
        // Initialize phone input
        var phoneInput = document.querySelector("#phone");
        var phoneError = document.querySelector("#phone-error");
        var fullPhoneInput = document.querySelector("#full_phone");
        
        // Add search box to country dropdown
        var countryListMarkup = '<div class="iti__search-container">' +
            '<input type="text" class="iti__search-input" placeholder="Search countries...">' +
            '</div>';

        var iti = window.intlTelInput(phoneInput, {
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
            separateDialCode: true,
            initialCountry: "auto",
            geoIpLookup: function(callback) {
                fetch("https://ipapi.co/json")
                .then(function(res) { return res.json(); })
                .then(function(data) { callback(data.country_code); })
                .catch(function() { callback("us"); });
            },
            preferredCountries: ["us", "gb", "in"],
            nationalMode: true,
            formatOnDisplay: true,
            autoPlaceholder: "polite"
        });

        // Add search functionality
        var countryList = document.querySelector('.iti__country-list');
        countryList.insertAdjacentHTML('afterbegin', countryListMarkup);
        
        var searchInput = document.querySelector('.iti__search-input');
        var countryItems = countryList.querySelectorAll('li.iti__country');
        
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase();
            countryItems.forEach(function(item) {
                var countryName = item.getAttribute('data-country-name').toLowerCase();
                var dialCode = item.getAttribute('data-dial-code');
                if (countryName.includes(query) || dialCode.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Prevent search input from closing dropdown
        searchInput.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Enforce 10-digit limit and numbers only
        phoneInput.addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }

            // Update validation status
            if (this.value.length === 10) {
                if (iti.isValidNumber()) {
                    phoneError.style.display = 'none';
                } else {
                    phoneError.style.display = 'block';
                    phoneError.textContent = 'Please enter a valid 10-digit phone number';
                }
            }
        });

        // Validate phone number on form submit
        document.querySelector(".auth-form").addEventListener("submit", function(e) {
            var phoneValue = phoneInput.value.replace(/\D/g, '');
            var isValid = true;
            var errorMsg = "";

            if (!phoneValue) {
                isValid = false;
                errorMsg = "Phone number is required.";
            } else if (phoneValue.length !== 10) {
                isValid = false;
                errorMsg = "Phone number must be exactly 10 digits.";
            } else if (!iti.isValidNumber()) {
                isValid = false;
                errorMsg = "Please enter a valid phone number.";
            }

            if (!isValid) {
                e.preventDefault();
                phoneError.style.display = "block";
                phoneError.textContent = errorMsg;
            } else {
                phoneError.style.display = "none";
                fullPhoneInput.value = iti.getNumber(); // E.164 format
            }
        });

        // Toggle password visibility for all password fields
        document.querySelectorAll('.toggle-password').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const passwordInput = this.parentElement.querySelector('input');
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.name = type === 'password' ? 'eye-outline' : 'eye-off-outline';
            });
        });
    </script>
</body>
</html> 