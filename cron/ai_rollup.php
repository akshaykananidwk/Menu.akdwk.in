<?php
/**
 * Nightly AI-usage maintenance (CLI or HTTP cron).
 *   1. Roll up ai_usage_logs into ai_usage_daily (per user, per day) — dashboards
 *      read the rollup for anything older than today; only today comes from raw.
 *   2. Delete ai_usage_logs older than ai_log_retention_days.
 *
 * Security: over HTTP the ?key= must match getSetting('cron_secret'). CLI always OK.
 * Usage:
 *   CLI : php cron/ai_rollup.php
 *   HTTP: GET /cron/ai_rollup.php?key=THE_CRON_SECRET
 * Schedule: once a day (e.g. 0 2 * * *).
 */
require_once dirname(__DIR__) . '/config/config.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $secret = (string)getSetting('cron_secret', '');
    if ($secret === '' || !hash_equals($secret, (string)($_GET['key'] ?? ''))) {
        http_response_code(403); echo "Forbidden: invalid cron key.\n"; exit;
    }
    @set_time_limit(600);
}

$L = tbl('ai_usage_logs');
$D = tbl('ai_usage_daily');
$done = 0; $deleted = 0;

try {
    // Roll up every day from the last rolled-up date up to (but not including) today.
    // Re-computing the last few days is cheap and self-heals any gaps.
    $sinceDays = 35; // rebuild the trailing window each run (idempotent upsert)
    for ($i = $sinceDays; $i >= 1; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        db_query("INSERT INTO $D (user_id, usage_date, calls, input_tokens, output_tokens, total_tokens, cost_usd, cost_local)
                  SELECT user_id, DATE(created_at), COUNT(*), COALESCE(SUM(input_tokens),0), COALESCE(SUM(output_tokens),0),
                         COALESCE(SUM(total_tokens),0), COALESCE(SUM(total_cost_usd),0), COALESCE(SUM(total_cost_local),0)
                  FROM $L WHERE DATE(created_at) = :d GROUP BY user_id
                  ON DUPLICATE KEY UPDATE calls=VALUES(calls), input_tokens=VALUES(input_tokens),
                    output_tokens=VALUES(output_tokens), total_tokens=VALUES(total_tokens),
                    cost_usd=VALUES(cost_usd), cost_local=VALUES(cost_local)", [':d' => $d]);
        $done++;
    }

    // Retention: drop raw logs older than the configured window (rollups are kept).
    $retention = function_exists('aiRetentionDays') ? aiRetentionDays() : (int)getSetting('ai_log_retention_days', 180);
    $cutoff = date('Y-m-d 00:00:00', strtotime("-{$retention} days"));
    $stmt = db_query("DELETE FROM $L WHERE created_at < :c", [':c' => $cutoff]);
    $deleted = $stmt->rowCount();
} catch (Throwable $e) {
    error_log('ai_rollup: ' . $e->getMessage());
    echo "ai_rollup error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "AI rollup complete: {$done} day(s) rolled up, {$deleted} old log row(s) purged (retention {$retention}d).\n";
