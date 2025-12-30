-- Phase 6: Radio Jingle Timing Customization
-- Run this SQL in phpMyAdmin or MySQL CLI
-- This adds jingle timing controls to radio stations (like TV has)

-- =====================================================
-- ADD RADIO JINGLE COLUMNS TO STATIONS TABLE
-- =====================================================

ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_jingle_enabled` TINYINT(1) DEFAULT 1
    COMMENT 'Enable/disable jingle playback for radio';

ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_jingle_interval` INT DEFAULT 5
    COMMENT 'Number of tracks between jingles for radio';

ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_advert_enabled` TINYINT(1) DEFAULT 0
    COMMENT 'Enable/disable advert jingles for radio';

ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_advert_interval` INT DEFAULT 10
    COMMENT 'Number of tracks between adverts for radio';

ALTER TABLE `stations` ADD COLUMN IF NOT EXISTS `radio_playlist_mode` ENUM('sequential', 'shuffle', 'priority') DEFAULT 'shuffle'
    COMMENT 'Radio playlist playback mode';

-- =====================================================
-- UPDATE JINGLES TABLE TO SUPPORT RADIO
-- =====================================================

ALTER TABLE `jingles` ADD COLUMN IF NOT EXISTS `is_for_radio` TINYINT(1) DEFAULT 0
    COMMENT '1 = for radio, 0 = for TV';

ALTER TABLE `jingles` ADD COLUMN IF NOT EXISTS `audio_only` TINYINT(1) DEFAULT 0
    COMMENT '1 = audio-only jingle (for radio), 0 = video jingle';

-- Create index for radio jingles
CREATE INDEX IF NOT EXISTS `idx_radio_jingles` ON `jingles`(`station_id`, `is_for_radio`, `is_active`);

-- =====================================================
-- VERIFY MIGRATION
-- =====================================================
-- Run these queries to verify:
-- SHOW COLUMNS FROM stations LIKE 'radio_jingle%';
-- SHOW COLUMNS FROM stations LIKE 'radio_advert%';
-- SHOW COLUMNS FROM stations LIKE 'radio_playlist_mode';
-- SHOW COLUMNS FROM jingles LIKE 'is_for_radio';
-- SHOW COLUMNS FROM jingles LIKE 'audio_only';
