<?php
// admin/clients.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

// Get all clients with coin balance
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT u.*, s.id as station_id, s.slug, s.mode as station_mode,
               u.coins as coin_balance
        FROM users u
        LEFT JOIN stations s ON u.id = s.user_id";

if ($search) {
    $sql .= " WHERE u.company_name LIKE :search OR u.email LIKE :search OR u.station_name LIKE :search";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
if ($search) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$clients = $stmt->fetchAll();

// Handle suspend/activate
if (isset($_GET['toggle_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    
    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $new_status = $user['status'] == 'active' ? 'suspended' : 'active';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        set_flash("Client status updated to $new_status.", "success");
    }
    
    redirect('clients.php');
}

// Handle delete client
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];

    // This will cascade delete stations, videos, payments, subscriptions
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    set_flash("Client deleted successfully.", "success");
    redirect('clients.php');
}

// Handle toggle radio access
if (isset($_GET['toggle_radio'])) {
    $user_id = (int)$_GET['toggle_radio'];

    $stmt = $conn->prepare("SELECT radio_enabled FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $new_status = $user['radio_enabled'] ? 0 : 1;

        $stmt = $conn->prepare("UPDATE users SET radio_enabled = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);

        $status_text = $new_status ? 'enabled' : 'disabled';
        set_flash("Radio access $status_text for this client.", "success");
    }

    redirect('clients.php');
}

// Handle admin station creation
if (isset($_POST['create_station'])) {
    $user_id = (int)$_POST['user_id'];
    $admin_id = $_SESSION['admin_id'];

    if (verify_csrf_token($_POST['csrf_token'])) {

        // Get user and station data
        $stmt = $conn->prepare("SELECT u.*, s.id as station_id FROM users u LEFT JOIN stations s ON u.id = s.user_id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if station already exists
            if ($user['station_id']) {
                set_flash("User already has a station.", "warning");
            } else {
                // Get station creation cost
                $stmt = $conn->prepare("SELECT coins_required FROM coin_pricing WHERE action_type = 'station_creation' AND is_active = 1");
                $stmt->execute();
                $pricing = $stmt->fetch();
                $creation_cost = $pricing['coins_required'] ?? 100;

                // Check if user has enough coins
                if ($user['coins'] < $creation_cost) {
                    set_flash("User has insufficient coins. They have {$user['coins']} coins but need {$creation_cost} coins.", "danger");
                } else {

                    $conn->beginTransaction();

                    try {
                        $station_type = $_POST['station_type'] ?? 'tv';

                        // Create station
                        $stmt = $conn->prepare("INSERT INTO stations (user_id, station_name, slug, station_type, status) VALUES (?, ?, ?, ?, 'active')");
                        $stmt->execute([$user_id, $user['station_name'], $user['station_slug'], $station_type]);
                        $station_id = $conn->lastInsertId();

                        // Deduct coins
                        $balance_before = $user['coins'];
                        $balance_after = $balance_before - $creation_cost;

                        $stmt = $conn->prepare("UPDATE users SET coins = ?, coins_updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$balance_after, $user_id]);

                        // Record transaction
                        $stmt = $conn->prepare("INSERT INTO coin_transactions
                            (user_id, amount, transaction_type, description, balance_before, balance_after, created_by, reference)
                            VALUES (?, ?, 'system', ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $user_id,
                            -$creation_cost,
                            "Station created by admin: {$user['station_name']}",
                            $balance_before,
                            $balance_after,
                            $admin_id,
                            "ADMIN_STATION_CREATE_{$station_id}"
                        ]);

                        $conn->commit();

                        // Send email notification
                        $message = "
                            <h2>Station Created!</h2>
                            <p>Dear {$user['company_name']},</p>
                            <p>Your station <strong>{$user['station_name']}</strong> has been created by our admin team.</p>
                            <p><strong>Station Type:</strong> " . strtoupper($station_type) . "</p>
                            <p><strong>Coins Deducted:</strong> {$creation_cost}</p>
                            <p><strong>Remaining Balance:</strong> {$balance_after} coins</p>
                            <p><strong>Your Station URL:</strong> " . SITE_URL . "/station/view.php?name={$user['station_slug']}</p>
                            <p>Login to start uploading and broadcasting!</p>
                        ";
                        send_email($user['email'], "Station Created - FDTV", $message);

                        set_flash("Station created successfully! {$creation_cost} coins deducted from user.", "success");

                    } catch (Exception $e) {
                        $conn->rollBack();
                        set_flash("Error creating station: " . $e->getMessage(), "danger");
                    }
                }
            }
        }
    }

    redirect('clients.php');
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients - FDTV Admin</title>
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
            <h1>Manage Clients</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Clients (<?php echo count($clients); ?>)</h2>
                </div>

                <form method="GET" style="margin-bottom: 1.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="text" name="search" placeholder="Search by company, email, or station..." value="<?php echo clean($search); ?>" style="max-width: 400px;">
                    </div>
                </form>

                <?php if (empty($clients)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No clients found.
                    </p>
                <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th>Company / Station</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Coins</th>
                            <th>Status</th>
                            <th>Features</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <strong><?php echo clean($client['company_name']); ?></strong><br>
                                <small style="color: #6b7280;"><?php echo clean($client['station_name']); ?></small>
                                <?php if (!$client['station_id']): ?>
                                    <br><span class="badge badge-warning" style="font-size: 0.65rem;">NO STATION</span>
                                <?php elseif (isset($client['station_mode']) && $client['station_mode'] == 'live'): ?>
                                    <br><span class="badge badge-info" style="font-size: 0.65rem;">LIVE MODE</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo clean($client['email']); ?></td>
                            <td><?php echo clean($client['phone']); ?></td>
                            <td>
                                <strong><?php echo number_format($client['coin_balance'] ?? 0); ?></strong>
                                <small style="color: #6b7280;">coins</small>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $client['status'] == 'active' ? 'success' : ($client['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                    <?php echo ucfirst($client['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo (!empty($client['radio_enabled']) && $client['radio_enabled']) ? 'success' : 'secondary'; ?>" style="font-size: 0.7rem;">
                                    Radio: <?php echo (!empty($client['radio_enabled']) && $client['radio_enabled']) ? 'ON' : 'OFF'; ?>
                                </span>
                            </td>
                            <td><?php echo format_date($client['created_at']); ?></td>
                            <td>
                                <?php if ($client['slug']): ?>
                                    <a href="../station/view.php?name=<?php echo $client['slug']; ?>" target="_blank" class="btn btn-small">View</a>
                                <?php else: ?>
                                    <button onclick="createStation(<?php echo $client['id']; ?>, '<?php echo clean($client['company_name']); ?>', <?php echo $client['coin_balance'] ?? 0; ?>)"
                                            class="btn btn-success btn-small"
                                            <?php echo ($client['coin_balance'] < 100) ? 'disabled title="Insufficient coins (need 100)"' : ''; ?>>
                                        Create Station
                                    </button>
                                <?php endif; ?>

                                <a href="?toggle_radio=<?php echo $client['id']; ?>"
                                   class="btn btn-small <?php echo (!empty($client['radio_enabled']) && $client['radio_enabled']) ? 'btn-warning' : 'btn-secondary'; ?>"
                                   title="<?php echo (!empty($client['radio_enabled']) && $client['radio_enabled']) ? 'Disable Radio' : 'Enable Radio'; ?>">
                                    <?php echo (!empty($client['radio_enabled']) && $client['radio_enabled']) ? '📻 Off' : '📻 On'; ?>
                                </a>

                                <a href="?toggle_status=<?php echo $client['id']; ?>"
                                   class="btn btn-small <?php echo $client['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                    <?php echo $client['status'] == 'active' ? 'Suspend' : 'Activate'; ?>
                                </a>

                                <a href="?delete=<?php echo $client['id']; ?>"
                                   onclick="return confirm('Delete this client and all their data?')"
                                   class="btn btn-danger btn-small">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Station Modal -->
    <div id="createStationModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:2rem; border-radius:8px; max-width:500px; width:90%;">
            <h3>Create Station for Client</h3>
            <p id="clientInfo" style="margin:1rem 0; padding:1rem; background:#f9fafb; border-radius:4px;"></p>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="user_id" id="create_user_id">

                <div class="form-group">
                    <label>Station Type *</label>
                    <select name="station_type" required>
                        <option value="tv">TV Station (Video Broadcasting)</option>
                        <option value="radio">Radio Station (Audio Only)</option>
                        <option value="both">Both TV & Radio</option>
                    </select>
                </div>

                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px;">
                    <strong style="color: #92400e;">⚠️ Important:</strong>
                    <p style="color: #92400e; margin-top: 0.5rem; margin-bottom: 0;">
                        Creating this station will deduct 100 coins from the user's account. Make sure they have enough coins.
                    </p>
                </div>

                <div style="display:flex; gap:1rem;">
                    <button type="submit" name="create_station" class="btn btn-success">Create Station (100 coins)</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function createStation(userId, companyName, coinBalance) {
        document.getElementById('create_user_id').value = userId;
        document.getElementById('clientInfo').innerHTML = `
            <strong>Company:</strong> ${companyName}<br>
            <strong>Current Coins:</strong> ${coinBalance.toLocaleString()}<br>
            <strong>After Creation:</strong> ${(coinBalance - 100).toLocaleString()} coins
        `;
        document.getElementById('createStationModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('createStationModal').style.display = 'none';
    }
    </script>
</body>
</html>