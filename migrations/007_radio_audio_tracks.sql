-- Radio Audio Tracks Migration
-- Allows users to upload audio files for self-contained radio streaming

-- Create audio_tracks table
CREATE TABLE IF NOT EXISTS `radio_audio_tracks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `station_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `artist` VARCHAR(255) DEFAULT NULL,
    `album` VARCHAR(255) DEFAULT NULL,
    `genre` VARCHAR(100) DEFAULT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) DEFAULT NULL,
    `file_size` BIGINT DEFAULT 0,
    `duration` INT DEFAULT 0 COMMENT 'Duration in seconds',
    `file_type` VARCHAR(50) DEFAULT 'audio/mpeg',
    `bitrate` INT DEFAULT 128,
    `sort_order` INT DEFAULT 0,
    `play_count` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `station_id` (`station_id`),
    KEY `sort_order` (`sort_order`),
    KEY `is_active` (`is_active`),
    CONSTRAINT `fk_radio_audio_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add column to stations table for radio mode (stream vs upload)
ALTER TABLE `stations`
ADD COLUMN IF NOT EXISTS `radio_mode` ENUM('stream', 'upload', 'both') DEFAULT 'stream' AFTER `radio_enabled`,
ADD COLUMN IF NOT EXISTS `radio_shuffle` TINYINT(1) DEFAULT 0 AFTER `radio_mode`,
ADD COLUMN IF NOT EXISTS `radio_auto_advance` TINYINT(1) DEFAULT 1 AFTER `radio_shuffle`;

-- Create radio playlist table for custom playlists
CREATE TABLE IF NOT EXISTS `radio_playlists` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `station_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `station_id` (`station_id`),
    CONSTRAINT `fk_radio_playlist_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create playlist_tracks junction table
CREATE TABLE IF NOT EXISTS `radio_playlist_tracks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `playlist_id` INT(11) NOT NULL,
    `track_id` INT(11) NOT NULL,
    `sort_order` INT DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `playlist_id` (`playlist_id`),
    KEY `track_id` (`track_id`),
    CONSTRAINT `fk_playlist_tracks_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `radio_playlists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_playlist_tracks_track` FOREIGN KEY (`track_id`) REFERENCES `radio_audio_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
