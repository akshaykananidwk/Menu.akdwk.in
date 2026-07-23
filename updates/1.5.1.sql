-- =============================================================================
-- AK Menu System — Migration 1.5.1  (Diner online payment — per-restaurant)
-- =============================================================================
-- Idempotent. Each restaurant's own Razorpay keys live here so diner payments
-- settle to the restaurant, not the platform. Own table → no ALTER on orders.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}tenant_payment_settings` (
  `tenant_id` INT NOT NULL,
  `razorpay_enabled` TINYINT(1) DEFAULT 0,
  `razorpay_key_id` VARCHAR(80) DEFAULT NULL,
  `razorpay_key_secret` VARCHAR(120) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
