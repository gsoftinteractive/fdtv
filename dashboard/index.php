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

// Count videos (only if station exists)
$video_count = 0;
$storage_used = 0;

if ($station) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM videos WHERE station_id = ?");
    $stmt->execute([$station['id']]);
    $video_count = $stmt->fetch()['total'];

    // Calculate storage used
    $stmt = $conn->prepare("SELECT SUM(file_size) as total_size FROM videos WHERE station_id = ?");
    $stmt->execute([$station['id']]);
    $storage_used = $stmt->fetch()['total_size'] ?? 0;
}

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
                <a href="display-settings.php">Display Settings</a>
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

            <?php
            // Coin balance warnings
            $user_coins = $user['coins'] ?? 0;
            if ($user_coins <= 0): ?>
                <div class="alert alert-danger">
                    <strong>⚠️ No Coins Remaining</strong>
                    <p>Your coin balance is empty. You cannot upload videos or access features until you purchase more coins.</p>
                    <a href="payment.php" class="btn btn-small">Buy Coins Now</a>
                </div>
            <?php elseif ($user_coins < 100): ?>
                <div class="alert alert-warning">
                    <strong>💰 Low Coin Balance</strong>
                    <p>You have only <?php echo $user_coins; ?> coins remaining. Consider purchasing more coins to avoid service interruption.</p>
                    <a href="payment.php" class="btn btn-small">Buy Coins</a>
                </div>
            <?php elseif ($user_coins < 200): ?>
                <div class="alert alert-info" style="background: #dbeafe; border-color: #3b82f6; color: #1e40af;">
                    <strong>💡 Coin Balance Low</strong>
                    <p>You have <?php echo $user_coins; ?> coins. Make sure to top up soon.</p>
                    <a href="payment.php" class="btn btn-small">Buy Coins</a>
                </div>
            <?php endif; ?>

            <?php if ($user['status'] == 'suspended'): ?>
                <div class="alert alert-danger">
                    <strong>⚠️ Station Suspended</strong>
                    <p>Your account has been suspended. Please contact support for assistance.</p>
                </div>
            <?php endif; ?>

            <?php if (!$station): ?>
                <div class="alert" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white;">
                    <h3 style="color: white; margin-top: 0;">🎬 Create Your Station</h3>
                    <p>You haven't created your station yet. Create your station to start broadcasting!</p>
                    <p><strong>Station Name:</strong> <?php echo clean($user['station_name']); ?></p>
                    <p><strong>Creation Cost:</strong> 100 coins (one-time fee)</p>
                    <a href="create-station.php" class="btn" style="background: white; color: #667eea; margin-top: 0.5rem;">
                        Create Station Now
                    </a>
                </div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <!-- Coin Balance - Prominent Display -->
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-number" style="font-size: 2.5rem; color: white;">
                        <?php
                        $user_coins = $user['coins'] ?? 0;
                        echo number_format($user_coins);
                        ?>
                        💰
                    </div>
                    <div class="stat-label" style="color: white;">Coin Balance</div>
                    <a href="payment.php" style="color: white; text-decoration: underline; font-size: 0.875rem; margin-top: 0.5rem; display: inline-block;">Buy More Coins</a>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $user['status'] == 'active' ? 'Active' : ucfirst($user['status']); ?></div>
                    <div class="stat-label">Station Status</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $video_count; ?></div>
                    <div class="stat-label">Videos Uploaded</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo format_file_size($storage_used); ?></div>
                    <div class="stat-label">Storage Used</div>
                </div>
            </div>

            <?php if ($station && $user['status'] == 'active'): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Your Station</h2>
                </div>
                <p><strong>Station Name:</strong> <?php echo clean($station['station_name']); ?></p>

                <?php
                $station_type = isset($station['station_type']) ? $station['station_type'] : 'both';
                $has_tv = in_array($station_type, ['tv', 'both']);
                $has_radio = in_array($station_type, ['radio', 'both']);
                ?>

                <?php if ($has_tv): ?>
                <p><strong>TV Station URL:</strong> <a href="<?php echo SITE_URL; ?>/station/view.php?name=<?php echo $station['slug']; ?>" target="_blank"><?php echo SITE_URL; ?>/station/<?php echo $station['slug']; ?></a></p>
                <?php endif; ?>

                <?php if ($has_radio): ?>
                <p><strong>Radio Station URL:</strong> <a href="<?php echo SITE_URL; ?>/radio/index.php?name=<?php echo $station['slug']; ?>" target="_blank"><?php echo SITE_URL; ?>/radio/<?php echo $station['slug']; ?></a></p>
                <?php endif; ?>

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