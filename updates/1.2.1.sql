-- =============================================================================
-- AK Menu System — Migration 1.2.1
-- =============================================================================
-- PURPOSE
--   Site-wide visitor analytics: how many people visit the whole platform each
--   day (deduped per device/day) and which pages they open most.
--     * new `site_visits` table — one row per (date, path); path='*' is the
--       site-wide daily aggregate. `views` = page loads, `visitors` = unique/day.
--
--   Idempotent (CREATE TABLE IF NOT EXISTS). Uses `{PREFIX}`; the runner
--   replaces it with the live DB_PREFIX.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `{PREFIX}site_visits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visit_date` DATE NOT NULL,
  `path` VARCHAR(190) NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `visitors` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_date_path` (`visit_date`,`path`),
  KEY `idx_sv_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
