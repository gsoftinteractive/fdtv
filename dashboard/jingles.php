<?php
// dashboard/jingles.php - Jingles & Adverts Management

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user is active
if ($user['status'] !== 'active') {
    set_flash("Your account is not active. Please make payment to activate.", "warning");
    redirect('payment.php');
}

// Get station
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

if (!$station) {
    set_flash("Station not created yet. Please contact admin.", "danger");
    redirect('index.php');
}

$station_id = $station['id'];
$errors = [];

// Handle jingle upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_jingle'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $title = trim($_POST['title']);
        $jingle_type = $_POST['jingle_type'];
        $priority = (int)$_POST['priority'];
        $play_frequency = $_POST['play_frequency'];

        // Validate
        if (empty($title)) {
            $errors[] = "Title is required.";
        }

        $allowed_types = ['station_id', 'jingle', 'advert', 'sponsor'];
        if (!in_array($jingle_type, $allowed_types)) {
            $errors[] = "Invalid jingle type.";
        }

        // Handle file upload
        if (!isset($_FILES['jingle_file']) || $_FILES['jingle_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Please select a video file.";
        } else {
            $file = $_FILES['jingle_file'];
            $allowed_extensions = ['mp4', 'mkv', 'avi', 'mov', 'webm'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowed_extensions)) {
                $errors[] = "Invalid file type. Allowed: " . implode(', ', $allowed_extensions);
            }

            // Max 100MB for jingles
            if ($file['size'] > 104857600) {
                $errors[] = "File too large. Maximum 100MB for jingles.";
            }
        }

        if (empty($errors)) {
            // Create jingles directory
            $jingle_dir = '../uploads/jingles/' . $station_id . '/';
            if (!file_exists($jingle_dir)) {
                mkdir($jingle_dir, 0755, true);
            }

            // Generate safe filename
            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $filename = $safe_name . '_' . time() . '.' . $extension;

            if (move_uploaded_file($file['tmp_name'], $jingle_dir . $filename)) {
                $stmt = $conn->prepare("INSERT INTO jingles (station_id, title, filename, file_size, jingle_type, priority, play_frequency) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$station_id, $title, $filename, $file['size'], $jingle_type, $priority, $play_frequency]);

                set_flash("Jingle added successfully!", "success");
                redirect('jingles.php');
            } else {
                $errors[] = "Failed to upload file.";
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf'])) {
    if (verify_csrf_token($_GET['csrf'])) {
        $jingle_id = (int)$_GET['delete'];

        $stmt = $conn->prepare("SELECT * FROM jingles WHERE id = ? AND station_id = ?");
        $stmt->execute([$jingle_id, $station_id]);
        $jingle = $stmt->fetch();

        if ($jingle) {
            // Delete file
            $file_path = '../uploads/jingles/' . $station_id . '/' . $jingle['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            $stmt = $conn->prepare("DELETE FROM jingles WHERE id = ?");
            $stmt->execute([$jingle_id]);

            set_flash("Jingle deleted successfully!", "success");
        }
    }
    redirect('jingles.php');
}

// Handle toggle active
if (isset($_GET['toggle']) && isset($_GET['csrf'])) {
    if (verify_csrf_token($_GET['csrf'])) {
        $jingle_id = (int)$_GET['toggle'];

        $stmt = $conn->prepare("SELECT is_active FROM jingles WHERE id = ? AND station_id = ?");
        $stmt->execute([$jingle_id, $station_id]);
        $jingle = $stmt->fetch();

        if ($jingle) {
            $new_status = $jingle['is_active'] ? 0 : 1;
            $stmt = $conn->prepare("UPDATE jingles SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $jingle_id]);

            set_flash("Jingle status updated!", "success");
        }
    }
    redirect('jingles.php');
}

// Get all jingles grouped by type
$stmt = $conn->prepare("SELECT * FROM jingles WHERE station_id = ? ORDER BY jingle_type, priority, created_at DESC");
$stmt->execute([$station_id]);
$all_jingles = $stmt->fetchAll();

$jingles_by_type = [];
foreach ($all_jingles as $jingle) {
    $jingles_by_type[$jingle['jingle_type']][] = $jingle;
}

$flash = get_flash();
$csrf_token = generate_csrf_token();

// Type labels and colors
$type_labels = [
    'station_id' => ['label' => 'Station ID', 'badge' => 'success', 'icon' => '📺'],
    'jingle' => ['label' => 'Jingle', 'badge' => 'info', 'icon' => '🎵'],
    'advert' => ['label' => 'Advertisement', 'badge' => 'warning', 'icon' => '📢'],
    'sponsor' => ['label' => 'Sponsor', 'badge' => 'secondary', 'icon' => '💰']
];

$frequency_labels = [
    'every_video' => 'After Every Video',
    'every_2_videos' => 'Every 2 Videos',
    'every_3_videos' => 'Every 3 Videos',
    'every_5_videos' => 'Every 5 Videos',
    'hourly' => 'Hourly',
    'custom' => 'Custom'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jingles & Adverts - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .jingle-upload-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .jingle-upload-form .full-width {
            grid-column: 1 / -1;
        }
        .jingle-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .jingle-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        .jingle-item.inactive {
            opacity: 0.6;
            border-left-color: #9ca3af;
        }
        .jingle-icon {
            font-size: 2rem;
        }
        .jingle-info {
            flex: 1;
        }
        .jingle-info h4 {
            margin-bottom: 0.25rem;
        }
        .jingle-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        .jingle-actions {
            display: flex;
            gap: 0.5rem;
        }
        .type-section {
            margin-bottom: 2rem;
        }
        .type-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
        }
        .type-header h3 {
            margin: 0;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-mini {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-mini .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .stat-mini .label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
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
            <h1>Jingles & Adverts</h1>

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

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-mini">
                    <div class="number"><?php echo count($jingles_by_type['station_id'] ?? []); ?></div>
                    <div class="label">Station IDs</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo count($jingles_by_type['jingle'] ?? []); ?></div>
                    <div class="label">Jingles</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo count($jingles_by_type['advert'] ?? []); ?></div>
                    <div class="label">Adverts</div>
                </div>
                <div class="stat-mini">
                    <div class="number"><?php echo count($jingles_by_type['sponsor'] ?? []); ?></div>
                    <div class="label">Sponsors</div>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upload Jingle / Advert</h2>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="jingle-upload-form">
                        <div class="form-group">
                            <label for="title">Title *</label>
                            <input type="text" id="title" name="title" placeholder="e.g., Station ID Intro" required>
                        </div>

                        <div class="form-group">
                            <label for="jingle_type">Type *</label>
                            <select id="jingle_type" name="jingle_type" required>
                                <option value="station_id">📺 Station ID</option>
                                <option value="jingle">🎵 Jingle</option>
                                <option value="advert">📢 Advertisement</option>
                                <option value="sponsor">💰 Sponsor Message</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority">
                                <option value="1">1 - Highest</option>
                                <option value="2">2 - High</option>
                                <option value="3" selected>3 - Normal</option>
                                <option value="4">4 - Low</option>
                                <option value="5">5 - Lowest</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="play_frequency">Play Frequency</label>
                            <select id="play_frequency" name="play_frequency">
                                <option value="every_video">After Every Video</option>
                                <option value="every_2_videos">Every 2 Videos</option>
                                <option value="every_3_videos" selected>Every 3 Videos</option>
                                <option value="every_5_videos">Every 5 Videos</option>
                                <option value="hourly">Hourly</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="jingle_file">Video File * (Max 100MB)</label>
                            <input type="file" id="jingle_file" name="jingle_file" accept="video/*" required>
                        </div>
                    </div>

                    <button type="submit" name="add_jingle" class="btn">Upload Jingle</button>
                </form>
            </div>

            <!-- Jingles List -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Jingles & Adverts (<?php echo count($all_jingles); ?>)</h2>
                </div>

                <?php if (empty($all_jingles)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No jingles uploaded yet. Upload your first jingle above!
                    </p>
                <?php else: ?>

                    <?php foreach ($type_labels as $type => $info): ?>
                        <?php if (!empty($jingles_by_type[$type])): ?>
                            <div class="type-section">
                                <div class="type-header">
                                    <span style="font-size: 1.5rem;"><?php echo $info['icon']; ?></span>
                                    <h3><?php echo $info['label']; ?>s (<?php echo count($jingles_by_type[$type]); ?>)</h3>
                                </div>

                                <div class="jingle-list">
                                    <?php foreach ($jingles_by_type[$type] as $jingle): ?>
                                        <div class="jingle-item <?php echo $jingle['is_active'] ? '' : 'inactive'; ?>">
                                            <div class="jingle-icon"><?php echo $info['icon']; ?></div>
                                            <div class="jingle-info">
                                                <h4><?php echo clean($jingle['title']); ?></h4>
                                                <div class="jingle-meta">
                                                    <span><?php echo format_file_size($jingle['file_size']); ?></span>
                                                    <span>•</span>
                                                    <span>Priority: <?php echo $jingle['priority']; ?></span>
                                                    <span>•</span>
                                                    <span><?php echo $frequency_labels[$jingle['play_frequency']] ?? $jingle['play_frequency']; ?></span>
                                                    <span>•</span>
                                                    <span class="badge badge-<?php echo $jingle['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $jingle['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="jingle-actions">
                                                <a href="../uploads/jingles/<?php echo $station_id; ?>/<?php echo $jingle['filename']; ?>"
                                                   target="_blank" class="btn btn-small btn-secondary">Preview</a>
                                                <a href="?toggle=<?php echo $jingle['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                                   class="btn btn-small <?php echo $jingle['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                    <?php echo $jingle['is_active'] ? 'Disable' : 'Enable'; ?>
                                                </a>
                                                <a href="?delete=<?php echo $jingle['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                                   onclick="return confirm('Delete this jingle?')"
                                                   class="btn btn-small btn-danger">Delete</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                <?php endif; ?>
            </div>

            <!-- Info Card -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">How Jingles Work</h2>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h4>📺 Station ID</h4>
                        <p style="color: #6b7280; font-size: 0.875rem;">
                            Your station's brand identity clip. Plays regularly to identify your channel.
                        </p>
                    </div>
                    <div>
                        <h4>🎵 Jingle</h4>
                        <p style="color: #6b7280; font-size: 0.875rem;">
                            Short musical clips that play between programs. Adds personality to your station.
                        </p>
                    </div>
                    <div>
                        <h4>📢 Advertisement</h4>
                        <p style="color: #6b7280; font-size: 0.875rem;">
                            Paid promotional content. Configure how often ads play in your rotation.
                        </p>
                    </div>
                    <div>
                        <h4>💰 Sponsor</h4>
                        <p style="color: #6b7280; font-size: 0.875rem;">
                            Sponsor messages and acknowledgments. "This program brought to you by..."
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
