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

// Check if user has completed step 1
if (!isset($_SESSION['registration_step']) || $_SESSION['registration_step'] < 2) {
    header("Location: multi-step-register.php");
    exit();
}

$error = '';
$success = '';

// Handle phone submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_otp') {
    $phone = trim($_POST['full_phone'] ?? '');
    
    if (empty($phone)) {
        $error = "Please enter a valid phone number";
    } elseif (!preg_match("/^\+[1-9]\d{6,14}$/", $phone)) {
        $error = "Please enter a valid phone number with country code";
    } else {
        // Check if phone number already exists
        $sql = "SELECT id FROM users WHERE phone = :phone";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Phone number already registered";
        } else {
            // Generate OTP
            $otp = rand(100000, 999999);
            
            // Store phone and OTP in session (in real app, send via SMS)
            $_SESSION['registration_data']['phone'] = $phone;
            $_SESSION['registration_data']['otp'] = $otp;
            $_SESSION['registration_data']['otp_time'] = time();
            
            // In a real application, you would send the OTP via SMS here
            // For demo purposes, we'll just show it
            $success = "OTP sent to your phone number. Demo OTP: " . $otp;
        }
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'verify_otp') {
    $entered_otp = trim($_POST['otp'] ?? '');
    
    if (empty($entered_otp)) {
        $error = "Please enter the OTP";
    } elseif (!isset($_SESSION['registration_data']['otp'])) {
        $error = "Please request an OTP first";
    } elseif (time() - $_SESSION['registration_data']['otp_time'] > 300) { // 5 minutes
        $error = "OTP has expired. Please request a new one";
        unset($_SESSION['registration_data']['otp']);
        unset($_SESSION['registration_data']['otp_time']);
    } elseif ($entered_otp != $_SESSION['registration_data']['otp']) {
        $error = "Invalid OTP. Please try again";
    } else {
        // OTP verified successfully
        $_SESSION['registration_step'] = 3;
        unset($_SESSION['registration_data']['otp']);
        unset($_SESSION['registration_data']['otp_time']);
        
        header("Location: game-selection.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phone Verification - Step 2 | KGX Gaming</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../favicon.svg" type="image/svg+xml">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/multi-step-auth.css">
    
    <!-- International Telephone Input CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css">
    
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
                    <div class="progress-fill" style="width: 66.66%"></div>
                </div>
                <div class="step-indicators">
                    <div class="step-indicator completed">
                        <div class="step-number">
                            <ion-icon name="checkmark-outline"></ion-icon>
                        </div>
                        <div class="step-label">Account Info</div>
                    </div>
                    <div class="step-indicator active">
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
                <h2 class="auth-title">Verify Your Phone</h2>
                <p class="auth-subtitle">We'll send you a verification code</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success-message">
                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!isset($_SESSION['registration_data']['phone'])): ?>
                <!-- Phone Number Input Form -->
                <form class="auth-form" method="POST" action="" id="phoneForm">
                    <input type="hidden" name="action" value="send_otp">
                    
                    <div class="form-group">
                        <label for="phone">
                            <ion-icon name="call-outline"></ion-icon>
                            Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone" required class="phone-input" 
                               maxlength="10" pattern="[0-9]{10}" placeholder="Enter your phone number">
                        <input type="hidden" id="full_phone" name="full_phone">
                        <div class="input-hint">We'll send a 6-digit verification code to this number</div>
                        <div id="phone-error" class="error-text" style="display: none;"></div>
                    </div>
                    
                    <button type="submit" class="auth-btn primary-btn">
                        <span>Send Verification Code</span>
                        <ion-icon name="paper-plane-outline"></ion-icon>
                    </button>
                </form>
            <?php else: ?>
                <!-- OTP Verification Form -->
                <div class="phone-display">
                    <div class="phone-icon">
                        <ion-icon name="phone-portrait-outline"></ion-icon>
                    </div>
                    <p>Verification code sent to</p>
                    <div class="phone-number"><?php echo htmlspecialchars($_SESSION['registration_data']['phone']); ?></div>
                </div>
                
                <form class="auth-form" method="POST" action="" id="otpForm">
                    <input type="hidden" name="action" value="verify_otp">
                    
                    <div class="form-group">
                        <label for="otp">
                            <ion-icon name="shield-checkmark-outline"></ion-icon>
                            Enter Verification Code
                        </label>
                        <div class="otp-input-container">
                            <input type="text" class="otp-digit" maxlength="1" data-index="0">
                            <input type="text" class="otp-digit" maxlength="1" data-index="1">
                            <input type="text" class="otp-digit" maxlength="1" data-index="2">
                            <input type="text" class="otp-digit" maxlength="1" data-index="3">
                            <input type="text" class="otp-digit" maxlength="1" data-index="4">
                            <input type="text" class="otp-digit" maxlength="1" data-index="5">
                        </div>
                        <input type="hidden" id="otp" name="otp">
                        <div class="input-hint">Enter the 6-digit code we sent to your phone</div>
                    </div>
                    
                    <button type="submit" class="auth-btn primary-btn" id="verifyBtn">
                        <span>Verify Code</span>
                        <ion-icon name="checkmark-outline"></ion-icon>
                    </button>
                </form>
                
                <div class="resend-section">
                    <p>Didn't receive the code?</p>
                    <button class="resend-btn" id="resendBtn">
                        <ion-icon name="refresh-outline"></ion-icon>
                        Resend Code
                    </button>
                    <div class="timer" id="timer">Resend available in <span id="countdown">60</span>s</div>
                </div>
                
                <div class="change-phone">
                    <a href="?change_phone=1" class="auth-link">
                        <ion-icon name="pencil-outline"></ion-icon>
                        Change Phone Number
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <div class="step-navigation">
                    <a href="multi-step-register.php" class="back-btn">
                        <ion-icon name="arrow-back-outline"></ion-icon>
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js"></script>
    <script src="../assets/js/multi-step-auth.js"></script>
    <script>
        // Phone input initialization
        <?php if (!isset($_SESSION['registration_data']['phone'])): ?>
        const phoneInput = document.querySelector("#phone");
        const fullPhoneInput = document.querySelector("#full_phone");
        
        const iti = window.intlTelInput(phoneInput, {
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

        // Phone validation
        document.querySelector("#phoneForm").addEventListener("submit", function(e) {
            const phoneValue = phoneInput.value.replace(/\D/g, '');
            const isValid = phoneValue.length === 10 && iti.isValidNumber();
            
            if (!isValid) {
                e.preventDefault();
                document.getElementById('phone-error').style.display = 'block';
                document.getElementById('phone-error').textContent = 'Please enter a valid 10-digit phone number';
            } else {
                fullPhoneInput.value = iti.getNumber();
            }
        });
        <?php endif; ?>

        // OTP input handling
        <?php if (isset($_SESSION['registration_data']['phone'])): ?>
        const otpDigits = document.querySelectorAll('.otp-digit');
        const otpHidden = document.getElementById('otp');
        let countdown = 60;
        
        // OTP digit input handling
        otpDigits.forEach((digit, index) => {
            digit.addEventListener('input', function(e) {
                const value = e.target.value;
                
                if (value.length === 1 && /^\d$/.test(value)) {
                    if (index < otpDigits.length - 1) {
                        otpDigits[index + 1].focus();
                    }
                }
                
                updateOTPValue();
            });
            
            digit.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpDigits[index - 1].focus();
                }
            });
            
            digit.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text');
                const digits = pastedData.replace(/\D/g, '').slice(0, 6);
                
                digits.split('').forEach((digit, i) => {
                    if (otpDigits[i]) {
                        otpDigits[i].value = digit;
                    }
                });
                
                updateOTPValue();
                
                if (digits.length === 6) {
                    document.getElementById('verifyBtn').focus();
                }
            });
        });
        
        function updateOTPValue() {
            const otp = Array.from(otpDigits).map(digit => digit.value).join('');
            otpHidden.value = otp;
            
            if (otp.length === 6) {
                document.getElementById('verifyBtn').disabled = false;
            } else {
                document.getElementById('verifyBtn').disabled = true;
            }
        }
        
        // Countdown timer
        function startCountdown() {
            const countdownElement = document.getElementById('countdown');
            const timerElement = document.getElementById('timer');
            const resendBtn = document.getElementById('resendBtn');
            
            const timer = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(timer);
                    timerElement.style.display = 'none';
                    resendBtn.style.display = 'block';
                }
            }, 1000);
        }
        
        // Start countdown
        startCountdown();
        
        // Resend functionality
        document.getElementById('resendBtn').addEventListener('click', function() {
            window.location.href = '?change_phone=1';
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Handle change phone request
if (isset($_GET['change_phone'])) {
    unset($_SESSION['registration_data']['phone']);
    unset($_SESSION['registration_data']['otp']);
    unset($_SESSION['registration_data']['otp_time']);
    header("Location: phone-verification.php");
    exit();
}
?>
