<?php
// includes/upload_handler.php - Chunked Video Upload Handler

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get station
$stmt = $conn->prepare("SELECT * FROM stations WHERE user_id = ?");
$stmt->execute([$user_id]);
$station = $stmt->fetch();

if (!$station) {
    echo json_encode(['success' => false, 'error' => 'No station found']);
    exit;
}

$station_id = $station['id'];

// Get user for coin balance
$stmt = $conn->prepare("SELECT users.* FROM users JOIN stations ON users.id = stations.user_id WHERE stations.id = ?");
$stmt->execute([$station_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Get current video count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM videos WHERE station_id = ?");
$stmt->execute([$station_id]);
$video_count = $stmt->fetch()['total'];

// Handle different actions
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'init':
        handleInit($station_id, $video_count);
        break;
    case 'upload_chunk':
        handleChunkUpload($station_id);
        break;
    case 'finalize':
        handleFinalize($station_id, $conn);
        break;
    case 'cancel':
        handleCancel($station_id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Initialize upload - create temp directory and return upload ID
 */
function handleInit($station_id, $video_count) {
    // Check video limit
    if ($video_count >= 20) {
        echo json_encode(['success' => false, 'error' => 'Video limit reached (20 max)']);
        return;
    }
    
    $filename = isset($_POST['filename']) ? $_POST['filename'] : '';
    $filesize = isset($_POST['filesize']) ? (int)$_POST['filesize'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content_type = isset($_POST['content_type']) ? $_POST['content_type'] : 'regular';
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 3;

    // Validate content_type
    $allowed_types = ['regular', 'jingle', 'advert', 'station_id', 'filler'];
    if (!in_array($content_type, $allowed_types)) {
        $content_type = 'regular';
    }

    // Validate priority (1-6)
    if ($priority < 1 || $priority > 6) {
        $priority = 3;
    }

    if (empty($filename) || empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Filename and title are required']);
        return;
    }
    
    // Validate file size (500MB max)
    $max_size = 524288000; // 500MB
    if ($filesize > $max_size) {
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 500MB']);
        return;
    }
    
    // Validate file extension
    $allowed_extensions = ['mp4', 'mkv', 'avi', 'mov', 'webm', 'flv', 'wmv'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)]);
        return;
    }
    
    // Generate unique upload ID
    $upload_id = bin2hex(random_bytes(16));
    
    // Create temp directory for chunks
    $temp_dir = __DIR__ . '/../uploads/temp/' . $upload_id . '/';
    
    if (!file_exists($temp_dir)) {
        if (!mkdir($temp_dir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Failed to create temp directory']);
            return;
        }
    }
    
    // Save upload metadata
    $metadata = [
        'station_id' => $station_id,
        'filename' => $filename,
        'filesize' => $filesize,
        'title' => $title,
        'extension' => $extension,
        'content_type' => $content_type,
        'priority' => $priority,
        'chunks_received' => 0,
        'created_at' => time()
    ];
    
    file_put_contents($temp_dir . 'metadata.json', json_encode($metadata));
    
    echo json_encode([
        'success' => true,
        'upload_id' => $upload_id,
        'message' => 'Upload initialized'
    ]);
}

/**
 * Handle individual chunk upload
 */
function handleChunkUpload($station_id) {
    $upload_id = isset($_POST['upload_id']) ? $_POST['upload_id'] : '';
    $chunk_index = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
    $total_chunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;
    
    if (empty($upload_id) || $chunk_index < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid chunk data']);
        return;
    }
    
    // Validate upload ID format (prevent directory traversal)
    if (!preg_match('/^[a-f0-9]{32}$/', $upload_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid upload ID']);
        return;
    }
    
    $temp_dir = __DIR__ . '/../uploads/temp/' . $upload_id . '/';
    
    if (!file_exists($temp_dir)) {
        echo json_encode(['success' => false, 'error' => 'Upload session not found']);
        return;
    }
    
    // Check if chunk file was uploaded
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        $error_message = isset($_FILES['chunk']) ? getUploadErrorMessage($_FILES['chunk']['error']) : 'No file received';
        echo json_encode(['success' => false, 'error' => $error_message]);
        return;
    }
    
    // Save chunk
    $chunk_path = $temp_dir . 'chunk_' . str_pad($chunk_index, 6, '0', STR_PAD_LEFT);
    
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save chunk']);
        return;
    }
    
    // Update metadata
    $metadata_file = $temp_dir . 'metadata.json';
    $metadata = json_decode(file_get_contents($metadata_file), true);
    $metadata['chunks_received']++;
    $metadata['total_chunks'] = $total_chunks;
    file_put_contents($metadata_file, json_encode($metadata));
    
    echo json_encode([
        'success' => true,
        'chunk_index' => $chunk_index,
        'chunks_received' => $metadata['chunks_received'],
        'total_chunks' => $total_chunks
    ]);
}

/**
 * Finalize upload - combine chunks and save to database
 */
function handleFinalize($station_id, $conn) {
    $upload_id = isset($_POST['upload_id']) ? $_POST['upload_id'] : '';
    
    if (empty($upload_id) || !preg_match('/^[a-f0-9]{32}$/', $upload_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid upload ID']);
        return;
    }
    
    $temp_dir = __DIR__ . '/../uploads/temp/' . $upload_id . '/';
    
    if (!file_exists($temp_dir . 'metadata.json')) {
        echo json_encode(['success' => false, 'error' => 'Upload session not found']);
        return;
    }
    
    // Read metadata
    $metadata = json_decode(file_get_contents($temp_dir . 'metadata.json'), true);
    
    // Verify all chunks received
    if ($metadata['chunks_received'] < $metadata['total_chunks']) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing chunks. Received: ' . $metadata['chunks_received'] . ' of ' . $metadata['total_chunks']
        ]);
        return;
    }
    
    // Create final directory
    $final_dir = __DIR__ . '/../uploads/videos/' . $station_id . '/';
    
    if (!file_exists($final_dir)) {
        if (!mkdir($final_dir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Failed to create video directory']);
            return;
        }
    }
    
    // Generate safe filename
    $safe_filename = generateSafeFilename($metadata['filename'], $final_dir);
    $final_path = $final_dir . $safe_filename;
    
    // Combine chunks
    $final_file = fopen($final_path, 'wb');
    
    if (!$final_file) {
        echo json_encode(['success' => false, 'error' => 'Failed to create final file']);
        return;
    }
    
    // Read and append each chunk
    for ($i = 0; $i < $metadata['total_chunks']; $i++) {
        $chunk_path = $temp_dir . 'chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
        
        if (!file_exists($chunk_path)) {
            fclose($final_file);
            unlink($final_path);
            echo json_encode(['success' => false, 'error' => 'Chunk ' . $i . ' missing']);
            return;
        }
        
        $chunk_data = file_get_contents($chunk_path);
        fwrite($final_file, $chunk_data);
        unset($chunk_data); // Free memory
    }
    
    fclose($final_file);
    
    // Verify final file size
    $final_size = filesize($final_path);
    
    // Allow small variance due to chunk boundaries
    if (abs($final_size - $metadata['filesize']) > 1024) {
        unlink($final_path);
        echo json_encode([
            'success' => false, 
            'error' => 'File size mismatch. Expected: ' . $metadata['filesize'] . ', Got: ' . $final_size
        ]);
        return;
    }
    
    // Save to database
    try {
        $content_type = isset($metadata['content_type']) ? $metadata['content_type'] : 'regular';
        $priority = isset($metadata['priority']) ? $metadata['priority'] : 3;

        // Get current coin pricing
        $stmt = $conn->prepare("SELECT coins_required FROM coin_pricing WHERE action_type = 'video_upload' LIMIT 1");
        $stmt->execute();
        $pricing = $stmt->fetch();
        $video_upload_cost = $pricing ? (int)$pricing['coins_required'] : 10;

        // Get current user coin balance
        $stmt = $conn->prepare("SELECT coins FROM users WHERE id = (SELECT user_id FROM stations WHERE id = ?)");
        $stmt->execute([$station_id]);
        $user_data = $stmt->fetch();
        $current_balance = $user_data ? (int)$user_data['coins'] : 0;

        // Check if user has enough coins
        if ($current_balance < $video_upload_cost) {
            unlink($final_path);
            echo json_encode([
                'success' => false,
                'error' => "Insufficient coins. You need {$video_upload_cost} coins to upload a video. Current balance: {$current_balance} coins."
            ]);
            return;
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert video
        $stmt = $conn->prepare("INSERT INTO videos (station_id, title, filename, file_size, status, content_type, priority) VALUES (?, ?, ?, ?, 'ready', ?, ?)");
        $stmt->execute([$station_id, $metadata['title'], $safe_filename, $final_size, $content_type, $priority]);
        $video_id = $conn->lastInsertId();

        // Deduct coins from user
        $balance_before = $current_balance;
        $balance_after = $balance_before - $video_upload_cost;

        $stmt = $conn->prepare("UPDATE users SET coins = ?, coins_updated_at = NOW() WHERE id = (SELECT user_id FROM stations WHERE id = ?)");
        $stmt->execute([$balance_after, $station_id]);

        // Record transaction
        $stmt = $conn->prepare("INSERT INTO coin_transactions
            (user_id, amount, transaction_type, description, balance_before, balance_after, reference)
            VALUES ((SELECT user_id FROM stations WHERE id = ?), ?, 'video_upload', ?, ?, ?, ?)");
        $stmt->execute([
            $station_id,
            $video_upload_cost,
            "Video upload: {$metadata['title']} (Video #{$video_id})",
            $balance_before,
            $balance_after,
            'VID_' . $video_id
        ]);

        // Commit transaction
        $conn->commit();

        // Clean up temp directory
        cleanupTempDir($temp_dir);

        echo json_encode([
            'success' => true,
            'video_id' => $video_id,
            'filename' => $safe_filename,
            'coins_deducted' => $video_upload_cost,
            'new_balance' => $balance_after,
            'message' => 'Video uploaded successfully! ' . $video_upload_cost . ' coins deducted.'
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        unlink($final_path);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Cancel upload - clean up temp files
 */
function handleCancel($station_id) {
    $upload_id = isset($_POST['upload_id']) ? $_POST['upload_id'] : '';
    
    if (empty($upload_id) || !preg_match('/^[a-f0-9]{32}$/', $upload_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid upload ID']);
        return;
    }
    
    $temp_dir = __DIR__ . '/../uploads/temp/' . $upload_id . '/';
    
    if (file_exists($temp_dir)) {
        cleanupTempDir($temp_dir);
    }
    
    echo json_encode(['success' => true, 'message' => 'Upload cancelled']);
}

/**
 * Generate safe unique filename
 */
function generateSafeFilename($original_filename, $directory) {
    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $base_name = pathinfo($original_filename, PATHINFO_FILENAME);
    
    // Sanitize base name
    $base_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name);
    $base_name = substr($base_name, 0, 50); // Limit length
    
    // Add timestamp for uniqueness
    $timestamp = time();
    $filename = $base_name . '_' . $timestamp . '.' . $extension;
    
    // If still exists, add random string
    if (file_exists($directory . $filename)) {
        $filename = $base_name . '_' . $timestamp . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    }
    
    return $filename;
}

/**
 * Clean up temp directory
 */
function cleanupTempDir($dir) {
    if (!is_dir($dir)) return;
    
    $files = glob($dir . '*', GLOB_MARK);
    
    foreach ($files as $file) {
        if (is_dir($file)) {
            cleanupTempDir($file);
        } else {
            unlink($file);
        }
    }
    
    rmdir($dir);
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    
    return isset($errors[$error_code]) ? $errors[$error_code] : 'Unknown upload error';
}
?>