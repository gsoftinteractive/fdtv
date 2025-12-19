<?php
// api/analytics.php - Analytics Tracking API

require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'start_session':
        handleStartSession($conn);
        break;
    case 'ping':
        handlePing($conn);
        break;
    case 'end_session':
        handleEndSession($conn);
        break;
    case 'video_view':
        handleVideoView($conn);
        break;
    case 'get_viewers':
        handleGetViewers($conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Start a new viewing session
 */
function handleStartSession($conn) {
    $station_id = (int)($_POST['station_id'] ?? 0);

    if (!$station_id) {
        echo json_encode(['success' => false, 'error' => 'Station ID required']);
        return;
    }

    // Verify station exists
    $stmt = $conn->prepare("SELECT id FROM stations WHERE id = ?");
    $stmt->execute([$station_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Station not found']);
        return;
    }

    // Generate session ID
    $session_id = bin2hex(random_bytes(16));

    // Get client info
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';

    // Parse device type and browser
    $device_info = parseUserAgent($user_agent);

    // Check if this IP viewed in last hour (for unique tracking)
    $stmt = $conn->prepare("SELECT id FROM station_views WHERE station_id = ? AND ip_address = ? AND started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1");
    $stmt->execute([$station_id, $ip_address]);
    $is_unique = $stmt->fetch() ? 0 : 1;

    // Insert view record
    $stmt = $conn->prepare("INSERT INTO station_views (station_id, session_id, ip_address, user_agent, referrer, device_type, browser, os, is_unique) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $station_id,
        $session_id,
        $ip_address,
        substr($user_agent, 0, 500),
        substr($referrer, 0, 500),
        $device_info['device'],
        $device_info['browser'],
        $device_info['os'],
        $is_unique
    ]);

    // Add to active viewers
    $stmt = $conn->prepare("INSERT INTO active_viewers (station_id, session_id, last_ping) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_ping = NOW()");
    $stmt->execute([$station_id, $session_id]);

    // Update station total views
    $stmt = $conn->prepare("UPDATE stations SET total_views = total_views + 1 WHERE id = ?");
    $stmt->execute([$station_id]);

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'is_unique' => $is_unique
    ]);
}

/**
 * Handle ping (keep-alive) from viewer
 */
function handlePing($conn) {
    $station_id = (int)($_POST['station_id'] ?? 0);
    $session_id = $_POST['session_id'] ?? '';
    $video_id = (int)($_POST['video_id'] ?? 0);

    if (!$station_id || !$session_id) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    // Update active viewer
    $stmt = $conn->prepare("UPDATE active_viewers SET last_ping = NOW(), current_video_id = ? WHERE station_id = ? AND session_id = ?");
    $stmt->execute([$video_id ?: null, $station_id, $session_id]);

    // Clean old active viewers (older than 2 minutes)
    $stmt = $conn->prepare("DELETE FROM active_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    $stmt->execute();

    // Get current viewer count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM active_viewers WHERE station_id = ?");
    $stmt->execute([$station_id]);
    $count = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'viewers' => (int)$count
    ]);
}

/**
 * End viewing session
 */
function handleEndSession($conn) {
    $station_id = (int)($_POST['station_id'] ?? 0);
    $session_id = $_POST['session_id'] ?? '';
    $duration = (int)($_POST['duration'] ?? 0);

    if (!$station_id || !$session_id) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    // Update view record with end time and duration
    $stmt = $conn->prepare("UPDATE station_views SET ended_at = NOW(), duration_seconds = ? WHERE station_id = ? AND session_id = ?");
    $stmt->execute([$duration, $station_id, $session_id]);

    // Remove from active viewers
    $stmt = $conn->prepare("DELETE FROM active_viewers WHERE station_id = ? AND session_id = ?");
    $stmt->execute([$station_id, $session_id]);

    // Update station total watch time
    $stmt = $conn->prepare("UPDATE stations SET total_watch_time = total_watch_time + ? WHERE id = ?");
    $stmt->execute([$duration, $station_id]);

    echo json_encode(['success' => true]);
}

/**
 * Record video view
 */
function handleVideoView($conn) {
    $station_id = (int)($_POST['station_id'] ?? 0);
    $video_id = (int)($_POST['video_id'] ?? 0);
    $session_id = $_POST['session_id'] ?? '';
    $duration = (int)($_POST['duration'] ?? 0);
    $completed = (int)($_POST['completed'] ?? 0);

    if (!$station_id || !$video_id || !$session_id) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    // Insert video view
    $stmt = $conn->prepare("INSERT INTO video_views (station_id, video_id, session_id, watch_duration, completed) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$station_id, $video_id, $session_id, $duration, $completed]);

    // Update video stats
    $stmt = $conn->prepare("UPDATE videos SET view_count = view_count + 1, total_watch_time = total_watch_time + ? WHERE id = ?");
    $stmt->execute([$duration, $video_id]);

    echo json_encode(['success' => true]);
}

/**
 * Get current viewer count (public API)
 */
function handleGetViewers($conn) {
    $station_id = (int)($_GET['station_id'] ?? 0);

    if (!$station_id) {
        echo json_encode(['success' => false, 'error' => 'Station ID required']);
        return;
    }

    // Get active viewer count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM active_viewers WHERE station_id = ? AND last_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    $stmt->execute([$station_id]);
    $count = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'viewers' => (int)$count
    ]);
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return trim($ip);
}

/**
 * Parse user agent for device/browser info
 */
function parseUserAgent($ua) {
    $result = [
        'device' => 'unknown',
        'browser' => 'Unknown',
        'os' => 'Unknown'
    ];

    $ua_lower = strtolower($ua);

    // Detect device type
    if (preg_match('/(tablet|ipad|playbook|silk)/i', $ua)) {
        $result['device'] = 'tablet';
    } elseif (preg_match('/(mobile|iphone|android|webos|blackberry|opera mini|iemobile)/i', $ua)) {
        $result['device'] = 'mobile';
    } elseif (preg_match('/(smart-tv|smarttv|googletv|appletv|hbbtv|pov_tv|netcast)/i', $ua)) {
        $result['device'] = 'tv';
    } else {
        $result['device'] = 'desktop';
    }

    // Detect browser
    if (strpos($ua_lower, 'firefox') !== false) {
        $result['browser'] = 'Firefox';
    } elseif (strpos($ua_lower, 'edg') !== false) {
        $result['browser'] = 'Edge';
    } elseif (strpos($ua_lower, 'chrome') !== false) {
        $result['browser'] = 'Chrome';
    } elseif (strpos($ua_lower, 'safari') !== false) {
        $result['browser'] = 'Safari';
    } elseif (strpos($ua_lower, 'opera') !== false || strpos($ua_lower, 'opr') !== false) {
        $result['browser'] = 'Opera';
    } elseif (strpos($ua_lower, 'msie') !== false || strpos($ua_lower, 'trident') !== false) {
        $result['browser'] = 'Internet Explorer';
    }

    // Detect OS
    if (strpos($ua_lower, 'windows') !== false) {
        $result['os'] = 'Windows';
    } elseif (strpos($ua_lower, 'mac os') !== false || strpos($ua_lower, 'macintosh') !== false) {
        $result['os'] = 'macOS';
    } elseif (strpos($ua_lower, 'linux') !== false) {
        $result['os'] = 'Linux';
    } elseif (strpos($ua_lower, 'android') !== false) {
        $result['os'] = 'Android';
    } elseif (strpos($ua_lower, 'iphone') !== false || strpos($ua_lower, 'ipad') !== false) {
        $result['os'] = 'iOS';
    }

    return $result;
}
