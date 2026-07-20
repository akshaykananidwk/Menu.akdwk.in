-- =============================================================================
-- AK Menu System — Migration 1.0.6
-- =============================================================================
-- PURPOSE
--   Add the Discounts/Coupons feature:
--     * new `coupons` table (per-tenant promo codes: flat/percent, min order,
--       max-discount cap, usage limit, expiry).
--     * `orders.coupon_code` column to record the code applied to an order
--       (the existing `orders.discount` column stores the amount).
--
--   Every statement is idempotent (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT
--   EXISTS — supported on MariaDB and MySQL 8) so a re-run cannot break an install.
--   Use `{PREFIX}` for table names; the runner replaces it with the live DB_PREFIX.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `type` ENUM('flat','percent') NOT NULL DEFAULT 'flat',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `usage_limit` INT NOT NULL DEFAULT 0,
  `used_count` INT NOT NULL DEFAULT 0,
  `expiry_date` DATE DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`tenant_id`, `code`),
  KEY `idx_coupon_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `{PREFIX}orders`
    ADD COLUMN IF NOT EXISTS `coupon_code` VARCHAR(40) NULL AFTER `notes`;
