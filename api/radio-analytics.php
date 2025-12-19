<?php
// api/radio-analytics.php - Radio Listener Analytics

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once '../config/database.php';
require_once '../config/settings.php';

$action = $_REQUEST['action'] ?? '';
$station_id = (int)($_REQUEST['station_id'] ?? 0);

if (!$station_id) {
    echo json_encode(['success' => false, 'error' => 'Station ID required']);
    exit;
}

// Verify station exists
$stmt = $conn->prepare("SELECT id FROM stations WHERE id = ?");
$stmt->execute([$station_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Invalid station']);
    exit;
}

switch ($action) {
    case 'start_session':
        startListenerSession($conn, $station_id);
        break;

    case 'ping':
        pingSession($conn, $station_id);
        break;

    case 'end_session':
        endListenerSession($conn, $station_id);
        break;

    case 'get_listeners':
        getListenerCount($conn, $station_id);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function startListenerSession($conn, $station_id) {
    $session_id = bin2hex(random_bytes(16));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Detect device type
    $device_type = 'unknown';
    $ua_lower = strtolower($user_agent);

    if (strpos($ua_lower, 'alexa') !== false || strpos($ua_lower, 'echo') !== false) {
        $device_type = 'smart_speaker';
    } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua_lower)) {
        $device_type = 'mobile';
    } elseif (preg_match('/tablet|ipad|playbook|silk/i', $ua_lower)) {
        $device_type = 'tablet';
    } elseif (!empty($user_agent)) {
        $device_type = 'desktop';
    }

    try {
        $stmt = $conn->prepare("INSERT INTO radio_listeners (station_id, session_id, ip_address, user_agent, device_type)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_ping = NOW(), device_type = ?");
        $stmt->execute([$station_id, $session_id, $ip, $user_agent, $device_type, $device_type]);

        echo json_encode([
            'success' => true,
            'session_id' => $session_id
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function pingSession($conn, $station_id) {
    $session_id = $_POST['session_id'] ?? '';

    if (empty($session_id)) {
        echo json_encode(['success' => false, 'error' => 'Session ID required']);
        return;
    }

    try {
        // Update last_ping and increment listen_time
        $stmt = $conn->prepare("UPDATE radio_listeners
            SET last_ping = NOW(), listen_time = listen_time + 30
            WHERE station_id = ? AND session_id = ?");
        $stmt->execute([$station_id, $session_id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function endListenerSession($conn, $station_id) {
    $session_id = $_POST['session_id'] ?? '';

    if (empty($session_id)) {
        echo json_encode(['success' => false, 'error' => 'Session ID required']);
        return;
    }

    try {
        // Get session data before deleting
        $stmt = $conn->prepare("SELECT listen_time, device_type FROM radio_listeners WHERE station_id = ? AND session_id = ?");
        $stmt->execute([$station_id, $session_id]);
        $session = $stmt->fetch();

        if ($session) {
            // Update station's total listen time (optional aggregation)
            // $stmt = $conn->prepare("UPDATE stations SET total_radio_time = total_radio_time + ? WHERE id = ?");
            // $stmt->execute([$session['listen_time'], $station_id]);

            // Delete the session
            $stmt = $conn->prepare("DELETE FROM radio_listeners WHERE station_id = ? AND session_id = ?");
            $stmt->execute([$station_id, $session_id]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

function getListenerCount($conn, $station_id) {
    try {
        // Clean up stale sessions (older than 2 minutes)
        $stmt = $conn->prepare("DELETE FROM radio_listeners WHERE station_id = ? AND last_ping < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        $stmt->execute([$station_id]);

        // Get active listener count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM radio_listeners WHERE station_id = ? AND last_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        $stmt->execute([$station_id]);
        $result = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'count' => (int)$result['count']
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}
