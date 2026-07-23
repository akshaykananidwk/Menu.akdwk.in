-- =============================================================================
-- AK Menu System — Migration 1.8.0  (Master staff app: property code + reception)
-- =============================================================================
-- Idempotent. ALTER ... MODIFY re-applies the same enum safely on re-run.
-- Property codes live in their own table (no ADD COLUMN) for idempotency.
-- =============================================================================

ALTER TABLE `{PREFIX}staff` MODIFY `role` ENUM('waiter','kitchen','reception') NOT NULL DEFAULT 'waiter';

CREATE TABLE IF NOT EXISTS `{PREFIX}tenant_app_codes` (
  `tenant_id` INT NOT NULL,
  `property_code` VARCHAR(8) NOT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`tenant_id`),
  UNIQUE KEY `uq_property_code` (`property_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
