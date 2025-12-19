<?php
// dashboard/index.php

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

// Check if logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get station data
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

// Get subscription data
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
$stmt->execute([$user_id]);
$subscription = $stmt->fetch();

// Count videos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM videos WHERE station_id = ?");
$stmt->execute([$station['id']]);
$video_count = $stmt->fetch()['total'];

// Calculate storage used
$stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM videos WHERE station_id = ?");
$stmt->execute([$station['id']]);
$storage_used = $stmt->fetch()['total_size'] ?? 0;

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FDTV</title>
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
                <a href="payment.php">Payment</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <h1>Welcome, <?php echo clean($user['company_name']); ?>!</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($user['status'] == 'pending'): ?>
                <div class="alert alert-warning">
                    <strong>Station Pending Activation</strong>
                    <p>Please make payment of ₦40,000 to activate your station.</p>
                    <a href="payment.php" class="btn btn-small">Make Payment</a>
                </div>
            <?php elseif ($user['status'] == 'suspended'): ?>
                <div class="alert alert-danger">
                    <strong>Station Suspended</strong>
                    <p>Your subscription has expired. Please renew to continue.</p>
                    <a href="payment.php" class="btn btn-small">Renew Now</a>
                </div>
            <?php elseif ($subscription && is_subscription_expired($subscription['end_date'])): ?>
                <div class="alert alert-warning">
                    <strong>Subscription Expired</strong>
                    <p>Your subscription expired on <?php echo format_date($subscription['end_date']); ?>. Please renew.</p>
                    <a href="payment.php" class="btn btn-small">Renew Now</a>
                </div>
            <?php elseif ($subscription && days_until($subscription['end_date']) <= 7): ?>
                <div class="alert alert-warning">
                    <strong>Subscription Expiring Soon</strong>
                    <p>Your subscription expires in <?php echo days_until($subscription['end_date']); ?> days on <?php echo format_date($subscription['end_date']); ?>.</p>
                    <a href="payment.php" class="btn btn-small">Renew Now</a>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user['status'] == 'active' ? 'Active' : ucfirst($user['status']); ?></div>
                    <div class="stat-label">Station Status</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $video_count; ?> / 20</div>
                    <div class="stat-label">Videos Uploaded</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo format_file_size($storage_used); ?></div>
                    <div class="stat-label">Storage Used</div>
                </div>

                <?php if ($subscription && $user['status'] == 'active'): ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo format_date($subscription['end_date']); ?></div>
                    <div class="stat-label">Subscription Expires</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($station && $user['status'] == 'active'): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Your Station</h2>
                </div>
                <p><strong>Station Name:</strong> <?php echo clean($station['station_name']); ?></p>
                <p><strong>Station URL:</strong> <a href="<?php echo SITE_URL; ?>/station/view.php?name=<?php echo $station['slug']; ?>" target="_blank"><?php echo SITE_URL; ?>/station/<?php echo $station['slug']; ?></a></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php echo $station['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($station['status']); ?></span></p>
                
                <div style="margin-top: 1.5rem;">
                    <a href="../station/view.php?name=<?php echo $station['slug']; ?>" target="_blank" class="btn">View Station</a>
                    <a href="videos.php" class="btn btn-secondary">Manage Videos</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="videos.php" class="btn">Upload Videos</a>
                    <a href="schedule.php" class="btn btn-secondary">Manage Schedule</a>
                    <a href="payment.php" class="btn btn-secondary">View Payments</a>
                    <a href="profile.php" class="btn btn-secondary">Edit Profile</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>