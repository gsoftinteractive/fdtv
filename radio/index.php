<?php
// radio/index.php - Public Radio Player Page

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

// Get station slug from URL
$station_slug = isset($_GET['name']) ? trim($_GET['name']) : '';

if (empty($station_slug)) {
    http_response_code(404);
    die("Radio station not found.");
}

// Get station with radio enabled
$stmt = $conn->prepare("SELECT s.*, u.company_name, u.status as user_status
                        FROM stations s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.slug = ? AND s.radio_enabled = 1");
$stmt->execute([$station_slug]);
$station = $stmt->fetch();

if (!$station) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Radio Not Found</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: linear-gradient(135deg, #1e1b4b, #312e81);
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-screen { text-align: center; padding: 2rem; }
            .error-screen h1 { font-size: 4rem; margin-bottom: 1rem; }
            .error-screen p { color: rgba(255,255,255,0.7); font-size: 1.25rem; }
        </style>
    </head>
    <body>
        <div class="error-screen">
            <h1>📻</h1>
            <h2>Radio Not Found</h2>
            <p>This radio station doesn't exist or is not available.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Check subscription
if ($station['status'] != 'active' || $station['user_status'] != 'active') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo clean($station['radio_name'] ?: $station['station_name']); ?> - Offline</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: linear-gradient(135deg, #1e1b4b, #312e81);
                color: #fff;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .offline-screen { text-align: center; padding: 2rem; }
            .offline-screen h1 { font-size: 4rem; margin-bottom: 1rem; }
            .offline-screen p { color: rgba(255,255,255,0.7); font-size: 1.25rem; }
        </style>
    </head>
    <body>
        <div class="offline-screen">
            <h1>📻</h1>
            <h2>Station Offline</h2>
            <p><?php echo clean($station['radio_name'] ?: $station['station_name']); ?> is currently offline.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get primary stream
$stmt = $conn->prepare("SELECT * FROM radio_streams WHERE station_id = ? AND is_active = 1 ORDER BY is_primary DESC LIMIT 1");
$stmt->execute([$station['id']]);
$stream = $stmt->fetch();

// Get all active streams for quality selector
$stmt = $conn->prepare("SELECT * FROM radio_streams WHERE station_id = ? AND is_active = 1 ORDER BY bitrate DESC");
$stmt->execute([$station['id']]);
$all_streams = $stmt->fetchAll();

// Get now playing
$stmt = $conn->prepare("SELECT * FROM radio_now_playing WHERE station_id = ?");
$stmt->execute([$station['id']]);
$now_playing = $stmt->fetch();

// Get current program from schedule
$current_day = strtolower(date('l'));
$current_time = date('H:i:s');
$stmt = $conn->prepare("SELECT * FROM radio_schedule WHERE station_id = ? AND day_of_week = ? AND start_time <= ? AND end_time > ? AND is_active = 1 LIMIT 1");
$stmt->execute([$station['id'], $current_day, $current_time, $current_time]);
$current_program = $stmt->fetch();

// Get upcoming programs
$stmt = $conn->prepare("SELECT * FROM radio_schedule WHERE station_id = ? AND day_of_week = ? AND start_time > ? AND is_active = 1 ORDER BY start_time ASC LIMIT 3");
$stmt->execute([$station['id'], $current_day, $current_time]);
$upcoming_programs = $stmt->fetchAll();

// Get recent history
$stmt = $conn->prepare("SELECT * FROM radio_history WHERE station_id = ? ORDER BY played_at DESC LIMIT 5");
$stmt->execute([$station['id']]);
$history = $stmt->fetchAll();

// Get radio mode and audio tracks (for upload mode)
$radio_mode = $station['radio_mode'] ?? 'stream';
$shuffle_enabled = $station['radio_shuffle'] ?? 0;
$audio_tracks = [];

if ($radio_mode == 'upload') {
    $stmt = $conn->prepare("SELECT id, title, artist, album, filename, duration FROM radio_audio_tracks WHERE station_id = ? AND is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute([$station['id']]);
    $audio_tracks = $stmt->fetchAll();
}

// Colors
$primary_color = $station['radio_color_primary'] ?? '#6366f1';
$secondary_color = $station['radio_color_secondary'] ?? '#8b5cf6';

// Radio name
$radio_name = $station['radio_name'] ?: $station['station_name'];
$tagline = $station['radio_tagline'] ?? '';
$genre = $station['radio_genre'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo clean($radio_name); ?> - Live Radio</title>
    <meta name="description" content="<?php echo clean($tagline ?: 'Listen to ' . $radio_name . ' live'); ?>">
    <meta property="og:title" content="<?php echo clean($radio_name); ?> - Live Radio">
    <meta property="og:type" content="music.radio_station">

    <!-- PWA Support -->
    <meta name="theme-color" content="<?php echo $primary_color; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?php echo $primary_color; ?>;
            --secondary: <?php echo $secondary_color; ?>;
            --bg-dark: #0f0f23;
            --bg-card: rgba(255, 255, 255, 0.05);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-muted: rgba(255, 255, 255, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            line-height: 1.5;
        }

        /* Background */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            <?php if ($station['radio_background']): ?>
            background: url('../uploads/radio/<?php echo $station['radio_background']; ?>') center/cover no-repeat;
            <?php else: ?>
            background: linear-gradient(135deg, var(--bg-dark) 0%, #1a1a3e 50%, var(--bg-dark) 100%);
            <?php endif; ?>
        }

        .background::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(15, 15, 35, 0.85);
            backdrop-filter: blur(30px);
        }

        /* Main Layout */
        .radio-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .radio-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .radio-logo {
            width: 120px;
            height: 120px;
            border-radius: 24px;
            object-fit: cover;
            margin-bottom: 1.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.1);
        }

        .radio-logo-text {
            width: 120px;
            height: 120px;
            border-radius: 24px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 auto 1.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .radio-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .radio-tagline {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .radio-genre {
            display: inline-block;
            background: var(--bg-card);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Player Card */
        .player-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        /* Now Playing */
        .now-playing {
            text-align: center;
            margin-bottom: 2rem;
        }

        .now-playing-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .now-playing-dot {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.9); }
        }

        .track-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .track-artist {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Visualizer */
        .visualizer {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 3px;
            height: 40px;
            margin: 1.5rem 0;
        }

        .visualizer-bar {
            width: 4px;
            background: linear-gradient(to top, var(--primary), var(--secondary));
            border-radius: 2px;
            animation: none;
        }

        .visualizer.playing .visualizer-bar {
            animation: visualize 0.5s ease-in-out infinite alternate;
        }

        .visualizer-bar:nth-child(1) { height: 60%; animation-delay: 0s; }
        .visualizer-bar:nth-child(2) { height: 40%; animation-delay: 0.1s; }
        .visualizer-bar:nth-child(3) { height: 80%; animation-delay: 0.2s; }
        .visualizer-bar:nth-child(4) { height: 50%; animation-delay: 0.3s; }
        .visualizer-bar:nth-child(5) { height: 70%; animation-delay: 0.4s; }
        .visualizer-bar:nth-child(6) { height: 45%; animation-delay: 0.5s; }
        .visualizer-bar:nth-child(7) { height: 90%; animation-delay: 0.6s; }
        .visualizer-bar:nth-child(8) { height: 55%; animation-delay: 0.7s; }
        .visualizer-bar:nth-child(9) { height: 75%; animation-delay: 0.8s; }

        @keyframes visualize {
            0% { transform: scaleY(0.3); }
            100% { transform: scaleY(1); }
        }

        /* Play Button */
        .play-button {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }

        .play-button:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.5);
        }

        .play-button:active {
            transform: scale(0.98);
        }

        .play-button svg {
            width: 32px;
            height: 32px;
            fill: white;
        }

        .play-button .pause-icon {
            display: none;
        }

        .play-button.playing .play-icon {
            display: none;
        }

        .play-button.playing .pause-icon {
            display: block;
        }

        /* Volume Control */
        .volume-control {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0 1rem;
        }

        .volume-icon {
            color: var(--text-secondary);
            cursor: pointer;
        }

        .volume-slider {
            flex: 1;
            -webkit-appearance: none;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            outline: none;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            background: var(--primary);
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .volume-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        /* Quality Selector */
        .quality-selector {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .quality-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: transparent;
            color: var(--text-secondary);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quality-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .quality-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Listener Count */
        .listener-count {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .listener-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
        }

        /* Info Section */
        .info-section {
            width: 100%;
            max-width: 400px;
            margin-top: 2rem;
        }

        .info-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-card h3 {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        /* Schedule */
        .schedule-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .schedule-item:last-child {
            border-bottom: none;
        }

        .schedule-name {
            font-weight: 500;
        }

        .schedule-time {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .schedule-live {
            display: inline-block;
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* History */
        .history-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-track {
            font-size: 0.9rem;
        }

        .history-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Social Links */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .social-link {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .social-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Footer */
        .radio-footer {
            margin-top: auto;
            padding-top: 2rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .radio-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .radio-container {
                padding: 1rem;
            }

            .player-card {
                padding: 1.5rem;
            }

            .radio-name {
                font-size: 1.5rem;
            }
        }

        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Error state */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="background"></div>

    <div class="radio-container">
        <!-- Header -->
        <div class="radio-header">
            <?php if ($station['radio_logo']): ?>
                <img src="../uploads/radio/<?php echo clean($station['radio_logo']); ?>" alt="<?php echo clean($radio_name); ?>" class="radio-logo">
            <?php else: ?>
                <div class="radio-logo-text"><?php echo strtoupper(substr($radio_name, 0, 2)); ?></div>
            <?php endif; ?>
            <h1 class="radio-name"><?php echo clean($radio_name); ?></h1>
            <?php if ($tagline): ?>
                <p class="radio-tagline"><?php echo clean($tagline); ?></p>
            <?php endif; ?>
            <?php if ($genre): ?>
                <span class="radio-genre"><?php echo clean($genre); ?></span>
            <?php endif; ?>
        </div>

        <!-- Player Card -->
        <div class="player-card">
            <!-- Now Playing -->
            <div class="now-playing">
                <div class="now-playing-label">
                    <span class="now-playing-dot"></span>
                    <span>Now Playing</span>
                </div>
                <div class="track-title" id="trackTitle">
                    <?php echo clean($now_playing['track_title'] ?? $current_program['program_name'] ?? $radio_name); ?>
                </div>
                <div class="track-artist" id="trackArtist">
                    <?php echo clean($now_playing['artist'] ?? ($current_program ? 'with ' . ($current_program['host_name'] ?? 'Live') : '')); ?>
                </div>
            </div>

            <!-- Visualizer -->
            <div class="visualizer" id="visualizer">
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
                <div class="visualizer-bar"></div>
            </div>

            <!-- Play Button -->
            <button class="play-button" id="playButton" aria-label="Play/Pause">
                <svg class="play-icon" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
                <svg class="pause-icon" viewBox="0 0 24 24">
                    <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
            </button>

            <!-- Volume Control -->
            <div class="volume-control">
                <svg class="volume-icon" id="volumeIcon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                </svg>
                <input type="range" class="volume-slider" id="volumeSlider" min="0" max="100" value="80">
            </div>

            <?php if (count($all_streams) > 1): ?>
            <!-- Quality Selector -->
            <div class="quality-selector">
                <?php foreach ($all_streams as $i => $s): ?>
                <button class="quality-btn <?php echo $i === 0 ? 'active' : ''; ?>"
                        data-url="<?php echo clean($s['stream_url']); ?>"
                        data-bitrate="<?php echo $s['bitrate']; ?>">
                    <?php echo $s['bitrate']; ?>kbps
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Listener Count -->
            <div class="listener-count">
                <span class="listener-dot"></span>
                <span id="listenerCount">--</span> listening now
            </div>

            <div class="error-message" id="errorMessage" style="display: none;">
                Unable to connect to stream. Please try again.
            </div>
        </div>

        <!-- Info Section -->
        <div class="info-section">
            <?php if ($current_program || !empty($upcoming_programs)): ?>
            <div class="info-card">
                <h3>Schedule</h3>
                <?php if ($current_program): ?>
                <div class="schedule-item">
                    <div>
                        <span class="schedule-name"><?php echo clean($current_program['program_name']); ?></span>
                        <span class="schedule-live">ON AIR</span>
                    </div>
                    <span class="schedule-time">
                        <?php echo date('g:i A', strtotime($current_program['start_time'])); ?> -
                        <?php echo date('g:i A', strtotime($current_program['end_time'])); ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php foreach ($upcoming_programs as $program): ?>
                <div class="schedule-item">
                    <span class="schedule-name"><?php echo clean($program['program_name']); ?></span>
                    <span class="schedule-time"><?php echo date('g:i A', strtotime($program['start_time'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($history)): ?>
            <div class="info-card">
                <h3>Recently Played</h3>
                <?php foreach ($history as $track): ?>
                <div class="history-item">
                    <div class="history-track">
                        <?php echo clean($track['track_title']); ?>
                        <?php if ($track['artist']): ?>
                            <span style="color: var(--text-muted);"> - <?php echo clean($track['artist']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="history-time"><?php echo date('g:i A', strtotime($track['played_at'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Social Links -->
        <?php if ($station['radio_social_facebook'] || $station['radio_social_twitter'] || $station['radio_social_instagram'] || $station['radio_social_whatsapp']): ?>
        <div class="social-links">
            <?php if ($station['radio_social_facebook']): ?>
            <a href="<?php echo clean($station['radio_social_facebook']); ?>" class="social-link" target="_blank" aria-label="Facebook">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($station['radio_social_twitter']): ?>
            <a href="<?php echo clean($station['radio_social_twitter']); ?>" class="social-link" target="_blank" aria-label="Twitter">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($station['radio_social_instagram']): ?>
            <a href="<?php echo clean($station['radio_social_instagram']); ?>" class="social-link" target="_blank" aria-label="Instagram">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </a>
            <?php endif; ?>
            <?php if ($station['radio_social_whatsapp']): ?>
            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $station['radio_social_whatsapp']); ?>" class="social-link" target="_blank" aria-label="WhatsApp">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="radio-footer">
            <p>Powered by <a href="<?php echo SITE_URL; ?>">FDTV</a></p>
        </div>
    </div>

    <!-- Audio Player -->
    <audio id="audioPlayer" preload="none"></audio>

    <script>
        const stationData = {
            id: <?php echo $station['id']; ?>,
            name: <?php echo json_encode($radio_name); ?>,
            mode: <?php echo json_encode($radio_mode); ?>,
            shuffle: <?php echo $shuffle_enabled ? 'true' : 'false'; ?>,
            streamUrl: <?php echo json_encode($stream['stream_url'] ?? ''); ?>,
            fallbackUrl: <?php echo json_encode($stream['fallback_url'] ?? ''); ?>,
            audioTracks: <?php echo json_encode(array_map(function($track) {
                return [
                    'id' => $track['id'],
                    'title' => $track['title'],
                    'artist' => $track['artist'],
                    'album' => $track['album'],
                    'url' => '../uploads/radio/audio/' . $track['filename']
                ];
            }, $audio_tracks)); ?>
        };
    </script>
    <script>
        // Radio Player for both Stream and Upload modes
        class RadioPlayer {
            constructor(data) {
                this.data = data;
                this.audio = document.getElementById('audioPlayer');
                this.playButton = document.getElementById('playButton');
                this.visualizer = document.getElementById('visualizer');
                this.volumeSlider = document.getElementById('volumeSlider');
                this.trackTitle = document.getElementById('trackTitle');
                this.trackArtist = document.getElementById('trackArtist');
                this.errorMessage = document.getElementById('errorMessage');

                this.isPlaying = false;
                this.currentTrackIndex = 0;
                this.playlist = data.audioTracks || [];

                // Shuffle if enabled
                if (data.shuffle && this.playlist.length > 0) {
                    this.shufflePlaylist();
                }

                this.init();
            }

            init() {
                // Set initial volume
                this.audio.volume = this.volumeSlider.value / 100;

                // Play button click
                this.playButton.addEventListener('click', () => this.togglePlay());

                // Volume change
                this.volumeSlider.addEventListener('input', (e) => {
                    this.audio.volume = e.target.value / 100;
                });

                // Audio events
                this.audio.addEventListener('play', () => this.onPlay());
                this.audio.addEventListener('pause', () => this.onPause());
                this.audio.addEventListener('ended', () => this.onEnded());
                this.audio.addEventListener('error', (e) => this.onError(e));

                // Quality selector (for stream mode)
                document.querySelectorAll('.quality-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const url = e.target.dataset.url;
                        if (url) {
                            document.querySelectorAll('.quality-btn').forEach(b => b.classList.remove('active'));
                            e.target.classList.add('active');
                            this.data.streamUrl = url;
                            if (this.isPlaying) {
                                this.audio.src = url;
                                this.audio.play();
                            }
                        }
                    });
                });

                // Set source based on mode
                if (this.data.mode === 'upload' && this.playlist.length > 0) {
                    this.loadTrack(0);
                } else if (this.data.streamUrl) {
                    this.audio.src = this.data.streamUrl;
                }
            }

            shufflePlaylist() {
                for (let i = this.playlist.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [this.playlist[i], this.playlist[j]] = [this.playlist[j], this.playlist[i]];
                }
            }

            loadTrack(index) {
                if (this.playlist.length === 0) return;

                this.currentTrackIndex = index % this.playlist.length;
                const track = this.playlist[this.currentTrackIndex];

                this.audio.src = track.url;
                this.updateNowPlaying(track);
            }

            updateNowPlaying(track) {
                if (this.trackTitle) {
                    this.trackTitle.textContent = track.title || this.data.name;
                }
                if (this.trackArtist) {
                    this.trackArtist.textContent = track.artist || '';
                }

                // Update media session for lock screen
                if ('mediaSession' in navigator) {
                    navigator.mediaSession.metadata = new MediaMetadata({
                        title: track.title || this.data.name,
                        artist: track.artist || '',
                        album: track.album || this.data.name
                    });
                }
            }

            togglePlay() {
                if (this.isPlaying) {
                    this.audio.pause();
                } else {
                    this.audio.play().catch(e => {
                        console.error('Play failed:', e);
                        this.showError();
                    });
                }
            }

            onPlay() {
                this.isPlaying = true;
                this.playButton.classList.add('playing');
                this.visualizer.classList.add('playing');
                this.hideError();
            }

            onPause() {
                this.isPlaying = false;
                this.playButton.classList.remove('playing');
                this.visualizer.classList.remove('playing');
            }

            onEnded() {
                // Auto-advance to next track (upload mode)
                if (this.data.mode === 'upload' && this.playlist.length > 0) {
                    this.nextTrack();
                }
            }

            nextTrack() {
                this.loadTrack(this.currentTrackIndex + 1);
                this.audio.play().catch(e => console.error('Auto-play failed:', e));
            }

            previousTrack() {
                this.loadTrack(this.currentTrackIndex - 1 + this.playlist.length);
                this.audio.play().catch(e => console.error('Play failed:', e));
            }

            onError(e) {
                console.error('Audio error:', e);

                // Try fallback for stream mode
                if (this.data.mode === 'stream' && this.data.fallbackUrl && this.audio.src !== this.data.fallbackUrl) {
                    console.log('Trying fallback URL...');
                    this.audio.src = this.data.fallbackUrl;
                    if (this.isPlaying) {
                        this.audio.play().catch(err => this.showError());
                    }
                } else if (this.data.mode === 'upload' && this.playlist.length > 1) {
                    // Skip to next track if current fails
                    console.log('Skipping broken track...');
                    setTimeout(() => this.nextTrack(), 1000);
                } else {
                    this.showError();
                }
            }

            showError() {
                if (this.errorMessage) {
                    this.errorMessage.style.display = 'block';
                }
            }

            hideError() {
                if (this.errorMessage) {
                    this.errorMessage.style.display = 'none';
                }
            }
        }

        // Initialize player
        const player = new RadioPlayer(stationData);

        // Media session controls (for lock screen)
        if ('mediaSession' in navigator) {
            navigator.mediaSession.setActionHandler('play', () => player.togglePlay());
            navigator.mediaSession.setActionHandler('pause', () => player.togglePlay());
            if (stationData.mode === 'upload') {
                navigator.mediaSession.setActionHandler('nexttrack', () => player.nextTrack());
                navigator.mediaSession.setActionHandler('previoustrack', () => player.previousTrack());
            }
        }
    </script>
</body>
</html>
