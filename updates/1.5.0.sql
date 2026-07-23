-- =============================================================================
-- AK Menu System — Migration 1.5.0  (Loyalty Points & Rewards)
-- =============================================================================
-- Idempotent. Uses `{PREFIX}`. Per-restaurant loyalty config lives in its own
-- table (loyalty_settings) so the live order path never depends on an ALTER of
-- the orders/tenants tables. Points are an append-only ledger; balance = SUM.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}loyalty_settings` (
  `tenant_id` INT NOT NULL,
  `enabled` TINYINT(1) DEFAULT 0,
  `earn_percent` DECIMAL(5,2) DEFAULT 5.00,
  `min_redeem` INT DEFAULT 50,
  `max_redeem_pct` DECIMAL(5,2) DEFAULT 20.00,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}loyalty_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `customer_mobile` VARCHAR(20) NOT NULL,
  `order_id` INT DEFAULT NULL,
  `points` INT NOT NULL,
  `type` ENUM('earn','redeem','adjust') DEFAULT 'earn',
  `note` VARCHAR(160) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_mobile` (`tenant_id`,`customer_mobile`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
