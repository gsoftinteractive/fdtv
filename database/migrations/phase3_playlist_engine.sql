-- Phase 3: Playlist Engine Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

-- =====================================================
-- MODIFY VIDEOS TABLE - Add content type and priority
-- =====================================================
ALTER TABLE `videos`
ADD COLUMN `content_type` ENUM('regular', 'jingle', 'advert', 'station_id', 'filler') DEFAULT 'regular' AFTER `status`,
ADD COLUMN `priority` TINYINT(1) DEFAULT 3 AFTER `content_type`,
ADD COLUMN `category` VARCHAR(100) DEFAULT NULL AFTER `priority`,
ADD COLUMN `tags` VARCHAR(500) DEFAULT NULL AFTER `category`,
ADD COLUMN `play_count` INT DEFAULT 0 AFTER `tags`,
ADD COLUMN `last_played` TIMESTAMP NULL DEFAULT NULL AFTER `play_count`;

-- Add indexes for efficient querying
ALTER TABLE `videos`
ADD INDEX `idx_content_type` (`station_id`, `content_type`),
ADD INDEX `idx_priority` (`station_id`, `priority`);

-- =====================================================
-- CREATE JINGLES TABLE (Station Identifiers / Adverts)
-- =====================================================
CREATE TABLE IF NOT EXISTS `jingles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_size` BIGINT DEFAULT 0,
    `duration` INT DEFAULT 0,
    `jingle_type` ENUM('station_id', 'jingle', 'advert', 'sponsor') NOT NULL DEFAULT 'jingle',
    `priority` TINYINT(1) DEFAULT 3,
    `play_frequency` ENUM('every_video', 'every_2_videos', 'every_3_videos', 'every_5_videos', 'hourly', 'custom') DEFAULT 'every_3_videos',
    `custom_interval` INT DEFAULT NULL,
    `time_slots` JSON DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `play_count` INT DEFAULT 0,
    `last_played` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_type` (`station_id`, `jingle_type`),
    INDEX `idx_active` (`station_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE PLAYLIST_RULES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `playlist_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `rule_name` VARCHAR(100) NOT NULL,
    `rule_type` ENUM('jingle_insertion', 'time_block', 'priority_weight', 'advert_break', 'content_mix') NOT NULL,
    `config` JSON NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `priority` INT DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_rules` (`station_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE TIME_BLOCKS TABLE (For time-based scheduling)
-- =====================================================
CREATE TABLE IF NOT EXISTS `time_blocks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `block_name` VARCHAR(100) NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `days_active` VARCHAR(20) DEFAULT '0123456',
    `content_priority` TINYINT(1) DEFAULT 3,
    `allowed_content_types` VARCHAR(100) DEFAULT 'regular,jingle',
    `jingle_frequency` INT DEFAULT 3,
    `advert_frequency` INT DEFAULT 5,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_blocks` (`station_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CREATE PLAYLIST_QUEUE TABLE (Active playback queue)
-- =====================================================
CREATE TABLE IF NOT EXISTS `playlist_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `station_id` INT NOT NULL,
    `content_type` ENUM('video', 'jingle') NOT NULL,
    `content_id` INT NOT NULL,
    `position` INT NOT NULL,
    `status` ENUM('pending', 'playing', 'played', 'skipped') DEFAULT 'pending',
    `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
    `played_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`station_id`) REFERENCES `stations`(`id`) ON DELETE CASCADE,
    INDEX `idx_station_queue` (`station_id`, `status`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MODIFY STATIONS TABLE - Add playlist settings
-- =====================================================
ALTER TABLE `stations`
ADD COLUMN `playlist_mode` ENUM('sequential', 'shuffle', 'priority', 'scheduled') DEFAULT 'sequential' AFTER `active_ticker_type`,
ADD COLUMN `jingle_enabled` TINYINT(1) DEFAULT 1 AFTER `playlist_mode`,
ADD COLUMN `advert_enabled` TINYINT(1) DEFAULT 0 AFTER `jingle_enabled`,
ADD COLUMN `default_jingle_interval` INT DEFAULT 3 AFTER `advert_enabled`,
ADD COLUMN `default_advert_interval` INT DEFAULT 5 AFTER `default_jingle_interval`;

-- =====================================================
-- INSERT DEFAULT PLAYLIST RULES
-- =====================================================
-- These are example rules that stations can customize

-- Example: Insert station ID every 3 videos
-- INSERT INTO `playlist_rules` (`station_id`, `rule_name`, `rule_type`, `config`) VALUES
-- (1, 'Station ID Insertion', 'jingle_insertion', '{"interval": 3, "jingle_type": "station_id", "position": "after"}');

-- =====================================================
-- PRIORITY LEVELS REFERENCE
-- =====================================================
-- Priority 1: Emergency/Breaking (always plays first)
-- Priority 2: Scheduled Programs (time-sensitive)
-- Priority 3: Regular Content (normal rotation)
-- Priority 4: Filler Content (plays when queue is empty)
-- Priority 5: Low Priority (rarely plays)
-- Priority 6: Archive (only on-demand)

-- =====================================================
-- VERIFY MIGRATION
-- =====================================================
-- Run these queries to verify:
-- DESCRIBE videos;
-- DESCRIBE jingles;
-- DESCRIBE playlist_rules;
-- DESCRIBE time_blocks;
-- DESCRIBE playlist_queue;
-- DESCRIBE stations;
