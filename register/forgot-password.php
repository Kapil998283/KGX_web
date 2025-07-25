<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/user-auth.php';

// Get database connection
$conn = getDbConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } else {
        // Check if email exists in database
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Email exists, generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing tokens for this email
            $sql = "DELETE FROM password_resets WHERE email = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            
            // Store token in database
            $sql = "INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sss', $email, $token, $expiry);
            
            if (mysqli_stmt_execute($stmt)) {
                // Send email with reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/newapp/pages/reset-password.php?token=" . $token;
                
                // For development, just show the link
                $success = "Password reset link: <a href='" . $reset_link . "'>" . $reset_link . "</a>";
                
                // In production, you would use PHPMailer or similar to send an actual email
                // sendPasswordResetEmail($email, $token);
            } else {
                $error = "Error creating reset token: " . mysqli_error($conn);
            }
        } else {
            $error = "No account found with that email address";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | KGX Gaming</title>
    
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
            <!-- Header -->
            <div class="auth-header">
                <div class="logo">
                    <h1 class="brand-text">KGX</h1>
                    <span class="brand-tagline">GAMING XTREME</span>
                </div>
                <h2 class="auth-title">Reset Password</h2>
                <p class="auth-subtitle">Enter your email to receive a reset link</p>
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
            
            <?php if(!$success): ?>
                <form class="auth-form" method="POST" action="">
                    <div class="form-group">
                        <label for="email">
                            <ion-icon name="mail-outline"></ion-icon>
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" required
                               placeholder="Enter your registered email address">
                        <div class="input-hint">We'll send a password reset link to this email</div>
                    </div>
                    
                    <button type="submit" class="auth-btn primary-btn">
                        <span>Send Reset Link</span>
                        <ion-icon name="paper-plane-outline"></ion-icon>
                    </button>
                </form>
            <?php else: ?>
                <div class="reset-success-info">
                    <div class="reset-icon">
                        <ion-icon name="mail-open-outline"></ion-icon>
                    </div>
                    <h3>Check Your Email</h3>
                    <p>We've sent a password reset link to your email address. Click the link in the email to reset your password.</p>
                    
                    <div class="reset-actions">
                        <button class="auth-btn secondary-btn" onclick="location.reload()">
                            <ion-icon name="refresh-outline"></ion-icon>
                            <span>Send Another Link</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="auth-footer">
                <div class="step-navigation">
                    <a href="login.php" class="back-btn">
                        <ion-icon name="arrow-back-outline"></ion-icon>
                        Back to Sign In
                    </a>
                </div>
                
                <p>Remember your password? <a href="login.php" class="auth-link">Sign In</a></p>
                <p>Don't have an account? <a href="multi-step-register.php" class="auth-link">Create Account</a></p>
                
                <div class="help-section">
                    <div class="help-item">
                        <ion-icon name="help-circle-outline"></ion-icon>
                        <span>Need help? <a href="../pages/dashboard/help-contact.php" class="auth-link">Contact Support</a></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/multi-step-auth.js"></script>
    <style>
        .reset-success-info {
            text-align: center;
            padding: 30px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
        }
        
        .reset-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gradient-primary);
            border-radius: 50%;
            font-size: 32px;
            color: white;
        }
        
        .reset-success-info h3 {
            color: var(--text-light);
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 16px 0;
        }
        
        .reset-success-info p {
            color: var(--text-muted);
            font-size: 16px;
            line-height: 1.5;
            margin: 0 0 24px 0;
        }
        
        .reset-actions {
            display: flex;
            justify-content: center;
            gap: 16px;
        }
        
        .secondary-btn {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .secondary-btn:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .help-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .help-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .help-item ion-icon {
            font-size: 18px;
            color: var(--primary-color);
        }
    </style>
</body>
</html>
