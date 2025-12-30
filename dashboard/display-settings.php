<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's station
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

if (!$station) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_display_settings') {
            try {
                // Ticker settings
                $ticker_color = $_POST['ticker_color'] ?? 'red';
                $ticker_label = strtoupper(substr(trim($_POST['ticker_label'] ?? 'BREAKING'), 0, 15));
                $ticker_mode = $_POST['ticker_mode'] ?? 'single';
                $ticker_speed = max(20, min(120, (int)($_POST['ticker_speed'] ?? 60)));

                // Clock position
                $clock_position_x = (int)($_POST['clock_position_x'] ?? 0);
                $clock_position_y = (int)($_POST['clock_position_y'] ?? 0);

                // Social badges (JSON)
                $social_badges = [];
                if (isset($_POST['social_icons']) && isset($_POST['social_handles'])) {
                    for ($i = 0; $i < count($_POST['social_icons']); $i++) {
                        $icon = trim($_POST['social_icons'][$i] ?? '');
                        $handle = trim($_POST['social_handles'][$i] ?? '');
                        if ($icon && $handle) {
                            $social_badges[] = [
                                'icon' => $icon,
                                'handle' => $handle,
                                'platform' => 'Custom'
                            ];
                        }
                    }
                }

                // Lower thirds presets (JSON)
                $lower_thirds_presets = [];
                if (isset($_POST['lt_names']) && isset($_POST['lt_titles']) && isset($_POST['lt_styles'])) {
                    for ($i = 0; $i < count($_POST['lt_names']); $i++) {
                        $name = trim($_POST['lt_names'][$i] ?? '');
                        $title = trim($_POST['lt_titles'][$i] ?? '');
                        $style = $_POST['lt_styles'][$i] ?? 'modern';
                        if ($name && $title) {
                            $lower_thirds_presets[] = [
                                'name' => $name,
                                'title' => $title,
                                'style' => $style
                            ];
                        }
                    }
                }

                // Update database
                $stmt = $conn->prepare("
                    UPDATE stations SET
                        ticker_color = ?,
                        ticker_label = ?,
                        ticker_mode = ?,
                        ticker_speed = ?,
                        clock_position_x = ?,
                        clock_position_y = ?,
                        social_badges = ?,
                        lower_thirds_presets = ?,
                        display_settings_updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $ticker_color,
                    $ticker_label,
                    $ticker_mode,
                    $ticker_speed,
                    $clock_position_x,
                    $clock_position_y,
                    json_encode($social_badges),
                    json_encode($lower_thirds_presets),
                    $station['id']
                ]);

                $success = 'Display settings updated successfully! All viewers will see these changes.';

                // Refresh station data
                $stmt = $conn->prepare("SELECT * FROM stations WHERE id = ?");
                $stmt->execute([$station['id']]);
                $station = $stmt->fetch();

            } catch (Exception $e) {
                $error = 'Error updating settings: ' . $e->getMessage();
            }
        }
    }
}

// Decode JSON fields for display
$social_badges = json_decode($station['social_badges'] ?? '[]', true) ?: [];
$lower_thirds_presets = json_decode($station['lower_thirds_presets'] ?? '[]', true) ?: [];

// Ensure at least one row for forms
if (empty($social_badges)) {
    $social_badges = [
        ['icon' => '𝕏', 'handle' => '@YourStation', 'platform' => 'Twitter'],
        ['icon' => '📘', 'handle' => '/YourStation', 'platform' => 'Facebook'],
        ['icon' => '📷', 'handle' => '@YourStation', 'platform' => 'Instagram'],
        ['icon' => '▶️', 'handle' => 'YourStation', 'platform' => 'YouTube']
    ];
}

if (empty($lower_thirds_presets)) {
    $lower_thirds_presets = [
        ['name' => 'John Smith', 'title' => 'News Anchor', 'style' => 'modern'],
        ['name' => 'Jane Doe', 'title' => 'Weather Reporter', 'style' => 'bold'],
        ['name' => 'Alex Johnson', 'title' => 'Sports Analyst', 'style' => 'news']
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display Settings - FDTV</title>
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
                <a href="display-settings.php" class="active">Display Settings</a>
                <a href="payment.php">Payment</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">

<style>
.display-settings-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.settings-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.settings-card h2 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #7c3aed;
    padding-bottom: 12px;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #555;
}

.form-control, .form-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.color-picker {
    width: 80px;
    height: 40px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.color-picker:hover {
    border-color: #7c3aed;
    transform: scale(1.05);
}

.color-preset-mini:hover {
    transform: scale(1.1);
    border-color: #7c3aed !important;
}

.color-presets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 8px;
}

.color-preset {
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.color-preset:hover {
    transform: scale(1.05);
}

.color-preset.active {
    border-color: #7c3aed;
    background: #f3f0ff;
}

.color-box {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    margin: 0 auto 8px;
}

.repeater-row {
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 12px;
    margin-bottom: 12px;
    align-items: start;
}

.repeater-row-lt {
    display: grid;
    grid-template-columns: 1fr 1fr 150px auto;
    gap: 12px;
    margin-bottom: 12px;
    align-items: start;
}

.btn-remove {
    background: #dc2626;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-add {
    background: #059669;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 12px;
}

.btn-primary {
    background: #7c3aed;
    color: white;
    border: none;
    padding: 12px 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
}

.btn-primary:hover {
    background: #6d28d9;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    border: 1px solid #059669;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    border: 1px solid #dc2626;
    color: #991b1b;
}

.info-box {
    background: #f0f9ff;
    border-left: 4px solid #0284c7;
    padding: 16px;
    margin-top: 12px;
    border-radius: 4px;
}

.clock-position-preview {
    position: relative;
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    margin-top: 12px;
    overflow: hidden;
}

.clock-preview {
    position: absolute;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.9) 0%, rgba(0, 0, 0, 0.9) 100%);
    padding: 8px 16px;
    border-radius: 6px;
    color: white;
    font-weight: 600;
    border: 2px solid rgba(124, 58, 237, 0.5);
    cursor: move;
    transition: transform 0.1s;
}
</style>

<div class="display-settings-page">
    <h1>📺 Display Settings</h1>
    <p>Configure how your station appears to all viewers. These settings apply globally to everyone watching your channel.</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="action" value="update_display_settings">

        <!-- Ticker Settings -->
        <div class="settings-card">
            <h2>🎨 Ticker Settings</h2>

            <div class="form-grid">
                <div class="form-group">
                    <label for="ticker_label">Ticker Label</label>
                    <input type="text" id="ticker_label" name="ticker_label" class="form-control"
                           value="<?php echo htmlspecialchars($station['ticker_label'] ?? 'BREAKING'); ?>"
                           maxlength="15" placeholder="BREAKING">
                    <small>Max 15 characters (e.g., BREAKING, LIVE, NEWS, ALERT)</small>
                </div>

                <div class="form-group">
                    <label for="ticker_mode">Ticker Mode</label>
                    <select id="ticker_mode" name="ticker_mode" class="form-select">
                        <option value="single" <?php echo ($station['ticker_mode'] ?? 'single') === 'single' ? 'selected' : ''; ?>>Single Line</option>
                        <option value="double" <?php echo ($station['ticker_mode'] ?? 'single') === 'double' ? 'selected' : ''; ?>>Double Line</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ticker_speed">Ticker Speed (seconds)</label>
                    <input type="number" id="ticker_speed" name="ticker_speed" class="form-control"
                           value="<?php echo $station['ticker_speed'] ?? 60; ?>"
                           min="20" max="120" step="10">
                    <small>20 = Very Fast, 60 = Normal, 120 = Very Slow</small>
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label for="ticker_color_picker">Ticker Color</label>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <input type="color" id="ticker_color_picker" name="ticker_color" class="color-picker"
                           value="<?php
                           // Convert named colors to hex
                           $color_value = $station['ticker_color'] ?? '#dc2626';
                           $color_map = [
                               'red' => '#dc2626',
                               'purple' => '#7c3aed',
                               'green' => '#059669',
                               'blue' => '#2563eb',
                               'orange' => '#ea580c',
                               'pink' => '#db2777',
                               'teal' => '#0d9488',
                               'indigo' => '#4f46e5'
                           ];
                           echo $color_map[$color_value] ?? $color_value;
                           ?>">
                    <div class="color-preview" id="colorPreview" style="width: 80px; height: 40px; border-radius: 6px; border: 2px solid #ddd; background: <?php echo $color_map[$station['ticker_color'] ?? 'red'] ?? ($station['ticker_color'] ?? '#dc2626'); ?>;"></div>
                    <span id="colorValue" style="font-family: monospace; color: #666;"><?php echo $color_map[$station['ticker_color'] ?? 'red'] ?? ($station['ticker_color'] ?? '#dc2626'); ?></span>
                </div>
                <small style="display: block; margin-top: 8px;">Pick any color for your ticker background</small>

                <div style="margin-top: 12px;">
                    <strong style="font-size: 13px; color: #666;">Quick Presets:</strong>
                    <div class="color-presets-mini" style="display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
                        <?php
                        $colors = [
                            ['name' => 'Red', 'bg' => '#dc2626'],
                            ['name' => 'Purple', 'bg' => '#7c3aed'],
                            ['name' => 'Green', 'bg' => '#059669'],
                            ['name' => 'Blue', 'bg' => '#2563eb'],
                            ['name' => 'Orange', 'bg' => '#ea580c'],
                            ['name' => 'Pink', 'bg' => '#db2777'],
                            ['name' => 'Teal', 'bg' => '#0d9488'],
                            ['name' => 'Indigo', 'bg' => '#4f46e5'],
                            ['name' => 'Yellow', 'bg' => '#eab308'],
                            ['name' => 'Cyan', 'bg' => '#06b6d4'],
                            ['name' => 'Rose', 'bg' => '#f43f5e'],
                            ['name' => 'Black', 'bg' => '#000000'],
                        ];
                        foreach ($colors as $color):
                        ?>
                            <button type="button" class="color-preset-mini" data-color="<?php echo $color['bg']; ?>" title="<?php echo $color['name']; ?>"
                                    style="width: 36px; height: 36px; border-radius: 4px; border: 2px solid #ddd; background: <?php echo $color['bg']; ?>; cursor: pointer;"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clock Position -->
        <div class="settings-card">
            <h2>🕐 Clock Position</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="clock_position_x">Horizontal Position (X)</label>
                    <input type="number" id="clock_position_x" name="clock_position_x" class="form-control"
                           value="<?php echo $station['clock_position_x'] ?? 0; ?>"
                           step="10">
                    <small>Negative = Left, Positive = Right, 0 = Center</small>
                </div>

                <div class="form-group">
                    <label for="clock_position_y">Vertical Position (Y)</label>
                    <input type="number" id="clock_position_y" name="clock_position_y" class="form-control"
                           value="<?php echo $station['clock_position_y'] ?? 0; ?>"
                           step="10">
                    <small>Negative = Up, Positive = Down, 0 = Default</small>
                </div>
            </div>

            <div class="clock-position-preview">
                <div class="clock-preview" id="clockPreview" style="left: 50%; top: 20px;">12:34</div>
            </div>
            <div class="info-box">
                <strong>💡 Tip:</strong> Adjust the X and Y values above to see the clock move in the preview. All viewers will see the clock in this position.
            </div>
        </div>

        <!-- Social Media Badges -->
        <div class="settings-card">
            <h2>📱 Social Media Badges</h2>
            <div id="social-badges-container">
                <?php foreach ($social_badges as $index => $badge): ?>
                    <div class="repeater-row">
                        <input type="text" name="social_icons[]" class="form-control"
                               value="<?php echo htmlspecialchars($badge['icon']); ?>"
                               placeholder="📘" maxlength="2">
                        <input type="text" name="social_handles[]" class="form-control"
                               value="<?php echo htmlspecialchars($badge['handle']); ?>"
                               placeholder="@YourStation">
                        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addSocialBadge()">+ Add Social Badge</button>
            <div class="info-box" style="margin-top: 16px;">
                <strong>💡 Tip:</strong> These badges will cycle every 5 seconds on your live stream. Use emoji icons like 𝕏, 📘, 📷, ▶️, 🎵
            </div>
        </div>

        <!-- Lower Thirds Presets -->
        <div class="settings-card">
            <h2>📺 Lower Thirds Presets</h2>
            <div id="lower-thirds-container">
                <?php foreach ($lower_thirds_presets as $index => $preset): ?>
                    <div class="repeater-row-lt">
                        <input type="text" name="lt_names[]" class="form-control"
                               value="<?php echo htmlspecialchars($preset['name']); ?>"
                               placeholder="John Smith" maxlength="50">
                        <input type="text" name="lt_titles[]" class="form-control"
                               value="<?php echo htmlspecialchars($preset['title']); ?>"
                               placeholder="News Anchor" maxlength="100">
                        <select name="lt_styles[]" class="form-select">
                            <option value="modern" <?php echo $preset['style'] === 'modern' ? 'selected' : ''; ?>>Modern</option>
                            <option value="bold" <?php echo $preset['style'] === 'bold' ? 'selected' : ''; ?>>Bold</option>
                            <option value="news" <?php echo $preset['style'] === 'news' ? 'selected' : ''; ?>>News</option>
                        </select>
                        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addLowerThird()">+ Add Lower Third</button>
            <div class="info-box" style="margin-top: 16px;">
                <strong>💡 Tip:</strong> Presenters can quickly load these presets using Ctrl+1, Ctrl+2, etc. during live broadcasts.
            </div>
        </div>

        <div class="settings-card">
            <button type="submit" class="btn-primary">💾 Save All Display Settings</button>
            <p style="margin-top: 12px; color: #666;">
                <strong>⚠️ Important:</strong> These settings will apply to ALL viewers immediately after saving.
            </p>
        </div>
    </form>
</div>

<script>
// Color picker handling
const colorPicker = document.getElementById('ticker_color_picker');
const colorPreview = document.getElementById('colorPreview');
const colorValue = document.getElementById('colorValue');

colorPicker.addEventListener('input', function() {
    const color = this.value;
    colorPreview.style.background = color;
    colorValue.textContent = color;
});

// Quick color presets
document.querySelectorAll('.color-preset-mini').forEach(btn => {
    btn.addEventListener('click', function() {
        const color = this.dataset.color;
        colorPicker.value = color;
        colorPreview.style.background = color;
        colorValue.textContent = color;
    });
});

// Clock position preview
const clockPreview = document.getElementById('clockPreview');
const xInput = document.getElementById('clock_position_x');
const yInput = document.getElementById('clock_position_y');

function updateClockPreview() {
    const x = parseInt(xInput.value) || 0;
    const y = parseInt(yInput.value) || 0;
    clockPreview.style.transform = `translate(${x}px, ${y}px) translateX(-50%)`;
}

xInput.addEventListener('input', updateClockPreview);
yInput.addEventListener('input', updateClockPreview);
updateClockPreview();

// Add social badge row
function addSocialBadge() {
    const container = document.getElementById('social-badges-container');
    const row = document.createElement('div');
    row.className = 'repeater-row';
    row.innerHTML = `
        <input type="text" name="social_icons[]" class="form-control" placeholder="📘" maxlength="2">
        <input type="text" name="social_handles[]" class="form-control" placeholder="@YourStation">
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>
    `;
    container.appendChild(row);
}

// Add lower third row
function addLowerThird() {
    const container = document.getElementById('lower-thirds-container');
    const row = document.createElement('div');
    row.className = 'repeater-row-lt';
    row.innerHTML = `
        <input type="text" name="lt_names[]" class="form-control" placeholder="John Smith" maxlength="50">
        <input type="text" name="lt_titles[]" class="form-control" placeholder="News Anchor" maxlength="100">
        <select name="lt_styles[]" class="form-select">
            <option value="modern">Modern</option>
            <option value="bold">Bold</option>
            <option value="news">News</option>
        </select>
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>
    `;
    container.appendChild(row);
}

// Update clock time
setInterval(() => {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    clockPreview.textContent = `${hours}:${minutes}`;
}, 1000);
</script>

        </div>
    </div>
</body>
</html>
