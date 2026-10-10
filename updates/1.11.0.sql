-- =============================================================================
-- AK Menu System — Migration 1.11.0  (Email engine: SMTP, welcome mail,
--                                     forgot-password links, email OTP login)
-- =============================================================================
-- Idempotent: CREATE TABLE IF NOT EXISTS + ALTER ... MODIFY only.
-- (Never ADD COLUMN here — MySQL has no ADD COLUMN IF NOT EXISTS, and the
--  migration runner executes every statement of a file in one pass.)
-- =============================================================================

-- Outbound email queue + delivery log. Mirrors whatsapp_logs so the same
-- "queue now, send from the master cron" pattern applies to email.
CREATE TABLE IF NOT EXISTS `{PREFIX}email_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `to_email` VARCHAR(190) NOT NULL,
  `to_name` VARCHAR(120) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` MEDIUMTEXT NULL,
  `trigger_key` VARCHAR(60) DEFAULT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `response` TEXT NULL,
  `retry_count` INT DEFAULT 0,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_status` (`status`,`id`),
  KEY `idx_email_to` (`to_email`),
  KEY `idx_email_tenant` (`tenant_id`,`id`),
  KEY `idx_email_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-use password-reset links. Only the SHA-256 of the token is stored,
-- so a database leak cannot be replayed into account takeover.
CREATE TABLE IF NOT EXISTS `{PREFIX}password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` VARCHAR(20) NOT NULL DEFAULT 'tenant',
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `email` VARCHAR(190) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token_hash`),
  KEY `idx_reset_email` (`email`,`id`),
  KEY `idx_reset_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP identifiers can now be an e-mail address, not just a 10-digit mobile.
-- The column keeps its historic name; it holds "mobile OR email" from 1.11.0.
ALTER TABLE `{PREFIX}otp_verifications` MODIFY `mobile` VARCHAR(190) NOT NULL;

-- Make e-mail a real login identifier: no two restaurants may share one.
-- (Legacy rows with NULL e-mail are unaffected — MySQL allows many NULLs in a
--  UNIQUE index. Added as a named index so a re-run is a harmless no-op error.)
ALTER TABLE `{PREFIX}tenants` MODIFY `email` VARCHAR(190) DEFAULT NULL;
