<?php
/**
 * AK Menu System — MASTER CRON. Configure exactly ONE server cron:
 *
 *   * * * * * curl -s "https://menu.akdwk.in/cron/run.php?key=YOUR_CRON_SECRET" >/dev/null 2>&1
 *   (or CLI)  * * * * * php /path/to/cron/run.php YOUR_CRON_SECRET
 *
 * Every minute this runs all DUE background jobs from the centralized scheduler
 * (config/scheduler.php). Per-job locking prevents overlap; each job is logged.
 * Add future jobs in cronRegistry() — never add another server cron.
 */
require_once dirname(__DIR__) . '/config/config.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $provided = (string)($_GET['key'] ?? '');
    $secret   = (string)getSetting('cron_secret', '');
    if ($secret === '' || !hash_equals($secret, $provided)) {
        http_response_code(403); echo "Forbidden: invalid cron key.\n"; exit;
    }
} else {
    // CLI: optional secret as first arg (accepted but not required).
    $secret = (string)getSetting('cron_secret', '');
    $provided = (string)($argv[1] ?? '');
    if ($secret !== '' && $provided !== '' && !hash_equals($secret, $provided)) {
        fwrite(STDERR, "Invalid cron key.\n"); exit(1);
    }
}

@set_time_limit(300);
$t0 = microtime(true);
$summary = cronRunDue();
$ms = (int)round((microtime(true) - $t0) * 1000);

echo 'AK master cron @ ' . date('c') . " ({$ms}ms)\n";
echo $summary ? ('Ran: ' . implode(', ', $summary) . "\n") : "No jobs due.\n";
