<?php
// dashboard/station.php - Station Settings (Logo, Live Feed, Mode)

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

// Check if user is active
if ($user['status'] != 'active') {
    set_flash('Your account must be active to manage station settings.', 'warning');
    redirect('index.php');
}

// Get station data
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

if (!$station) {
    set_flash('Station not found.', 'danger');
    redirect('index.php');
}

// Get live feeds for this station
$stmt = $conn->prepare("SELECT * FROM live_feeds WHERE station_id = ? ORDER BY created_at DESC");
$stmt->execute([$station['id']]);
$live_feeds = $stmt->fetchAll();

// Handle form submissions
$errors = [];
$success = '';

// Handle logo upload
if (isset($_POST['action']) && $_POST['action'] == 'upload_logo') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB

            $file = $_FILES['logo'];
            $file_type = mime_content_type($file['tmp_name']);

            if (!in_array($file_type, $allowed_types)) {
                $errors[] = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP';
            } elseif ($file['size'] > $max_size) {
                $errors[] = 'File too large. Maximum size is 2MB.';
            } else {
                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . $station['id'] . '_' . time() . '.' . $ext;
                $upload_dir = '../uploads/logos/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Delete old logo if exists
                if ($station['logo_path'] && file_exists($upload_dir . $station['logo_path'])) {
                    unlink($upload_dir . $station['logo_path']);
                }

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $stmt = $conn->prepare("UPDATE stations SET logo = ?, logo_path = ? WHERE id = ?");
                    $stmt->execute([$filename, $filename, $station['id']]);
                    $station['logo'] = $filename;
                    $station['logo_path'] = $filename;
                    $success = 'Logo uploaded successfully!';
                } else {
                    $errors[] = 'Failed to upload file.';
                }
            }
        } else {
            $errors[] = 'Please select a logo file to upload.';
        }
    }
}

// Handle logo settings update
if (isset($_POST['action']) && $_POST['action'] == 'update_logo_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $position = $_POST['logo_position'] ?? 'top-right';
        $opacity = floatval($_POST['logo_opacity'] ?? 0.9);
        $size = $_POST['logo_size'] ?? 'medium';

        // Validate
        $valid_positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
        $valid_sizes = ['small', 'medium', 'large'];

        if (!in_array($position, $valid_positions)) $position = 'top-right';
        if (!in_array($size, $valid_sizes)) $size = 'medium';
        if ($opacity < 0.1 || $opacity > 1) $opacity = 0.9;

        $stmt = $conn->prepare("UPDATE stations SET logo_position = ?, logo_opacity = ?, logo_size = ? WHERE id = ?");
        $stmt->execute([$position, $opacity, $size, $station['id']]);

        $station['logo_position'] = $position;
        $station['logo_opacity'] = $opacity;
        $station['logo_size'] = $size;

        $success = 'Logo settings updated!';
    }
}

// Handle mode toggle
if (isset($_POST['action']) && $_POST['action'] == 'update_mode') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $mode = $_POST['mode'] ?? 'playlist';
        if (!in_array($mode, ['playlist', 'live'])) $mode = 'playlist';

        $stmt = $conn->prepare("UPDATE stations SET mode = ? WHERE id = ?");
        $stmt->execute([$mode, $station['id']]);

        $station['mode'] = $mode;
        $success = 'Broadcast mode updated to ' . ucfirst($mode) . '!';
    }
}

// Handle add live feed
if (isset($_POST['action']) && $_POST['action'] == 'add_live_feed') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $feed_name = trim($_POST['feed_name'] ?? '');
        $source_url = trim($_POST['source_url'] ?? '');

        if (empty($feed_name)) {
            $errors[] = 'Feed name is required.';
        } elseif (empty($source_url)) {
            $errors[] = 'Source URL is required.';
        } else {
            // Detect source type
            $source_type = detect_live_feed_type($source_url);

            $stmt = $conn->prepare("INSERT INTO live_feeds (station_id, name, source_type, source_url, is_active) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$station['id'], $feed_name, $source_type, $source_url]);

            $success = 'Live feed added successfully!';

            // Refresh feeds list
            $stmt = $conn->prepare("SELECT * FROM live_feeds WHERE station_id = ? ORDER BY created_at DESC");
            $stmt->execute([$station['id']]);
            $live_feeds = $stmt->fetchAll();
        }
    }
}

// Handle activate/deactivate live feed
if (isset($_GET['toggle_feed'])) {
    $feed_id = (int)$_GET['toggle_feed'];

    // First deactivate all feeds for this station
    $stmt = $conn->prepare("UPDATE live_feeds SET is_active = 0 WHERE station_id = ?");
    $stmt->execute([$station['id']]);

    // Then activate the selected one (if it was inactive)
    $stmt = $conn->prepare("SELECT is_active FROM live_feeds WHERE id = ? AND station_id = ?");
    $stmt->execute([$feed_id, $station['id']]);
    $feed = $stmt->fetch();

    if ($feed) {
        // Toggle: if it was active (now deactivated), leave it off; if it was inactive, activate it
        $stmt = $conn->prepare("UPDATE live_feeds SET is_active = 1 WHERE id = ? AND station_id = ?");
        $stmt->execute([$feed_id, $station['id']]);
    }

    set_flash('Live feed status updated.', 'success');
    redirect('station.php');
}

// Handle delete live feed
if (isset($_GET['delete_feed'])) {
    $feed_id = (int)$_GET['delete_feed'];

    $stmt = $conn->prepare("DELETE FROM live_feeds WHERE id = ? AND station_id = ?");
    $stmt->execute([$feed_id, $station['id']]);

    set_flash('Live feed deleted.', 'success');
    redirect('station.php');
}

// Handle playlist settings update
if (isset($_POST['action']) && $_POST['action'] == 'update_playlist_settings') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $playlist_mode = $_POST['playlist_mode'] ?? 'sequential';
        $jingle_enabled = isset($_POST['jingle_enabled']) ? 1 : 0;
        $advert_enabled = isset($_POST['advert_enabled']) ? 1 : 0;
        $default_jingle_interval = $_POST['default_jingle_interval'] ?? 'every_5min';
        $default_advert_interval = $_POST['default_advert_interval'] ?? 'every_15min';

        // Validate playlist mode
        $allowed_modes = ['sequential', 'shuffle', 'priority', 'scheduled'];
        if (!in_array($playlist_mode, $allowed_modes)) {
            $playlist_mode = 'sequential';
        }

        // Validate time-based intervals
        $allowed_intervals = ['now', 'every_1min', 'every_2min', 'every_5min', 'every_15min', 'every_30min', 'every_hour'];
        if (!in_array($default_jingle_interval, $allowed_intervals)) {
            $default_jingle_interval = 'every_5min';
        }
        if (!in_array($default_advert_interval, $allowed_intervals)) {
            $default_advert_interval = 'every_15min';
        }

        $stmt = $conn->prepare("UPDATE stations SET playlist_mode = ?, jingle_enabled = ?, advert_enabled = ?, default_jingle_interval = ?, default_advert_interval = ? WHERE id = ?");
        $stmt->execute([$playlist_mode, $jingle_enabled, $advert_enabled, $default_jingle_interval, $default_advert_interval, $station['id']]);

        // Refresh station data
        $station['playlist_mode'] = $playlist_mode;
        $station['jingle_enabled'] = $jingle_enabled;
        $station['advert_enabled'] = $advert_enabled;
        $station['default_jingle_interval'] = $default_jingle_interval;
        $station['default_advert_interval'] = $default_advert_interval;

        $success = 'Playlist settings updated!';
    }
}

// Helper function to detect live feed type
function detect_live_feed_type($url) {
    $url_lower = strtolower($url);

    if (strpos($url_lower, 'youtube.com') !== false || strpos($url_lower, 'youtu.be') !== false) {
        return 'youtube';
    } elseif (strpos($url_lower, 'facebook.com') !== false || strpos($url_lower, 'fb.watch') !== false) {
        return 'facebook';
    } elseif (strpos($url_lower, 'vimeo.com') !== false) {
        return 'vimeo';
    } elseif (preg_match('/\.m3u8(\?|$)/i', $url_lower)) {
        return 'hls';
    } elseif (preg_match('/\.mp4(\?|$)/i', $url_lower)) {
        return 'mp4';
    } else {
        return 'iframe';
    }
}

$flash = get_flash();
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Settings - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .logo-preview {
            width: 200px;
            height: 100px;
            border: 2px dashed #374151;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 1rem 0;
            background: #1f2937;
            position: relative;
            overflow: hidden;
        }

        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .logo-preview .placeholder {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .position-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            max-width: 300px;
        }

        .position-btn {
            padding: 0.75rem;
            border: 2px solid #374151;
            background: #1f2937;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .position-btn:hover {
            border-color: #7c3aed;
        }

        .position-btn.active {
            border-color: #7c3aed;
            background: rgba(124, 58, 237, 0.2);
        }

        .mode-toggle {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
        }

        .mode-option {
            flex: 1;
            padding: 1.5rem;
            border: 2px solid #374151;
            background: #1f2937;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .mode-option:hover {
            border-color: #7c3aed;
        }

        .mode-option.active {
            border-color: #7c3aed;
            background: rgba(124, 58, 237, 0.2);
        }

        .mode-option h3 {
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }

        .mode-option p {
            color: #9ca3af;
            font-size: 0.875rem;
            margin: 0;
        }

        .feed-list {
            margin-top: 1rem;
        }

        .feed-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #1f2937;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .feed-info {
            flex: 1;
        }

        .feed-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feed-type {
            font-size: 0.75rem;
            color: #9ca3af;
            text-transform: uppercase;
        }

        .feed-url {
            font-size: 0.75rem;
            color: #6b7280;
            word-break: break-all;
            max-width: 300px;
        }

        .feed-actions {
            display: flex;
            gap: 0.5rem;
        }

        .slider-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .slider-container input[type="range"] {
            flex: 1;
            max-width: 200px;
        }

        .slider-value {
            min-width: 50px;
            text-align: center;
            background: #374151;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .size-options {
            display: flex;
            gap: 0.5rem;
        }

        .size-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #374151;
            background: #1f2937;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .size-btn:hover {
            border-color: #7c3aed;
        }

        .size-btn.active {
            border-color: #7c3aed;
            background: rgba(124, 58, 237, 0.2);
        }

        .section-divider {
            border-top: 1px solid #374151;
            margin: 2rem 0;
            padding-top: 2rem;
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .mode-toggle {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">FDTV</a>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="videos.php">Videos</a>
                <a href="jingles.php">Jingles</a>
                <a href="station.php" class="active">Station</a>
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
            <h1>Station Settings</h1>
            <p style="color: #9ca3af; margin-bottom: 1.5rem;">Configure your station's appearance and broadcast mode.</p>

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

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo clean($success); ?>
                </div>
            <?php endif; ?>

            <!-- Broadcast Mode Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Broadcast Mode</h2>
                </div>
                <p style="color: #9ca3af; margin-bottom: 1rem;">Choose how your station broadcasts content.</p>

                <form method="POST" id="modeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_mode">
                    <input type="hidden" name="mode" id="modeInput" value="<?php echo $station['mode'] ?? 'playlist'; ?>">

                    <div class="mode-toggle">
                        <div class="mode-option <?php echo ($station['mode'] ?? 'playlist') == 'playlist' ? 'active' : ''; ?>" onclick="selectMode('playlist')">
                            <h3>Playlist Mode</h3>
                            <p>Play uploaded videos in rotation based on your schedule</p>
                        </div>
                        <div class="mode-option <?php echo ($station['mode'] ?? 'playlist') == 'live' ? 'active' : ''; ?>" onclick="selectMode('live')">
                            <h3>Live Feed Mode</h3>
                            <p>Stream live content from YouTube, Facebook, or other sources</p>
                        </div>
                    </div>
                </form>
            </div>

            <div class="settings-grid">
                <!-- Logo/Watermark Section -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Station Logo / Watermark</h2>
                    </div>

                    <!-- Current Logo -->
                    <div class="logo-preview">
                        <?php if ($station['logo_path']): ?>
                            <img src="../uploads/logos/<?php echo clean($station['logo_path']); ?>" alt="Station Logo">
                        <?php else: ?>
                            <span class="placeholder">No logo uploaded</span>
                        <?php endif; ?>
                    </div>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 1.5rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="upload_logo">

                        <div class="form-group">
                            <label>Upload New Logo</label>
                            <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small style="color: #6b7280;">Max 2MB. Recommended: PNG with transparent background, 200x100px</small>
                        </div>

                        <button type="submit" class="btn">Upload Logo</button>
                    </form>

                    <div class="section-divider"></div>

                    <!-- Logo Settings Form -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_logo_settings">

                        <div class="form-group">
                            <label>Position on Screen</label>
                            <div class="position-grid">
                                <button type="button" class="position-btn <?php echo ($station['logo_position'] ?? 'top-right') == 'top-left' ? 'active' : ''; ?>" onclick="setPosition('top-left')">Top Left</button>
                                <button type="button" class="position-btn <?php echo ($station['logo_position'] ?? 'top-right') == 'top-right' ? 'active' : ''; ?>" onclick="setPosition('top-right')">Top Right</button>
                                <button type="button" class="position-btn <?php echo ($station['logo_position'] ?? 'top-right') == 'bottom-left' ? 'active' : ''; ?>" onclick="setPosition('bottom-left')">Bottom Left</button>
                                <button type="button" class="position-btn <?php echo ($station['logo_position'] ?? 'top-right') == 'bottom-right' ? 'active' : ''; ?>" onclick="setPosition('bottom-right')">Bottom Right</button>
                            </div>
                            <input type="hidden" name="logo_position" id="logoPosition" value="<?php echo $station['logo_position'] ?? 'top-right'; ?>">
                        </div>

                        <div class="form-group">
                            <label>Opacity</label>
                            <div class="slider-container">
                                <input type="range" name="logo_opacity" id="logoOpacity" min="0.1" max="1" step="0.1" value="<?php echo $station['logo_opacity'] ?? 0.9; ?>">
                                <span class="slider-value" id="opacityValue"><?php echo ($station['logo_opacity'] ?? 0.9) * 100; ?>%</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Size</label>
                            <div class="size-options">
                                <button type="button" class="size-btn <?php echo ($station['logo_size'] ?? 'medium') == 'small' ? 'active' : ''; ?>" onclick="setSize('small')">Small</button>
                                <button type="button" class="size-btn <?php echo ($station['logo_size'] ?? 'medium') == 'medium' ? 'active' : ''; ?>" onclick="setSize('medium')">Medium</button>
                                <button type="button" class="size-btn <?php echo ($station['logo_size'] ?? 'medium') == 'large' ? 'active' : ''; ?>" onclick="setSize('large')">Large</button>
                            </div>
                            <input type="hidden" name="logo_size" id="logoSize" value="<?php echo $station['logo_size'] ?? 'medium'; ?>">
                        </div>

                        <button type="submit" class="btn">Save Logo Settings</button>
                    </form>
                </div>

                <!-- Live Feeds Section -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Live Feeds</h2>
                    </div>
                    <p style="color: #9ca3af; margin-bottom: 1rem;">Add external live streams to broadcast when in Live Feed Mode.</p>

                    <!-- Add Feed Form -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add_live_feed">

                        <div class="form-group">
                            <label>Feed Name</label>
                            <input type="text" name="feed_name" placeholder="e.g., Main YouTube Stream" required>
                        </div>

                        <div class="form-group">
                            <label>Source URL</label>
                            <input type="url" name="source_url" placeholder="https://youtube.com/watch?v=... or .m3u8 URL" required>
                            <small style="color: #6b7280;">Supports: YouTube, Facebook, Vimeo, HLS (.m3u8), MP4, or iframe embeds</small>
                        </div>

                        <button type="submit" class="btn">Add Live Feed</button>
                    </form>

                    <!-- Feeds List -->
                    <div class="feed-list">
                        <?php if (empty($live_feeds)): ?>
                            <p style="color: #6b7280; text-align: center; padding: 2rem;">No live feeds configured yet.</p>
                        <?php else: ?>
                            <?php foreach ($live_feeds as $feed): ?>
                                <div class="feed-item">
                                    <div class="feed-info">
                                        <div class="feed-name">
                                            <?php echo clean($feed['name']); ?>
                                            <?php if ($feed['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="feed-type"><?php echo strtoupper($feed['source_type']); ?></div>
                                        <div class="feed-url"><?php echo clean(substr($feed['source_url'], 0, 50)) . (strlen($feed['source_url']) > 50 ? '...' : ''); ?></div>
                                    </div>
                                    <div class="feed-actions">
                                        <a href="?toggle_feed=<?php echo $feed['id']; ?>" class="btn btn-small <?php echo $feed['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                            <?php echo $feed['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                        <a href="?delete_feed=<?php echo $feed['id']; ?>" onclick="return confirm('Delete this feed?')" class="btn btn-small btn-danger">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Playlist Settings -->
            <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">Playlist Engine Settings</h2>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="update_playlist_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <!-- Playlist Mode -->
                        <div class="form-group">
                            <label for="playlist_mode">Playlist Mode</label>
                            <select name="playlist_mode" id="playlist_mode">
                                <option value="sequential" <?php echo ($station['playlist_mode'] ?? 'sequential') == 'sequential' ? 'selected' : ''; ?>>Sequential (In Order)</option>
                                <option value="shuffle" <?php echo ($station['playlist_mode'] ?? '') == 'shuffle' ? 'selected' : ''; ?>>Shuffle (Random)</option>
                                <option value="priority" <?php echo ($station['playlist_mode'] ?? '') == 'priority' ? 'selected' : ''; ?>>Priority Based</option>
                                <option value="scheduled" <?php echo ($station['playlist_mode'] ?? '') == 'scheduled' ? 'selected' : ''; ?>>Scheduled Only</option>
                            </select>
                            <small style="color: #6b7280; display: block; margin-top: 0.25rem;">How videos are selected for playback</small>
                        </div>

                        <!-- Jingle Interval -->
                        <div class="form-group">
                            <label for="default_jingle_interval">Jingle Interval ⏱️</label>
                            <select name="default_jingle_interval" id="default_jingle_interval">
                                <?php
                                $jingle_intervals = [
                                    'now' => 'Play Now (Manual)',
                                    'every_1min' => 'Every 1 Minute',
                                    'every_2min' => 'Every 2 Minutes',
                                    'every_5min' => 'Every 5 Minutes (Recommended)',
                                    'every_15min' => 'Every 15 Minutes',
                                    'every_30min' => 'Every 30 Minutes',
                                    'every_hour' => 'Every Hour'
                                ];
                                $current_jingle = $station['default_jingle_interval'] ?? 'every_5min';
                                foreach ($jingle_intervals as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php echo $current_jingle === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #6b7280; display: block; margin-top: 0.25rem;">⏰ How often station jingles/IDs play (time-based)</small>
                        </div>

                        <!-- Advert Interval -->
                        <div class="form-group">
                            <label for="default_advert_interval">Advert Interval 📺</label>
                            <select name="default_advert_interval" id="default_advert_interval">
                                <?php
                                $advert_intervals = [
                                    'now' => 'Play Now (Manual)',
                                    'every_1min' => 'Every 1 Minute',
                                    'every_2min' => 'Every 2 Minutes',
                                    'every_5min' => 'Every 5 Minutes',
                                    'every_15min' => 'Every 15 Minutes (Recommended)',
                                    'every_30min' => 'Every 30 Minutes',
                                    'every_hour' => 'Every Hour'
                                ];
                                $current_advert = $station['default_advert_interval'] ?? 'every_15min';
                                foreach ($advert_intervals as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" <?php echo $current_advert === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #6b7280; display: block; margin-top: 0.25rem;">💰 How often adverts play (time-based)</small>
                        </div>
                    </div>

                    <div style="display: flex; gap: 2rem; margin: 1.5rem 0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="jingle_enabled" <?php echo ($station['jingle_enabled'] ?? 1) ? 'checked' : ''; ?> style="width: auto;">
                            <span>Enable Jingles & Station IDs</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="advert_enabled" <?php echo ($station['advert_enabled'] ?? 0) ? 'checked' : ''; ?> style="width: auto;">
                            <span>Enable Advertisements</span>
                        </label>
                    </div>

                    <button type="submit" class="btn">Save Playlist Settings</button>
                </form>

                <!-- Priority Levels Reference -->
                <div style="margin-top: 2rem; padding: 1rem; background: #f9fafb; border-radius: 8px;">
                    <h4 style="margin-bottom: 0.75rem;">Priority Levels Reference</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; font-size: 0.875rem;">
                        <div><strong>P1:</strong> Emergency/Breaking - Always plays first</div>
                        <div><strong>P2:</strong> Scheduled Programs - Time-sensitive</div>
                        <div><strong>P3:</strong> Regular Content - Normal rotation</div>
                        <div><strong>P4:</strong> Filler Content - Plays when queue empty</div>
                        <div><strong>P5:</strong> Low Priority - Rarely plays</div>
                        <div><strong>P6:</strong> Archive - On-demand only</div>
                    </div>
                </div>
            </div>

            <!-- Preview Link -->
            <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <h2 class="card-title">Preview Your Station</h2>
                </div>
                <p style="margin-bottom: 1rem;">See how your settings look on the live broadcast.</p>
                <a href="../station/view.php?name=<?php echo clean($station['slug']); ?>" target="_blank" class="btn">Open Station Preview</a>
            </div>
        </div>
    </div>

    <script>
        // Mode selection
        function selectMode(mode) {
            document.getElementById('modeInput').value = mode;
            document.querySelectorAll('.mode-option').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('modeForm').submit();
        }

        // Position selection
        function setPosition(position) {
            document.getElementById('logoPosition').value = position;
            document.querySelectorAll('.position-btn').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }

        // Size selection
        function setSize(size) {
            document.getElementById('logoSize').value = size;
            document.querySelectorAll('.size-btn').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }

        // Opacity slider
        document.getElementById('logoOpacity').addEventListener('input', function() {
            document.getElementById('opacityValue').textContent = Math.round(this.value * 100) + '%';
        });
    </script>
</body>
</html>
