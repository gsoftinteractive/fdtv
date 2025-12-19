<?php
// config/settings.php

define('SITE_NAME', 'FDTV');
define('SITE_URL', 'https://fdtv.ng');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('STREAM_PATH', BASE_PATH . '/streams/');

// File limits
define('MAX_VIDEOS_PER_CLIENT', 20);
define('MAX_FILE_SIZE', 524288000); // 500MB in bytes
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/x-matroska', 'video/avi']);

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Set to 0 for localhost

session_start();
?>