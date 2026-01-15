<?php
// config/settings.php

define('SITE_NAME', 'FDTV');
define('SITE_URL', 'https://fdtv.ng');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('STREAM_PATH', BASE_PATH . '/streams/');

// File limits
define('MAX_VIDEOS_PER_CLIENT', 20);
// No file size limit - pricing is based on file size
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/x-matroska', 'video/avi']);

// Coin pricing for video uploads (dynamic based on file size)
define('VIDEO_UPLOAD_BASE_COINS', 10);      // Base cost for any upload
define('VIDEO_UPLOAD_COINS_PER_100MB', 5);  // Additional coins per 100MB

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Set to 0 for localhost

session_start();
?>