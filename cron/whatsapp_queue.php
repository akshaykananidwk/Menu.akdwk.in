<?php
/**
 * WhatsApp queue worker (CLI or HTTP cron).
 *
 * Drains pending whatsapp_logs oldest-first and dispatches each via
 * dispatchWhatsAppLog(), respecting:
 *   - getWaSetting('send_delay')  seconds slept between sends
 *   - getWaSetting('daily_limit') max messages sent per calendar day
 *
 * Retry policy: dispatchWhatsAppLog() increments retry_count and sets status to
 * 'sent' or 'failed'. This worker re-queues (status back to 'pending') any row
 * that failed while retry_count < 3, so it is retried on the next run. Rows with
 * retry_count >= 3 are left as 'failed'.
 *
 * Security: when invoked over HTTP the ?key= query param MUST match
 * getSetting('cron_secret'). CLI invocation is always allowed.
 *
 * Usage:
 *   CLI : php cron/whatsapp_queue.php
 *   HTTP: GET /cron/whatsapp_queue.php?key=THE_CRON_SECRET
 */
require_once dirname(__DIR__) . '/config/config.php';

$isCli = (PHP_SAPI === 'cli');

// ---- Access guard for HTTP invocation ---------------------------------------
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key    = $_GET['key'] ?? '';
    $secret = (string)getSetting('cron_secret', '');
    if ($secret === '' || !hash_equals($secret, (string)$key)) {
        http_response_code(403);
        echo "Forbidden: invalid cron key.\n";
        exit;
    }
    // Keep running even if the "poke" caller disconnected after 300ms.
    ignore_user_abort(true);
    @set_time_limit(180);
}

// ---- Single-worker lock: avoid overlapping runs double-sending a row --------
$__lockFp = @fopen(ROOT_PATH . '/temp/.wa_queue.lock', 'c');
if ($__lockFp && !flock($__lockFp, LOCK_EX | LOCK_NB)) {
    // Another worker is already draining the queue — let it finish.
    echo "Another worker is running.\n";
    exit;
}

/** Emit a line to CLI stdout / HTTP body. */
function out(string $line): void {
    echo $line . "\n";
}

// ---- Configuration ----------------------------------------------------------
$sendDelay  = max(0, (int)getWaSetting('send_delay', '3'));
$dailyLimit = max(0, (int)getWaSetting('daily_limit', '500'));

// How many have we already sent today? (Counts sent rows dated today.)
$sentToday = (int)db_val(
    "SELECT COUNT(*) FROM " . tbl('whatsapp_logs') . "
     WHERE status = 'sent' AND DATE(sent_at) = CURDATE()"
);

$remaining = $dailyLimit > 0 ? max(0, $dailyLimit - $sentToday) : PHP_INT_MAX;

out('[' . date('Y-m-d H:i:s') . '] WhatsApp queue worker starting.');
out("Sent today: $sentToday / " . ($dailyLimit ?: '∞') . " — remaining capacity: " . ($remaining === PHP_INT_MAX ? '∞' : $remaining));

if ($remaining <= 0) {
    out('Daily limit reached. Nothing sent.');
    exit;
}

if (getWaSetting('enabled', '1') !== '1') {
    out('WhatsApp sending is disabled in settings. Exiting.');
    exit;
}

// ---- Fetch pending rows, oldest-first ---------------------------------------
// Cap the batch to remaining daily capacity.
$batchLimit = $remaining === PHP_INT_MAX ? 200 : min(200, $remaining);
$pending = db_all(
    "SELECT id, retry_count FROM " . tbl('whatsapp_logs') . "
     WHERE status = 'pending'
     ORDER BY id ASC
     LIMIT " . (int)$batchLimit
);

out('Pending in this batch: ' . count($pending));

$sent = 0; $failed = 0; $requeued = 0;

foreach ($pending as $row) {
    if ($sent >= $remaining) {
        out('Reached daily capacity mid-batch. Stopping.');
        break;
    }

    $logId = (int)$row['id'];
    $ok = false;
    try {
        $ok = dispatchWhatsAppLog($logId, 20);
    } catch (Throwable $e) {
        error_log('cron whatsapp dispatch error (log ' . $logId . '): ' . $e->getMessage());
    }

    if ($ok) {
        $sent++;
    } else {
        // Re-read to inspect the (now incremented) retry_count.
        $fresh = db_one('SELECT retry_count FROM ' . tbl('whatsapp_logs') . ' WHERE id = :id', [':id' => $logId]);
        $retries = (int)($fresh['retry_count'] ?? 0);
        if ($retries < 3) {
            // Put it back in the queue for the next run.
            db_update('whatsapp_logs', ['status' => 'pending'], ['id' => $logId]);
            $requeued++;
        } else {
            // Give up — leave as 'failed'.
            $failed++;
        }
    }

    // Respect the inter-send delay (skip after the last item).
    if ($sendDelay > 0) { sleep($sendDelay); }
}

out('[' . date('Y-m-d H:i:s') . '] Done. Sent: ' . $sent . ', Re-queued: ' . $requeued . ', Permanently failed: ' . $failed . '.');
