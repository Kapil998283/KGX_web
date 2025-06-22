<?php
session_start();

// If admin is already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Prepare statement to prevent SQL injection
        $stmt = $db->prepare("
            SELECT id, username, password, role, full_name 
            FROM admin_users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Set admin session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['full_name'];
            
            // Update last login time
            $update_stmt = $db->prepare("
                UPDATE admin_users 
                SET last_login = NOW() 
                WHERE id = ?
            ");
            $update_stmt->execute([$admin['id']]);
            
            // Log admin login
            $log_stmt = $db->prepare("
                INSERT INTO admin_activity_log (
                    admin_id, 
                    action, 
                    details,
                    ip_address
                ) VALUES (
                    ?, 
                    'login', 
                    'Admin logged in successfully',
                    ?
                )
            ");
            $log_stmt->execute([$admin['id'], $_SERVER['REMOTE_ADDR']]);
            
            // Redirect to dashboard
            header('Location: index.php');
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    } catch (PDOException $e) {
        $error = "Database error occurred. Please try again later.";
        // Log the error for admin review
        error_log("Admin Login Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - KGX</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            font-size: 1.8rem;
            color: #00c896;
            margin-bottom: 0.5rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #00c896;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(0, 200, 150, 0.25);
        }
        .btn-primary {
            background: #00c896;
            border: none;
            width: 100%;
            padding: 0.75rem;
        }
        .btn-primary:hover {
            background: #00b085;
        }
        .alert {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>KGX Admin</h1>
            <p>Enter your credentials to access the admin panel</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html> 