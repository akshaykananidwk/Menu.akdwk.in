<?php
/**
 * AK Menu System — Cron: daily business summary to each active restaurant.
 *
 * For every active tenant, compute today's order count, revenue and top-selling
 * item, then send the 'daily_summary' WhatsApp template to the owner.
 * Intended to run once at end of day.
 *
 * Usage:
 *   Web: /cron/daily_summary.php?key=<cron_secret>
 *   CLI: php cron/daily_summary.php <cron_secret>
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

echo "AK Menu System — daily summary @ " . date('c') . "\n";

$today = date('Y-m-d');
$sent  = 0;
$skipped = 0;

$tenants = db_all('SELECT id, restaurant_name, owner_name, mobile, whatsapp_no, currency
                   FROM ' . tbl('tenants') . " WHERE status = 'active'");

foreach ($tenants as $t) {
    $tid = (int)$t['id'];

    // Count + revenue for orders created today (exclude cancelled).
    $stats = db_one('SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS revenue
                     FROM ' . tbl('orders') . "
                     WHERE tenant_id = :t AND DATE(created_at) = :d AND status <> 'cancelled'",
        [':t' => $tid, ':d' => $today]);

    $count   = (int)($stats['cnt'] ?? 0);
    $revenue = (float)($stats['revenue'] ?? 0);

    // Top-selling item today by quantity.
    $top = db_one('SELECT oi.name, SUM(oi.qty) AS qty
                   FROM ' . tbl('order_items') . ' oi
                   JOIN ' . tbl('orders') . " o ON o.id = oi.order_id
                   WHERE o.tenant_id = :t AND DATE(o.created_at) = :d AND o.status <> 'cancelled'
                   GROUP BY oi.name ORDER BY qty DESC LIMIT 1",
        [':t' => $tid, ':d' => $today]);
    $topItem = $top['name'] ?? '—';

    // Skip tenants with no owner contact.
    $mobile = $t['mobile'] ?: $t['whatsapp_no'];
    if (!$mobile) { $skipped++; continue; }

    $currency = $t['currency'] ?: getSetting('currency', '₹');

    sendWaTemplate('daily_summary', $mobile, [
        'order_no' => $count,                                   // {order_no} = order count
        'total'    => $currency . number_format($revenue, 2),   // {total} = revenue
        'items'    => $topItem,                                 // {items} = top item
        'date'     => $today,
        'name'     => $t['owner_name'] ?: $t['restaurant_name'],
    ], null, $tid);

    $sent++;
    echo "Summary → {$t['restaurant_name']}: {$count} orders, {$currency}" . number_format($revenue, 2) . ", top: {$topItem}\n";
}

logActivity('cron', null, "Daily summary cron: sent $sent, skipped $skipped.");
echo "Done. Sent: $sent, Skipped (no contact): $skipped.\n";
