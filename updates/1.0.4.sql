-- =============================================================================
-- AK Menu System — Migration 1.0.4
-- =============================================================================
-- PURPOSE
--   Widen orders.payment_mode from the old ENUM('cash','online','counter') to a
--   free VARCHAR so the POS can record Cash / UPI / Card / Bank / Online / Due.
--   MODIFY COLUMN is a safe, in-place widening of the existing enum: all current
--   values ('cash','online','counter') remain valid strings, no data is lost, and
--   re-running the statement is harmless (idempotent).
--
--   Use `{PREFIX}` for table names — the runner replaces it with the live
--   DB_PREFIX (e.g. `ak_`). Do NOT hard-code `ak_`.
-- =============================================================================

ALTER TABLE `{PREFIX}orders`
    MODIFY COLUMN `payment_mode` VARCHAR(20) NOT NULL DEFAULT 'cash';
