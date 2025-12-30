<?php
// dashboard/create-station.php - Create Station (Costs 100 Coins)

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user already has a station
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$existing_station = $stmt->fetch();

if ($existing_station) {
    set_flash('You already have a station created.', 'info');
    redirect('index.php');
}

// Get station creation cost
$stmt = $conn->prepare("SELECT coins_required FROM coin_pricing WHERE action_type = 'station_creation' AND is_active = 1");
$stmt->execute();
$pricing = $stmt->fetch();
$creation_cost = $pricing['coins_required'] ?? 100;

$errors = [];
$success = '';

// Handle station creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_station'])) {

    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Invalid request. Please try again.";
    } else {

        $station_type = $_POST['station_type'] ?? 'tv';

        // Validate station type
        if (!in_array($station_type, ['tv', 'radio', 'both'])) {
            $errors[] = "Invalid station type.";
        }

        // Check if user has enough coins
        if ($user['coins'] < $creation_cost) {
            $errors[] = "Insufficient coins. You need {$creation_cost} coins to create a station. You have {$user['coins']} coins.";
        }

        if (empty($errors)) {

            $conn->beginTransaction();

            try {

                // Create station
                $stmt = $conn->prepare("INSERT INTO stations
                    (user_id, station_name, slug, station_type, status)
                    VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([
                    $user_id,
                    $user['station_name'],
                    $user['station_slug'],
                    $station_type
                ]);

                $station_id = $conn->lastInsertId();

                // Deduct coins from user
                $balance_before = $user['coins'];
                $balance_after = $balance_before - $creation_cost;

                $stmt = $conn->prepare("UPDATE users SET coins = ?, coins_updated_at = NOW() WHERE id = ?");
                $stmt->execute([$balance_after, $user_id]);

                // Record transaction
                $stmt = $conn->prepare("INSERT INTO coin_transactions
                    (user_id, amount, transaction_type, description, balance_before, balance_after, reference)
                    VALUES (?, ?, 'system', ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id,
                    -$creation_cost,
                    "Station creation: {$user['station_name']}",
                    $balance_before,
                    $balance_after,
                    "STATION_CREATE_{$station_id}"
                ]);

                $conn->commit();

                // Send email notification
                $message = "
                    <h2>Station Created Successfully!</h2>
                    <p>Congratulations, {$user['company_name']}!</p>
                    <p>Your station <strong>{$user['station_name']}</strong> has been created.</p>
                    <p><strong>Station Type:</strong> " . strtoupper($station_type) . "</p>
                    <p><strong>Coins Deducted:</strong> {$creation_cost}</p>
                    <p><strong>Remaining Balance:</strong> {$balance_after} coins</p>
                    <p><strong>Your Station URL:</strong> " . SITE_URL . "/station/view.php?name={$user['station_slug']}</p>
                    <p>Next steps:</p>
                    <ul>
                        <li>Upload videos (10 coins per video)</li>
                        <li>Configure your station settings</li>
                        <li>Start broadcasting!</li>
                    </ul>
                ";
                send_email($user['email'], "Station Created - FDTV", $message);

                set_flash("Station created successfully! {$creation_cost} coins deducted.", "success");
                redirect('index.php');

            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = "Error creating station: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Station - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <a href="../index.php" class="logo">FDTV</a>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="payment.php">Buy Coins</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container" style="max-width: 600px;">
            <h1>Create Your Station</h1>

            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 1.5rem;">
                <div style="text-align: center; padding: 1rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">💰</div>
                    <div style="font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem;">
                        <?php echo number_format($user['coins']); ?> Coins
                    </div>
                    <div style="opacity: 0.9;">Your Current Balance</div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                    <?php if ($user['coins'] < $creation_cost): ?>
                        <p><a href="payment.php" style="color: inherit; text-decoration: underline; font-weight: bold;">Buy More Coins</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Station Details</h2>
                </div>

                <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Station Name:</span>
                        <strong><?php echo clean($user['station_name']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Station URL:</span>
                        <strong><?php echo clean($user['station_slug']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Creation Cost:</span>
                        <strong style="color: #dc2626;"><?php echo $creation_cost; ?> coins</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>After Creation:</span>
                        <strong style="color: <?php echo ($user['coins'] - $creation_cost) >= 0 ? '#059669' : '#dc2626'; ?>;">
                            <?php echo number_format($user['coins'] - $creation_cost); ?> coins
                        </strong>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="form-group">
                        <label>Station Type *</label>
                        <select name="station_type" required>
                            <option value="tv">TV Station (Video Broadcasting)</option>
                            <option value="radio">Radio Station (Audio Only)</option>
                            <option value="both">Both TV & Radio</option>
                        </select>
                        <small style="color: #6b7280;">Choose the type of broadcasting you want to do</small>
                    </div>

                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px;">
                        <strong style="color: #92400e;">⚠️ Important:</strong>
                        <p style="color: #92400e; margin-top: 0.5rem; margin-bottom: 0;">
                            Creating your station will deduct <?php echo $creation_cost; ?> coins from your account.
                            This is a one-time fee. Make sure you have enough coins before proceeding.
                        </p>
                    </div>

                    <?php if ($user['coins'] >= $creation_cost): ?>
                        <button type="submit" name="create_station" class="btn" style="width: 100%;">
                            Create Station (<?php echo $creation_cost; ?> coins)
                        </button>
                    <?php else: ?>
                        <a href="payment.php" class="btn" style="width: 100%; display: inline-block; text-align: center; background: #dc2626;">
                            Buy More Coins (Need <?php echo ($creation_cost - $user['coins']); ?> more)
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="index.php" style="color: #6b7280;">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
