-- Phase 9: Add station_type column to support TV/Radio separation
-- Run this migration to fix station creation

-- Add station_type column to stations table
ALTER TABLE stations
ADD COLUMN IF NOT EXISTS station_type ENUM('tv', 'radio', 'both') DEFAULT 'tv' COMMENT 'Type of station: TV only, Radio only, or Both' AFTER status;

-- Update existing stations to 'both' so they have full access
UPDATE stations SET station_type = 'both' WHERE station_type IS NULL OR station_type = '';

-- Verification
SELECT id, user_id, station_name, station_type, status FROM stations;
