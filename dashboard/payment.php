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

// Coin packages
$coin_packages = [
    ['coins' => 500, 'price' => 5000, 'bonus' => 0, 'label' => 'Starter'],
    ['coins' => 1000, 'price' => 10000, 'bonus' => 100, 'label' => 'Basic'],
    ['coins' => 2500, 'price' => 25000, 'bonus' => 300, 'label' => 'Standard'],
    ['coins' => 5000, 'price' => 50000, 'bonus' => 750, 'label' => 'Pro'],
    ['coins' => 10000, 'price' => 100000, 'bonus' => 2000, 'label' => 'Premium']
];

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request.";
    } else {

        $payment_type = $_POST['payment_type'] ?? 'subscription'; // 'subscription' or 'coins'
        $reference = trim($_POST['reference']);

        if (empty($reference)) {
            $errors[] = "Payment reference is required.";
        }

        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] != 0) {
            $errors[] = "Please upload payment receipt.";
        }

        // Validate coin package if purchasing coins
        if ($payment_type === 'coins') {
            $coin_package_index = (int)($_POST['coin_package'] ?? -1);
            if (!isset($coin_packages[$coin_package_index])) {
                $errors[] = "Invalid coin package selected.";
            }
        }

        if (empty($errors)) {

            $file = $_FILES['receipt'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];

            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = "Only JPG, PNG, PDF files allowed.";
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

                    // Determine amount based on payment type
                    if ($payment_type === 'coins') {
                        $package = $coin_packages[$coin_package_index];
                        $amount = $package['price'];
                        $description = "Coin purchase: {$package['coins']} coins + {$package['bonus']} bonus";
                    } else {
                        // Default to basic coin package if no package selected
                        $amount = 10000;
                        $description = "Coin purchase payment";
                    }

                    // Insert payment record
                    $stmt = $conn->prepare("INSERT INTO payments
                        (user_id, amount, reference, receipt_image, status, payment_type, description)
                        VALUES (?, ?, ?, ?, 'pending', ?, ?)");
                    $stmt->execute([$user_id, $amount, $reference, $filename, $payment_type, $description]);

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
            <h1>Payment & Coins</h1>

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

            <!-- Coin Balance Display -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem; text-align: center;">
                <div style="font-size: 1rem; opacity: 0.9; margin-bottom: 0.5rem;">Your Coin Balance</div>
                <div style="font-size: 3.5rem; font-weight: 700; margin-bottom: 1rem;">
                    <?php echo number_format($user['coins'] ?? 0); ?> 💰
                </div>
                <p style="opacity: 0.95; max-width: 600px; margin: 0 auto;">
                    Coins are used for video uploads, storage, and streaming. Purchase coin packages below to top up your balance.
                </p>
            </div>

            <!-- Coin Packages -->
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">Buy Coin Packages</h2>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                    <?php foreach ($coin_packages as $index => $package): ?>
                    <div class="coin-package" style="
                        border: 2px solid #e5e7eb;
                        border-radius: 12px;
                        padding: 1.5rem;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.2s;
                        position: relative;
                    " onclick="selectCoinPackage(<?php echo $index; ?>, <?php echo $package['price']; ?>)">
                        <?php if ($package['bonus'] > 0): ?>
                            <div style="position: absolute; top: -10px; right: -10px; background: #f59e0b; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                +<?php echo $package['bonus']; ?> Bonus!
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;"><?php echo $package['label']; ?></div>
                        <div style="font-size: 2.5rem; font-weight: 700; color: #7c3aed; margin-bottom: 0.5rem;">
                            <?php echo number_format($package['coins']); ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">Coins</div>
                        <?php if ($package['bonus'] > 0): ?>
                            <div style="font-size: 0.875rem; color: #059669; margin-bottom: 1rem; font-weight: 600;">
                                Total: <?php echo number_format($package['coins'] + $package['bonus']); ?> coins
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #111827; margin-top: 1rem;">
                            ₦<?php echo number_format($package['price']); ?>
                        </div>
                        <button type="button" class="btn btn-small" style="margin-top: 1rem; width: 100%;" onclick="event.stopPropagation(); selectCoinPackage(<?php echo $index; ?>, <?php echo $package['price']; ?>)">
                            Select
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

                <!-- Left Column -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">💳 Bank Transfer Details</h2>
                        </div>

                        <p style="margin-bottom: 1.5rem; color: #6b7280;">
                            Select a coin package above, then make bank transfer to the account below.
                        </p>

                        <div style="background: #f3f4f6; padding: 1.5rem; border-radius: 8px;">
                            <p style="margin-bottom: 1rem;"><strong>Bank Name:</strong><br><?php echo clean($bank_name); ?></p>
                            <p style="margin-bottom: 1rem;"><strong>Account Number:</strong><br>
                                <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?php echo clean($account_number); ?></span>
                            </p>
                            <p><strong>Account Name:</strong><br><?php echo clean($account_name); ?></p>
                        </div>

                        <div style="margin-top: 1.5rem; padding: 1rem; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                            <p style="font-size: 0.875rem; color: #92400e; margin: 0;">
                                <strong>⚠️ Important:</strong> After making the bank transfer, submit your payment receipt in the form on the right. Admin will review and credit your coins within 24 hours.
                            </p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📊 Coin Usage Breakdown</h2>
                        </div>

                        <div style="font-size: 0.875rem;">
                            <p style="margin-bottom: 1rem; color: #6b7280;">
                                Here's how coins are used in your account:
                            </p>

                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px; margin-bottom: 0.5rem;">
                                <span>📹 Video Upload</span>
                                <strong>10 coins</strong>
                            </div>

                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px; margin-bottom: 0.5rem;">
                                <span>💾 Storage (per GB/month)</span>
                                <strong>50 coins</strong>
                            </div>

                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px; margin-bottom: 0.5rem;">
                                <span>📡 Streaming (per hour)</span>
                                <strong>5 coins</strong>
                            </div>

                            <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: #f9fafb; border-radius: 6px;">
                                <span>🔧 Monthly Maintenance</span>
                                <strong>100 coins</strong>
                            </div>

                            <p style="margin-top: 1rem; padding: 0.75rem; background: #e0f2fe; border-radius: 6px; font-size: 0.8125rem; color: #0c4a6e;">
                                💡 <strong>Tip:</strong> The more you use the platform, the more coins you'll need. Monitor your balance regularly!
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title" id="formTitle">Submit Payment Proof for Coin Purchase</h2>
                        </div>

                        <div id="selectedPackageInfo" style="display: none; background: #f0fdf4; border: 2px solid #22c55e; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <strong>Selected Package:</strong>
                            <div id="packageDetails" style="margin-top: 0.5rem;"></div>
                            <button type="button" onclick="clearPackageSelection()" class="btn btn-small btn-secondary" style="margin-top: 0.5rem;">
                                Change Package
                            </button>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="paymentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="payment_type" id="paymentType" value="coins">
                            <input type="hidden" name="coin_package" id="coinPackage" value="">

                            <div class="form-group">
                                <label>Payment Reference / Transaction ID *</label>
                                <input type="text" name="reference" placeholder="e.g., TXN123456789" required>
                                <small style="color: #6b7280;">Enter the reference number from your bank transfer</small>
                            </div>

                            <div class="form-group">
                                <label>Upload Receipt / Screenshot *</label>
                                <input type="file" name="receipt" accept="image/*,application/pdf" required>
                                <small style="color: #6b7280;">JPG, PNG, PDF only. Max 5MB</small>
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

    <script>
        const coinPackages = <?php echo json_encode($coin_packages); ?>;

        function selectCoinPackage(index, price) {
            const package = coinPackages[index];

            // Update hidden fields
            document.getElementById('paymentType').value = 'coins';
            document.getElementById('coinPackage').value = index;

            // Show selected package info
            const detailsHtml = `
                <div style="font-size: 1.25rem; font-weight: 700; color: #7c3aed; margin-bottom: 0.5rem;">
                    ${package.coins.toLocaleString()} coins ${package.bonus > 0 ? '+ ' + package.bonus + ' bonus' : ''}
                </div>
                <div style="font-size: 1rem; color: #6b7280;">
                    ${package.label} Package - ₦${price.toLocaleString()}
                </div>
            `;
            document.getElementById('packageDetails').innerHTML = detailsHtml;
            document.getElementById('selectedPackageInfo').style.display = 'block';

            // Update form title
            document.getElementById('formTitle').textContent = 'Submit Payment Proof for Coin Package';

            // Highlight selected package
            document.querySelectorAll('.coin-package').forEach((el, i) => {
                if (i === index) {
                    el.style.borderColor = '#7c3aed';
                    el.style.borderWidth = '3px';
                    el.style.background = '#f5f3ff';
                } else {
                    el.style.borderColor = '#e5e7eb';
                    el.style.borderWidth = '2px';
                    el.style.background = 'white';
                }
            });

            // Scroll to form
            document.getElementById('paymentForm').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function clearPackageSelection() {
            // Reset hidden fields (keep as coins)
            document.getElementById('paymentType').value = 'coins';
            document.getElementById('coinPackage').value = '';

            // Hide package info
            document.getElementById('selectedPackageInfo').style.display = 'none';

            // Reset form title
            document.getElementById('formTitle').textContent = 'Submit Payment Proof for Coin Purchase';

            // Reset package highlighting
            document.querySelectorAll('.coin-package').forEach((el) => {
                el.style.borderColor = '#e5e7eb';
                el.style.borderWidth = '2px';
                el.style.background = 'white';
            });
        }

        // Add hover effects to coin packages
        document.querySelectorAll('.coin-package').forEach((el) => {
            el.addEventListener('mouseenter', function() {
                if (this.style.borderColor !== 'rgb(124, 58, 237)') {
                    this.style.borderColor = '#9ca3af';
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                }
            });
            el.addEventListener('mouseleave', function() {
                if (this.style.borderColor !== 'rgb(124, 58, 237)') {
                    this.style.borderColor = '#e5e7eb';
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                }
            });
        });
    </script>
</body>
</html>