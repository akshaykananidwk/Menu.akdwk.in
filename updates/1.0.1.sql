-- =============================================================================
-- AK Menu System — Migration 1.0.1  (SAMPLE)
-- =============================================================================
-- HOW MIGRATIONS WORK
--   * Migration files live in /updates and are named after the target version:
--       <version>.sql   (raw SQL, one statement per ";")
--       <version>.php   (arbitrary PHP; receives $pdo + tbl() helpers)
--   * The update runner (api/update.php → ak_run_migrations) scans this folder,
--     selects every file whose <version> is GREATER than the currently installed
--     app_version, and runs them in ascending version order.
--   * Each executed file is recorded in the `migrations` table by file_name, so
--     a migration NEVER runs twice (the unique key on file_name enforces this).
--   * Use `{PREFIX}` wherever a table name appears — the runner replaces it with
--     the live DB_PREFIX (e.g. `ak_`) before executing. Do NOT hard-code `ak_`.
--   * Keep every change ADDITIVE and idempotent (IF NOT EXISTS / IF EXISTS) so a
--     re-run or a partial failure cannot break an existing install.
-- =============================================================================

-- Sample additive change: give tenants an optional Instagram handle field.
ALTER TABLE `{PREFIX}tenants`
    ADD COLUMN IF NOT EXISTS `instagram_handle` VARCHAR(80) NULL AFTER `instagram_url`;
