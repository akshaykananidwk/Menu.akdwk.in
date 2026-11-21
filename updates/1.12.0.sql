-- =============================================================================
-- AK Menu System — Migration 1.12.0  (Plan coupons + manual payments on bills)
-- =============================================================================
-- Idempotent: CREATE TABLE IF NOT EXISTS only (no ADD COLUMN — see 1.11.0).
-- =============================================================================

-- Subscription coupon codes created by the super admin (percent or flat ₹).
CREATE TABLE IF NOT EXISTS `{PREFIX}plan_coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `type` ENUM('flat','percent') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `plan_id` INT UNSIGNED DEFAULT NULL,
  `usage_limit` INT NOT NULL DEFAULT 0,
  `used_count` INT NOT NULL DEFAULT 0,
  `once_per_client` TINYINT(1) NOT NULL DEFAULT 1,
  `expiry_date` DATE DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extra facts about a subscription invoice: list price, discount, coupon and
-- how it was paid (Razorpay / cash / UPI / bank ...). One row per invoice.
CREATE TABLE IF NOT EXISTS `{PREFIX}invoice_details` (
  `invoice_id` INT UNSIGNED NOT NULL,
  `list_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_label` VARCHAR(120) DEFAULT NULL,
  `coupon_code` VARCHAR(40) DEFAULT NULL,
  `payment_mode` VARCHAR(20) DEFAULT NULL,
  `payment_ref` VARCHAR(120) DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`invoice_id`),
  KEY `idx_invd_coupon` (`coupon_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
