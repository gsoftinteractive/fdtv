<?php
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
$stmt = $pdo->prepare("SELECT * FROM stations WHERE user_id = ? LIMIT 1");
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
                $stmt = $pdo->prepare("
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
                $stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
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

include '../includes/header.php';
?>

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
                <label>Ticker Color</label>
                <input type="hidden" id="ticker_color" name="ticker_color" value="<?php echo $station['ticker_color'] ?? 'red'; ?>">
                <div class="color-presets">
                    <?php
                    $colors = [
                        ['name' => 'Red', 'value' => 'red', 'bg' => '#dc2626'],
                        ['name' => 'Purple', 'value' => 'purple', 'bg' => '#7c3aed'],
                        ['name' => 'Green', 'value' => 'green', 'bg' => '#059669'],
                        ['name' => 'Blue', 'value' => 'blue', 'bg' => '#2563eb'],
                        ['name' => 'Orange', 'value' => 'orange', 'bg' => '#ea580c'],
                        ['name' => 'Pink', 'value' => 'pink', 'bg' => '#db2777'],
                        ['name' => 'Teal', 'value' => 'teal', 'bg' => '#0d9488'],
                        ['name' => 'Indigo', 'value' => 'indigo', 'bg' => '#4f46e5'],
                    ];
                    foreach ($colors as $color):
                    ?>
                        <div class="color-preset <?php echo ($station['ticker_color'] ?? 'red') === $color['value'] ? 'active' : ''; ?>"
                             data-color="<?php echo $color['value']; ?>">
                            <div class="color-box" style="background: <?php echo $color['bg']; ?>;"></div>
                            <div><?php echo $color['name']; ?></div>
                        </div>
                    <?php endforeach; ?>
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
// Color preset selection
document.querySelectorAll('.color-preset').forEach(preset => {
    preset.addEventListener('click', function() {
        document.querySelectorAll('.color-preset').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('ticker_color').value = this.dataset.color;
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

<?php include '../includes/footer.php'; ?>
