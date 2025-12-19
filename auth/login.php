<?php
// auth/login.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

// If already logged in, redirect
if (is_logged_in()) {
    redirect('../dashboard/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request.";
    } else {
        
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $errors[] = "Email and password are required.";
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id, company_name, email, password, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                
                // Check if account is suspended
                if ($user['status'] == 'suspended') {
                    $errors[] = "Your account has been suspended. Please contact support.";
                } else {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['company_name'];
                    $_SESSION['user_status'] = $user['status'];
                    
                    // Remember me
                    if ($remember) {
                        setcookie('user_email', $email, time() + (86400 * 30), '/');
                    }
                    
                    redirect('../dashboard/index.php');
                }
                
            } else {
                $errors[] = "Invalid email or password.";
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
    <title>Login - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">FDTV</div>
                <p>Login to Your Dashboard</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo isset($_COOKIE['user_email']) ? clean($_COOKIE['user_email']) : ''; ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; font-weight: normal;">
                        <input type="checkbox" name="remember" style="width: auto; margin-right: 0.5rem;">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </form>

            <p style="text-align: center; margin-top: 1rem;">
                <a href="forgot-password.php" style="color: var(--primary);">Forgot Password?</a>
            </p>

            <p style="text-align: center; margin-top: 1.5rem;">
                Don't have an account? <a href="register.php" style="color: var(--primary);">Register</a>
            </p>
        </div>
    </div>
</body>
</html>