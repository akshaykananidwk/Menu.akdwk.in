-- =============================================================================
-- AK Menu System — Migration 1.9.1  (Centralized cron / scheduler)
-- =============================================================================
-- Idempotent. One master cron runs every minute and drives every job from here.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}cron_jobs` (
  `job_key` VARCHAR(60) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `interval_minutes` INT DEFAULT 0,
  `at_hour` TINYINT DEFAULT NULL,
  `enabled` TINYINT(1) DEFAULT 1,
  `last_run_at` DATETIME DEFAULT NULL,
  `last_status` VARCHAR(16) DEFAULT 'idle',
  `last_duration_ms` INT DEFAULT 0,
  `last_message` TEXT NULL,
  `next_run_at` DATETIME DEFAULT NULL,
  `locked_at` DATETIME DEFAULT NULL,
  `run_count` INT DEFAULT 0,
  `fail_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`job_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}cron_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_key` VARCHAR(60) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME DEFAULT NULL,
  `status` VARCHAR(16) DEFAULT 'running',
  `duration_ms` INT DEFAULT 0,
  `message` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job` (`job_key`,`id`),
  KEY `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
