<?php
// dashboard/schedule.php

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
    set_flash("Station not created yet.", "danger");
    redirect('index.php');
}

// Get all videos
$stmt = $conn->prepare("SELECT * FROM videos WHERE station_id = ? AND status = 'ready' ORDER BY title");
$stmt->execute([$station['id']]);
$videos = $stmt->fetchAll();

// Get schedules grouped by day
$stmt = $conn->prepare("SELECT s.*, v.title as video_title 
                        FROM schedules s 
                        JOIN videos v ON s.video_id = v.id 
                        WHERE s.station_id = ? 
                        ORDER BY s.day_of_week, s.play_time, s.play_order");
$stmt->execute([$station['id']]);
$all_schedules = $stmt->fetchAll();

$schedules_by_day = [];
foreach ($all_schedules as $schedule) {
    $schedules_by_day[$schedule['day_of_week']][] = $schedule;
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$errors = [];

// Handle schedule creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    
    if (verify_csrf_token($_POST['csrf_token'])) {
        
        $video_id = (int)$_POST['video_id'];
        $day_of_week = (int)$_POST['day_of_week'];
        $play_time = $_POST['play_time'];
        $play_order = (int)$_POST['play_order'];
        
        if (empty($video_id) || empty($play_time)) {
            $errors[] = "All fields are required.";
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO schedules (station_id, video_id, day_of_week, play_time, play_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$station['id'], $video_id, $day_of_week, $play_time, $play_order]);
            
            set_flash("Schedule added successfully!", "success");
            redirect('schedule.php');
        }
    }
}

// Handle schedule delete
if (isset($_GET['delete'])) {
    $schedule_id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ? AND station_id = ?");
    $stmt->execute([$schedule_id, $station['id']]);
    
    set_flash("Schedule deleted.", "success");
    redirect('schedule.php');
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - FDTV</title>
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
                <a href="display-settings.php">Display Settings</a>
                <a href="payment.php">Payment</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <div class="dashboard">
        <div class="container">
            <h1>Manage Schedule</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo clean($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($videos)): ?>
                <div class="alert alert-warning">
                    Please upload videos first before creating schedule.
                    <a href="videos.php" class="btn btn-small">Upload Videos</a>
                </div>
            <?php else: ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Add to Schedule</h2>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        
                        <div class="form-group">
                            <label>Select Video</label>
                            <select name="video_id" required>
                                <option value="">Choose video...</option>
                                <?php foreach ($videos as $video): ?>
                                    <option value="<?php echo $video['id']; ?>"><?php echo clean($video['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Day</label>
                            <select name="day_of_week" required>
                                <?php foreach ($days as $index => $day): ?>
                                    <option value="<?php echo $index; ?>"><?php echo $day; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="play_time" required>
                        </div>

                        <div class="form-group">
                            <label>Order</label>
                            <input type="number" name="play_order" value="1" min="1" required>
                        </div>
                    </div>

                    <button type="submit" name="add_schedule" class="btn">Add to Schedule</button>
                </form>
            </div>

            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Weekly Schedule</h2>
                </div>

                <?php if (empty($all_schedules)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">
                        No schedule created yet.
                    </p>
                <?php else: ?>

                <?php foreach ($days as $day_index => $day_name): ?>
                    <h3 style="margin: 1.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary);">
                        <?php echo $day_name; ?>
                    </h3>

                    <?php if (isset($schedules_by_day[$day_index])): ?>
                        <table style="margin-bottom: 2rem;">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Video</th>
                                    <th>Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules_by_day[$day_index] as $schedule): ?>
                                <tr>
                                    <td><?php echo date('g:i A', strtotime($schedule['play_time'])); ?></td>
                                    <td><?php echo clean($schedule['video_title']); ?></td>
                                    <td><?php echo $schedule['play_order']; ?></td>
                                    <td>
                                        <a href="?delete=<?php echo $schedule['id']; ?>" 
                                           onclick="return confirm('Delete this schedule?')" 
                                           class="btn btn-danger btn-small">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #6b7280; padding: 1rem 0;">No schedule for this day.</p>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>