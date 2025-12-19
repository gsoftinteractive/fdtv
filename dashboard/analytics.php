<?php
// dashboard/analytics.php - Analytics Dashboard

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

if ($user['status'] !== 'active') {
    set_flash("Your account is not active.", "warning");
    redirect('payment.php');
}

// Get station
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

if (!$station) {
    set_flash("Station not found.", "danger");
    redirect('index.php');
}

$station_id = $station['id'];

// Date range filter
$range = $_GET['range'] ?? '7days';
$start_date = date('Y-m-d', strtotime('-7 days'));
$end_date = date('Y-m-d');

switch ($range) {
    case 'today':
        $start_date = date('Y-m-d');
        break;
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date = $start_date;
        break;
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
}

// Get total stats for period
$stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_views,
        COUNT(DISTINCT ip_address) as unique_viewers,
        SUM(duration_seconds) as total_watch_time,
        AVG(duration_seconds) as avg_watch_time
    FROM station_views
    WHERE station_id = ?
    AND DATE(started_at) BETWEEN ? AND ?
");
$stmt->execute([$station_id, $start_date, $end_date]);
$period_stats = $stmt->fetch();

// Get current active viewers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM active_viewers WHERE station_id = ? AND last_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
$stmt->execute([$station_id]);
$active_viewers = $stmt->fetch()['count'] ?? 0;

// Get daily views for chart
$stmt = $conn->prepare("
    SELECT DATE(started_at) as date, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_views
    FROM station_views
    WHERE station_id = ? AND DATE(started_at) BETWEEN ? AND ?
    GROUP BY DATE(started_at)
    ORDER BY date ASC
");
$stmt->execute([$station_id, $start_date, $end_date]);
$daily_views = $stmt->fetchAll();

// Get hourly distribution (for today or yesterday)
$hourly_date = $range === 'yesterday' ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$stmt = $conn->prepare("
    SELECT HOUR(started_at) as hour, COUNT(*) as views
    FROM station_views
    WHERE station_id = ? AND DATE(started_at) = ?
    GROUP BY HOUR(started_at)
    ORDER BY hour ASC
");
$stmt->execute([$station_id, $hourly_date]);
$hourly_views = $stmt->fetchAll();

// Get device breakdown
$stmt = $conn->prepare("
    SELECT device_type, COUNT(*) as count
    FROM station_views
    WHERE station_id = ? AND DATE(started_at) BETWEEN ? AND ?
    GROUP BY device_type
    ORDER BY count DESC
");
$stmt->execute([$station_id, $start_date, $end_date]);
$device_stats = $stmt->fetchAll();

// Get browser breakdown
$stmt = $conn->prepare("
    SELECT browser, COUNT(*) as count
    FROM station_views
    WHERE station_id = ? AND DATE(started_at) BETWEEN ? AND ?
    GROUP BY browser
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$station_id, $start_date, $end_date]);
$browser_stats = $stmt->fetchAll();

// Get top videos
$stmt = $conn->prepare("
    SELECT v.id, v.title, COUNT(vv.id) as views, SUM(vv.watch_duration) as watch_time
    FROM videos v
    LEFT JOIN video_views vv ON v.id = vv.video_id AND DATE(vv.viewed_at) BETWEEN ? AND ?
    WHERE v.station_id = ?
    GROUP BY v.id
    ORDER BY views DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date, $station_id]);
$top_videos = $stmt->fetchAll();

// Get recent views
$stmt = $conn->prepare("
    SELECT started_at, device_type, browser, duration_seconds, ip_address
    FROM station_views
    WHERE station_id = ?
    ORDER BY started_at DESC
    LIMIT 20
");
$stmt->execute([$station_id]);
$recent_views = $stmt->fetchAll();

// Helper functions
function formatWatchTime($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

function getDeviceIcon($type) {
    $icons = [
        'desktop' => '🖥️',
        'mobile' => '📱',
        'tablet' => '📱',
        'tv' => '📺',
        'unknown' => '❓'
    ];
    return $icons[$type] ?? '❓';
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .analytics-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .analytics-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .analytics-card .label {
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            margin-top: 0.25rem;
        }
        .analytics-card.live .number {
            color: #10b981;
        }
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .chart-container h3 {
            margin-bottom: 1rem;
            color: #374151;
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        .range-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .range-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: none;
            color: var(--text-dark);
        }
        .range-btn:hover, .range-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .stat-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .stat-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .stat-list li:last-child {
            border-bottom: none;
        }
        .stat-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.25rem;
        }
        .stat-bar-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
        }
        .recent-table {
            width: 100%;
            font-size: 0.875rem;
        }
        .recent-table td {
            padding: 0.75rem 0.5rem;
        }
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #d1fae5;
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h1>Analytics</h1>
                <span class="live-badge">
                    <span class="live-dot"></span>
                    <span id="liveViewers"><?php echo $active_viewers; ?></span> watching now
                </span>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Date Range Filters -->
            <div class="range-filters">
                <a href="?range=today" class="range-btn <?php echo $range === 'today' ? 'active' : ''; ?>">Today</a>
                <a href="?range=yesterday" class="range-btn <?php echo $range === 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
                <a href="?range=7days" class="range-btn <?php echo $range === '7days' ? 'active' : ''; ?>">Last 7 Days</a>
                <a href="?range=30days" class="range-btn <?php echo $range === '30days' ? 'active' : ''; ?>">Last 30 Days</a>
                <a href="?range=90days" class="range-btn <?php echo $range === '90days' ? 'active' : ''; ?>">Last 90 Days</a>
            </div>

            <!-- Key Metrics -->
            <div class="analytics-grid">
                <div class="analytics-card live">
                    <div class="number" id="activeViewersCard"><?php echo $active_viewers; ?></div>
                    <div class="label">Live Viewers</div>
                </div>
                <div class="analytics-card">
                    <div class="number"><?php echo number_format($period_stats['total_views'] ?? 0); ?></div>
                    <div class="label">Total Views</div>
                </div>
                <div class="analytics-card">
                    <div class="number"><?php echo number_format($period_stats['unique_viewers'] ?? 0); ?></div>
                    <div class="label">Unique Viewers</div>
                </div>
                <div class="analytics-card">
                    <div class="number"><?php echo formatWatchTime($period_stats['total_watch_time'] ?? 0); ?></div>
                    <div class="label">Watch Time</div>
                </div>
                <div class="analytics-card">
                    <div class="number"><?php echo formatWatchTime(round($period_stats['avg_watch_time'] ?? 0)); ?></div>
                    <div class="label">Avg Session</div>
                </div>
            </div>

            <!-- Views Chart -->
            <div class="chart-container">
                <h3>Views Over Time</h3>
                <div class="chart-wrapper">
                    <canvas id="viewsChart"></canvas>
                </div>
            </div>

            <!-- Hourly Distribution -->
            <div class="chart-container">
                <h3>Hourly Distribution (<?php echo $range === 'yesterday' ? 'Yesterday' : 'Today'; ?>)</h3>
                <div class="chart-wrapper">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- Stats Grids -->
            <div class="stats-grid">
                <!-- Device Breakdown -->
                <div class="chart-container">
                    <h3>Devices</h3>
                    <?php if (empty($device_stats)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">No data yet</p>
                    <?php else: ?>
                        <?php
                        $total_devices = array_sum(array_column($device_stats, 'count'));
                        ?>
                        <ul class="stat-list">
                            <?php foreach ($device_stats as $device): ?>
                                <?php $percent = $total_devices > 0 ? ($device['count'] / $total_devices) * 100 : 0; ?>
                                <li>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span><?php echo getDeviceIcon($device['device_type']); ?> <?php echo ucfirst($device['device_type']); ?></span>
                                            <span><?php echo number_format($device['count']); ?> (<?php echo round($percent); ?>%)</span>
                                        </div>
                                        <div class="stat-bar">
                                            <div class="stat-bar-fill" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Browser Breakdown -->
                <div class="chart-container">
                    <h3>Browsers</h3>
                    <?php if (empty($browser_stats)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">No data yet</p>
                    <?php else: ?>
                        <?php $total_browsers = array_sum(array_column($browser_stats, 'count')); ?>
                        <ul class="stat-list">
                            <?php foreach ($browser_stats as $browser): ?>
                                <?php $percent = $total_browsers > 0 ? ($browser['count'] / $total_browsers) * 100 : 0; ?>
                                <li>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span><?php echo clean($browser['browser'] ?: 'Unknown'); ?></span>
                                            <span><?php echo number_format($browser['count']); ?> (<?php echo round($percent); ?>%)</span>
                                        </div>
                                        <div class="stat-bar">
                                            <div class="stat-bar-fill" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Videos -->
            <div class="chart-container">
                <h3>Top Videos</h3>
                <?php if (empty($top_videos) || !$top_videos[0]['views']): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">No video views yet</p>
                <?php else: ?>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Video</th>
                                <th style="text-align: right;">Views</th>
                                <th style="text-align: right;">Watch Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_videos as $video): ?>
                                <?php if ($video['views']): ?>
                                <tr>
                                    <td><?php echo clean($video['title']); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($video['views']); ?></td>
                                    <td style="text-align: right;"><?php echo formatWatchTime($video['watch_time'] ?? 0); ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Recent Views -->
            <div class="chart-container">
                <h3>Recent Views</h3>
                <?php if (empty($recent_views)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem;">No views yet</p>
                <?php else: ?>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Time</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th style="text-align: right;">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_views as $view): ?>
                            <tr>
                                <td><?php echo date('M j, g:i A', strtotime($view['started_at'])); ?></td>
                                <td style="text-align: center;"><?php echo getDeviceIcon($view['device_type']); ?></td>
                                <td style="text-align: center;"><?php echo clean($view['browser'] ?: '-'); ?></td>
                                <td style="text-align: right;"><?php echo formatWatchTime($view['duration_seconds'] ?? 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Chart data
        const dailyData = <?php echo json_encode($daily_views); ?>;
        const hourlyData = <?php echo json_encode($hourly_views); ?>;

        // Views Over Time Chart
        const viewsCtx = document.getElementById('viewsChart').getContext('2d');
        new Chart(viewsCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Total Views',
                    data: dailyData.map(d => d.views),
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    fill: true,
                    tension: 0.3
                }, {
                    label: 'Unique Viewers',
                    data: dailyData.map(d => d.unique_views),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Hourly Distribution Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyLabels = Array.from({length: 24}, (_, i) => {
            const hour = i % 12 || 12;
            const ampm = i < 12 ? 'AM' : 'PM';
            return `${hour}${ampm}`;
        });
        const hourlyValues = Array(24).fill(0);
        hourlyData.forEach(d => {
            hourlyValues[d.hour] = d.views;
        });

        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Views',
                    data: hourlyValues,
                    backgroundColor: '#7c3aed',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Update live viewers periodically
        function updateLiveViewers() {
            fetch('../api/analytics.php?action=get_viewers&station_id=<?php echo $station_id; ?>')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('liveViewers').textContent = data.viewers;
                        document.getElementById('activeViewersCard').textContent = data.viewers;
                    }
                })
                .catch(() => {});
        }

        setInterval(updateLiveViewers, 30000); // Every 30 seconds
    </script>
</body>
</html>
