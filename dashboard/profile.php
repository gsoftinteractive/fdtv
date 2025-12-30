<?php
// dashboard/profile.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    
    if (verify_csrf_token($_POST['csrf_token'])) {
        
        $company_name = trim($_POST['company_name']);
        $phone = trim($_POST['phone']);
        $station_name = trim($_POST['station_name']);
        
        if (empty($company_name) || empty($phone) || empty($station_name)) {
            $errors[] = "All fields are required.";
        }
        
        // Check if station name changed and is available
        if ($station_name != $user['station_name']) {
            $station_slug = create_slug($station_name);
            $stmt = $conn->prepare("SELECT id FROM users WHERE station_slug = ? AND id != ?");
            $stmt->execute([$station_slug, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "Station name already taken.";
            }
        } else {
            $station_slug = $user['station_slug'];
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET company_name = ?, phone = ?, station_name = ?, station_slug = ? WHERE id = ?");
            $stmt->execute([$company_name, $phone, $station_name, $station_slug, $user_id]);
            
            // Update station name too
            $stmt = $conn->prepare("UPDATE stations SET station_name = ?, slug = ? WHERE user_id = ?");
            $stmt->execute([$station_name, $station_slug, $user_id]);
            
            set_flash("Profile updated successfully!", "success");
            redirect('profile.php');
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    
    if (verify_csrf_token($_POST['csrf_token'])) {
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect.";
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        }
        
        if (empty($errors)) {
            $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            set_flash("Password changed successfully!", "success");
            redirect('profile.php');
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">FDTV</a>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="videos.php">Videos</a>
                <a href="jingles.php">Jingles</a>
                <a href="station.php">Station</a>
                <a href="analytics.php">Analytics</a>
                <a href="radio.php">Radio</a>
                <a href="ticker.php">Ticker</a>
                <a href="display-settings.php">Display Settings</a>
                <a href="payment.php">Payment</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <h1>My Profile</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Profile Information</h2>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label>Company Name</label>
                            <input type="text" name="company_name" value="<?php echo clean($user['company_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email (cannot be changed)</label>
                            <input type="email" value="<?php echo clean($user['email']); ?>" disabled>
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo clean($user['phone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Station Name</label>
                            <input type="text" name="station_name" value="<?php echo clean($user['station_name']); ?>" required>
                            <small style="color: #6b7280;">URL: fdtv.ng/station/<?php echo $user['station_slug']; ?></small>
                        </div>

                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Change Password</h2>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>

                        <button type="submit" name="change_password" class="btn">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>