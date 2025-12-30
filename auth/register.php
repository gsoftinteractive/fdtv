<?php
// auth/register.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

// If already logged in, redirect
if (is_logged_in()) {
    redirect('../dashboard/index.php');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        
        $company_name = trim($_POST['company_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $station_name = trim($_POST['station_name']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($company_name)) {
            $errors[] = "Company name is required.";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required.";
        }
        
        if (empty($phone)) {
            $errors[] = "Phone number is required.";
        }
        
        if (empty($station_name)) {
            $errors[] = "Station name is required.";
        }
        
        if (empty($password) || strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        
        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        // Check if email exists
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered.";
            }
        }
        
        // Check if station name exists
        if (empty($errors)) {
            $station_slug = create_slug($station_name);
            $stmt = $conn->prepare("SELECT id FROM users WHERE station_slug = ?");
            $stmt->execute([$station_slug]);
            if ($stmt->fetch()) {
                $errors[] = "Station name already taken. Please choose another.";
            }
        }
        
        // Register user
        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_ARGON2ID);

            // New users start with 0 coins and active status
            $stmt = $conn->prepare("INSERT INTO users (company_name, email, phone, password, station_name, station_slug, status, coins) VALUES (?, ?, ?, ?, ?, ?, 'active', 0)");

            if ($stmt->execute([$company_name, $email, $phone, $password_hash, $station_name, $station_slug])) {

                // Send welcome email
                $message = "
                    <h2>Welcome to FDTV!</h2>
                    <p>Thank you for registering, $company_name.</p>
                    <p>Your desired station name: <strong>$station_name</strong></p>
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Login to your dashboard</li>
                        <li>Purchase coins (starting from ₦5,000 for 500 coins)</li>
                        <li>Create your station (costs 100 coins)</li>
                        <li>Start uploading and broadcasting!</li>
                    </ol>
                    <p>You only pay for what you use - fair pricing for everyone!</p>
                    <p><a href='" . SITE_URL . "/auth/login.php'>Login Now</a></p>
                ";
                send_email($email, "Welcome to FDTV", $message);

                $success = "Registration successful! Login and purchase coins to create your station.";
            } else {
                $errors[] = "Registration failed. Please try again.";
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
    <title>Register - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">FDTV</div>
                <p>Create Your TV Station</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?php echo clean($success); ?></p>
                    <p><a href="login.php">Login Now</a></p>
                </div>
            <?php else: ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group">
                    <label>Company Name *</label>
                    <input type="text" name="company_name" value="<?php echo isset($_POST['company_name']) ? clean($_POST['company_name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" value="<?php echo isset($_POST['phone']) ? clean($_POST['phone']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Station Name *</label>
                    <input type="text" name="station_name" value="<?php echo isset($_POST['station_name']) ? clean($_POST['station_name']) : ''; ?>" placeholder="e.g., My Channel TV" required>
                    <small style="color: #6b7280;">This will be your station URL: fdtv.ng/station/your-station-name</small>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                    <small style="color: #6b7280;">Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">Create Account</button>
            </form>

            <p style="text-align: center; margin-top: 1.5rem;">
                Already have an account? <a href="login.php" style="color: var(--primary);">Login</a>
            </p>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>