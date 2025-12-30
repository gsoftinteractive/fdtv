<?php
// admin/coins.php - Coin Management Interface

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

$admin_id = $_SESSION['admin_id'] ?? 0;
$success = '';
$error = '';

// Handle coin credit/debit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_coins') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $type = $_POST['type'] ?? 'admin_credit'; // admin_credit or admin_debit
        $description = trim($_POST['description'] ?? '');

        if ($user_id <= 0 || $amount === 0 || empty($description)) {
            $error = 'Please provide valid user, amount, and description.';
        } else {
            // Get current balance
            $stmt = $conn->prepare("SELECT coins, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'User not found.';
            } else {
                $balance_before = $user['coins'] ?? 0;

                // Calculate new balance
                if ($type === 'admin_debit') {
                    $amount = -abs($amount); // Make negative for debit
                }
                $balance_after = max(0, $balance_before + $amount);

                // Update user balance
                $stmt = $conn->prepare("UPDATE users SET coins = ?, coins_updated_at = NOW() WHERE id = ?");
                $stmt->execute([$balance_after, $user_id]);

                // Record transaction
                $stmt = $conn->prepare("INSERT INTO coin_transactions
                    (user_id, amount, transaction_type, description, balance_before, balance_after, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    $amount,
                    $type,
                    $description,
                    $balance_before,
                    $balance_after,
                    $admin_id
                ]);

                $success = "Successfully " . ($amount > 0 ? 'credited' : 'debited') . " {$amount} coins for {$user['email']}. New balance: {$balance_after}";
            }
        }
    }
}

// Handle coin pricing update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $video_upload = (int)($_POST['video_upload'] ?? 10);
        $storage_per_gb = (int)($_POST['storage_per_gb'] ?? 50);
        $streaming_per_hour = (int)($_POST['streaming_per_hour'] ?? 5);
        $monthly_maintenance = (int)($_POST['monthly_maintenance'] ?? 100);

        $updates = [
            ['video_upload', $video_upload, 'Coins per video upload'],
            ['storage_per_gb', $storage_per_gb, 'Coins per GB storage/month'],
            ['streaming_per_hour', $streaming_per_hour, 'Coins per hour streaming'],
            ['monthly_maintenance', $monthly_maintenance, 'Monthly maintenance fee']
        ];

        foreach ($updates as [$action_type, $coins, $desc]) {
            $stmt = $conn->prepare("UPDATE coin_pricing SET coins_required = ?, description = ? WHERE action_type = ?");
            $stmt->execute([$coins, $desc, $action_type]);
        }

        $success = 'Coin pricing updated successfully.';
    }
}

// Get all users with coin balances
$stmt = $conn->prepare("SELECT id, email, company_name, station_name, coins, coins_updated_at, status, created_at
    FROM users ORDER BY coins DESC");
$stmt->execute();
$users = $stmt->fetchAll();

// Get coin pricing
$stmt = $conn->prepare("SELECT * FROM coin_pricing ORDER BY id");
$stmt->execute();
$pricing = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent transactions
$stmt = $conn->prepare("SELECT ct.*, u.email, u.company_name
    FROM coin_transactions ct
    JOIN users u ON ct.user_id = u.id
    ORDER BY ct.created_at DESC
    LIMIT 50");
$stmt->execute();
$recent_transactions = $stmt->fetchAll();

// Calculate total coins in system
$total_coins = array_sum(array_column($users, 'coins'));

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coin Management - FDTV Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            opacity: 0.9;
            font-size: 0.875rem;
        }
        .coins-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .coins-table th,
        .coins-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .coins-table th {
            background: #f9fafb;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            color: #6b7280;
        }
        .coins-table tr:hover {
            background: #f9fafb;
        }
        .coin-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .coin-badge.high {
            background: #d1fae5;
            color: #065f46;
        }
        .coin-badge.medium {
            background: #fef3c7;
            color: #92400e;
        }
        .coin-badge.low {
            background: #fee2e2;
            color: #991b1b;
        }
        .transaction-credit {
            color: #059669;
            font-weight: 600;
        }
        .transaction-debit {
            color: #dc2626;
            font-weight: 600;
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        .tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">FDTV Admin</a>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="clients.php">Clients</a>
                <a href="stations.php">Stations</a>
                <a href="payments.php">Payments</a>
                <a href="coins.php" style="font-weight: 700;">Coins</a>
                <a href="settings.php">Settings</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <h1>Coin Management</h1>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo clean($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo clean($error); ?></div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo clean($flash['message']); ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($total_coins); ?></div>
                    <div class="stat-label">Total Coins in System</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($users); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($recent_transactions); ?></div>
                    <div class="stat-label">Recent Transactions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(array_sum($pricing)); ?></div>
                    <div class="stat-label">Total Pricing Points</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="users">User Balances</button>
                <button class="tab" data-tab="transactions">Transaction History</button>
                <button class="tab" data-tab="pricing">Coin Pricing</button>
            </div>

            <!-- User Balances Tab -->
            <div class="tab-content active" id="tab-users">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">User Coin Balances</h2>
                    </div>

                    <table class="coins-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Company / Station</th>
                                <th>Coins</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo clean($user['email']); ?></td>
                                <td>
                                    <?php if ($user['company_name']): ?>
                                        <?php echo clean($user['company_name']); ?>
                                    <?php elseif ($user['station_name']): ?>
                                        <?php echo clean($user['station_name']); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $coins = $user['coins'] ?? 0;
                                    $badge_class = $coins > 500 ? 'high' : ($coins > 100 ? 'medium' : 'low');
                                    ?>
                                    <span class="coin-badge <?php echo $badge_class; ?>">
                                        <?php echo number_format($coins); ?> coins
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['coins_updated_at']): ?>
                                        <?php echo date('M d, Y g:i A', strtotime($user['coins_updated_at'])); ?>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-small" onclick="showAdjustModal(<?php echo $user['id']; ?>, '<?php echo clean($user['email']); ?>', <?php echo $coins; ?>)">
                                        Adjust Coins
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Transaction History Tab -->
            <div class="tab-content" id="tab-transactions">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Transactions (Last 50)</h2>
                    </div>

                    <table class="coins-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">
                                        No transactions yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $txn): ?>
                                <tr>
                                    <td><?php echo date('M d, Y g:i A', strtotime($txn['created_at'])); ?></td>
                                    <td>
                                        <?php echo clean($txn['email']); ?>
                                        <?php if ($txn['company_name']): ?>
                                            <br><small style="color: #6b7280;"><?php echo clean($txn['company_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <?php echo str_replace('_', ' ', ucfirst($txn['transaction_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="transaction-<?php echo $txn['amount'] > 0 ? 'credit' : 'debit'; ?>">
                                            <?php echo $txn['amount'] > 0 ? '+' : ''; ?><?php echo number_format($txn['amount']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo number_format($txn['balance_before']); ?> →
                                        <strong><?php echo number_format($txn['balance_after']); ?></strong>
                                    </td>
                                    <td><?php echo clean($txn['description']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Coin Pricing Tab -->
            <div class="tab-content" id="tab-pricing">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Coin Pricing Configuration</h2>
                    </div>

                    <p style="color: #6b7280; margin-bottom: 1.5rem;">
                        Configure how many coins are required for each action. These prices affect how coins are deducted from user accounts.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="update_pricing">

                        <div class="form-group">
                            <label>Video Upload Cost</label>
                            <input type="number" name="video_upload" value="<?php echo $pricing['video_upload'] ?? 10; ?>" min="0" required>
                            <small>Coins deducted each time a user uploads a video</small>
                        </div>

                        <div class="form-group">
                            <label>Storage Cost (per GB per month)</label>
                            <input type="number" name="storage_per_gb" value="<?php echo $pricing['storage_per_gb'] ?? 50; ?>" min="0" required>
                            <small>Coins deducted per GB of storage used monthly</small>
                        </div>

                        <div class="form-group">
                            <label>Streaming Cost (per hour)</label>
                            <input type="number" name="streaming_per_hour" value="<?php echo $pricing['streaming_per_hour'] ?? 5; ?>" min="0" required>
                            <small>Coins deducted per hour of streaming time</small>
                        </div>

                        <div class="form-group">
                            <label>Monthly Maintenance Fee</label>
                            <input type="number" name="monthly_maintenance" value="<?php echo $pricing['monthly_maintenance'] ?? 100; ?>" min="0" required>
                            <small>Fixed coins deducted monthly (auto-deduction)</small>
                        </div>

                        <button type="submit" class="btn">Update Pricing</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Coins Modal -->
    <div id="adjustModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Adjust Coins</h3>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="adjust_coins">
                    <input type="hidden" name="user_id" id="modalUserId">

                    <div style="background: #f3f4f6; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="color: #6b7280;">User:</span>
                            <strong id="modalUserEmail"></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #6b7280;">Current Balance:</span>
                            <strong id="modalCurrentBalance"></strong>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Transaction Type</label>
                        <select name="type" required>
                            <option value="admin_credit">Credit (Add Coins)</option>
                            <option value="admin_debit">Debit (Remove Coins)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" min="1" required placeholder="Enter amount">
                        <small>Positive number only - type is selected above</small>
                    </div>

                    <div class="form-group">
                        <label>Description / Reason</label>
                        <textarea name="description" rows="3" required placeholder="E.g., Manual credit for payment received via bank transfer"></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeAdjustModal()">Cancel</button>
                        <button type="submit" class="btn">Save Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
            });
        });

        // Show adjust modal
        function showAdjustModal(userId, userEmail, currentBalance) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUserEmail').textContent = userEmail;
            document.getElementById('modalCurrentBalance').textContent = currentBalance + ' coins';
            document.getElementById('adjustModal').classList.add('active');
        }

        // Close adjust modal
        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('adjustModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('adjustModal')) {
                closeAdjustModal();
            }
        });
    </script>
</body>
</html>
