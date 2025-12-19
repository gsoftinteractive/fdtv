<?php
// dashboard/ticker.php - Enhanced Ticker Management (3 Types)

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
$active_ticker_type = $station['active_ticker_type'] ?? 'breaking';

// Get current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'breaking';
if (!in_array($current_tab, ['breaking', 'events', 'schedule'])) {
    $current_tab = 'breaking';
}

// Handle Update Active Ticker Type
if (isset($_POST['action']) && $_POST['action'] == 'set_ticker_type') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $type = $_POST['ticker_type'];
        if (in_array($type, ['breaking', 'events', 'schedule', 'none'])) {
            $stmt = $conn->prepare("UPDATE stations SET active_ticker_type = ? WHERE id = ?");
            $stmt->execute([$type, $station_id]);
            $active_ticker_type = $type;
            set_flash("Active ticker type updated to " . ucfirst($type) . "!", "success");
        }
    }
    redirect('ticker.php?tab=' . $current_tab);
}

// Handle Add Breaking News Ticker
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_breaking'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $message = trim($_POST['message']);
        $priority = (int)$_POST['priority'];
        $color = $_POST['color'] ?? '#ffffff';
        $bg_color = $_POST['bg_color'] ?? '#dc2626';
        $speed = $_POST['speed'] ?? 'normal';
        $font_size = $_POST['font_size'] ?? 'medium';

        if (!empty($message)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM station_tickers WHERE station_id = ? AND ticker_category = 'breaking' AND is_active = 1");
            $stmt->execute([$station_id]);
            $count = $stmt->fetch()['count'];

            if ($count >= 10) {
                set_flash("You can have maximum 10 active breaking news messages.", "warning");
            } else {
                $stmt = $conn->prepare("INSERT INTO station_tickers (station_id, message, type, ticker_category, priority, color, bg_color, speed, font_size) VALUES (?, ?, 'breaking', 'breaking', ?, ?, ?, ?, ?)");
                $stmt->execute([$station_id, $message, $priority, $color, $bg_color, $speed, $font_size]);
                set_flash("Breaking news message added!", "success");
            }
        }
    }
    redirect('ticker.php?tab=breaking');
}

// Handle Add Event Announcement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_event'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $message = trim($_POST['message']);
        $event_type = $_POST['event_type'] ?? 'general';
        $icon = $_POST['icon'] ?? null;
        $scheduled_start = !empty($_POST['scheduled_start']) ? $_POST['scheduled_start'] : null;
        $scheduled_end = !empty($_POST['scheduled_end']) ? $_POST['scheduled_end'] : null;
        $color = $_POST['color'] ?? '#ffffff';
        $bg_color = $_POST['bg_color'] ?? '#7c3aed';

        if (!empty($message)) {
            $stmt = $conn->prepare("INSERT INTO station_tickers (station_id, message, type, ticker_category, event_type, icon, scheduled_start, scheduled_end, color, bg_color, is_active) VALUES (?, ?, 'info', 'events', ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$station_id, $message, $event_type, $icon, $scheduled_start, $scheduled_end, $color, $bg_color]);
            set_flash("Event announcement added!", "success");
        }
    }
    redirect('ticker.php?tab=events');
}

// Handle Toggle Active
if (isset($_GET['toggle']) && isset($_GET['csrf'])) {
    if (verify_csrf_token($_GET['csrf'])) {
        $ticker_id = (int)$_GET['toggle'];

        $stmt = $conn->prepare("SELECT * FROM station_tickers WHERE id = ? AND station_id = ?");
        $stmt->execute([$ticker_id, $station_id]);
        $ticker = $stmt->fetch();

        if ($ticker) {
            $new_status = $ticker['is_active'] ? 0 : 1;
            $stmt = $conn->prepare("UPDATE station_tickers SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $ticker_id]);
            set_flash("Ticker status updated!", "success");
        }
    }
    redirect('ticker.php?tab=' . $current_tab);
}

// Handle Delete
if (isset($_GET['delete']) && isset($_GET['csrf'])) {
    if (verify_csrf_token($_GET['csrf'])) {
        $ticker_id = (int)$_GET['delete'];

        $stmt = $conn->prepare("DELETE FROM station_tickers WHERE id = ? AND station_id = ?");
        $stmt->execute([$ticker_id, $station_id]);
        set_flash("Ticker message deleted!", "success");
    }
    redirect('ticker.php?tab=' . $current_tab);
}

// Get tickers by category - use COALESCE for backwards compatibility
$stmt = $conn->prepare("SELECT * FROM station_tickers WHERE station_id = ? AND (ticker_category = 'breaking' OR ticker_category IS NULL) ORDER BY priority DESC, created_at DESC");
$stmt->execute([$station_id]);
$breaking_tickers = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM station_tickers WHERE station_id = ? AND ticker_category = 'events' ORDER BY scheduled_start ASC, created_at DESC");
$stmt->execute([$station_id]);
$event_tickers = $stmt->fetchAll();

// Get upcoming schedule for Program Schedule ticker
$stmt = $conn->prepare("SELECT s.*, v.title as video_title, v.duration
                        FROM schedules s
                        JOIN videos v ON s.video_id = v.id
                        WHERE s.station_id = ?
                        ORDER BY s.day_of_week, s.play_time");
$stmt->execute([$station_id]);
$schedules = $stmt->fetchAll();

$flash = get_flash();
$csrf_token = generate_csrf_token();

// Icon options for events
$event_icons = [
    '' => 'None',
    '🎂' => 'Birthday',
    '🎉' => 'Celebration',
    '📢' => 'Announcement',
    '⚡' => 'Breaking',
    '🔴' => 'Live',
    '📺' => 'TV',
    '🎬' => 'Movie',
    '🎵' => 'Music',
    '⚽' => 'Sports',
    '🏆' => 'Award',
    '💰' => 'Promo',
    '🛒' => 'Sale',
    '❤️' => 'Love',
    '🌟' => 'Star',
    '📅' => 'Event'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticker Manager - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .ticker-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 1.5rem;
            background: #1f2937;
            border-radius: 8px;
            overflow: hidden;
        }
        .ticker-tab {
            flex: 1;
            padding: 1rem 1.5rem;
            text-align: center;
            color: #9ca3af;
            text-decoration: none;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .ticker-tab:hover { background: #374151; color: #fff; }
        .ticker-tab.active { background: var(--primary); color: #fff; }
        .ticker-tab-icon { display: block; font-size: 1.5rem; margin-bottom: 0.25rem; }

        .active-type-selector {
            background: #1f2937;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .active-type-selector label { color: #9ca3af; margin: 0; }
        .active-type-selector select { max-width: 200px; }

        .ticker-preview {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .preview-ticker-bar {
            height: 44px;
            display: flex;
            align-items: stretch;
        }
        .preview-ticker-label {
            background: #000;
            padding: 0 20px;
            display: flex;
            align-items: center;
            font-weight: 800;
            font-size: 0.875rem;
            letter-spacing: 2px;
        }
        .preview-ticker-label span {
            background: #fff;
            color: #dc2626;
            padding: 6px 12px;
        }
        .preview-ticker-content {
            flex: 1;
            background: #dc2626;
            overflow: hidden;
            display: flex;
            align-items: center;
        }
        .preview-ticker-track {
            display: flex;
            animation: ticker-scroll 30s linear infinite;
            white-space: nowrap;
        }
        .preview-ticker-text {
            font-weight: 600;
            font-size: 0.9375rem;
            padding: 0 20px;
            color: #fff;
        }
        @keyframes ticker-scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .color-inputs { display: flex; gap: 1rem; align-items: center; }
        .color-input-group { display: flex; align-items: center; gap: 0.5rem; }
        .color-input-group input[type="color"] {
            width: 40px; height: 40px; padding: 2px; border-radius: 6px; cursor: pointer;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .ticker-list { display: flex; flex-direction: column; gap: 0.75rem; }
        .ticker-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #1f2937;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        .ticker-item.inactive { opacity: 0.6; border-left-color: #6b7280; }
        .ticker-item-content { flex: 1; min-width: 0; }
        .ticker-message { margin: 0.5rem 0; word-break: break-word; }
        .ticker-meta { font-size: 0.75rem; color: #9ca3af; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .ticker-item-actions { display: flex; gap: 0.5rem; flex-shrink: 0; margin-left: 1rem; }

        .ticker-type-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-breaking { background: #fee2e2; color: #991b1b; }
        .badge-event { background: #ede9fe; color: #5b21b6; }

        .event-icon { font-size: 1.5rem; margin-right: 0.5rem; }
        .schedule-preview { background: #1f2937; border-radius: 8px; padding: 1rem; }
        .schedule-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #374151;
        }
        .schedule-item:last-child { border-bottom: none; }
        .schedule-time { font-weight: 600; color: var(--primary); min-width: 80px; }
        .schedule-title { flex: 1; }
        .schedule-label { font-size: 0.75rem; color: #9ca3af; text-transform: uppercase; }

        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .empty-state svg { margin-bottom: 1rem; opacity: 0.5; }
        .ticker-count { font-size: 0.875rem; color: #9ca3af; }
        .datetime-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        input[type="datetime-local"] {
            width: 100%; padding: 0.75rem; border: 1px solid var(--border);
            border-radius: 6px; font-size: 1rem;
        }
        .help-text { font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; }
        .schedule-info-box {
            background: #1e3a5f;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .schedule-info-box h4 { color: #93c5fd; margin-bottom: 0.5rem; }
        .schedule-info-box p { color: #bfdbfe; font-size: 0.875rem; margin: 0; }

        @media (max-width: 768px) {
            .ticker-tabs { flex-direction: column; }
            .datetime-row { grid-template-columns: 1fr; }
            .ticker-item { flex-direction: column; align-items: flex-start; }
            .ticker-item-actions { margin-left: 0; margin-top: 1rem; }
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
            <h1>Ticker Manager</h1>
            <p style="color: #9ca3af; margin-bottom: 1.5rem;">Configure the scrolling messages that appear at the bottom of your TV station.</p>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Active Ticker Type Selector -->
            <div class="active-type-selector">
                <div>
                    <strong>Active Ticker:</strong>
                    <span style="color: #9ca3af; margin-left: 0.5rem;">Choose which ticker type to display</span>
                </div>
                <form method="POST" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="set_ticker_type">
                    <select name="ticker_type" onchange="this.form.submit()">
                        <option value="breaking" <?php echo $active_ticker_type == 'breaking' ? 'selected' : ''; ?>>Breaking News</option>
                        <option value="events" <?php echo $active_ticker_type == 'events' ? 'selected' : ''; ?>>Event Announcements</option>
                        <option value="schedule" <?php echo $active_ticker_type == 'schedule' ? 'selected' : ''; ?>>Program Schedule</option>
                        <option value="none" <?php echo $active_ticker_type == 'none' ? 'selected' : ''; ?>>None (Hidden)</option>
                    </select>
                </form>
            </div>

            <!-- Ticker Type Tabs -->
            <div class="ticker-tabs">
                <a href="?tab=breaking" class="ticker-tab <?php echo $current_tab == 'breaking' ? 'active' : ''; ?>">
                    <span class="ticker-tab-icon">📰</span>
                    Breaking News
                </a>
                <a href="?tab=events" class="ticker-tab <?php echo $current_tab == 'events' ? 'active' : ''; ?>">
                    <span class="ticker-tab-icon">🎉</span>
                    Events
                </a>
                <a href="?tab=schedule" class="ticker-tab <?php echo $current_tab == 'schedule' ? 'active' : ''; ?>">
                    <span class="ticker-tab-icon">📺</span>
                    Program Schedule
                </a>
            </div>

            <!-- BREAKING NEWS TAB -->
            <?php if ($current_tab == 'breaking'): ?>

            <div class="ticker-preview">
                <div class="preview-ticker-bar">
                    <div class="preview-ticker-label"><span>BREAKING</span></div>
                    <div class="preview-ticker-content">
                        <div class="preview-ticker-track">
                            <span class="preview-ticker-text">
                                <?php
                                $preview_text = '';
                                foreach ($breaking_tickers as $t) {
                                    if ($t['is_active']) {
                                        $preview_text .= clean($t['message']) . ' ••• ';
                                    }
                                }
                                echo $preview_text ? $preview_text . $preview_text : 'No active messages. Add one below!';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Add Breaking News</h2>
                    <span class="ticker-count"><?php echo count(array_filter($breaking_tickers, fn($t) => $t['is_active'])); ?> / 10 active</span>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label>Message Text *</label>
                        <textarea name="message" rows="2" maxlength="500" placeholder="Enter your breaking news..." required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority">
                                <option value="0">Normal</option>
                                <option value="1">High</option>
                                <option value="2">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Speed</label>
                            <select name="speed">
                                <option value="slow">Slow</option>
                                <option value="normal" selected>Normal</option>
                                <option value="fast">Fast</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Font Size</label>
                            <select name="font_size">
                                <option value="small">Small</option>
                                <option value="medium" selected>Medium</option>
                                <option value="large">Large</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Colors</label>
                        <div class="color-inputs">
                            <div class="color-input-group">
                                <span>Text:</span>
                                <input type="color" name="color" value="#ffffff">
                            </div>
                            <div class="color-input-group">
                                <span>Background:</span>
                                <input type="color" name="bg_color" value="#dc2626">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="add_breaking" class="btn">Add Breaking News</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Your Breaking News (<?php echo count($breaking_tickers); ?>)</h2>
                </div>

                <?php if (empty($breaking_tickers)): ?>
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <rect x="2" y="7" width="20" height="10" rx="2"/>
                            <line x1="6" y1="12" x2="18" y2="12"/>
                        </svg>
                        <p>No breaking news messages yet.</p>
                    </div>
                <?php else: ?>
                    <div class="ticker-list">
                        <?php foreach ($breaking_tickers as $ticker): ?>
                        <div class="ticker-item <?php echo $ticker['is_active'] ? '' : 'inactive'; ?>" style="border-left-color: <?php echo $ticker['bg_color'] ?? '#dc2626'; ?>;">
                            <div class="ticker-item-content">
                                <span class="ticker-type-badge badge-breaking">Breaking</span>
                                <p class="ticker-message"><?php echo clean($ticker['message']); ?></p>
                                <div class="ticker-meta">
                                    <span>Priority: <?php echo $ticker['priority'] == 2 ? 'Urgent' : ($ticker['priority'] == 1 ? 'High' : 'Normal'); ?></span>
                                    <span>•</span>
                                    <span>Speed: <?php echo ucfirst($ticker['speed'] ?? 'normal'); ?></span>
                                    <span>•</span>
                                    <span><?php echo format_date($ticker['created_at']); ?></span>
                                </div>
                            </div>
                            <div class="ticker-item-actions">
                                <a href="?tab=breaking&toggle=<?php echo $ticker['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                   class="btn btn-small <?php echo $ticker['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                    <?php echo $ticker['is_active'] ? 'Disable' : 'Enable'; ?>
                                </a>
                                <a href="?tab=breaking&delete=<?php echo $ticker['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                   onclick="return confirm('Delete this message?')"
                                   class="btn btn-small btn-danger">Delete</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>

            <!-- EVENTS TAB -->
            <?php if ($current_tab == 'events'): ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Add Event Announcement</h2>
                </div>
                <p style="color: #9ca3af; margin-bottom: 1rem;">Create announcements for birthdays, promotions, holidays, and special events.</p>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Event Type</label>
                            <select name="event_type">
                                <option value="general">General Announcement</option>
                                <option value="birthday">Birthday Shoutout</option>
                                <option value="anniversary">Anniversary</option>
                                <option value="promotion">Promotion / Sale</option>
                                <option value="holiday">Holiday Greeting</option>
                                <option value="advert">Advertisement</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Icon</label>
                            <select name="icon">
                                <?php foreach ($event_icons as $emoji => $label): ?>
                                    <option value="<?php echo $emoji; ?>"><?php echo $emoji ? "$emoji $label" : $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Message *</label>
                        <textarea name="message" rows="2" maxlength="500" placeholder="Happy Birthday John! Wishing you a wonderful day..." required></textarea>
                    </div>

                    <div class="datetime-row">
                        <div class="form-group">
                            <label>Start Date/Time (Optional)</label>
                            <input type="datetime-local" name="scheduled_start">
                            <p class="help-text">Leave empty to show immediately</p>
                        </div>
                        <div class="form-group">
                            <label>End Date/Time (Optional)</label>
                            <input type="datetime-local" name="scheduled_end">
                            <p class="help-text">Leave empty to show indefinitely</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Colors</label>
                        <div class="color-inputs">
                            <div class="color-input-group">
                                <span>Text:</span>
                                <input type="color" name="color" value="#ffffff">
                            </div>
                            <div class="color-input-group">
                                <span>Background:</span>
                                <input type="color" name="bg_color" value="#7c3aed">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="add_event" class="btn">Add Event</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Your Events (<?php echo count($event_tickers); ?>)</h2>
                </div>

                <?php if (empty($event_tickers)): ?>
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <p>No event announcements yet.</p>
                    </div>
                <?php else: ?>
                    <div class="ticker-list">
                        <?php foreach ($event_tickers as $ticker): ?>
                        <div class="ticker-item <?php echo $ticker['is_active'] ? '' : 'inactive'; ?>" style="border-left-color: <?php echo $ticker['bg_color'] ?? '#7c3aed'; ?>;">
                            <div class="ticker-item-content">
                                <?php if (!empty($ticker['icon'])): ?>
                                    <span class="event-icon"><?php echo $ticker['icon']; ?></span>
                                <?php endif; ?>
                                <span class="ticker-type-badge badge-event"><?php echo ucfirst($ticker['event_type'] ?? 'general'); ?></span>
                                <p class="ticker-message"><?php echo clean($ticker['message']); ?></p>
                                <div class="ticker-meta">
                                    <?php if (!empty($ticker['scheduled_start'])): ?>
                                        <span>Starts: <?php echo date('M j, Y g:i A', strtotime($ticker['scheduled_start'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($ticker['scheduled_end'])): ?>
                                        <span>Ends: <?php echo date('M j, Y g:i A', strtotime($ticker['scheduled_end'])); ?></span>
                                    <?php endif; ?>
                                    <?php if (empty($ticker['scheduled_start']) && empty($ticker['scheduled_end'])): ?>
                                        <span>Always showing</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ticker-item-actions">
                                <a href="?tab=events&toggle=<?php echo $ticker['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                   class="btn btn-small <?php echo $ticker['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                    <?php echo $ticker['is_active'] ? 'Disable' : 'Enable'; ?>
                                </a>
                                <a href="?tab=events&delete=<?php echo $ticker['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                   onclick="return confirm('Delete this event?')"
                                   class="btn btn-small btn-danger">Delete</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>

            <!-- SCHEDULE TAB -->
            <?php if ($current_tab == 'schedule'): ?>

            <div class="schedule-info-box">
                <h4>Auto-Generated Schedule Ticker</h4>
                <p>This ticker automatically displays your current program, what's coming up next, and a countdown timer. It's generated from your video schedule - no manual setup needed!</p>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Your Program Schedule</h2>
                </div>

                <?php if (empty($schedules)): ?>
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <p>No schedule configured yet.</p>
                        <p style="font-size: 0.875rem;">Go to <a href="schedule.php" style="color: var(--primary);">Schedule</a> to add programs.</p>
                    </div>
                <?php else: ?>
                    <p style="color: #9ca3af; margin-bottom: 1rem;">When "Program Schedule" ticker is active, viewers will see:</p>

                    <div class="ticker-preview" style="margin-bottom: 1.5rem;">
                        <div class="preview-ticker-bar" style="background: #1e40af;">
                            <div class="preview-ticker-label" style="background: #1e3a8a;">
                                <span style="background: #3b82f6; color: #fff;">NOW</span>
                            </div>
                            <div class="preview-ticker-content" style="background: #1e40af;">
                                <div class="preview-ticker-track" style="animation: none;">
                                    <span class="preview-ticker-text">
                                        Now: <?php echo clean($schedules[0]['video_title'] ?? 'Program'); ?> | Up Next: <?php echo clean($schedules[1]['video_title'] ?? 'Next Program'); ?> | Starting in 12:34
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 1rem;">Today's Schedule</h3>
                    <div class="schedule-preview">
                        <?php
                        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        $today = date('w');
                        $today_schedules = array_filter($schedules, fn($s) => $s['day_of_week'] == $today);

                        if (empty($today_schedules)):
                        ?>
                            <p style="color: #6b7280; padding: 1rem;">No programs scheduled for today (<?php echo $days[$today]; ?>).</p>
                        <?php else: ?>
                            <?php foreach (array_values($today_schedules) as $i => $schedule): ?>
                            <div class="schedule-item">
                                <span class="schedule-time"><?php echo date('g:i A', strtotime($schedule['play_time'])); ?></span>
                                <span class="schedule-title"><?php echo clean($schedule['video_title']); ?></span>
                                <?php if ($i === 0): ?>
                                    <span class="schedule-label" style="color: var(--primary);">Now</span>
                                <?php elseif ($i === 1): ?>
                                    <span class="schedule-label">Next</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <a href="schedule.php" class="btn btn-secondary">Manage Full Schedule</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>

        </div>
    </div>
</body>
</html>
