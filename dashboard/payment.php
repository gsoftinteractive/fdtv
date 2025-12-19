<?php
// dashboard/payment.php

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

// Get bank details from settings
$bank_name = get_setting('bank_name', $conn);
$account_number = get_setting('account_number', $conn);
$account_name = get_setting('account_name', $conn);
$monthly_price = get_setting('monthly_price', $conn);

// Get payment history
$stmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY submitted_at DESC");
$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

// Get subscription
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
$stmt->execute([$user_id]);
$subscription = $stmt->fetch();

$errors = [];

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request.";
    } else {
        
        $reference = trim($_POST['reference']);
        
        if (empty($reference)) {
            $errors[] = "Payment reference is required.";
        }
        
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] != 0) {
            $errors[] = "Please upload payment receipt.";
        }
        
        if (empty($errors)) {
            
            $file = $_FILES['receipt'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = "Only JPG, PNG images allowed.";
            }
            
            if ($file['size'] > 5242880) { // 5MB
                $errors[] = "File too large. Max 5MB.";
            }
            
            if (empty($errors)) {
                
                // Create upload directory
                $upload_dir = UPLOAD_PATH . 'receipts/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'receipt_' . $user_id . '_' . time() . '.' . $extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    
                    // Insert payment record
                    $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, reference, receipt_image, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $monthly_price, $reference, $filename]);
                    
                    set_flash("Payment submitted successfully! Admin will review and approve shortly.", "success");
                    redirect('payment.php');
                    
                } else {
                    $errors[] = "Failed to upload receipt. Please try again.";
                }
            }
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
    <title>Payment - FDTV</title>
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
                <a href="ticker.php">Ticker</a>
                <a href="payment.php">Payment</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <h1>Payment & Subscription</h1>

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
                
                <!-- Left Column -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Subscription Details</h2>
                        </div>
                        
                        <p><strong>Monthly Price:</strong> ₦<?php echo number_format($monthly_price); ?></p>
                        
                        <?php if ($subscription): ?>
                            <p><strong>Current Period:</strong> 
                                <?php echo format_date($subscription['start_date']); ?> - 
                                <?php echo format_date($subscription['end_date']); ?>
                            </p>
                            <p><strong>Status:</strong> 
                                <span class="badge badge-<?php echo $subscription['status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($subscription['status']); ?>
                                </span>
                            </p>
                            
                            <?php if ($subscription['status'] == 'active'): ?>
                                <?php $days_left = days_until($subscription['end_date']); ?>
                                <p><strong>Days Remaining:</strong> <?php echo $days_left; ?> days</p>
                                
                                <?php if ($days_left <= 7): ?>
                                    <div class="alert alert-warning" style="margin-top: 1rem;">
                                        Your subscription expires soon. Please renew to avoid interruption.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-danger" style="margin-top: 1rem;">
                                    Your subscription has expired. Please make payment to reactivate.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning" style="margin-top: 1rem;">
                                No active subscription. Please make your first payment.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Bank Transfer Details</h2>
                        </div>
                        
                        <div style="background: #f3f4f6; padding: 1.5rem; border-radius: 8px;">
                            <p style="margin-bottom: 1rem;"><strong>Bank Name:</strong><br><?php echo clean($bank_name); ?></p>
                            <p style="margin-bottom: 1rem;"><strong>Account Number:</strong><br>
                                <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?php echo clean($account_number); ?></span>
                            </p>
                            <p><strong>Account Name:</strong><br><?php echo clean($account_name); ?></p>
                        </div>

                        <p style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280;">
                            <strong>Amount:</strong> ₦<?php echo number_format($monthly_price); ?><br>
                            After making transfer, submit your payment receipt below.
                        </p>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Submit Payment Proof</h2>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="form-group">
                                <label>Payment Reference / Transaction ID *</label>
                                <input type="text" name="reference" placeholder="e.g., TXN123456789" required>
                                <small style="color: #6b7280;">Enter the reference number from your bank transfer</small>
                            </div>

                            <div class="form-group">
                                <label>Upload Receipt / Screenshot *</label>
                                <input type="file" name="receipt" accept="image/*" required>
                                <small style="color: #6b7280;">JPG, PNG only. Max 5MB</small>
                            </div>

                            <button type="submit" class="btn" style="width: 100%;">Submit Payment</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Payment History</h2>
                </div>

                <?php if (empty($payments)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No payments yet.
                    </p>
                <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Approved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo clean($payment['reference']); ?></td>
                            <td>₦<?php echo number_format($payment['amount']); ?></td>
                            <td>
                                <?php if ($payment['status'] == 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($payment['status'] == 'pending'): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($payment['submitted_at']); ?></td>
                            <td><?php echo $payment['approved_at'] ? format_date($payment['approved_at']) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>