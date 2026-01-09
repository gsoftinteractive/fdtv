<?php
// station/view.php - Professional Live TV Station View

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

// Get station slug from URL
$station_slug = isset($_GET['name']) ? trim($_GET['name']) : '';

if (empty($station_slug)) {
    http_response_code(404);
    die("Station not found.");
}

// Get station details with new fields
$stmt = $conn->prepare("SELECT s.*, u.company_name, u.status as user_status
                        FROM stations s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.slug = ?");
$stmt->execute([$station_slug]);
$station = $stmt->fetch();

// Set defaults for new fields if not present
$station['logo_position'] = $station['logo_position'] ?? 'top-right';
$station['logo_opacity'] = $station['logo_opacity'] ?? 0.9;
$station['logo_size'] = $station['logo_size'] ?? 'medium';
$station['mode'] = $station['mode'] ?? 'playlist';

// Get active live feed if in live mode
$active_feed = null;
if ($station['mode'] == 'live') {
    $stmt = $conn->prepare("SELECT * FROM live_feeds WHERE station_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$station['id']]);
    $active_feed = $stmt->fetch();
}

if (!$station) {
    http_response_code(404);
    die("Station not found.");
}

// Check if station is active
if ($station['status'] != 'active' || $station['user_status'] != 'active') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo clean($station['station_name']); ?> - Offline</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                background: #000; 
                color: #fff; 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .offline-screen {
                text-align: center;
                padding: 2rem;
            }
            .offline-screen h1 { font-size: 3rem; margin-bottom: 1rem; }
            .offline-screen p { color: #888; font-size: 1.25rem; }
            .static-effect {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: url('data:image/svg+xml,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noise"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noise)" opacity="0.3"/></svg>');
                pointer-events: none;
                opacity: 0.1;
            }
        </style>
    </head>
    <body>
        <div class="static-effect"></div>
        <div class="offline-screen">
            <h1>📺 Station Offline</h1>
            <p><?php echo clean($station['station_name']); ?> is currently not broadcasting.</p>
            <p style="margin-top: 1rem; font-size: 1rem;">Please check back later.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get playlist settings with defaults
$playlist_mode = $station['playlist_mode'] ?? 'sequential';
$jingle_enabled = $station['jingle_enabled'] ?? 1;
$advert_enabled = $station['advert_enabled'] ?? 0;
$jingle_interval = $station['default_jingle_interval'] ?? 3;
$advert_interval = $station['default_advert_interval'] ?? 5;

// Get videos for this station (ordered based on playlist mode)
$video_order = 'uploaded_at ASC';
if ($playlist_mode === 'priority') {
    $video_order = 'priority ASC, uploaded_at ASC';
} elseif ($playlist_mode === 'shuffle') {
    $video_order = 'RAND()';
}

$stmt = $conn->prepare("SELECT * FROM videos WHERE station_id = ? AND status = 'ready' ORDER BY $video_order");
$stmt->execute([$station['id']]);
$videos = $stmt->fetchAll();

// Get jingles if enabled
$jingles = [];
if ($jingle_enabled) {
    $stmt = $conn->prepare("SELECT * FROM jingles WHERE station_id = ? AND is_active = 1 ORDER BY priority ASC, RAND()");
    $stmt->execute([$station['id']]);
    $jingles = $stmt->fetchAll();
}

// Get active ticker type
$active_ticker_type = $station['active_ticker_type'] ?? 'breaking';

// Color map for named colors to hex
$color_map = [
    'red' => '#dc2626',
    'purple' => '#7c3aed',
    'green' => '#059669',
    'blue' => '#2563eb',
    'orange' => '#ea580c',
    'pink' => '#db2777',
    'teal' => '#0d9488',
    'indigo' => '#4f46e5',
    'yellow' => '#eab308',
    'cyan' => '#06b6d4',
    'rose' => '#f43f5e',
    'black' => '#000000'
];

// Get station ticker color setting (from display settings) - this is the main color
$station_ticker_color = $station['ticker_color'] ?? 'red';
// Convert named color to hex if needed
if (isset($color_map[$station_ticker_color])) {
    $station_ticker_color = $color_map[$station_ticker_color];
}

// Get ticker settings based on active type
$ticker_data = [];
$ticker_style = [
    'bg_color' => $station_ticker_color, // Use station's ticker color setting
    'color' => '#ffffff',
    'speed' => (int)($station['ticker_speed'] ?? 60),
    'font_size' => 15,
    'label' => $station['ticker_label'] ?? 'BREAKING'
];

if ($active_ticker_type === 'breaking') {
    // Get breaking news tickers
    $stmt = $conn->prepare("SELECT * FROM station_tickers WHERE station_id = ? AND is_active = 1 AND (ticker_category = 'breaking' OR ticker_category IS NULL) ORDER BY priority DESC, created_at DESC");
    $stmt->execute([$station['id']]);
    $ticker_data = $stmt->fetchAll();
    // Keep station's ticker color, label, and speed - don't override from individual messages

} elseif ($active_ticker_type === 'events') {
    // Get event tickers (only currently scheduled ones)
    $current_datetime = date('Y-m-d H:i:s');
    $current_day = strtolower(date('l')); // monday, tuesday, etc.

    $stmt = $conn->prepare("SELECT * FROM station_tickers
        WHERE station_id = ?
        AND is_active = 1
        AND ticker_category = 'events'
        AND (scheduled_start IS NULL OR scheduled_start <= ?)
        AND (scheduled_end IS NULL OR scheduled_end >= ?)
        ORDER BY priority DESC, created_at DESC");
    $stmt->execute([$station['id'], $current_datetime, $current_datetime]);
    $ticker_data = $stmt->fetchAll();
    // Keep station's ticker color, label, and speed - don't override from individual messages

} elseif ($active_ticker_type === 'schedule') {
    // Generate program schedule ticker from videos
    $stmt = $conn->prepare("SELECT v.title, v.duration, s.start_time, s.end_time
        FROM schedule_items s
        JOIN videos v ON s.video_id = v.id
        WHERE s.station_id = ?
        ORDER BY s.start_time ASC
        LIMIT 10");
    $stmt->execute([$station['id']]);
    $schedule_items = $stmt->fetchAll();

    // Convert schedule to ticker format
    if (!empty($schedule_items)) {
        foreach ($schedule_items as $item) {
            $time_str = date('g:i A', strtotime($item['start_time']));
            $ticker_data[] = [
                'message' => $time_str . ' - ' . $item['title'],
                'icon' => '📺'
            ];
        }
    }
    // Keep station's ticker color, label, and speed from display settings
}

// Fallback to default tickers
if (empty($ticker_data)) {
    $ticker_data = [
        ['message' => 'Welcome to ' . $station['station_name'] . ' • Your 24/7 Entertainment Channel', 'type' => 'info'],
        ['message' => 'Stay tuned for more exciting content • Like and Subscribe!', 'type' => 'info']
    ];
}

// Build ticker text with icons
$ticker_text = '';
foreach ($ticker_data as $ticker) {
    $icon = isset($ticker['icon']) && !empty($ticker['icon']) ? $ticker['icon'] . ' ' : '';
    $msg = is_array($ticker) ? (isset($ticker['message']) ? $ticker['message'] : $ticker[0]) : $ticker;
    $ticker_text .= $icon . $msg . ' ••• ';
}

// Current time for "live" display
$current_time = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo clean($station['station_name']); ?> - Live TV</title>
    <meta name="description" content="Watch <?php echo clean($station['station_name']); ?> live - 24/7 streaming">
    <meta property="og:title" content="<?php echo clean($station['station_name']); ?> - Live TV">
    <meta property="og:type" content="video.other">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/live-tv.css">
</head>
<body>
    <!-- Main TV Container -->
    <div class="tv-container" id="tvContainer">
        
        <!-- Video Player -->
        <div class="video-wrapper" id="videoWrapper">
            <video id="tvPlayer" playsinline></video>
            
            <!-- Station Logo Overlay - Dynamic Position -->
            <div class="station-logo position-<?php echo $station['logo_position']; ?> size-<?php echo $station['logo_size']; ?>" id="stationLogo" style="opacity: <?php echo $station['logo_opacity']; ?>;">
                <?php if ($station['logo'] || $station['logo_path']): ?>
                    <img src="../uploads/logos/<?php echo $station['logo_path'] ?: $station['logo']; ?>" alt="<?php echo clean($station['station_name']); ?>">
                <?php else: ?>
                    <span class="logo-text"><?php echo strtoupper(substr($station['station_name'], 0, 3)); ?></span>
                <?php endif; ?>
            </div>

            <!-- Live Feed Container (for external streams) -->
            <div id="liveFeedContainer" class="live-feed-container" style="display: none;">
                <iframe id="liveFeedIframe" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                <div id="liveFeedVideo" class="live-feed-video"></div>
            </div>

            <!-- Live Badge -->
            <div class="live-badge" id="liveBadge">
                <span class="live-dot"></span>
                <span class="live-text">LIVE</span>
            </div>

            <!-- Current Time -->
            <div class="time-display" id="timeDisplay">
                <span class="time-value"><?php echo $current_time; ?></span>
            </div>

            <!-- Program Info Bar (shows on hover/tap) -->
            <div class="program-info" id="programInfo">
                <div class="program-now">
                    <span class="program-label">NOW PLAYING</span>
                    <span class="program-title" id="currentProgram">Loading...</span>
                </div>
                <div class="program-next">
                    <span class="program-label">UP NEXT</span>
                    <span class="program-title" id="nextProgram">-</span>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div class="loading-overlay" id="loadingOverlay">
                <div class="loading-spinner"></div>
                <p>Connecting to broadcast...</p>
            </div>

            <!-- No Content Overlay -->
            <div class="no-content-overlay" id="noContentOverlay" style="display: none;">
                <div class="no-content-icon">📺</div>
                <h2>No Content Available</h2>
                <p>This station hasn't uploaded any videos yet.</p>
            </div>

            <!-- Click to Unmute Overlay -->
            <div class="unmute-overlay" id="unmuteOverlay" style="display: none;">
                <button class="unmute-btn" id="unmuteBtn">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <line x1="23" y1="9" x2="17" y2="15"/>
                        <line x1="17" y1="9" x2="23" y2="15"/>
                    </svg>
                    <span>Tap to Unmute</span>
                </button>
            </div>
        </div>

        <!-- Dynamic Ticker Bar -->
        <div class="ticker-bar" id="tickerBar" style="background: <?php echo $ticker_style['bg_color']; ?>;">
            <div class="ticker-label">
                <span class="ticker-label-text" style="color: <?php echo $ticker_style['bg_color']; ?>;"><?php echo $ticker_style['label']; ?></span>
            </div>
            <div class="ticker-content">
                <div class="ticker-track" id="tickerTrack" style="animation-duration: <?php echo $ticker_style['speed']; ?>s;">
                    <span class="ticker-text" style="color: <?php echo $ticker_style['color']; ?>; font-size: <?php echo $ticker_style['font_size']; ?>px;"><?php echo clean($ticker_text . $ticker_text); ?></span>
                </div>
            </div>
        </div>

        <!-- Bottom Control Bar -->
        <div class="control-bar" id="controlBar">
            <div class="control-left">
                <button class="control-btn" id="muteBtn" title="Mute/Unmute">
                    <svg class="icon-volume-on" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
                    </svg>
                    <svg class="icon-volume-off" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                        <line x1="23" y1="9" x2="17" y2="15"/>
                        <line x1="17" y1="9" x2="23" y2="15"/>
                    </svg>
                </button>
                <input type="range" class="volume-slider" id="volumeSlider" min="0" max="100" value="80">
            </div>
            
            <div class="control-center">
                <span class="station-name"><?php echo clean($station['station_name']); ?></span>
                <span class="viewer-count" id="viewerCount">
                    <span class="viewer-dot"></span>
                    <span id="viewerNumber">--</span> watching
                </span>
            </div>
            
            <div class="control-right">
                <button class="control-btn" id="fullscreenBtn" title="Fullscreen">
                    <svg class="icon-fullscreen" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 3 21 3 21 9"/>
                        <polyline points="9 21 3 21 3 15"/>
                        <line x1="21" y1="3" x2="14" y2="10"/>
                        <line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                    <svg class="icon-exit-fullscreen" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;">
                        <polyline points="4 14 10 14 10 20"/>
                        <polyline points="20 10 14 10 14 4"/>
                        <line x1="14" y1="10" x2="21" y2="3"/>
                        <line x1="3" y1="21" x2="10" y2="14"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Channel Info Panel (Mobile slide-up) -->
    <div class="channel-info-panel" id="channelInfoPanel">
        <div class="panel-header">
            <h2><?php echo clean($station['station_name']); ?></h2>
            <p><?php echo clean($station['company_name']); ?></p>
        </div>
        <div class="panel-schedule">
            <h3>Now Playing</h3>
            <div class="schedule-item current" id="scheduleNow">
                <span class="schedule-time">--:--</span>
                <span class="schedule-title">Loading...</span>
            </div>
            <h3>Coming Up</h3>
            <div class="schedule-list" id="scheduleList">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <!-- Video Data -->
    <script>
        const stationData = {
            id: <?php echo $station['id']; ?>,
            name: <?php echo json_encode($station['station_name']); ?>,
            slug: <?php echo json_encode($station['slug']); ?>,
            mode: <?php echo json_encode($station['mode']); ?>,
            playlistMode: <?php echo json_encode($playlist_mode); ?>,
            jingleEnabled: <?php echo $jingle_enabled ? 'true' : 'false'; ?>,
            advertEnabled: <?php echo $advert_enabled ? 'true' : 'false'; ?>,
            jingleInterval: <?php echo json_encode($jingle_interval); ?>,
            advertInterval: <?php echo json_encode($advert_interval); ?>,
            // Display Settings (from admin dashboard)
            ticker_color: <?php echo json_encode($station['ticker_color'] ?? 'red'); ?>,
            ticker_label: <?php echo json_encode($station['ticker_label'] ?? 'BREAKING'); ?>,
            ticker_mode: <?php echo json_encode($station['ticker_mode'] ?? 'single'); ?>,
            ticker_speed: <?php echo (int)($station['ticker_speed'] ?? 60); ?>,
            clock_position_x: <?php echo (int)($station['clock_position_x'] ?? 50); ?>,
            clock_position_y: <?php echo (int)($station['clock_position_y'] ?? 5); ?>,
            social_badges: <?php echo json_encode($station['social_badges'] ?? '[]'); ?>,
            lower_thirds_presets: <?php echo json_encode($station['lower_thirds_presets'] ?? '[]'); ?>,
            videos: <?php echo json_encode(array_map(function($v) use ($station) {
                return [
                    'id' => $v['id'],
                    'title' => $v['title'],
                    'filename' => $v['filename'],
                    'duration' => $v['duration'] ?? 0,
                    'contentType' => $v['content_type'] ?? 'regular',
                    'priority' => $v['priority'] ?? 3,
                    'url' => '../uploads/videos/' . $station['id'] . '/' . $v['filename']
                ];
            }, $videos)); ?>,
            jingles: <?php echo json_encode(array_map(function($j) use ($station) {
                return [
                    'id' => $j['id'],
                    'title' => $j['title'],
                    'filename' => $j['filename'],
                    'type' => $j['jingle_type'],
                    'priority' => $j['priority'] ?? 3,
                    'frequency' => $j['play_frequency'],
                    'url' => '../uploads/jingles/' . $station['id'] . '/' . $j['filename']
                ];
            }, $jingles)); ?>,
            liveFeed: <?php echo json_encode($active_feed ? [
                'id' => $active_feed['id'],
                'name' => $active_feed['name'],
                'sourceType' => $active_feed['source_type'],
                'sourceUrl' => $active_feed['source_url'],
                'embedCode' => $active_feed['embed_code']
            ] : null); ?>,
            ticker: <?php echo json_encode($ticker_text); ?>,
            tickerStyle: <?php echo json_encode($ticker_style); ?>
        };
    </script>
    <!-- HLS.js for HLS streams -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="../assets/js/live-tv-player.js"></script>
</body>
</html>