<?php
// admin/index.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM users");
$total_clients = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$active_clients = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM stations");
$total_stations = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
$pending_payments = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'approved' AND MONTH(approved_at) = MONTH(CURRENT_DATE())");
$monthly_revenue = $stmt->fetch()['total'] ?? 0;

// Recent clients
$stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_clients = $stmt->fetchAll();

// Recent payments
$stmt = $conn->query("SELECT p.*, u.company_name FROM payments p JOIN users u ON p.user_id = u.id ORDER BY p.submitted_at DESC LIMIT 5");
$recent_payments = $stmt->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FDTV</title>
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
            <h1>Admin Dashboard</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_clients; ?></div>
                    <div class="stat-label">Total Clients</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_clients; ?></div>
                    <div class="stat-label">Active Clients</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_stations; ?></div>
                    <div class="stat-label">Total Stations</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $pending_payments; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number">₦<?php echo number_format($monthly_revenue); ?></div>
                    <div class="stat-label">Revenue This Month</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Clients</h2>
                    </div>

                    <?php if (empty($recent_clients)): ?>
                        <p style="color: #6b7280;">No clients yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_clients as $client): ?>
                                <tr>
                                    <td><?php echo clean($client['company_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $client['status'] == 'active' ? 'success' : ($client['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($client['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($client['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Payments</h2>
                    </div>

                    <?php if (empty($recent_payments)): ?>
                        <p style="color: #6b7280;">No payments yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo clean($payment['company_name']); ?></td>
                                    <td>₦<?php echo number_format($payment['amount']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $payment['status'] == 'approved' ? 'success' : ($payment['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>