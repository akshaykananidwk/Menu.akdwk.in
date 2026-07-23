-- =============================================================================
-- AK Menu System — Migration 1.6.0  (Waiter-call + Table reservations)
-- =============================================================================
-- Idempotent. New tables only (no ALTER). Both features are additive.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}service_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `table_id` INT DEFAULT NULL,
  `table_no` VARCHAR(20) DEFAULT NULL,
  `type` ENUM('call','bill','water','clean') DEFAULT 'call',
  `note` VARCHAR(160) DEFAULT NULL,
  `status` ENUM('pending','done') DEFAULT 'pending',
  `created_at` DATETIME NOT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}reservations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_mobile` VARCHAR(20) NOT NULL,
  `party_size` INT DEFAULT 2,
  `reserve_date` DATE NOT NULL,
  `reserve_time` TIME NOT NULL,
  `note` VARCHAR(200) DEFAULT NULL,
  `status` ENUM('pending','confirmed','seated','cancelled') DEFAULT 'pending',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_date` (`tenant_id`,`reserve_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
