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

// Check for registration success message
if(isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Registration successful! Please login to continue.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $sql = "SELECT id, username, email, password, role FROM users WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Create welcome notification for the user
                $user_id = $user['id'];
                $username = $user['username'];
                $welcome_message = "Welcome back, " . $username . "! We're glad to see you again.";
                
                // Check if notifications table exists first
                $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
                if ($table_check->rowCount() > 0) {
                    // Insert welcome notification
                    $notification_sql = "INSERT INTO notifications (user_id, message, type) VALUES (:user_id, :message, 'welcome')";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bindParam(':user_id', $user_id);
                    $notification_stmt->bindParam(':message', $welcome_message);
                    $notification_stmt->execute();
                }
                
                // Redirect to dashboard
                header("Location: ../index.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Esports Tournament Platform</title>
    
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
        <h1 class="auth-title">Sign In</h1>
        
        <?php if($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" required>
                    <ion-icon name="eye-outline" class="toggle-password"></ion-icon>
                </div>
            </div>
            
            <button type="submit" class="auth-btn">Sign In</button>
        </form>
        
        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
            <p><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = document.querySelector('#password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.name = type === 'password' ? 'eye-outline' : 'eye-off-outline';
        });
    </script>
</body>
</html> 