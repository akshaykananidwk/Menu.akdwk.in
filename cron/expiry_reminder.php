<?php
/**
 * AK Menu System — Cron: plan expiry reminders + auto-expire.
 *
 * 1. Tenants whose plan expires in exactly 7 / 3 / 1 days → WhatsApp reminder
 *    using the 'plan_expiring' template.
 * 2. Active tenants already past their expiry_date → mark status='expired' and
 *    send the 'plan_expired' template.
 *
 * Usage:
 *   Web: /cron/expiry_reminder.php?key=<cron_secret>
 *   CLI: php cron/expiry_reminder.php <cron_secret>
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');

// -----------------------------------------------------------------------------
// Cron authentication.
// -----------------------------------------------------------------------------
$provided = $_GET['key'] ?? ($argv[1] ?? '');
$secret   = (string)getSetting('cron_secret', '');
if ($secret === '' || !is_string($provided) || !hash_equals($secret, (string)$provided)) {
    http_response_code(403);
    echo "Forbidden: invalid or missing cron key.\n";
    exit;
}

echo "AK Menu System — expiry reminders @ " . date('c') . "\n";

$today = new DateTime('today');
$remindSent = 0;
$expiredSet = 0;

// -----------------------------------------------------------------------------
// 1) Expiring soon (7/3/1 days out) — reminders.
// -----------------------------------------------------------------------------
$windows = [7, 3, 1];
$targets = [];
foreach ($windows as $d) {
    $targets[$d] = (clone $today)->modify("+$d day")->format('Y-m-d');
}

$active = db_all('SELECT id, restaurant_name, owner_name, mobile, whatsapp_no, expiry_date
                  FROM ' . tbl('tenants') . "
                  WHERE status = 'active' AND expiry_date IS NOT NULL");

foreach ($active as $t) {
    $mobile = $t['mobile'] ?: $t['whatsapp_no'];
    if (!$mobile) { continue; }

    foreach ($targets as $days => $date) {
        if ($t['expiry_date'] === $date) {
            sendWaTemplate('plan_expiring', $mobile, [
                'expiry_date' => $t['expiry_date'],
                'days'        => $days,
                'name'        => $t['owner_name'] ?: $t['restaurant_name'],
            ], null, (int)$t['id']);
            $remindSent++;
            echo "Reminder ({$days}d) → {$t['restaurant_name']} ({$t['expiry_date']})\n";
        }
    }
}

// -----------------------------------------------------------------------------
// 2) Already expired active tenants → suspend + notify.
// -----------------------------------------------------------------------------
$expired = db_all('SELECT id, restaurant_name, owner_name, mobile, whatsapp_no, expiry_date
                   FROM ' . tbl('tenants') . "
                   WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < :today",
    [':today' => $today->format('Y-m-d')]);

foreach ($expired as $t) {
    db_update('tenants', ['status' => 'expired'], ['id' => (int)$t['id']]);
    $expiredSet++;

    $mobile = $t['mobile'] ?: $t['whatsapp_no'];
    if ($mobile) {
        sendWaTemplate('plan_expired', $mobile, [
            'name'        => $t['owner_name'] ?: $t['restaurant_name'],
            'expiry_date' => $t['expiry_date'],
        ], null, (int)$t['id']);
    }
    echo "Expired → {$t['restaurant_name']} (was due {$t['expiry_date']})\n";
}

logActivity('cron', null, "Expiry cron: $remindSent reminder(s), $expiredSet expired.");
echo "Done. Reminders: $remindSent, Newly expired: $expiredSet.\n";
