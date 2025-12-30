-- Phase 7: Station Display Settings (Database-Backed)
-- Add columns for station-wide display settings controlled by admin

ALTER TABLE stations ADD COLUMN IF NOT EXISTS ticker_color VARCHAR(20) DEFAULT 'red' COMMENT 'Ticker color preset: red, purple, green, blue, orange, pink, teal, indigo';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS ticker_label VARCHAR(15) DEFAULT 'BREAKING' COMMENT 'Custom ticker label text';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS ticker_mode ENUM('single', 'double') DEFAULT 'single' COMMENT 'Single or double-line ticker';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS ticker_speed INT DEFAULT 60 COMMENT 'Ticker scroll speed in seconds';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS clock_position_x INT DEFAULT 0 COMMENT 'Clock X position offset';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS clock_position_y INT DEFAULT 0 COMMENT 'Clock Y position offset';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS social_badges JSON DEFAULT NULL COMMENT 'Social media badges configuration';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS lower_thirds_presets JSON DEFAULT NULL COMMENT 'Lower thirds presets for presenters';
ALTER TABLE stations ADD COLUMN IF NOT EXISTS display_settings_updated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time display settings were updated';

-- Set default social badges for existing stations
UPDATE stations
SET social_badges = JSON_ARRAY(
    JSON_OBJECT('icon', '𝕏', 'handle', CONCAT('@', REPLACE(name, ' ', '')), 'platform', 'Twitter'),
    JSON_OBJECT('icon', '📘', 'handle', CONCAT('/', REPLACE(name, ' ', '')), 'platform', 'Facebook'),
    JSON_OBJECT('icon', '📷', 'handle', CONCAT('@', REPLACE(name, ' ', '')), 'platform', 'Instagram'),
    JSON_OBJECT('icon', '▶️', 'handle', REPLACE(name, ' ', ''), 'platform', 'YouTube')
)
WHERE social_badges IS NULL;

-- Set default lower thirds presets for existing stations
UPDATE stations
SET lower_thirds_presets = JSON_ARRAY(
    JSON_OBJECT('name', 'John Smith', 'title', 'News Anchor', 'style', 'modern'),
    JSON_OBJECT('name', 'Jane Doe', 'title', 'Weather Reporter', 'style', 'bold'),
    JSON_OBJECT('name', 'Alex Johnson', 'title', 'Sports Analyst', 'style', 'news')
)
WHERE lower_thirds_presets IS NULL;

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_stations_display_updated ON stations(display_settings_updated_at);
