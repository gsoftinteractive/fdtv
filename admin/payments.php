<?php
// admin/payments.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

$admin_id = $_SESSION['admin_id'];

// Get all payments
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT p.*, u.company_name, u.email, u.station_name 
        FROM payments p 
        JOIN users u ON p.user_id = u.id";

if ($filter != 'all') {
    $sql .= " WHERE p.status = :status";
}

$sql .= " ORDER BY p.submitted_at DESC";

$stmt = $conn->prepare($sql);
if ($filter != 'all') {
    $stmt->bindParam(':status', $filter);
}
$stmt->execute();
$payments = $stmt->fetchAll();

// Handle payment approval
if (isset($_POST['approve'])) {
    $payment_id = (int)$_POST['payment_id'];
    
    if (verify_csrf_token($_POST['csrf_token'])) {
        
        $stmt = $conn->prepare("SELECT p.*, u.* FROM payments p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            
            // Start transaction
            $conn->beginTransaction();
            
            try {

                // Update payment status
                $stmt = $conn->prepare("UPDATE payments SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
                $stmt->execute([$admin_id, $payment_id]);

                // Handle coin purchase payment
                $payment_type = $payment['payment_type'] ?? 'coins';

                if ($payment_type === 'coins') {
                    // Parse coins from description (format: "Purchase of X coins")
                    // Or use amount to determine coin package
                    $coins_to_credit = 0;

                    // Determine coin package based on amount
                    $coin_packages = [
                        5000 => 500,
                        10000 => 1100,   // 1000 + 100 bonus
                        25000 => 2800,   // 2500 + 300 bonus
                        50000 => 5750,   // 5000 + 750 bonus
                        100000 => 12000  // 10000 + 2000 bonus
                    ];

                    $coins_to_credit = $coin_packages[$payment['amount']] ?? 0;

                    if ($coins_to_credit > 0) {
                        // Get current balance
                        $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
                        $stmt->execute([$payment['user_id']]);
                        $user_data = $stmt->fetch();
                        $balance_before = $user_data['coins'] ?? 0;
                        $balance_after = $balance_before + $coins_to_credit;

                        // Credit coins to user
                        $stmt = $conn->prepare("UPDATE users SET coins = ?, coins_updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$balance_after, $payment['user_id']]);

                        // Record transaction
                        $stmt = $conn->prepare("INSERT INTO coin_transactions
                            (user_id, amount, transaction_type, description, balance_before, balance_after, created_by, reference)
                            VALUES (?, ?, 'purchase', ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $payment['user_id'],
                            $coins_to_credit,
                            "Purchase of {$coins_to_credit} coins (Payment #{$payment_id})",
                            $balance_before,
                            $balance_after,
                            $admin_id,
                            $payment['reference']
                        ]);
                    }
                }

                $conn->commit();

                // Send approval email
                $message = "
                    <h2>Payment Approved!</h2>
                    <p>Dear {$payment['company_name']},</p>
                    <p>Your payment of ₦" . number_format($payment['amount']) . " has been approved.</p>
                    <p><strong>{$coins_to_credit} coins</strong> have been credited to your account.</p>
                    <p>You can now create your station (costs 100 coins) or upload videos (10 coins per video).</p>
                    <p><a href='" . SITE_URL . "/dashboard/index.php'>Go to Dashboard</a></p>
                ";
                send_email($payment['email'], "Payment Approved - FDTV", $message);
                
                set_flash("Payment approved successfully!", "success");
                
            } catch (Exception $e) {
                $conn->rollBack();
                set_flash("Error approving payment: " . $e->getMessage(), "danger");
            }
        }
    }
    
    redirect('payments.php');
}

// Handle payment rejection
if (isset($_POST['reject'])) {
    $payment_id = (int)$_POST['payment_id'];
    $admin_note = trim($_POST['admin_note']);
    
    if (verify_csrf_token($_POST['csrf_token'])) {
        
        $stmt = $conn->prepare("SELECT p.*, u.email, u.company_name FROM payments p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            $stmt = $conn->prepare("UPDATE payments SET status = 'rejected', admin_note = ?, approved_by = ? WHERE id = ?");
            $stmt->execute([$admin_note, $admin_id, $payment_id]);
            
            // Send rejection email
            $message = "
                <h2>Payment Rejected</h2>
                <p>Dear {$payment['company_name']},</p>
                <p>Your payment of ₦" . number_format($payment['amount']) . " has been rejected.</p>
                <p><strong>Reason:</strong> $admin_note</p>
                <p>Please contact support or submit a new payment.</p>
            ";
            send_email($payment['email'], "Payment Rejected - FDTV", $message);
            
            set_flash("Payment rejected.", "success");
        }
    }
    
    redirect('payments.php');
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - FDTV Admin</title>
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
            <h1>Manage Payments</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="card-title">All Payments</h2>
                    <div>
                        <a href="?status=all" class="btn btn-small <?php echo $filter == 'all' ? '' : 'btn-secondary'; ?>">All</a>
                        <a href="?status=pending" class="btn btn-small <?php echo $filter == 'pending' ? '' : 'btn-secondary'; ?>">Pending</a>
                        <a href="?status=approved" class="btn btn-small <?php echo $filter == 'approved' ? '' : 'btn-secondary'; ?>">Approved</a>
                        <a href="?status=rejected" class="btn btn-small <?php echo $filter == 'rejected' ? '' : 'btn-secondary'; ?>">Rejected</a>
                    </div>
                </div>

                <?php if (empty($payments)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No payments found.
                    </p>
                <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td>
                                <strong><?php echo clean($payment['company_name']); ?></strong><br>
                                <small style="color: #6b7280;"><?php echo clean($payment['email']); ?></small>
                            </td>
                            <td>₦<?php echo number_format($payment['amount']); ?></td>
                            <td><?php echo clean($payment['reference']); ?></td>
                            <td>
                                <?php if ($payment['receipt_image']): ?>
                                    <a href="../uploads/receipts/<?php echo $payment['receipt_image']; ?>" target="_blank" class="btn btn-small">View</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $payment['status'] == 'approved' ? 'success' : ($payment['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($payment['submitted_at']); ?></td>
                            <td>
                                <?php if ($payment['status'] == 'pending'): ?>
                                    <button onclick="approvePayment(<?php echo $payment['id']; ?>)" class="btn btn-success btn-small">Approve</button>
                                    <button onclick="rejectPayment(<?php echo $payment['id']; ?>)" class="btn btn-danger btn-small">Reject</button>
                                <?php else: ?>
                                    <?php echo $payment['approved_at'] ? format_date($payment['approved_at']) : '-'; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:2rem; border-radius:8px; max-width:400px; width:90%;">
            <h3>Approve Payment?</h3>
            <p>This will activate the client's station for 30 days.</p>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="payment_id" id="approve_payment_id">
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <button type="submit" name="approve" class="btn btn-success">Yes, Approve</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:2rem; border-radius:8px; max-width:400px; width:90%;">
            <h3>Reject Payment?</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="payment_id" id="reject_payment_id">
                <div class="form-group">
                    <label>Reason for rejection:</label>
                    <textarea name="admin_note" rows="3" style="width:100%;" required></textarea>
                </div>
                <div style="display:flex; gap:1rem;">
                    <button type="submit" name="reject" class="btn btn-danger">Yes, Reject</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function approvePayment(id) {
        document.getElementById('approve_payment_id').value = id;
        document.getElementById('approveModal').style.display = 'flex';
    }

    function rejectPayment(id) {
        document.getElementById('reject_payment_id').value = id;
        document.getElementById('rejectModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('approveModal').style.display = 'none';
        document.getElementById('rejectModal').style.display = 'none';
    }
    </script>
</body>
</html>