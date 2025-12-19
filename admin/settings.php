<?php
// admin/settings.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

$errors = [];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request.";
    } else {
        
        $monthly_price = (int)$_POST['monthly_price'];
        $bank_name = trim($_POST['bank_name']);
        $account_number = trim($_POST['account_number']);
        $account_name = trim($_POST['account_name']);
        
        if ($monthly_price < 1000) {
            $errors[] = "Monthly price must be at least ₦1,000";
        }
        
        if (empty($bank_name) || empty($account_number) || empty($account_name)) {
            $errors[] = "All bank details are required.";
        }
        
        if (empty($errors)) {
            update_setting('monthly_price', $monthly_price, $conn);
            update_setting('bank_name', $bank_name, $conn);
            update_setting('account_number', $account_number, $conn);
            update_setting('account_name', $account_name, $conn);
            
            set_flash("Settings updated successfully!", "success");
            redirect('settings.php');
        }
    }
}

// Get current settings
$monthly_price = get_setting('monthly_price', $conn);
$bank_name = get_setting('bank_name', $conn);
$account_number = get_setting('account_number', $conn);
$account_name = get_setting('account_name', $conn);

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FDTV Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">FDTV Admin</a>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="clients.php">Clients</a>
                <a href="payments.php">Payments</a>
                <a href="stations.php">Stations</a>
                <a href="settings.php">Settings</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <h1>Settings</h1>

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

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">General Settings</h2>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-group">
                        <label>Monthly Subscription Price (₦)</label>
                        <input type="number" name="monthly_price" value="<?php echo $monthly_price; ?>" required>
                    </div>

                    <h3 style="margin: 2rem 0 1rem;">Bank Transfer Details</h3>

                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?php echo clean($bank_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" value="<?php echo clean($account_number); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Account Name</label>
                        <input type="text" name="account_name" value="<?php echo clean($account_name); ?>" required>
                    </div>

                    <button type="submit" class="btn">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>