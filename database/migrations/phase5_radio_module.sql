-- Phase 5: Internet Radio Module Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

-- =====================================================
-- CREATE RADIO_STREAMS TABLE (Stream Configuration)
-- =====================================================
CREATE TABLE IF NOT EXISTS `radio_streams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `stream_url` VARCHAR(500) NOT NULL,
    `stream_type` ENUM('shoutcast', 'icecast', 'hls', 'mp3', 'aac', 'other') DEFAULT 'shoutcast',
    `bitrate` INT DEFAULT 128,
    `format` VARCHAR(50) DEFAULT 'mp3',
    `is_active` TINYINT(1) DEFAULT 1,
    `is_primary` TINYINT(1) DEFAULT 0,
    `fallback_url` VARCHAR(500) DEFAULT NULL,
    `metadata_url` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_active` (`station_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE RADIO_SCHEDULE TABLE (Program Schedule)
-- =====================================================
CREATE TABLE IF NOT EXISTS `radio_schedule` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `program_name` VARCHAR(255) NOT NULL,
    `host_name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `cover_image` VARCHAR(255) DEFAULT NULL,
    `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `is_live` TINYINT(1) DEFAULT 0,
    `is_repeat` TINYINT(1) DEFAULT 0,
    `genre` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_day` (`station_id`, `day_of_week`),
    INDEX `idx_time` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE RADIO_NOW_PLAYING TABLE (Current Track Info)
-- =====================================================
CREATE TABLE IF NOT EXISTS `radio_now_playing` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `track_title` VARCHAR(255) DEFAULT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `album` VARCHAR(255) DEFAULT NULL,
    `cover_art` VARCHAR(500) DEFAULT NULL,
    `duration` INT DEFAULT 0,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `source` ENUM('metadata', 'manual', 'api') DEFAULT 'metadata',
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_station` (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE RADIO_HISTORY TABLE (Play History)
-- =====================================================
CREATE TABLE IF NOT EXISTS `radio_history` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `track_title` VARCHAR(255) NOT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `played_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_played` (`station_id`, `played_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE RADIO_LISTENERS TABLE (Listener Tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS `radio_listeners` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `device_type` ENUM('desktop', 'mobile', 'tablet', 'smart_speaker', 'unknown') DEFAULT 'unknown',
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `listen_time` INT DEFAULT 0,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_session` (`station_id`, `session_id`),
    INDEX `idx_last_ping` (`last_ping`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ADD RADIO COLUMNS TO STATIONS TABLE
-- =====================================================
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_enabled` TINYINT(1) DEFAULT 0;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_name` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_tagline` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_genre` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_website` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_logo` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_background` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_color_primary` VARCHAR(7) DEFAULT '#6366f1';
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_color_secondary` VARCHAR(7) DEFAULT '#8b5cf6';
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_social_facebook` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_social_twitter` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_social_instagram` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_social_whatsapp` VARCHAR(255) DEFAULT NULL;

-- =====================================================
-- VERIFY MIGRATION
-- =====================================================
-- Run these queries to verify:
-- DESCRIBE radio_streams;
-- DESCRIBE radio_schedule;
-- DESCRIBE radio_now_playing;
-- DESCRIBE radio_history;
-- DESCRIBE radio_listeners;
-- SHOW COLUMNS FROM stations LIKE 'radio%';
