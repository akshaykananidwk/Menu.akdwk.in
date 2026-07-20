-- Migration 1.0.5 — menu view/scan analytics.
-- Aggregates public menu opens per restaurant per day (cheap at scale).
-- Runs automatically via the admin auto-migrator or the Update Now pipeline.
CREATE TABLE IF NOT EXISTS `{PREFIX}menu_views` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `view_date` DATE NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_date` (`tenant_id`, `view_date`),
  KEY `idx_view_date` (`view_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
