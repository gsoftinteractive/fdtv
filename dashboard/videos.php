<?php
// dashboard/videos.php - Video Management with Chunked Upload

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

// Get videos
$stmt = $conn->prepare("SELECT * FROM videos WHERE station_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$station_id]);
$videos = $stmt->fetchAll();
$video_count = count($videos);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf'])) {
    if (verify_csrf_token($_GET['csrf'])) {
        $video_id = (int)$_GET['delete'];
        
        $stmt = $conn->prepare("SELECT * FROM videos WHERE id = ? AND station_id = ?");
        $stmt->execute([$video_id, $station_id]);
        $video = $stmt->fetch();
        
        if ($video) {
            // Delete file
            $file_path = '../uploads/videos/' . $station_id . '/' . $video['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$video_id]);
            
            // Delete from schedules
            $stmt = $conn->prepare("DELETE FROM schedules WHERE video_id = ?");
            $stmt->execute([$video_id]);
            
            set_flash("Video deleted successfully!", "success");
        }
    }
    redirect('videos.php');
}

$flash = get_flash();
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Videos - FDTV</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/video-upload.css">
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
            <h1>Manage Videos</h1>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Upload Section -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Upload New Video</h2>
                    <span class="video-count"><?php echo $video_count; ?> / 20 videos</span>
                </div>

                <?php if ($video_count >= 20): ?>
                    <div class="alert alert-warning">
                        You have reached the maximum video limit (20). Please delete some videos to upload new ones.
                    </div>
                <?php else: ?>

                <div class="upload-container" id="uploadContainer">
                    <!-- Drop Zone -->
                    <div class="drop-zone" id="dropZone">
                        <div class="drop-zone-content">
                            <div class="drop-zone-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                            </div>
                            <p class="drop-zone-text">Drag & drop your video here</p>
                            <p class="drop-zone-subtext">or</p>
                            <button type="button" class="btn" id="selectFileBtn">Select Video File</button>
                            <input type="file" id="videoInput" accept="video/*" hidden>
                            <p class="drop-zone-info">Max: 500MB | Allowed: MP4, MKV, AVI, MOV, WebM</p>
                        </div>
                    </div>

                    <!-- File Selected Preview -->
                    <div class="file-preview" id="filePreview" style="display: none;">
                        <div class="file-info">
                            <div class="file-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                </svg>
                            </div>
                            <div class="file-details">
                                <p class="file-name" id="fileName"></p>
                                <p class="file-size" id="fileSize"></p>
                            </div>
                            <button type="button" class="btn-icon" id="removeFileBtn" title="Remove">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="videoTitle">Video Title *</label>
                            <input type="text" id="videoTitle" placeholder="Enter video title" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="contentType">Content Type</label>
                                <select id="contentType">
                                    <option value="regular">Regular Content</option>
                                    <option value="jingle">Jingle</option>
                                    <option value="advert">Advertisement</option>
                                    <option value="station_id">Station ID</option>
                                    <option value="filler">Filler Content</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="videoPriority">Priority Level</label>
                                <select id="videoPriority">
                                    <option value="1">1 - Emergency/Breaking</option>
                                    <option value="2">2 - Scheduled Programs</option>
                                    <option value="3" selected>3 - Regular Content</option>
                                    <option value="4">4 - Filler Content</option>
                                    <option value="5">5 - Low Priority</option>
                                    <option value="6">6 - Archive Only</option>
                                </select>
                            </div>
                        </div>

                        <button type="button" class="btn btn-large" id="startUploadBtn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Start Upload
                        </button>
                    </div>

                    <!-- Upload Progress -->
                    <div class="upload-progress" id="uploadProgress" style="display: none;">
                        <div class="progress-header">
                            <div class="file-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                </svg>
                            </div>
                            <div class="progress-info">
                                <p class="progress-title" id="progressTitle"></p>
                                <p class="progress-status" id="progressStatus">Initializing...</p>
                            </div>
                        </div>

                        <div class="progress-bar-container">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                            <span class="progress-percent" id="progressPercent">0%</span>
                        </div>

                        <div class="progress-stats">
                            <span id="uploadedSize">0 MB</span>
                            <span id="uploadSpeed"></span>
                            <span id="timeRemaining"></span>
                        </div>

                        <div class="progress-actions">
                            <button type="button" class="btn btn-secondary" id="pauseBtn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="6" y="4" width="4" height="16"/>
                                    <rect x="14" y="4" width="4" height="16"/>
                                </svg>
                                Pause
                            </button>
                            <button type="button" class="btn btn-danger" id="cancelBtn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Upload Success -->
                    <div class="upload-success" id="uploadSuccess" style="display: none;">
                        <div class="success-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h3>Upload Complete!</h3>
                        <p id="successMessage">Your video has been uploaded successfully.</p>
                        <button type="button" class="btn" id="uploadAnotherBtn">Upload Another Video</button>
                    </div>

                    <!-- Upload Error -->
                    <div class="upload-error" id="uploadError" style="display: none;">
                        <div class="error-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                        </div>
                        <h3>Upload Failed</h3>
                        <p id="errorMessage">An error occurred during upload.</p>
                        <button type="button" class="btn" id="retryBtn">Try Again</button>
                    </div>
                </div>

                <?php endif; ?>
            </div>

            <!-- Video List -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">My Videos (<?php echo $video_count; ?>)</h2>
                </div>

                <?php if (empty($videos)): ?>
                    <div class="empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <polygon points="23 7 16 12 23 17 23 7"/>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                        </svg>
                        <p>No videos uploaded yet.</p>
                        <p class="empty-state-sub">Upload your first video to get started!</p>
                    </div>
                <?php else: ?>
                    <div class="video-list">
                        <?php foreach ($videos as $video): ?>
                        <div class="video-item">
                            <div class="video-thumbnail">
                                <?php if ($video['thumbnail']): ?>
                                    <img src="../uploads/thumbnails/<?php echo $video['thumbnail']; ?>" alt="Thumbnail">
                                <?php else: ?>
                                    <div class="thumbnail-placeholder">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polygon points="23 7 16 12 23 17 23 7"/>
                                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="video-info">
                                <h4 class="video-title"><?php echo clean($video['title']); ?></h4>
                                <div class="video-meta">
                                    <span class="video-size"><?php echo format_file_size($video['file_size']); ?></span>
                                    <span class="video-date"><?php echo format_date($video['uploaded_at']); ?></span>
                                    <?php
                                    $content_type = $video['content_type'] ?? 'regular';
                                    $priority = $video['priority'] ?? 3;
                                    $type_badges = [
                                        'regular' => 'secondary',
                                        'jingle' => 'info',
                                        'advert' => 'warning',
                                        'station_id' => 'success',
                                        'filler' => 'secondary'
                                    ];
                                    $badge_class = $type_badges[$content_type] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?php echo $badge_class; ?>"><?php echo ucfirst($content_type); ?></span>
                                    <span class="badge badge-secondary">P<?php echo $priority; ?></span>
                                </div>
                            </div>
                            <div class="video-actions">
                                <a href="../uploads/videos/<?php echo $station_id; ?>/<?php echo $video['filename']; ?>" 
                                   target="_blank" class="btn btn-small btn-secondary" title="Preview">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                    </svg>
                                    Preview
                                </a>
                                <a href="?delete=<?php echo $video['id']; ?>&csrf=<?php echo $csrf_token; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this video? This action cannot be undone.')"
                                   class="btn btn-small btn-danger" title="Delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/chunked-upload.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('videoInput');
        const selectFileBtn = document.getElementById('selectFileBtn');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFileBtn = document.getElementById('removeFileBtn');
        const videoTitle = document.getElementById('videoTitle');
        const contentType = document.getElementById('contentType');
        const videoPriority = document.getElementById('videoPriority');
        const startUploadBtn = document.getElementById('startUploadBtn');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressTitle = document.getElementById('progressTitle');
        const progressStatus = document.getElementById('progressStatus');
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        const uploadedSize = document.getElementById('uploadedSize');
        const uploadSpeed = document.getElementById('uploadSpeed');
        const timeRemaining = document.getElementById('timeRemaining');
        const pauseBtn = document.getElementById('pauseBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const uploadSuccess = document.getElementById('uploadSuccess');
        const successMessage = document.getElementById('successMessage');
        const uploadAnotherBtn = document.getElementById('uploadAnotherBtn');
        const uploadError = document.getElementById('uploadError');
        const errorMessage = document.getElementById('errorMessage');
        const retryBtn = document.getElementById('retryBtn');

        let selectedFile = null;
        let uploader = null;
        let lastUploadedBytes = 0;
        let lastTime = Date.now();
        let isPaused = false;

        // Initialize uploader
        function createUploader() {
            return new ChunkedUploader({
                endpoint: '../includes/upload_handler.php',
                onProgress: handleProgress,
                onSuccess: handleSuccess,
                onError: handleError,
                onStateChange: handleStateChange
            });
        }

        // File selection via button
        selectFileBtn.addEventListener('click', () => fileInput.click());

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length > 0) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });

        // Handle file selection
        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['video/mp4', 'video/x-matroska', 'video/avi', 'video/quicktime', 'video/webm', 'video/x-flv', 'video/x-ms-wmv'];
            const allowedExtensions = ['mp4', 'mkv', 'avi', 'mov', 'webm', 'flv', 'wmv'];
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension)) {
                alert('Invalid file type. Please select a video file (MP4, MKV, AVI, MOV, WebM).');
                return;
            }

            // Validate file size (500MB)
            const maxSize = 500 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File too large. Maximum size is 500MB.');
                return;
            }

            selectedFile = file;
            fileName.textContent = file.name;
            fileSize.textContent = ChunkedUploader.formatBytes(file.size);
            
            // Auto-generate title from filename
            const titleFromFile = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]/g, ' ');
            videoTitle.value = titleFromFile;

            dropZone.style.display = 'none';
            filePreview.style.display = 'block';
        }

        // Remove file
        removeFileBtn.addEventListener('click', resetUploadUI);

        // Start upload
        startUploadBtn.addEventListener('click', () => {
            const title = videoTitle.value.trim();
            
            if (!title) {
                alert('Please enter a video title.');
                videoTitle.focus();
                return;
            }

            if (!selectedFile) {
                alert('Please select a video file.');
                return;
            }

            uploader = createUploader();
            filePreview.style.display = 'none';
            uploadProgress.style.display = 'block';
            progressTitle.textContent = title;
            lastUploadedBytes = 0;
            lastTime = Date.now();

            // Pass additional metadata
            const metadata = {
                content_type: contentType.value,
                priority: videoPriority.value
            };

            uploader.start(selectedFile, title, metadata);
        });

        // Pause/Resume
        pauseBtn.addEventListener('click', () => {
            if (!uploader) return;

            if (isPaused) {
                uploader.resume();
                pauseBtn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="6" y="4" width="4" height="16"/>
                        <rect x="14" y="4" width="4" height="16"/>
                    </svg>
                    Pause
                `;
                isPaused = false;
            } else {
                uploader.pause();
                pauseBtn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    Resume
                `;
                isPaused = true;
            }
        });

        // Cancel
        cancelBtn.addEventListener('click', () => {
            if (uploader && confirm('Are you sure you want to cancel this upload?')) {
                uploader.cancel();
            }
        });

        // Upload another
        uploadAnotherBtn.addEventListener('click', () => {
            resetUploadUI();
            location.reload();
        });

        // Retry
        retryBtn.addEventListener('click', resetUploadUI);

        // Progress handler
        function handleProgress(percent, uploaded, total, currentChunk, totalChunks) {
            progressFill.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
            uploadedSize.textContent = ChunkedUploader.formatBytes(uploaded) + ' / ' + ChunkedUploader.formatBytes(total);

            // Calculate speed
            const now = Date.now();
            const timeDiff = (now - lastTime) / 1000;
            if (timeDiff >= 1) {
                const bytesDiff = uploaded - lastUploadedBytes;
                const speed = bytesDiff / timeDiff;
                uploadSpeed.textContent = ChunkedUploader.formatBytes(speed) + '/s';

                // Estimate time remaining
                const remaining = total - uploaded;
                const timeRem = remaining / speed;
                timeRemaining.textContent = ChunkedUploader.formatTime(timeRem) + ' remaining';

                lastUploadedBytes = uploaded;
                lastTime = now;
            }

            progressStatus.textContent = `Uploading chunk ${currentChunk} of ${totalChunks}...`;
        }

        // Success handler
        function handleSuccess(result) {
            uploadProgress.style.display = 'none';
            uploadSuccess.style.display = 'block';
            successMessage.textContent = `"${result.filename}" has been uploaded successfully!`;
        }

        // Error handler
        function handleError(error) {
            uploadProgress.style.display = 'none';
            uploadError.style.display = 'block';
            errorMessage.textContent = error;
        }

        // State change handler
        function handleStateChange(state) {
            switch (state) {
                case 'initializing':
                    progressStatus.textContent = 'Initializing upload...';
                    break;
                case 'uploading':
                    progressStatus.textContent = 'Uploading...';
                    break;
                case 'paused':
                    progressStatus.textContent = 'Paused';
                    break;
                case 'finalizing':
                    progressStatus.textContent = 'Finalizing upload...';
                    break;
                case 'completed':
                    progressStatus.textContent = 'Complete!';
                    break;
                case 'cancelled':
                    resetUploadUI();
                    break;
                case 'error':
                    progressStatus.textContent = 'Error';
                    break;
            }
        }

        // Reset UI
        function resetUploadUI() {
            selectedFile = null;
            uploader = null;
            isPaused = false;
            fileInput.value = '';
            videoTitle.value = '';
            progressFill.style.width = '0%';
            progressPercent.textContent = '0%';
            
            dropZone.style.display = 'block';
            filePreview.style.display = 'none';
            uploadProgress.style.display = 'none';
            uploadSuccess.style.display = 'none';
            uploadError.style.display = 'none';

            pauseBtn.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="6" y="4" width="4" height="16"/>
                    <rect x="14" y="4" width="4" height="16"/>
                </svg>
                Pause
            `;
        }
    });
    </script>
</body>
</html>