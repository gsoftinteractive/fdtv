<?php
// admin/clients.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

// Get all clients
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT u.*, s.id as station_id, s.slug, s.mode as station_mode
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
                                <?php if (isset($client['station_mode']) && $client['station_mode'] == 'live'): ?>
                                    <br><span class="badge badge-info" style="font-size: 0.65rem;">LIVE MODE</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo clean($client['email']); ?></td>
                            <td><?php echo clean($client['phone']); ?></td>
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
</body>
</html>