-- Phase 7: Fix - Set default values for display settings
-- Run this in phpMyAdmin to complete the migration

-- Set default social badges for existing stations
UPDATE stations
SET social_badges = JSON_ARRAY(
    JSON_OBJECT('icon', '𝕏', 'handle', CONCAT('@', REPLACE(station_name, ' ', '')), 'platform', 'Twitter'),
    JSON_OBJECT('icon', '📘', 'handle', CONCAT('/', REPLACE(station_name, ' ', '')), 'platform', 'Facebook'),
    JSON_OBJECT('icon', '📷', 'handle', CONCAT('@', REPLACE(station_name, ' ', '')), 'platform', 'Instagram'),
    JSON_OBJECT('icon', '▶️', 'handle', REPLACE(station_name, ' ', ''), 'platform', 'YouTube')
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

-- Add index for faster queries (if not already exists)
CREATE INDEX IF NOT EXISTS idx_stations_display_updated ON stations(display_settings_updated_at);
