<?php
// admin/stations.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_admin_logged_in()) {
    redirect('login.php');
}

// Get all stations with video count
$stmt = $conn->query("
    SELECT s.*, u.company_name, u.email, u.status as user_status,
           COUNT(v.id) as video_count,
           SUM(v.file_size) as total_storage
    FROM stations s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN videos v ON s.id = v.station_id
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stations = $stmt->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stations - FDTV Admin</title>
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
            <h1>All Stations</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Stations (<?php echo count($stations); ?>)</h2>
                </div>

                <?php if (empty($stations)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No stations yet.
                    </p>
                <?php else: ?>

                <table>
                    <thead>
                        <tr>
                            <th>Station Name</th>
                            <th>Company</th>
                            <th>Videos</th>
                            <th>Storage</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stations as $station): ?>
                        <tr>
                            <td>
                                <strong><?php echo clean($station['station_name']); ?></strong><br>
                                <small style="color: #6b7280;">fdtv.ng/station/<?php echo $station['slug']; ?></small>
                            </td>
                            <td><?php echo clean($station['company_name']); ?></td>
                            <td><?php echo $station['video_count']; ?> / 20</td>
                            <td><?php echo format_file_size($station['total_storage'] ?? 0); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $station['status'] == 'active' && $station['user_status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo $station['status'] == 'active' && $station['user_status'] == 'active' ? 'Active' : 'Suspended'; ?>
                                </span>
                            </td>
                            <td><?php echo format_date($station['created_at']); ?></td>
                            <td>
                                <a href="../station/view.php?name=<?php echo $station['slug']; ?>" target="_blank" class="btn btn-small">View</a>
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