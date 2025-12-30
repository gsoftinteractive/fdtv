-- Phase 7: Fix - Set default values with emojis (proper collation)
-- Run this in phpMyAdmin to complete the migration
-- NOTE: Requires utf8mb4 database/table collation

-- Ensure proper character set for this session
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Set default social badges for existing stations (with emojis)
UPDATE stations
SET social_badges = CAST('[
    {"icon": "𝕏", "handle": "@YourStation", "platform": "Twitter"},
    {"icon": "📘", "handle": "/YourStation", "platform": "Facebook"},
    {"icon": "📷", "handle": "@YourStation", "platform": "Instagram"},
    {"icon": "▶️", "handle": "YourStation", "platform": "YouTube"}
]' AS JSON)
WHERE social_badges IS NULL;

-- Set default lower thirds presets for existing stations
UPDATE stations
SET lower_thirds_presets = CAST('[
    {"name": "John Smith", "title": "News Anchor", "style": "modern"},
    {"name": "Jane Doe", "title": "Weather Reporter", "style": "bold"},
    {"name": "Alex Johnson", "title": "Sports Analyst", "style": "news"}
]' AS JSON)
WHERE lower_thirds_presets IS NULL;

-- Add index for faster queries (if not already exists)
CREATE INDEX IF NOT EXISTS idx_stations_display_updated ON stations(display_settings_updated_at);
