-- Phase 8: Time-Based Jingles & Coin-Based Subscription System
-- Run this SQL in phpMyAdmin

-- =====================================================
-- PART 1: TIME-BASED JINGLES FOR TV & RADIO
-- =====================================================

-- Modify jingles table to support time-based intervals
ALTER TABLE jingles
MODIFY COLUMN play_frequency ENUM(
    'now',
    'every_1min',
    'every_2min',
    'every_5min',
    'every_15min',
    'every_30min',
    'every_hour',
    'every_video',
    'every_2_videos',
    'every_3_videos',
    'every_5_videos',
    'hourly',
    'custom'
) DEFAULT 'every_3_videos';

-- Modify stations table for time-based TV jingle intervals
ALTER TABLE stations
MODIFY COLUMN default_jingle_interval VARCHAR(20) DEFAULT 'every_5min',
MODIFY COLUMN default_advert_interval VARCHAR(20) DEFAULT 'every_15min';

-- Add time-based interval columns for radio (from phase6)
ALTER TABLE stations
MODIFY COLUMN radio_jingle_interval VARCHAR(20) DEFAULT 'every_5min' COMMENT 'Time-based interval for radio jingles';

ALTER TABLE stations
MODIFY COLUMN radio_advert_interval VARCHAR(20) DEFAULT 'every_15min' COMMENT 'Time-based interval for radio adverts';

-- =====================================================
-- PART 2: COIN-BASED SUBSCRIPTION SYSTEM
-- =====================================================

-- Add coins column to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS coins INT DEFAULT 0 COMMENT 'User coin balance' AFTER status,
ADD COLUMN IF NOT EXISTS coins_updated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Last coin update time' AFTER coins;

-- Create coin transactions table for history
CREATE TABLE IF NOT EXISTS coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL COMMENT 'Positive for credits, negative for debits',
    transaction_type ENUM('purchase', 'admin_credit', 'admin_debit', 'video_upload', 'storage_usage', 'streaming_usage', 'system') NOT NULL,
    description TEXT,
    balance_before INT NOT NULL,
    balance_after INT NOT NULL,
    created_by INT NULL COMMENT 'Admin user ID if manually added',
    reference VARCHAR(100) NULL COMMENT 'Payment reference or system reference',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_transactions (user_id, created_at),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create coin pricing configuration table
CREATE TABLE IF NOT EXISTS coin_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type ENUM('video_upload', 'storage_per_gb', 'streaming_per_hour', 'monthly_maintenance') NOT NULL,
    coins_required INT NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default coin pricing
INSERT INTO coin_pricing (action_type, coins_required, description) VALUES
('video_upload', 10, '10 coins per video upload'),
('storage_per_gb', 50, '50 coins per GB of storage per month'),
('streaming_per_hour', 5, '5 coins per hour of streaming'),
('monthly_maintenance', 100, '100 coins monthly maintenance fee (auto-deducted)')
ON DUPLICATE KEY UPDATE coins_required=VALUES(coins_required), description=VALUES(description);

-- Add last coin deduction tracking to stations
ALTER TABLE stations
ADD COLUMN IF NOT EXISTS last_coin_deduction TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time coins were deducted for this station';

-- Grant 1000 starter coins to all existing active users
UPDATE users
SET coins = 1000, coins_updated_at = NOW()
WHERE status = 'active' AND (coins IS NULL OR coins = 0);

-- =====================================================
-- PART 3: COIN USAGE TRACKING
-- =====================================================

-- Add storage size tracking to stations (for coin deduction)
ALTER TABLE stations
ADD COLUMN IF NOT EXISTS total_storage_used BIGINT DEFAULT 0 COMMENT 'Total storage in bytes' AFTER last_coin_deduction;

-- =====================================================
-- PART 4: UPDATE PAYMENTS TABLE FOR COIN PURCHASES
-- =====================================================

-- Add payment_type and description columns to payments table
ALTER TABLE payments
ADD COLUMN IF NOT EXISTS payment_type ENUM('subscription', 'coins') DEFAULT 'subscription' COMMENT 'Type of payment' AFTER status,
ADD COLUMN IF NOT EXISTS description TEXT COMMENT 'Payment description' AFTER payment_type;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================
-- Run these to verify:
-- SELECT * FROM coin_pricing;
-- SELECT id, email, coins FROM users WHERE status = 'active';
-- DESCRIBE coin_transactions;
-- DESCRIBE payments;
