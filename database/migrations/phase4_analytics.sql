-- Phase 4: Analytics Dashboard Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

-- =====================================================
-- CREATE STATION_VIEWS TABLE (View/Session Tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS `station_views` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet', 'tv', 'unknown') DEFAULT 'unknown',
    `browser` VARCHAR(100) DEFAULT NULL,
    `os` VARCHAR(100) DEFAULT NULL,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at` TIMESTAMP NULL DEFAULT NULL,
    `duration_seconds` INT DEFAULT 0,
    `is_unique` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_date` (`station_id`, `started_at`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE VIDEO_VIEWS TABLE (Per-Video Analytics)
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_views` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `video_id` INT NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `watch_duration` INT DEFAULT 0,
    `completed` TINYINT(1) DEFAULT 0,
    `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`video_id`) REFERENCES `videos`(`id`) ON DELETE CASCADE,
    INDEX `idx_video_date` (`video_id`, `viewed_at`),
    INDEX `idx_station_video` (`station_id`, `video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE HOURLY_STATS TABLE (Aggregated Stats)
-- =====================================================
CREATE TABLE IF NOT EXISTS `hourly_stats` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `stat_date` DATE NOT NULL,
    `stat_hour` TINYINT NOT NULL,
    `views` INT DEFAULT 0,
    `unique_viewers` INT DEFAULT 0,
    `total_watch_time` INT DEFAULT 0,
    `peak_concurrent` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_station_hour` (`station_id`, `stat_date`, `stat_hour`),
    INDEX `idx_station_date` (`station_id`, `stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE DAILY_STATS TABLE (Daily Aggregates)
-- =====================================================
CREATE TABLE IF NOT EXISTS `daily_stats` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `stat_date` DATE NOT NULL,
    `total_views` INT DEFAULT 0,
    `unique_viewers` INT DEFAULT 0,
    `total_watch_time` INT DEFAULT 0,
    `avg_watch_time` INT DEFAULT 0,
    `peak_concurrent` INT DEFAULT 0,
    `top_video_id` INT DEFAULT NULL,
    `top_country` VARCHAR(100) DEFAULT NULL,
    `desktop_views` INT DEFAULT 0,
    `mobile_views` INT DEFAULT 0,
    `tablet_views` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_station_date` (`station_id`, `stat_date`),
    INDEX `idx_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE ACTIVE_VIEWERS TABLE (Real-time Tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS `active_viewers` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `last_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `current_video_id` INT DEFAULT NULL,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_session` (`station_id`, `session_id`),
    INDEX `idx_last_ping` (`last_ping`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ADD ANALYTICS COLUMNS TO VIDEOS TABLE
-- =====================================================
-- Note: Using separate ALTER statements for better compatibility
ALTER TABLE `videos` ADD COLUMN IF NOT EXISTS `view_count` INT DEFAULT 0;
ALTER TABLE `videos` ADD COLUMN IF NOT EXISTS `total_watch_time` INT DEFAULT 0;
ALTER TABLE `videos` ADD COLUMN IF NOT EXISTS `completion_rate` DECIMAL(5,2) DEFAULT 0;

-- =====================================================
-- ADD ANALYTICS COLUMNS TO STATIONS TABLE
-- =====================================================
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `total_views` INT DEFAULT 0;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `total_watch_time` INT DEFAULT 0;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `peak_viewers` INT DEFAULT 0;

-- =====================================================
-- CREATE EVENT TO CLEAN OLD ACTIVE VIEWERS (Optional)
-- =====================================================
-- This removes stale active viewer records after 2 minutes of inactivity
-- Run manually or set up as cron job:
-- DELETE FROM active_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 2 MINUTE);

-- =====================================================
-- STORED PROCEDURE: Aggregate Hourly Stats
-- =====================================================
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `aggregate_hourly_stats`(IN p_station_id INT, IN p_date DATE, IN p_hour INT)
BEGIN
    INSERT INTO hourly_stats (station_id, stat_date, stat_hour, views, unique_viewers, total_watch_time)
    SELECT
        p_station_id,
        p_date,
        p_hour,
        COUNT(*) as views,
        COUNT(DISTINCT ip_address) as unique_viewers,
        SUM(duration_seconds) as total_watch_time
    FROM station_views
    WHERE station_id = p_station_id
    AND DATE(started_at) = p_date
    AND HOUR(started_at) = p_hour
    ON DUPLICATE KEY UPDATE
        views = VALUES(views),
        unique_viewers = VALUES(unique_viewers),
        total_watch_time = VALUES(total_watch_time),
        updated_at = NOW();
END //
DELIMITER ;

-- =====================================================
-- STORED PROCEDURE: Aggregate Daily Stats
-- =====================================================
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `aggregate_daily_stats`(IN p_station_id INT, IN p_date DATE)
BEGIN
    INSERT INTO daily_stats (station_id, stat_date, total_views, unique_viewers, total_watch_time, avg_watch_time, desktop_views, mobile_views, tablet_views)
    SELECT
        p_station_id,
        p_date,
        COUNT(*) as total_views,
        COUNT(DISTINCT ip_address) as unique_viewers,
        SUM(duration_seconds) as total_watch_time,
        AVG(duration_seconds) as avg_watch_time,
        SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) as desktop_views,
        SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) as mobile_views,
        SUM(CASE WHEN device_type = 'tablet' THEN 1 ELSE 0 END) as tablet_views
    FROM station_views
    WHERE station_id = p_station_id
    AND DATE(started_at) = p_date
    ON DUPLICATE KEY UPDATE
        total_views = VALUES(total_views),
        unique_viewers = VALUES(unique_viewers),
        total_watch_time = VALUES(total_watch_time),
        avg_watch_time = VALUES(avg_watch_time),
        desktop_views = VALUES(desktop_views),
        mobile_views = VALUES(mobile_views),
        tablet_views = VALUES(tablet_views);
END //
DELIMITER ;

-- =====================================================
-- VERIFY MIGRATION
-- =====================================================
-- Run these queries to verify:
-- DESCRIBE station_views;
-- DESCRIBE video_views;
-- DESCRIBE hourly_stats;
-- DESCRIBE daily_stats;
-- DESCRIBE active_viewers;
