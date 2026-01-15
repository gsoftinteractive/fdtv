-- Phase 10: Free 100 Coins for All Users + Unlimited Upload Size
-- This migration gives all existing users 100 free coins and removes file size limits
-- Date: 2026-01-15

-- Give all existing users 100 free coins (add to their current balance)
UPDATE users
SET coins = coins + 100,
    coins_updated_at = NOW()
WHERE coins < 100 OR coins IS NULL;

-- Record the free coin credit transaction for all users
INSERT INTO coin_transactions (user_id, amount, transaction_type, description, balance_before, balance_after, reference, created_at)
SELECT
    id as user_id,
    100 as amount,
    'system' as transaction_type,
    'Welcome bonus: 100 free coins for unlimited upload feature' as description,
    GREATEST(0, coins - 100) as balance_before,
    coins as balance_after,
    CONCAT('WELCOME_BONUS_', id) as reference,
    NOW() as created_at
FROM users;

-- Update coin_pricing to note that video_upload is now dynamic
-- The base price is still used as minimum, but actual cost depends on file size
UPDATE coin_pricing
SET description = 'Base cost for video upload (10 coins) + 5 coins per 100MB'
WHERE action_type = 'video_upload';
