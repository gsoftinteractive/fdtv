-- Phase 1: Watermark/Logo + Live Feed Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

-- =====================================================
-- MODIFY USERS TABLE - Add radio_enabled column
-- =====================================================
ALTER TABLE `users`
ADD COLUMN `radio_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

-- =====================================================
-- MODIFY STATIONS TABLE - Add logo position, mode, ticker type
-- =====================================================
ALTER TABLE `stations`
ADD COLUMN `logo_path` VARCHAR(255) DEFAULT NULL AFTER `logo`,
ADD COLUMN `logo_position` ENUM('top-left', 'top-right', 'bottom-left', 'bottom-right') DEFAULT 'top-right' AFTER `logo_path`,
ADD COLUMN `logo_opacity` DECIMAL(3,2) DEFAULT 0.90 AFTER `logo_position`,
ADD COLUMN `logo_size` ENUM('small', 'medium', 'large') DEFAULT 'medium' AFTER `logo_opacity`,
ADD COLUMN `mode` ENUM('playlist', 'live') DEFAULT 'playlist' AFTER `logo_size`,
ADD COLUMN `active_ticker_type` ENUM('breaking', 'events', 'schedule', 'none') DEFAULT 'breaking' AFTER `mode`;

-- =====================================================
-- CREATE LIVE_FEEDS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `live_feeds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `source_type` ENUM('youtube', 'facebook', 'vimeo', 'hls', 'mp4', 'iframe') NOT NULL,
    `source_url` TEXT NOT NULL,
    `embed_code` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_active` (`station_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE EMERGENCY_BROADCASTS TABLE (for Phase 3, but structure now)
-- =====================================================
CREATE TABLE IF NOT EXISTS `emergency_broadcasts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `video_url` TEXT DEFAULT NULL,
    `is_global` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 0,
    `priority` INT DEFAULT 100,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_active` (`is_active`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- VERIFY MIGRATION
-- =====================================================
-- Run these queries to verify the migration worked:
-- DESCRIBE users;
-- DESCRIBE stations;
-- DESCRIBE live_feeds;
-- DESCRIBE emergency_broadcasts;
