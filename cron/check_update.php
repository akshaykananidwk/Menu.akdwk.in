<?php
/**
 * Backward-compatible wrapper. The real logic now lives in the centralized
 * scheduler (config/scheduler.php, job 'check_update'). Prefer the single master cron:
 *   * * * * * curl -s "https://menu.akdwk.in/cron/run.php?key=YOUR_CRON_SECRET"
 * This standalone entry still works if an old server cron points here.
 */
require_once dirname(__DIR__) . '/config/config.php';
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    $secret = (string)getSetting('cron_secret', '');
    if ($secret === '' || !hash_equals($secret, (string)($_GET['key'] ?? ''))) {
        http_response_code(403); echo "Forbidden: invalid cron key.\n"; exit;
    }
}
@set_time_limit(600);
cronEnsureJobs();
$r = cronRunJob('check_update', true);
echo "check_update: " . ($r['message'] ?? '') . "\n";
