-- =============================================================================
-- AK Menu System — Migration 1.0.8
-- =============================================================================
-- PURPOSE
--   Client-facing subscription upgrades with payment.
--     * new `plan_requests` table: a client's request to buy/renew a plan,
--       paid offline (UPI/GPay + screenshot) or online (Razorpay). Super admin
--       approves offline requests; online payments auto-activate.
--
--   Idempotent (CREATE TABLE IF NOT EXISTS). Uses `{PREFIX}`; the runner
--   replaces it with the live DB_PREFIX.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}plan_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `method` ENUM('offline','online') NOT NULL DEFAULT 'offline',
  `txn_ref` VARCHAR(120) DEFAULT NULL,
  `screenshot` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pr_tenant` (`tenant_id`),
  KEY `idx_pr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
