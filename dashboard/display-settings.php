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
                // Clock position (now percentage-based for draggable positioning)
                $clock_position_x = max(5, min(95, (int)($_POST['clock_position_x'] ?? 50)));
                $clock_position_y = max(3, min(90, (int)($_POST['clock_position_y'] ?? 5)));

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

                // Update database (ticker settings are now managed in ticker.php)
                $stmt = $conn->prepare("
                    UPDATE stations SET
                        clock_position_x = ?,
                        clock_position_y = ?,
                        social_badges = ?,
                        lower_thirds_presets = ?,
                        display_settings_updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
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
    padding: 10px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    font-family: 'Courier New', monospace;
    font-size: 1.2rem;
    border: 2px solid rgba(124, 58, 237, 0.5);
    cursor: grab;
    transition: box-shadow 0.2s, border-color 0.2s;
    z-index: 5;
}

.clock-preview:hover {
    box-shadow: 0 4px 20px rgba(124, 58, 237, 0.5);
    border-color: #7c3aed;
}

.clock-preview:active {
    cursor: grabbing;
}

.quick-pos-btn {
    width: 32px;
    height: 32px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-pos-btn:hover {
    background: rgba(124, 58, 237, 0.8);
    border-color: #7c3aed;
    transform: scale(1.1);
}
</style>

<div class="display-settings-page">
    <h1>📺 Display Settings</h1>
    <p>Configure clock position, social badges, and lower thirds for your station. <a href="ticker.php" style="color: #7c3aed; text-decoration: none;">Manage ticker settings here &rarr;</a></p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="action" value="update_display_settings">

        <!-- Clock Position -->
        <div class="settings-card">
            <h2>🕐 Clock Position</h2>
            <p style="color: #666; margin-bottom: 16px;">Drag the clock to any position on the preview screen below. The position will be saved as a percentage so it works on all screen sizes.</p>

            <input type="hidden" id="clock_position_x" name="clock_position_x" value="<?php echo $station['clock_position_x'] ?? 50; ?>">
            <input type="hidden" id="clock_position_y" name="clock_position_y" value="<?php echo $station['clock_position_y'] ?? 5; ?>">

            <div class="clock-position-preview" id="clockPositionPreview" style="height: 300px; cursor: crosshair; position: relative;">
                <!-- Grid overlay for guidance -->
                <div style="position: absolute; inset: 0; pointer-events: none; opacity: 0.2;">
                    <div style="position: absolute; left: 33.33%; top: 0; bottom: 0; border-left: 1px dashed #fff;"></div>
                    <div style="position: absolute; left: 66.66%; top: 0; bottom: 0; border-left: 1px dashed #fff;"></div>
                    <div style="position: absolute; top: 33.33%; left: 0; right: 0; border-top: 1px dashed #fff;"></div>
                    <div style="position: absolute; top: 66.66%; left: 0; right: 0; border-top: 1px dashed #fff;"></div>
                </div>

                <!-- Draggable clock -->
                <div class="clock-preview" id="clockPreview" style="position: absolute; cursor: grab; user-select: none;">
                    <span id="clockTime">12:34</span>
                </div>

                <!-- Quick position buttons -->
                <div class="quick-positions" style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10;">
                    <button type="button" class="quick-pos-btn" data-x="10" data-y="5" title="Top Left">↖</button>
                    <button type="button" class="quick-pos-btn" data-x="50" data-y="5" title="Top Center">↑</button>
                    <button type="button" class="quick-pos-btn" data-x="90" data-y="5" title="Top Right">↗</button>
                    <button type="button" class="quick-pos-btn" data-x="10" data-y="50" title="Middle Left">←</button>
                    <button type="button" class="quick-pos-btn" data-x="50" data-y="50" title="Center">●</button>
                    <button type="button" class="quick-pos-btn" data-x="90" data-y="50" title="Middle Right">→</button>
                    <button type="button" class="quick-pos-btn" data-x="10" data-y="85" title="Bottom Left">↙</button>
                    <button type="button" class="quick-pos-btn" data-x="50" data-y="85" title="Bottom Center">↓</button>
                    <button type="button" class="quick-pos-btn" data-x="90" data-y="85" title="Bottom Right">↘</button>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 12px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                <span style="color: #666;">Position: <strong id="positionDisplay">X: 50%, Y: 5%</strong></span>
                <button type="button" id="resetClockPos" class="btn-small" style="background: #6b7280; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">Reset to Default</button>
            </div>

            <div class="info-box">
                <strong>💡 Tip:</strong> Drag the clock anywhere on the preview, or click the position buttons for quick placement. The clock position uses percentages so it looks correct on all screen sizes.
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
// Clock position - Draggable functionality
const clockPreview = document.getElementById('clockPreview');
const clockContainer = document.getElementById('clockPositionPreview');
const xInput = document.getElementById('clock_position_x');
const yInput = document.getElementById('clock_position_y');
const positionDisplay = document.getElementById('positionDisplay');

let isDragging = false;
let dragOffsetX = 0;
let dragOffsetY = 0;

// Initialize clock position
function initClockPosition() {
    const xPercent = parseFloat(xInput.value) || 50;
    const yPercent = parseFloat(yInput.value) || 5;
    setClockPosition(xPercent, yPercent);
}

function setClockPosition(xPercent, yPercent) {
    // Clamp values
    xPercent = Math.max(5, Math.min(95, xPercent));
    yPercent = Math.max(3, Math.min(90, yPercent));

    clockPreview.style.left = xPercent + '%';
    clockPreview.style.top = yPercent + '%';
    clockPreview.style.transform = 'translate(-50%, -50%)';

    xInput.value = Math.round(xPercent);
    yInput.value = Math.round(yPercent);
    positionDisplay.textContent = `X: ${Math.round(xPercent)}%, Y: ${Math.round(yPercent)}%`;
}

// Mouse drag events
clockPreview.addEventListener('mousedown', (e) => {
    isDragging = true;
    clockPreview.style.cursor = 'grabbing';
    e.preventDefault();
});

document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;

    const rect = clockContainer.getBoundingClientRect();
    const xPercent = ((e.clientX - rect.left) / rect.width) * 100;
    const yPercent = ((e.clientY - rect.top) / rect.height) * 100;

    setClockPosition(xPercent, yPercent);
});

document.addEventListener('mouseup', () => {
    if (isDragging) {
        isDragging = false;
        clockPreview.style.cursor = 'grab';
    }
});

// Touch drag events for mobile
clockPreview.addEventListener('touchstart', (e) => {
    isDragging = true;
    e.preventDefault();
});

document.addEventListener('touchmove', (e) => {
    if (!isDragging) return;

    const touch = e.touches[0];
    const rect = clockContainer.getBoundingClientRect();
    const xPercent = ((touch.clientX - rect.left) / rect.width) * 100;
    const yPercent = ((touch.clientY - rect.top) / rect.height) * 100;

    setClockPosition(xPercent, yPercent);
});

document.addEventListener('touchend', () => {
    isDragging = false;
});

// Click on container to move clock
clockContainer.addEventListener('click', (e) => {
    if (e.target.closest('.quick-pos-btn') || e.target === clockPreview) return;

    const rect = clockContainer.getBoundingClientRect();
    const xPercent = ((e.clientX - rect.left) / rect.width) * 100;
    const yPercent = ((e.clientY - rect.top) / rect.height) * 100;

    setClockPosition(xPercent, yPercent);
});

// Quick position buttons
document.querySelectorAll('.quick-pos-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const x = parseFloat(this.dataset.x);
        const y = parseFloat(this.dataset.y);
        setClockPosition(x, y);
    });
});

// Reset button
document.getElementById('resetClockPos').addEventListener('click', function(e) {
    e.preventDefault();
    setClockPosition(50, 5); // Default: top center
});

// Initialize
initClockPosition();

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
