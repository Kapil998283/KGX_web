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
    <title>Forgot Password - Esports Tournament Platform</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../favicon.svg" type="image/svg+xml">
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    
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
        <h1 class="auth-title">Forgot Password</h1>
        
        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <button type="submit" class="auth-btn">Reset Password</button>
        </form>
        
        <div class="auth-links">
            <p>Remember your password? <a href="login.php">Sign in</a></p>
        </div>
    </div>
</body>
</html> 