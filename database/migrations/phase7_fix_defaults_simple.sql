-- Phase 7: Fix - Set default values (Simple version without emoji conflicts)
-- Run this in phpMyAdmin to complete the migration

-- Set default social badges for existing stations (simple text icons)
UPDATE stations
SET social_badges = '[
    {"icon": "X", "handle": "@YourStation", "platform": "Twitter"},
    {"icon": "FB", "handle": "/YourStation", "platform": "Facebook"},
    {"icon": "IG", "handle": "@YourStation", "platform": "Instagram"},
    {"icon": "YT", "handle": "YourStation", "platform": "YouTube"}
]'
WHERE social_badges IS NULL;

-- Set default lower thirds presets for existing stations
UPDATE stations
SET lower_thirds_presets = '[
    {"name": "John Smith", "title": "News Anchor", "style": "modern"},
    {"name": "Jane Doe", "title": "Weather Reporter", "style": "bold"},
    {"name": "Alex Johnson", "title": "Sports Analyst", "style": "news"}
]'
WHERE lower_thirds_presets IS NULL;

-- Add index for faster queries (if not already exists)
CREATE INDEX IF NOT EXISTS idx_stations_display_updated ON stations(display_settings_updated_at);
