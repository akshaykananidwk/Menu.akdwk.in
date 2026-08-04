<?php
/**
 * Centralized task scheduler.
 *
 * ONE server cron (cron/run.php, every minute) drives every background job from
 * here. To add a future job: write a cron_job_* handler that returns
 * ['ok'=>bool,'message'=>string], then add one line to cronRegistry(). No new
 * server cron is ever needed.
 *
 * Scheduling:
 *   - 'interval' (minutes)  → run every N minutes (e.g. the WhatsApp queue).
 *   - 'at_hour'  (0-23)     → run once a day at/after that hour.
 * Safety: per-job DB lock (atomic UPDATE) prevents overlapping runs; a stale
 * lock (older than CRON_LOCK_STALE minutes) is reclaimed. Every run is logged
 * to cron_logs with duration + message; handlers are wrapped in try/catch so one
 * failing job never blocks the others.
 */

if (!defined('CRON_LOCK_STALE')) { define('CRON_LOCK_STALE', 15); } // minutes

// =============================================================================
// REGISTRY — the single place every scheduled task is declared.
// =============================================================================
function cronRegistry(): array {
    return [
        [
            'key' => 'whatsapp_queue', 'title' => 'WhatsApp Message Queue',
            'interval' => 1, 'at_hour' => null, 'default_enabled' => 1,
            'handler' => 'cron_job_whatsapp_queue',
            'desc' => 'Dispatch pending WhatsApp messages (orders, OTP, reminders) every minute.',
        ],
        [
            'key' => 'expiry_reminder', 'title' => 'Subscription & Payment Reminders',
            'interval' => 0, 'at_hour' => 9, 'default_enabled' => 1,
            'handler' => 'cron_job_expiry_reminder',
            'desc' => 'Remind restaurants 7/3/1 days before their plan expires, and suspend expired ones.',
        ],
        [
            'key' => 'daily_summary', 'title' => 'Auto Reports (Daily Summary)',
            'interval' => 0, 'at_hour' => 21, 'default_enabled' => 1,
            'handler' => 'cron_job_daily_summary',
            'desc' => "Send each restaurant its day's orders, revenue and top item.",
        ],
        [
            'key' => 'ai_rollup', 'title' => 'AI Usage Rollup & Cleanup',
            'interval' => 0, 'at_hour' => 2, 'default_enabled' => 1,
            'handler' => 'cron_job_ai_rollup',
            'desc' => 'Roll AI usage into the daily table and purge old raw logs.',
        ],
        [
            'key' => 'check_update', 'title' => 'Software Update Check',
            'interval' => 720, 'at_hour' => null, 'default_enabled' => 1,
            'handler' => 'cron_job_check_update',
            'desc' => 'Check GitHub for a newer version (notify only — never installs).',
        ],
        [
            'key' => 'db_backup', 'title' => 'Database Backup',
            'interval' => 0, 'at_hour' => 3, 'default_enabled' => 0,
            'handler' => 'cron_job_db_backup',
            'desc' => 'Dump all tables to /backups nightly and keep the latest few. Off by default.',
        ],
    ];
}

/** Registry entry for a key, or null. */
function cronDef(string $key): ?array {
    foreach (cronRegistry() as $j) { if ($j['key'] === $key) { return $j; } }
    return null;
}

// =============================================================================
// ENGINE
// =============================================================================

/** Insert any newly-registered jobs; refresh title/schedule; keep admin state. */
function cronEnsureJobs(): void {
    foreach (cronRegistry() as $j) {
        $exists = db_val('SELECT job_key FROM ' . tbl('cron_jobs') . ' WHERE job_key = :k', [':k' => $j['key']]);
        if ($exists === null || $exists === false) {
            db_insert('cron_jobs', [
                'job_key'          => $j['key'],
                'title'            => $j['title'],
                'interval_minutes' => (int)$j['interval'],
                'at_hour'          => $j['at_hour'],
                'enabled'          => (int)$j['default_enabled'],
                'last_status'      => 'idle',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } else {
            // Keep enabled/state; only sync the definition fields.
            db_update('cron_jobs',
                ['title' => $j['title'], 'interval_minutes' => (int)$j['interval'], 'at_hour' => $j['at_hour']],
                ['job_key' => $j['key']]);
        }
    }
}

/** Compute the next scheduled run for a job definition from "now". */
function cronNextRun(array $def, ?int $now = null): string {
    $now = $now ?? time();
    if ($def['at_hour'] !== null) {
        $next = mktime((int)$def['at_hour'], 0, 0, (int)date('n', $now), (int)date('j', $now) + 1, (int)date('Y', $now));
        return date('Y-m-d H:i:s', $next);
    }
    $iv = max(1, (int)$def['interval']);
    return date('Y-m-d H:i:s', $now + $iv * 60);
}

/** Is a job row due to run right now? */
function cronIsDue(array $job, array $def, ?int $now = null): bool {
    $now = $now ?? time();
    if ((int)$job['enabled'] !== 1) { return false; }
    // Skip if a fresh lock is held (another run is in progress).
    if (!empty($job['locked_at']) && strtotime($job['locked_at']) > $now - CRON_LOCK_STALE * 60) { return false; }

    if ($def['at_hour'] !== null) {
        $ranToday = !empty($job['last_run_at']) && date('Y-m-d', strtotime($job['last_run_at'])) === date('Y-m-d', $now);
        return !$ranToday && (int)date('G', $now) >= (int)$def['at_hour'];
    }
    return empty($job['next_run_at']) || strtotime($job['next_run_at']) <= $now;
}

/** Atomically claim the lock. Returns true if THIS caller now owns it. */
function cronAcquireLock(string $key): bool {
    $stale = date('Y-m-d H:i:s', time() - CRON_LOCK_STALE * 60);
    $stmt = db_query('UPDATE ' . tbl('cron_jobs') . "
                      SET locked_at = :now, last_status = 'running'
                      WHERE job_key = :k AND (locked_at IS NULL OR locked_at < :stale)",
        [':now' => date('Y-m-d H:i:s'), ':k' => $key, ':stale' => $stale]);
    return $stmt->rowCount() === 1;
}

/**
 * Run one job. $force ignores the "is it due?" check (manual run) but still
 * respects the lock so it can never double-execute. Returns a result array.
 */
function cronRunJob(string $key, bool $force = false): array {
    $def = cronDef($key);
    $job = db_one('SELECT * FROM ' . tbl('cron_jobs') . ' WHERE job_key = :k', [':k' => $key]);
    if (!$def || !$job) { return ['ok' => false, 'skipped' => true, 'message' => 'Unknown job.']; }
    if (!function_exists($def['handler'])) { return ['ok' => false, 'skipped' => true, 'message' => 'Handler missing.']; }
    if (!$force && !cronIsDue($job, $def)) { return ['ok' => true, 'skipped' => true, 'message' => 'Not due.']; }

    if (!cronAcquireLock($key)) { return ['ok' => false, 'skipped' => true, 'message' => 'Already running (locked).']; }

    $logId = db_insert('cron_logs', ['job_key' => $key, 'started_at' => date('Y-m-d H:i:s'), 'status' => 'running']);
    $t0 = microtime(true);
    $ok = false; $msg = '';
    try {
        @set_time_limit(CRON_LOCK_STALE * 60);
        $res = call_user_func($def['handler']);
        $ok  = !empty($res['ok']);
        $msg = (string)($res['message'] ?? ($ok ? 'Done.' : 'Failed.'));
    } catch (Throwable $e) {
        $ok = false;
        $msg = 'Error: ' . $e->getMessage();
        error_log('cron ' . $key . ': ' . $e->getMessage());
    }
    $dur = (int)round((microtime(true) - $t0) * 1000);

    db_update('cron_jobs', [
        'last_run_at'      => date('Y-m-d H:i:s'),
        'last_status'      => $ok ? 'success' : 'failed',
        'last_duration_ms' => $dur,
        'last_message'     => mb_substr($msg, 0, 2000),
        'next_run_at'      => cronNextRun($def),
        'locked_at'        => null,
        'run_count'        => (int)$job['run_count'] + 1,
        'fail_count'       => (int)$job['fail_count'] + ($ok ? 0 : 1),
    ], ['job_key' => $key]);

    db_update('cron_logs', [
        'finished_at' => date('Y-m-d H:i:s'),
        'status'      => $ok ? 'success' : 'failed',
        'duration_ms' => $dur,
        'message'     => mb_substr($msg, 0, 2000),
    ], ['id' => $logId]);

    return ['ok' => $ok, 'skipped' => false, 'message' => $msg, 'duration_ms' => $dur];
}

/** The master tick: run every job that is due. Called by cron/run.php. */
function cronRunDue(): array {
    cronEnsureJobs();
    setSetting('cron_last_run', date('Y-m-d H:i:s'));
    $jobs = db_all('SELECT * FROM ' . tbl('cron_jobs'));
    $summary = [];
    foreach ($jobs as $job) {
        $def = cronDef($job['job_key']);
        if (!$def) { continue; } // de-registered job: leave its row, skip it
        if (!cronIsDue($job, $def)) { continue; }
        $r = cronRunJob($job['job_key']); // not forced (respects due + lock)
        if (empty($r['skipped'])) { $summary[] = $job['job_key'] . ': ' . ($r['ok'] ? 'ok' : 'FAIL'); }
    }
    return $summary;
}

/** Trim old cron_logs (keep the newest $keep per job is overkill; keep by age). */
function cronPruneLogs(int $days = 30): int {
    $stmt = db_query('DELETE FROM ' . tbl('cron_logs') . ' WHERE started_at < :c',
        [':c' => date('Y-m-d H:i:s', time() - $days * 86400)]);
    return $stmt->rowCount();
}

/** Health snapshot for the admin dashboard. */
function cronHealth(): array {
    $last = getSetting('cron_last_run', '');
    $ago  = $last ? (time() - strtotime($last)) : null;
    return [
        'last_run'      => $last,
        'seconds_ago'   => $ago,
        // The server cron should fire every minute; >5 min silence = misconfigured.
        'server_ok'     => $ago !== null && $ago <= 300,
        'failing'       => (int)db_val('SELECT COUNT(*) FROM ' . tbl('cron_jobs') . " WHERE enabled = 1 AND last_status = 'failed'"),
        'secret_set'    => trim((string)getSetting('cron_secret', '')) !== '',
    ];
}

// =============================================================================
// HANDLERS  (each returns ['ok'=>bool,'message'=>string]; may throw)
// =============================================================================

/** WhatsApp queue — dispatch pending messages. */
function cron_job_whatsapp_queue(): array {
    $delay = (int)getWaSetting('send_delay', 0);
    $n = processWhatsAppQueue(50, 15, $delay);
    return ['ok' => true, 'message' => "Dispatched {$n} message(s)."];
}

/** Plan expiry reminders (7/3/1 days) + auto-suspend expired tenants. */
function cron_job_expiry_reminder(): array {
    $today = new DateTime('today');
    $remind = 0; $expired = 0;
    $targets = [];
    foreach ([7, 3, 1] as $d) { $targets[$d] = (clone $today)->modify("+$d day")->format('Y-m-d'); }

    foreach (db_all('SELECT id, restaurant_name, owner_name, mobile, whatsapp_no, expiry_date
                     FROM ' . tbl('tenants') . " WHERE status = 'active' AND expiry_date IS NOT NULL") as $t) {
        $mobile = $t['mobile'] ?: $t['whatsapp_no'];
        if (!$mobile) { continue; }
        foreach ($targets as $days => $date) {
            if ($t['expiry_date'] === $date) {
                sendWaTemplate('plan_expiring', $mobile, [
                    'expiry_date' => $t['expiry_date'], 'days' => $days,
                    'name' => $t['owner_name'] ?: $t['restaurant_name'],
                ], null, (int)$t['id']);
                $remind++;
            }
        }
    }
    foreach (db_all('SELECT id, restaurant_name, owner_name, mobile, whatsapp_no, expiry_date
                     FROM ' . tbl('tenants') . " WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < :d",
                     [':d' => $today->format('Y-m-d')]) as $t) {
        db_update('tenants', ['status' => 'expired'], ['id' => (int)$t['id']]);
        $expired++;
        $mobile = $t['mobile'] ?: $t['whatsapp_no'];
        if ($mobile) {
            sendWaTemplate('plan_expired', $mobile, [
                'name' => $t['owner_name'] ?: $t['restaurant_name'], 'expiry_date' => $t['expiry_date'],
            ], null, (int)$t['id']);
        }
    }
    return ['ok' => true, 'message' => "Reminders: {$remind}, newly expired: {$expired}."];
}

/** Daily business summary to each active restaurant. */
function cron_job_daily_summary(): array {
    $today = date('Y-m-d');
    $sent = 0; $skipped = 0;
    foreach (db_all('SELECT id, restaurant_name, owner_name, mobile, whatsapp_no, currency
                     FROM ' . tbl('tenants') . " WHERE status = 'active'") as $t) {
        $tid = (int)$t['id'];
        $stats = db_one('SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS revenue FROM ' . tbl('orders') . "
                         WHERE tenant_id = :t AND DATE(created_at) = :d AND status <> 'cancelled'",
            [':t' => $tid, ':d' => $today]);
        $count = (int)($stats['cnt'] ?? 0);
        $revenue = (float)($stats['revenue'] ?? 0);
        $top = db_one('SELECT oi.item_name FROM ' . tbl('order_items') . ' oi
                       JOIN ' . tbl('orders') . " o ON o.id = oi.order_id
                       WHERE o.tenant_id = :t AND DATE(o.created_at) = :d AND o.status <> 'cancelled'
                       GROUP BY oi.item_name ORDER BY SUM(oi.qty) DESC LIMIT 1", [':t' => $tid, ':d' => $today]);
        $mobile = $t['mobile'] ?: $t['whatsapp_no'];
        if (!$mobile) { $skipped++; continue; }
        $currency = $t['currency'] ?: getSetting('currency', '₹');
        sendWaTemplate('daily_summary', $mobile, [
            'order_no' => $count,
            'total'    => $currency . number_format($revenue, 2),
            'items'    => $top['item_name'] ?? '—',
            'date'     => $today,
            'name'     => $t['owner_name'] ?: $t['restaurant_name'],
        ], null, $tid);
        $sent++;
    }
    return ['ok' => true, 'message' => "Summaries sent: {$sent}, skipped (no contact): {$skipped}."];
}

/** AI usage rollup + retention purge (trailing 35-day rebuild). */
function cron_job_ai_rollup(): array {
    $L = tbl('ai_usage_logs'); $D = tbl('ai_usage_daily');
    $done = 0;
    for ($i = 35; $i >= 1; $i--) {
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
    $retention = function_exists('aiRetentionDays') ? aiRetentionDays() : (int)getSetting('ai_log_retention_days', 180);
    $cutoff = date('Y-m-d 00:00:00', strtotime("-{$retention} days"));
    $del = db_query("DELETE FROM $L WHERE created_at < :c", [':c' => $cutoff])->rowCount();
    // Opportunistically trim our own history too.
    if (function_exists('cronPruneLogs')) { cronPruneLogs(30); }
    return ['ok' => true, 'message' => "Rolled up {$done} day(s); purged {$del} old AI log row(s)."];
}

/** Notify-only GitHub update check (never installs). */
function cron_job_check_update(): array {
    $current = (string)getSetting('app_version', defined('APP_VERSION') ? APP_VERSION : '');
    $owner = trim((string)getSetting('github_owner', ''));
    $repo  = trim((string)getSetting('github_repo', ''));
    if ($owner === '' || $repo === '') { return ['ok' => true, 'message' => 'GitHub repo not configured — skipped.']; }
    if (!function_exists('curl_init')) { return ['ok' => false, 'message' => 'cURL unavailable.']; }

    $token = (string)getSetting('github_token', '');
    if (strncmp($token, 'enc:', 4) === 0 && function_exists('openssl_decrypt')) {
        $key = hash('sha256', (defined('DB_PASS') ? DB_PASS : '') . '|' . (defined('DB_NAME') ? DB_NAME : '') . '|ak-menu-update', true);
        $raw = base64_decode(substr($token, 4), true);
        $token = ($raw !== false && strlen($raw) >= 17)
            ? (string)openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16)) : '';
    }
    $headers = ['User-Agent: AK-Menu-System', 'Accept: application/vnd.github+json'];
    if ($token !== '') { $headers[] = 'Authorization: token ' . $token; }
    $ch = curl_init('https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/releases/latest');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
    $resp = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($resp === false || $http < 200 || $http >= 300) { return ['ok' => false, 'message' => "GitHub API HTTP {$http}."]; }
    $rel = json_decode((string)$resp, true);
    $latest = ltrim(trim((string)($rel['tag_name'] ?? '')), 'vV');
    if ($latest === '') { return ['ok' => true, 'message' => 'No release tag found.']; }
    $newer = version_compare($latest, $current, '>');
    setSetting('update_available', $newer ? '1' : '0');
    return ['ok' => true, 'message' => $newer ? "Update available: v{$latest} (current v{$current})." : "Up to date (v{$latest})."];
}

/** Nightly database backup to /backups, keeping the newest few. Off by default. */
function cron_job_db_backup(): array {
    $dir = ROOT_PATH . '/backups';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_dir($dir) || !is_writable($dir)) { return ['ok' => false, 'message' => 'backups/ not writable.']; }
    global $pdo;
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    $file = $dir . '/db_' . date('Ymd_His') . '.sql';
    $fh = @fopen($file, 'w');
    if (!$fh) { return ['ok' => false, 'message' => 'Could not open backup file.']; }

    $rows = 0; $tables = 0;
    fwrite($fh, "-- AK Menu System backup " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $like = str_replace('_', '\\_', $prefix) . '%';
    foreach (db_all("SHOW TABLES LIKE '" . $like . "'") as $r) {
        $table = array_values($r)[0];
        $tables++;
        $create = db_one("SHOW CREATE TABLE `$table`");
        $createSql = $create['Create Table'] ?? ($create['Create View'] ?? '');
        fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n" . $createSql . ";\n\n");
        foreach (db_all("SELECT * FROM `$table`") as $row) {
            $cols = '`' . implode('`,`', array_keys($row)) . '`';
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            fwrite($fh, "INSERT INTO `$table` ($cols) VALUES (" . implode(',', $vals) . ");\n");
            $rows++;
        }
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);

    // Keep only the newest 7 backups.
    $files = glob($dir . '/db_*.sql') ?: [];
    rsort($files);
    foreach (array_slice($files, 7) as $old) { @unlink($old); }

    return ['ok' => true, 'message' => "Backed up {$tables} table(s), {$rows} row(s) → " . basename($file)];
}
