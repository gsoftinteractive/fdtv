<?php
// dashboard/radio.php - Internet Radio Management

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get station
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

if (!$station) {
    redirect('index.php');
}

$station_id = $station['id'];
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // Update radio settings
    if ($action == 'update_settings') {
        $radio_enabled = isset($_POST['radio_enabled']) ? 1 : 0;
        $radio_name = trim($_POST['radio_name'] ?? '');
        $radio_tagline = trim($_POST['radio_tagline'] ?? '');
        $radio_genre = trim($_POST['radio_genre'] ?? '');
        $radio_website = trim($_POST['radio_website'] ?? '');
        $radio_color_primary = $_POST['radio_color_primary'] ?? '#6366f1';
        $radio_color_secondary = $_POST['radio_color_secondary'] ?? '#8b5cf6';
        $radio_social_facebook = trim($_POST['radio_social_facebook'] ?? '');
        $radio_social_twitter = trim($_POST['radio_social_twitter'] ?? '');
        $radio_social_instagram = trim($_POST['radio_social_instagram'] ?? '');
        $radio_social_whatsapp = trim($_POST['radio_social_whatsapp'] ?? '');

        // Handle logo upload
        $radio_logo = $station['radio_logo'];
        if (isset($_FILES['radio_logo']) && $_FILES['radio_logo']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['radio_logo']['type'], $allowed)) {
                $ext = pathinfo($_FILES['radio_logo']['name'], PATHINFO_EXTENSION);
                $filename = 'radio_logo_' . $station_id . '_' . time() . '.' . $ext;
                $upload_dir = UPLOAD_PATH . 'radio/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                if (move_uploaded_file($_FILES['radio_logo']['tmp_name'], $upload_dir . $filename)) {
                    $radio_logo = $filename;
                }
            }
        }

        // Handle background upload
        $radio_background = $station['radio_background'];
        if (isset($_FILES['radio_background']) && $_FILES['radio_background']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (in_array($_FILES['radio_background']['type'], $allowed)) {
                $ext = pathinfo($_FILES['radio_background']['name'], PATHINFO_EXTENSION);
                $filename = 'radio_bg_' . $station_id . '_' . time() . '.' . $ext;
                $upload_dir = UPLOAD_PATH . 'radio/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                if (move_uploaded_file($_FILES['radio_background']['tmp_name'], $upload_dir . $filename)) {
                    $radio_background = $filename;
                }
            }
        }

        $stmt = $conn->prepare("UPDATE stations SET
            radio_enabled = ?, radio_name = ?, radio_tagline = ?, radio_genre = ?,
            radio_website = ?, radio_logo = ?, radio_background = ?,
            radio_color_primary = ?, radio_color_secondary = ?,
            radio_social_facebook = ?, radio_social_twitter = ?,
            radio_social_instagram = ?, radio_social_whatsapp = ?
            WHERE id = ?");
        $stmt->execute([
            $radio_enabled, $radio_name, $radio_tagline, $radio_genre,
            $radio_website, $radio_logo, $radio_background,
            $radio_color_primary, $radio_color_secondary,
            $radio_social_facebook, $radio_social_twitter,
            $radio_social_instagram, $radio_social_whatsapp,
            $station_id
        ]);

        $success = "Radio settings updated successfully!";

        // Refresh station data
        $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
        $stmt->execute([$station_id]);
        $station = $stmt->fetch();
    }

    // Add/Update stream
    if ($action == 'save_stream') {
        $stream_id = (int)($_POST['stream_id'] ?? 0);
        $name = trim($_POST['stream_name'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $stream_type = $_POST['stream_type'] ?? 'shoutcast';
        $bitrate = (int)($_POST['bitrate'] ?? 128);
        $format = $_POST['format'] ?? 'mp3';
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        $fallback_url = trim($_POST['fallback_url'] ?? '');
        $metadata_url = trim($_POST['metadata_url'] ?? '');

        if (empty($name) || empty($stream_url)) {
            $errors[] = "Stream name and URL are required.";
        } else {
            // If setting as primary, unset others
            if ($is_primary) {
                $stmt = $conn->prepare("UPDATE radio_streams SET is_primary = 0 WHERE station_id = ?");
                $stmt->execute([$station_id]);
            }

            if ($stream_id > 0) {
                $stmt = $conn->prepare("UPDATE radio_streams SET
                    name = ?, stream_url = ?, stream_type = ?, bitrate = ?,
                    format = ?, is_primary = ?, fallback_url = ?, metadata_url = ?
                    WHERE id = ? AND station_id = ?");
                $stmt->execute([
                    $name, $stream_url, $stream_type, $bitrate,
                    $format, $is_primary, $fallback_url, $metadata_url,
                    $stream_id, $station_id
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO radio_streams
                    (station_id, name, stream_url, stream_type, bitrate, format, is_primary, fallback_url, metadata_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $station_id, $name, $stream_url, $stream_type, $bitrate,
                    $format, $is_primary, $fallback_url, $metadata_url
                ]);
            }
            $success = "Stream saved successfully!";
        }
    }

    // Delete stream
    if ($action == 'delete_stream') {
        $stream_id = (int)($_POST['stream_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM radio_streams WHERE id = ? AND station_id = ?");
        $stmt->execute([$stream_id, $station_id]);
        $success = "Stream deleted.";
    }

    // Toggle stream
    if ($action == 'toggle_stream') {
        $stream_id = (int)($_POST['stream_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE radio_streams SET is_active = NOT is_active WHERE id = ? AND station_id = ?");
        $stmt->execute([$stream_id, $station_id]);
    }

    // Add/Update schedule
    if ($action == 'save_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $program_name = trim($_POST['program_name'] ?? '');
        $host_name = trim($_POST['host_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $day_of_week = $_POST['day_of_week'] ?? 'monday';
        $start_time = $_POST['start_time'] ?? '00:00';
        $end_time = $_POST['end_time'] ?? '01:00';
        $is_live = isset($_POST['is_live']) ? 1 : 0;
        $genre = trim($_POST['genre'] ?? '');

        if (empty($program_name)) {
            $errors[] = "Program name is required.";
        } else {
            if ($schedule_id > 0) {
                $stmt = $conn->prepare("UPDATE radio_schedule SET
                    program_name = ?, host_name = ?, description = ?,
                    day_of_week = ?, start_time = ?, end_time = ?,
                    is_live = ?, genre = ?
                    WHERE id = ? AND station_id = ?");
                $stmt->execute([
                    $program_name, $host_name, $description,
                    $day_of_week, $start_time, $end_time,
                    $is_live, $genre,
                    $schedule_id, $station_id
                ]);
            } else {
                $stmt = $conn->prepare("INSERT INTO radio_schedule
                    (station_id, program_name, host_name, description, day_of_week, start_time, end_time, is_live, genre)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $station_id, $program_name, $host_name, $description,
                    $day_of_week, $start_time, $end_time, $is_live, $genre
                ]);
            }
            $success = "Schedule saved successfully!";
        }
    }

    // Delete schedule
    if ($action == 'delete_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM radio_schedule WHERE id = ? AND station_id = ?");
        $stmt->execute([$schedule_id, $station_id]);
        $success = "Schedule item deleted.";
    }

    // Update now playing manually
    if ($action == 'update_now_playing') {
        $track_title = trim($_POST['track_title'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $album = trim($_POST['album'] ?? '');

        $stmt = $conn->prepare("INSERT INTO radio_now_playing (station_id, track_title, artist, album, source)
            VALUES (?, ?, ?, ?, 'manual')
            ON DUPLICATE KEY UPDATE track_title = ?, artist = ?, album = ?, source = 'manual', started_at = NOW()");
        $stmt->execute([$station_id, $track_title, $artist, $album, $track_title, $artist, $album]);

        // Add to history
        if (!empty($track_title)) {
            $stmt = $conn->prepare("INSERT INTO radio_history (station_id, track_title, artist) VALUES (?, ?, ?)");
            $stmt->execute([$station_id, $track_title, $artist]);
        }

        $success = "Now playing updated!";
    }

    // Upload audio track
    if ($action == 'upload_audio') {
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] == 0) {
            $allowed = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/aac', 'audio/m4a', 'audio/x-m4a'];
            $file_type = $_FILES['audio_file']['type'];

            if (in_array($file_type, $allowed) || preg_match('/audio/', $file_type)) {
                $original_name = $_FILES['audio_file']['name'];
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                $filename = 'audio_' . $station_id . '_' . time() . '_' . uniqid() . '.' . $ext;
                $upload_dir = UPLOAD_PATH . 'radio/audio/';

                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $upload_dir . $filename)) {
                    $title = trim($_POST['track_title'] ?? pathinfo($original_name, PATHINFO_FILENAME));
                    $artist = trim($_POST['track_artist'] ?? '');
                    $album = trim($_POST['track_album'] ?? '');
                    $genre = trim($_POST['track_genre'] ?? '');
                    $file_size = $_FILES['audio_file']['size'];

                    // Get max sort order
                    $stmt = $conn->prepare("SELECT MAX(sort_order) as max_order FROM radio_audio_tracks WHERE station_id = ?");
                    $stmt->execute([$station_id]);
                    $max_order = $stmt->fetch()['max_order'] ?? 0;

                    $stmt = $conn->prepare("INSERT INTO radio_audio_tracks
                        (station_id, title, artist, album, genre, filename, original_filename, file_size, file_type, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $station_id, $title, $artist, $album, $genre,
                        $filename, $original_name, $file_size, $file_type, $max_order + 1
                    ]);

                    $success = "Audio track uploaded successfully!";
                } else {
                    $errors[] = "Failed to upload audio file.";
                }
            } else {
                $errors[] = "Invalid audio file format. Allowed: MP3, WAV, OGG, AAC, M4A";
            }
        } else {
            $errors[] = "Please select an audio file to upload.";
        }
    }

    // Update audio track
    if ($action == 'update_audio') {
        $track_id = (int)($_POST['track_id'] ?? 0);
        $title = trim($_POST['track_title'] ?? '');
        $artist = trim($_POST['track_artist'] ?? '');
        $album = trim($_POST['track_album'] ?? '');
        $genre = trim($_POST['track_genre'] ?? '');

        if ($track_id > 0 && !empty($title)) {
            $stmt = $conn->prepare("UPDATE radio_audio_tracks SET title = ?, artist = ?, album = ?, genre = ? WHERE id = ? AND station_id = ?");
            $stmt->execute([$title, $artist, $album, $genre, $track_id, $station_id]);
            $success = "Track updated successfully!";
        }
    }

    // Delete audio track
    if ($action == 'delete_audio') {
        $track_id = (int)($_POST['track_id'] ?? 0);

        // Get filename first
        $stmt = $conn->prepare("SELECT filename FROM radio_audio_tracks WHERE id = ? AND station_id = ?");
        $stmt->execute([$track_id, $station_id]);
        $track = $stmt->fetch();

        if ($track) {
            // Delete file
            $file_path = UPLOAD_PATH . 'radio/audio/' . $track['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Delete from database
            $stmt = $conn->prepare("DELETE FROM radio_audio_tracks WHERE id = ? AND station_id = ?");
            $stmt->execute([$track_id, $station_id]);
            $success = "Track deleted successfully!";
        }
    }

    // Toggle audio track
    if ($action == 'toggle_audio') {
        $track_id = (int)($_POST['track_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE radio_audio_tracks SET is_active = NOT is_active WHERE id = ? AND station_id = ?");
        $stmt->execute([$track_id, $station_id]);
    }

    // Update radio mode
    if ($action == 'update_radio_mode') {
        $radio_mode = $_POST['radio_mode'] ?? 'stream';
        $radio_shuffle = isset($_POST['radio_shuffle']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE stations SET radio_mode = ?, radio_shuffle = ? WHERE id = ?");
        $stmt->execute([$radio_mode, $radio_shuffle, $station_id]);

        $success = "Radio mode updated!";

        // Refresh station data
        $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
        $stmt->execute([$station_id]);
        $station = $stmt->fetch();
    }
}

// Get streams
$stmt = $conn->prepare("SELECT * FROM radio_streams WHERE station_id = ? ORDER BY is_primary DESC, created_at ASC");
$stmt->execute([$station_id]);
$streams = $stmt->fetchAll();

// Get schedule
$stmt = $conn->prepare("SELECT * FROM radio_schedule WHERE station_id = ? ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), start_time ASC");
$stmt->execute([$station_id]);
$schedules = $stmt->fetchAll();

// Get current now playing
$stmt = $conn->prepare("SELECT * FROM radio_now_playing WHERE station_id = ?");
$stmt->execute([$station_id]);
$now_playing = $stmt->fetch();

// Get recent history
$stmt = $conn->prepare("SELECT * FROM radio_history WHERE station_id = ? ORDER BY played_at DESC LIMIT 10");
$stmt->execute([$station_id]);
$history = $stmt->fetchAll();

// Get audio tracks
$stmt = $conn->prepare("SELECT * FROM radio_audio_tracks WHERE station_id = ? ORDER BY sort_order ASC, created_at DESC");
$stmt->execute([$station_id]);
$audio_tracks = $stmt->fetchAll();

// Calculate total tracks and size
$total_tracks = count($audio_tracks);
$total_audio_size = array_sum(array_column($audio_tracks, 'file_size'));

// Get current listener count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM radio_listeners WHERE station_id = ? AND last_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
$stmt->execute([$station_id]);
$listeners = $stmt->fetch()['count'] ?? 0;

// Radio URL
$radio_url = rtrim(SITE_URL, '/') . '/radio/' . $station['slug'];

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radio Management - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .radio-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .radio-url {
            background: #f3f4f6;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: monospace;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .radio-url a {
            color: var(--primary);
            text-decoration: none;
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
            transition: all 0.2s;
        }
        .tab:hover {
            color: var(--primary);
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
        .stream-item, .schedule-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .stream-item.primary {
            border-color: var(--primary);
            background: #f0f9ff;
        }
        .stream-header, .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .stream-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .stream-url {
            font-size: 0.875rem;
            color: #6b7280;
            word-break: break-all;
        }
        .stream-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        .color-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .now-playing-card {
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .now-playing-card h3 {
            opacity: 0.8;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .now-playing-card .track {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .now-playing-card .artist {
            opacity: 0.9;
        }
        .listener-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        .listener-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .day-group {
            margin-bottom: 1.5rem;
        }
        .day-group h4 {
            background: #f3f4f6;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            text-transform: capitalize;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .audio-tracks-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .audio-track-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .audio-track-item:hover {
            background: #f3f4f6;
        }
        .audio-track-item.disabled {
            opacity: 0.5;
        }
        .track-number {
            width: 30px;
            height: 30px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        .track-info {
            flex: 1;
            min-width: 0;
        }
        .track-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .track-meta {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .track-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        @media (max-width: 768px) {
            .audio-track-item {
                flex-wrap: wrap;
            }
            .track-actions {
                width: 100%;
                margin-top: 0.5rem;
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
            <div class="radio-header">
                <h1>Internet Radio</h1>
                <?php if ($station['radio_enabled']): ?>
                <div class="radio-url">
                    <span>Your Radio:</span>
                    <a href="<?php echo $radio_url; ?>" target="_blank"><?php echo $radio_url; ?></a>
                    <button onclick="copyToClipboard('<?php echo $radio_url; ?>')" class="btn btn-small">Copy</button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo clean($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Now Playing Card -->
            <?php if ($station['radio_enabled'] && $now_playing): ?>
            <div class="now-playing-card">
                <h3>NOW PLAYING</h3>
                <div class="track"><?php echo clean($now_playing['track_title'] ?: 'Unknown Track'); ?></div>
                <div class="artist"><?php echo clean($now_playing['artist'] ?: 'Unknown Artist'); ?></div>
                <div class="listener-count">
                    <span class="listener-dot"></span>
                    <span><?php echo $listeners; ?> listeners</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="settings">Settings</button>
                <button class="tab" data-tab="audio">Audio Library</button>
                <button class="tab" data-tab="streams">External Streams</button>
                <button class="tab" data-tab="schedule">Schedule</button>
                <button class="tab" data-tab="nowplaying">Now Playing</button>
            </div>

            <!-- Settings Tab -->
            <div class="tab-content active" id="tab-settings">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Radio Settings</h2>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="update_settings">

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="radio_enabled" <?php echo $station['radio_enabled'] ? 'checked' : ''; ?>>
                                Enable Internet Radio
                            </label>
                            <small style="color: #6b7280; display: block; margin-top: 0.25rem;">
                                When enabled, your radio will be accessible at the URL above
                            </small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Radio Station Name</label>
                                <input type="text" name="radio_name" value="<?php echo clean($station['radio_name'] ?? $station['station_name']); ?>" placeholder="My Radio Station">
                            </div>
                            <div class="form-group">
                                <label>Tagline / Slogan</label>
                                <input type="text" name="radio_tagline" value="<?php echo clean($station['radio_tagline'] ?? ''); ?>" placeholder="The best music 24/7">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Genre</label>
                                <input type="text" name="radio_genre" value="<?php echo clean($station['radio_genre'] ?? ''); ?>" placeholder="Pop, Rock, Jazz...">
                            </div>
                            <div class="form-group">
                                <label>Website URL</label>
                                <input type="url" name="radio_website" value="<?php echo clean($station['radio_website'] ?? ''); ?>" placeholder="https://yourwebsite.com">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Radio Logo</label>
                                <input type="file" name="radio_logo" accept="image/*">
                                <?php if ($station['radio_logo']): ?>
                                    <small>Current: <?php echo clean($station['radio_logo']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>Background Image</label>
                                <input type="file" name="radio_background" accept="image/*">
                                <?php if ($station['radio_background']): ?>
                                    <small>Current: <?php echo clean($station['radio_background']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Primary Color</label>
                                <div class="color-group">
                                    <input type="color" name="radio_color_primary" value="<?php echo $station['radio_color_primary'] ?? '#6366f1'; ?>" class="color-preview">
                                    <span><?php echo $station['radio_color_primary'] ?? '#6366f1'; ?></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Secondary Color</label>
                                <div class="color-group">
                                    <input type="color" name="radio_color_secondary" value="<?php echo $station['radio_color_secondary'] ?? '#8b5cf6'; ?>" class="color-preview">
                                    <span><?php echo $station['radio_color_secondary'] ?? '#8b5cf6'; ?></span>
                                </div>
                            </div>
                        </div>

                        <h3 style="margin: 1.5rem 0 1rem;">Social Media Links</h3>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Facebook</label>
                                <input type="url" name="radio_social_facebook" value="<?php echo clean($station['radio_social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/...">
                            </div>
                            <div class="form-group">
                                <label>Twitter/X</label>
                                <input type="url" name="radio_social_twitter" value="<?php echo clean($station['radio_social_twitter'] ?? ''); ?>" placeholder="https://twitter.com/...">
                            </div>
                            <div class="form-group">
                                <label>Instagram</label>
                                <input type="url" name="radio_social_instagram" value="<?php echo clean($station['radio_social_instagram'] ?? ''); ?>" placeholder="https://instagram.com/...">
                            </div>
                            <div class="form-group">
                                <label>WhatsApp</label>
                                <input type="text" name="radio_social_whatsapp" value="<?php echo clean($station['radio_social_whatsapp'] ?? ''); ?>" placeholder="+234...">
                            </div>
                        </div>

                        <button type="submit" class="btn">Save Settings</button>
                    </form>
                </div>
            </div>

            <!-- Audio Library Tab -->
            <div class="tab-content" id="tab-audio">
                <!-- Radio Mode Card -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">Radio Mode</h2>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="update_radio_mode">

                        <div class="form-group">
                            <label>Content Source</label>
                            <select name="radio_mode" style="max-width: 300px;">
                                <option value="upload" <?php echo ($station['radio_mode'] ?? 'stream') == 'upload' ? 'selected' : ''; ?>>
                                    Uploaded Audio Files (Self-Contained)
                                </option>
                                <option value="stream" <?php echo ($station['radio_mode'] ?? 'stream') == 'stream' ? 'selected' : ''; ?>>
                                    External Stream (Shoutcast/Icecast)
                                </option>
                            </select>
                            <small style="display: block; color: #6b7280; margin-top: 0.25rem;">
                                Choose "Uploaded Audio Files" to play your own music without external streaming services
                            </small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="radio_shuffle" <?php echo ($station['radio_shuffle'] ?? 0) ? 'checked' : ''; ?>>
                                Shuffle playback (randomize track order)
                            </label>
                        </div>

                        <button type="submit" class="btn">Save Mode</button>
                    </form>
                </div>

                <!-- Upload Form -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-header">
                        <h2 class="card-title">Upload Audio</h2>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="upload_audio">

                        <div class="form-group">
                            <label>Audio File *</label>
                            <input type="file" name="audio_file" accept="audio/*" required>
                            <small style="color: #6b7280;">Supported: MP3, WAV, OGG, AAC, M4A (Max 50MB)</small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Track Title</label>
                                <input type="text" name="track_title" placeholder="Leave empty to use filename">
                            </div>
                            <div class="form-group">
                                <label>Artist</label>
                                <input type="text" name="track_artist" placeholder="Artist name">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Album</label>
                                <input type="text" name="track_album" placeholder="Album name">
                            </div>
                            <div class="form-group">
                                <label>Genre</label>
                                <input type="text" name="track_genre" placeholder="Pop, Rock, Jazz...">
                            </div>
                        </div>

                        <button type="submit" class="btn">Upload Track</button>
                    </form>
                </div>

                <!-- Audio Tracks List -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Your Audio Library</h2>
                        <span style="color: #6b7280;">
                            <?php echo $total_tracks; ?> tracks &bull; <?php echo format_file_size($total_audio_size); ?>
                        </span>
                    </div>

                    <?php if (empty($audio_tracks)): ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem; opacity: 0.5;">
                                <path d="M9 18V5l12-2v13"/>
                                <circle cx="6" cy="18" r="3"/>
                                <circle cx="18" cy="16" r="3"/>
                            </svg>
                            <p>No audio tracks uploaded yet.</p>
                            <p style="font-size: 0.875rem;">Upload your first track above to get started!</p>
                        </div>
                    <?php else: ?>
                        <div class="audio-tracks-list">
                            <?php foreach ($audio_tracks as $index => $track): ?>
                            <div class="audio-track-item <?php echo !$track['is_active'] ? 'disabled' : ''; ?>">
                                <div class="track-number"><?php echo $index + 1; ?></div>
                                <div class="track-info">
                                    <div class="track-title"><?php echo clean($track['title']); ?></div>
                                    <div class="track-meta">
                                        <?php if ($track['artist']): ?>
                                            <span><?php echo clean($track['artist']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($track['album']): ?>
                                            <span>&bull; <?php echo clean($track['album']); ?></span>
                                        <?php endif; ?>
                                        <span>&bull; <?php echo format_file_size($track['file_size']); ?></span>
                                    </div>
                                </div>
                                <div class="track-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle_audio">
                                        <input type="hidden" name="track_id" value="<?php echo $track['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-secondary">
                                            <?php echo $track['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-small" onclick='editAudioTrack(<?php echo json_encode($track); ?>)'>Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this track?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete_audio">
                                        <input type="hidden" name="track_id" value="<?php echo $track['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Streams Tab -->
            <div class="tab-content" id="tab-streams">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">External Audio Streams</h2>
                        <button class="btn" onclick="showStreamModal()">Add Stream</button>
                    </div>

                    <?php if (empty($streams)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">
                            No streams configured. Add your first stream to get started.
                        </p>
                    <?php else: ?>
                        <?php foreach ($streams as $stream): ?>
                        <div class="stream-item <?php echo $stream['is_primary'] ? 'primary' : ''; ?>">
                            <div class="stream-header">
                                <div class="stream-title">
                                    <?php echo clean($stream['name']); ?>
                                    <?php if ($stream['is_primary']): ?>
                                        <span class="badge badge-success">Primary</span>
                                    <?php endif; ?>
                                    <?php if (!$stream['is_active']): ?>
                                        <span class="badge badge-secondary">Disabled</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="toggle_stream">
                                        <input type="hidden" name="stream_id" value="<?php echo $stream['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-secondary">
                                            <?php echo $stream['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-small" onclick='editStream(<?php echo json_encode($stream); ?>)'>Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this stream?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete_stream">
                                        <input type="hidden" name="stream_id" value="<?php echo $stream['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                            <div class="stream-url"><?php echo clean($stream['stream_url']); ?></div>
                            <div class="stream-meta">
                                <span><strong>Type:</strong> <?php echo ucfirst($stream['stream_type']); ?></span>
                                <span><strong>Bitrate:</strong> <?php echo $stream['bitrate']; ?>kbps</span>
                                <span><strong>Format:</strong> <?php echo strtoupper($stream['format']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div class="tab-content" id="tab-schedule">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Program Schedule</h2>
                        <button class="btn" onclick="showScheduleModal()">Add Program</button>
                    </div>

                    <?php if (empty($schedules)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">
                            No programs scheduled. Add your first program to create a schedule.
                        </p>
                    <?php else: ?>
                        <?php
                        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                        foreach ($days as $day):
                            $day_schedules = array_filter($schedules, fn($s) => $s['day_of_week'] === $day);
                            if (empty($day_schedules)) continue;
                        ?>
                        <div class="day-group">
                            <h4><?php echo $day; ?></h4>
                            <?php foreach ($day_schedules as $schedule): ?>
                            <div class="schedule-item">
                                <div class="schedule-header">
                                    <div>
                                        <strong><?php echo clean($schedule['program_name']); ?></strong>
                                        <?php if ($schedule['is_live']): ?>
                                            <span class="badge badge-danger">LIVE</span>
                                        <?php endif; ?>
                                        <?php if ($schedule['host_name']): ?>
                                            <span style="color: #6b7280;"> with <?php echo clean($schedule['host_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span style="font-weight: 500;">
                                            <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> -
                                            <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                        </span>
                                        <button class="btn btn-small" onclick='editSchedule(<?php echo json_encode($schedule); ?>)'>Edit</button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this program?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_schedule">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                            <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <?php if ($schedule['description']): ?>
                                    <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
                                        <?php echo clean($schedule['description']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Now Playing Tab -->
            <div class="tab-content" id="tab-nowplaying">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Update Now Playing</h2>
                        </div>
                        <p style="color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem;">
                            Manually update what's currently playing. This overrides any automatic metadata.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="update_now_playing">

                            <div class="form-group">
                                <label>Track Title</label>
                                <input type="text" name="track_title" value="<?php echo clean($now_playing['track_title'] ?? ''); ?>" placeholder="Song name">
                            </div>

                            <div class="form-group">
                                <label>Artist</label>
                                <input type="text" name="artist" value="<?php echo clean($now_playing['artist'] ?? ''); ?>" placeholder="Artist name">
                            </div>

                            <div class="form-group">
                                <label>Album</label>
                                <input type="text" name="album" value="<?php echo clean($now_playing['album'] ?? ''); ?>" placeholder="Album name">
                            </div>

                            <button type="submit" class="btn">Update Now Playing</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Recently Played</h2>
                        </div>

                        <?php if (empty($history)): ?>
                            <p style="color: #6b7280; text-align: center; padding: 1rem;">No play history yet.</p>
                        <?php else: ?>
                            <?php foreach ($history as $track): ?>
                            <div class="history-item">
                                <div>
                                    <strong><?php echo clean($track['track_title']); ?></strong>
                                    <?php if ($track['artist']): ?>
                                        <span style="color: #6b7280;"> - <?php echo clean($track['artist']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span style="color: #6b7280; font-size: 0.75rem;">
                                    <?php echo date('g:i A', strtotime($track['played_at'])); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stream Modal -->
    <div id="streamModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                <h3 id="streamModalTitle">Add Stream</h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="save_stream">
                <input type="hidden" name="stream_id" id="streamId" value="0">

                <div class="form-group">
                    <label>Stream Name *</label>
                    <input type="text" name="stream_name" id="streamName" required placeholder="Main Stream">
                </div>

                <div class="form-group">
                    <label>Stream URL *</label>
                    <input type="url" name="stream_url" id="streamUrl" required placeholder="https://stream.example.com/live">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Stream Type</label>
                        <select name="stream_type" id="streamType">
                            <option value="shoutcast">Shoutcast</option>
                            <option value="icecast">Icecast</option>
                            <option value="hls">HLS</option>
                            <option value="mp3">Direct MP3</option>
                            <option value="aac">AAC</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bitrate (kbps)</label>
                        <input type="number" name="bitrate" id="streamBitrate" value="128" min="32" max="320">
                    </div>
                </div>

                <div class="form-group">
                    <label>Format</label>
                    <select name="format" id="streamFormat">
                        <option value="mp3">MP3</option>
                        <option value="aac">AAC</option>
                        <option value="ogg">OGG</option>
                        <option value="opus">Opus</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fallback URL (optional)</label>
                    <input type="url" name="fallback_url" id="streamFallback" placeholder="Backup stream URL">
                </div>

                <div class="form-group">
                    <label>Metadata URL (optional)</label>
                    <input type="url" name="metadata_url" id="streamMetadata" placeholder="URL for now playing info">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_primary" id="streamPrimary">
                        Set as Primary Stream
                    </label>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeStreamModal()">Cancel</button>
                    <button type="submit" class="btn">Save Stream</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Audio Track Edit Modal -->
    <div id="audioModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                <h3>Edit Track</h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_audio">
                <input type="hidden" name="track_id" id="editTrackId" value="0">

                <div class="form-group">
                    <label>Track Title *</label>
                    <input type="text" name="track_title" id="editTrackTitle" required>
                </div>

                <div class="form-group">
                    <label>Artist</label>
                    <input type="text" name="track_artist" id="editTrackArtist">
                </div>

                <div class="form-group">
                    <label>Album</label>
                    <input type="text" name="track_album" id="editTrackAlbum">
                </div>

                <div class="form-group">
                    <label>Genre</label>
                    <input type="text" name="track_genre" id="editTrackGenre">
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAudioModal()">Cancel</button>
                    <button type="submit" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                <h3 id="scheduleModalTitle">Add Program</h3>
            </div>
            <form method="POST" style="padding: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="save_schedule">
                <input type="hidden" name="schedule_id" id="scheduleId" value="0">

                <div class="form-group">
                    <label>Program Name *</label>
                    <input type="text" name="program_name" id="programName" required placeholder="Morning Show">
                </div>

                <div class="form-group">
                    <label>Host Name</label>
                    <input type="text" name="host_name" id="hostName" placeholder="DJ John">
                </div>

                <div class="form-group">
                    <label>Day of Week</label>
                    <select name="day_of_week" id="dayOfWeek">
                        <option value="monday">Monday</option>
                        <option value="tuesday">Tuesday</option>
                        <option value="wednesday">Wednesday</option>
                        <option value="thursday">Thursday</option>
                        <option value="friday">Friday</option>
                        <option value="saturday">Saturday</option>
                        <option value="sunday">Sunday</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="startTime" value="09:00">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="endTime" value="12:00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Genre</label>
                    <input type="text" name="genre" id="programGenre" placeholder="Talk Show, Music, News...">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="programDescription" rows="3" placeholder="Brief description of the program"></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_live" id="isLive">
                        Live Show (not pre-recorded)
                    </label>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">Cancel</button>
                    <button type="submit" class="btn">Save Program</button>
                </div>
            </form>
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

        // Stream Modal
        function showStreamModal() {
            document.getElementById('streamModalTitle').textContent = 'Add Stream';
            document.getElementById('streamId').value = '0';
            document.getElementById('streamName').value = '';
            document.getElementById('streamUrl').value = '';
            document.getElementById('streamType').value = 'shoutcast';
            document.getElementById('streamBitrate').value = '128';
            document.getElementById('streamFormat').value = 'mp3';
            document.getElementById('streamFallback').value = '';
            document.getElementById('streamMetadata').value = '';
            document.getElementById('streamPrimary').checked = false;
            document.getElementById('streamModal').style.display = 'flex';
        }

        function editStream(stream) {
            document.getElementById('streamModalTitle').textContent = 'Edit Stream';
            document.getElementById('streamId').value = stream.id;
            document.getElementById('streamName').value = stream.name;
            document.getElementById('streamUrl').value = stream.stream_url;
            document.getElementById('streamType').value = stream.stream_type;
            document.getElementById('streamBitrate').value = stream.bitrate;
            document.getElementById('streamFormat').value = stream.format;
            document.getElementById('streamFallback').value = stream.fallback_url || '';
            document.getElementById('streamMetadata').value = stream.metadata_url || '';
            document.getElementById('streamPrimary').checked = stream.is_primary == 1;
            document.getElementById('streamModal').style.display = 'flex';
        }

        function closeStreamModal() {
            document.getElementById('streamModal').style.display = 'none';
        }

        // Schedule Modal
        function showScheduleModal() {
            document.getElementById('scheduleModalTitle').textContent = 'Add Program';
            document.getElementById('scheduleId').value = '0';
            document.getElementById('programName').value = '';
            document.getElementById('hostName').value = '';
            document.getElementById('dayOfWeek').value = 'monday';
            document.getElementById('startTime').value = '09:00';
            document.getElementById('endTime').value = '12:00';
            document.getElementById('programGenre').value = '';
            document.getElementById('programDescription').value = '';
            document.getElementById('isLive').checked = false;
            document.getElementById('scheduleModal').style.display = 'flex';
        }

        function editSchedule(schedule) {
            document.getElementById('scheduleModalTitle').textContent = 'Edit Program';
            document.getElementById('scheduleId').value = schedule.id;
            document.getElementById('programName').value = schedule.program_name;
            document.getElementById('hostName').value = schedule.host_name || '';
            document.getElementById('dayOfWeek').value = schedule.day_of_week;
            document.getElementById('startTime').value = schedule.start_time;
            document.getElementById('endTime').value = schedule.end_time;
            document.getElementById('programGenre').value = schedule.genre || '';
            document.getElementById('programDescription').value = schedule.description || '';
            document.getElementById('isLive').checked = schedule.is_live == 1;
            document.getElementById('scheduleModal').style.display = 'flex';
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'none';
        }

        // Close modals on outside click
        document.getElementById('streamModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('streamModal')) closeStreamModal();
        });
        document.getElementById('scheduleModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('scheduleModal')) closeScheduleModal();
        });

        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('URL copied to clipboard!');
            });
        }

        // Audio Modal
        function editAudioTrack(track) {
            document.getElementById('editTrackId').value = track.id;
            document.getElementById('editTrackTitle').value = track.title;
            document.getElementById('editTrackArtist').value = track.artist || '';
            document.getElementById('editTrackAlbum').value = track.album || '';
            document.getElementById('editTrackGenre').value = track.genre || '';
            document.getElementById('audioModal').style.display = 'flex';
        }

        function closeAudioModal() {
            document.getElementById('audioModal').style.display = 'none';
        }

        document.getElementById('audioModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('audioModal')) closeAudioModal();
        });
    </script>
</body>
</html>
