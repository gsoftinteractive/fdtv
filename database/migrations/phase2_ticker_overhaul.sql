-- Phase 2: Ticker Overhaul Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

-- =====================================================
-- MODIFY STATION_TICKERS TABLE - Add customization columns
-- =====================================================
ALTER TABLE `station_tickers`
ADD COLUMN `ticker_category` ENUM('breaking', 'events', 'schedule') DEFAULT 'breaking' AFTER `type`,
ADD COLUMN `color` VARCHAR(7) DEFAULT '#dc2626' AFTER `ticker_category`,
ADD COLUMN `bg_color` VARCHAR(7) DEFAULT '#000000' AFTER `color`,
ADD COLUMN `speed` ENUM('slow', 'normal', 'fast') DEFAULT 'normal' AFTER `bg_color`,
ADD COLUMN `font_size` ENUM('small', 'medium', 'large') DEFAULT 'medium' AFTER `speed`,
ADD COLUMN `icon` VARCHAR(50) DEFAULT NULL AFTER `font_size`,
ADD COLUMN `scheduled_start` DATETIME DEFAULT NULL AFTER `icon`,
ADD COLUMN `scheduled_end` DATETIME DEFAULT NULL AFTER `scheduled_start`,
ADD COLUMN `display_days` VARCHAR(20) DEFAULT NULL AFTER `scheduled_end`,
ADD COLUMN `event_type` ENUM('general', 'birthday', 'anniversary', 'promotion', 'holiday', 'advert') DEFAULT 'general' AFTER `display_days`;

-- Add index for scheduled tickers
ALTER TABLE `station_tickers` ADD INDEX `idx_scheduled` (`station_id`, `scheduled_start`, `scheduled_end`, `is_active`);

-- =====================================================
-- UPDATE STATIONS TABLE - Ensure active_ticker_type exists
-- =====================================================
-- This should already exist from Phase 1, but just in case:
-- ALTER TABLE `stations` ADD COLUMN `active_ticker_type` ENUM('breaking', 'events', 'schedule', 'none') DEFAULT 'breaking';

-- =====================================================
-- CREATE TICKER_PRESETS TABLE - For saved ticker styles
-- =====================================================
CREATE TABLE IF NOT EXISTS `ticker_presets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `color` VARCHAR(7) DEFAULT '#dc2626',
    `bg_color` VARCHAR(7) DEFAULT '#000000',
    `speed` ENUM('slow', 'normal', 'fast') DEFAULT 'normal',
    `font_size` ENUM('small', 'medium', 'large') DEFAULT 'medium',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INSERT DEFAULT PRESETS (Optional - for new stations)
-- =====================================================
-- These will be created per-station when needed

-- =====================================================
-- VERIFY MIGRATION
-- =====================================================
-- Run these queries to verify:
-- DESCRIBE station_tickers;
-- DESCRIBE ticker_presets;
-- SHOW INDEX FROM station_tickers;
