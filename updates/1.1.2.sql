-- =============================================================================
-- AK Menu System — Migration 1.1.2
-- =============================================================================
-- PURPOSE
--   Refer & Earn: each restaurant gets a referral code to share. When a referred
--   restaurant activates (pays for) a plan, the referrer earns bonus days.
--     * tenants.referral_code  — this tenant's own shareable code
--     * tenants.referred_by    — the tenant id who referred this one (set once)
--     * new `referrals` table   — one row per referred restaurant + reward state
--
--   Idempotent (ADD COLUMN IF NOT EXISTS / CREATE TABLE IF NOT EXISTS). Uses
--   `{PREFIX}`; the runner replaces it with the live DB_PREFIX.
-- =============================================================================

ALTER TABLE `{PREFIX}tenants`
    ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(12) NULL AFTER `slug`;

ALTER TABLE `{PREFIX}tenants`
    ADD COLUMN IF NOT EXISTS `referred_by` INT UNSIGNED NULL AFTER `referral_code`;

CREATE TABLE IF NOT EXISTS `{PREFIX}referrals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT UNSIGNED NOT NULL,
  `referred_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','rewarded') NOT NULL DEFAULT 'pending',
  `reward_days` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rewarded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_referred` (`referred_id`),
  KEY `idx_ref_referrer` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
