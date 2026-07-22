-- =============================================================================
-- AK Menu System — Migration 1.4.0  (AI Token Usage & Cost Metering)
-- =============================================================================
-- Self-contained. Idempotent. Uses `{PREFIX}`. Keeps the legacy ai_logs table.
-- ai_settings scalars are stored as rows in the existing `settings` table
-- (getSetting/setSetting) to avoid duplicating configuration storage.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}ai_usage_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `request_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `provider` VARCHAR(32) DEFAULT NULL,
  `model_name` VARCHAR(80) DEFAULT NULL,
  `input_tokens` INT DEFAULT 0,
  `output_tokens` INT DEFAULT 0,
  `thinking_tokens` INT DEFAULT 0,
  `cached_tokens` INT DEFAULT 0,
  `total_tokens` INT DEFAULT 0,
  `input_cost_usd` DECIMAL(14,10) DEFAULT 0,
  `output_cost_usd` DECIMAL(14,10) DEFAULT 0,
  `cached_cost_usd` DECIMAL(14,10) DEFAULT 0,
  `total_cost_usd` DECIMAL(14,10) DEFAULT 0,
  `total_cost_local` DECIMAL(14,4) DEFAULT 0,
  `key_owner` ENUM('platform','user') DEFAULT 'platform',
  `source` VARCHAR(40) DEFAULT NULL,
  `reference_id` VARCHAR(80) DEFAULT NULL,
  `status` ENUM('success','error','blocked','incomplete') DEFAULT 'success',
  `error_message` TEXT NULL,
  `response_time_ms` INT DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`created_at`),
  KEY `idx_created` (`created_at`),
  KEY `idx_owner_date` (`key_owner`,`created_at`),
  KEY `idx_status_date` (`status`,`created_at`),
  KEY `idx_user_status_date` (`user_id`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}ai_pricing_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider` VARCHAR(32) NOT NULL,
  `model_name` VARCHAR(80) NOT NULL,
  `input_per_million` DECIMAL(12,6) DEFAULT 0,
  `output_per_million` DECIMAL(12,6) DEFAULT 0,
  `cached_per_million` DECIMAL(12,6) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_provider_model` (`provider`,`model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}ai_usage_daily` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `usage_date` DATE NOT NULL,
  `calls` INT DEFAULT 0,
  `input_tokens` BIGINT DEFAULT 0,
  `output_tokens` BIGINT DEFAULT 0,
  `total_tokens` BIGINT DEFAULT 0,
  `cost_usd` DECIMAL(14,6) DEFAULT 0,
  `cost_local` DECIMAL(14,4) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_date` (`user_id`,`usage_date`),
  KEY `idx_daily_date` (`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed current Gemini model prices (USD per 1M tokens). Admin can edit anytime.
INSERT INTO `{PREFIX}ai_pricing_config`
  (`provider`,`model_name`,`input_per_million`,`output_per_million`,`cached_per_million`,`is_active`,`notes`,`updated_at`)
VALUES
  ('gemini','gemini-2.5-flash',0.30,2.50,0.075,1,'Seeded default — verify current price',NOW()),
  ('gemini','gemini-2.5-pro',1.25,10.00,0.3125,1,'Seeded default — verify current price',NOW()),
  ('gemini','gemini-2.0-flash',0.10,0.40,0.025,1,'Seeded default — verify current price',NOW()),
  ('gemini','gemini-1.5-flash',0.075,0.30,0.01875,1,'Seeded default — verify current price',NOW()),
  ('gemini','gemini-1.5-pro',1.25,5.00,0.3125,1,'Seeded default — verify current price',NOW())
ON DUPLICATE KEY UPDATE `model_name` = VALUES(`model_name`);
