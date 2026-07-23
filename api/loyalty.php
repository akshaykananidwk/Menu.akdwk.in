<?php
/**
 * Loyalty API.
 *   balance (PUBLIC) : {slug|table, mobile, subtotal?} → a customer's point balance
 *                      + how much is redeemable on the current cart.
 *   save    (CLIENT) : upsert this restaurant's loyalty settings.
 *   members (CLIENT) : top members by balance for the owner dashboard.
 * 1 point = 1 currency unit. Redemption is always recomputed server-side at
 * checkout (api/order.php) — this endpoint is only a hint for the UI.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    // ---------------- PUBLIC: balance -------------------------------------
    if ($action === 'balance') {
        $slug   = trim((string)($_REQUEST['slug'] ?? ''));
        $token  = trim((string)($_REQUEST['table'] ?? ''));
        $mobile = preg_replace('/[^0-9]/', '', (string)($_REQUEST['mobile'] ?? ''));
        $tenant = null;
        if ($token !== '') {
            $row = db_one('SELECT tenant_id FROM ' . tbl('tables') . ' WHERE qr_token = :t', [':t' => $token]);
            if ($row) { $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $row['tenant_id']]); }
        } elseif ($slug !== '') {
            $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
        }
        if (!$tenant) { jsonError('Restaurant not found.', 404); }
        $tid  = (int)$tenant['id'];
        $cfg  = loyaltyConfig($tid);
        if (!$cfg['enabled']) { jsonSuccess('', ['enabled' => false]); }

        $balance  = ($mobile !== '') ? loyaltyBalance($tid, $mobile) : 0;
        $subtotal = max(0, (float)($_REQUEST['subtotal'] ?? 0));
        $redeemable = 0;
        if ($balance >= $cfg['min_redeem'] && $subtotal > 0) {
            $maxByPct   = (int)floor($subtotal * $cfg['max_redeem_pct'] / 100);
            $redeemable = (int)min($balance, $maxByPct);
        }
        jsonSuccess('', [
            'enabled'        => true,
            'balance'        => $balance,
            'min_redeem'     => $cfg['min_redeem'],
            'max_redeem_pct' => $cfg['max_redeem_pct'],
            'earn_percent'   => $cfg['earn_percent'],
            'redeemable'     => $redeemable,
            'can_redeem'     => $redeemable > 0,
        ]);
    }

    // ---------------- CLIENT-only below -----------------------------------
    requireClient();
    $tid = (int)currentTenantId();

    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
        $enabled     = ($_POST['enabled'] ?? '0') === '1' ? 1 : 0;
        $earn        = max(0, min(100, (float)($_POST['earn_percent'] ?? 5)));
        $minRedeem   = max(1, (int)($_POST['min_redeem'] ?? 50));
        $maxPct      = max(0, min(100, (float)($_POST['max_redeem_pct'] ?? 20)));

        $exists = db_val('SELECT tenant_id FROM ' . tbl('loyalty_settings') . ' WHERE tenant_id = :t', [':t' => $tid]);
        if ($exists !== null && $exists !== false) {
            db_update('loyalty_settings', [
                'enabled' => $enabled, 'earn_percent' => $earn,
                'min_redeem' => $minRedeem, 'max_redeem_pct' => $maxPct,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['tenant_id' => $tid]);
        } else {
            db_insert('loyalty_settings', [
                'tenant_id' => $tid, 'enabled' => $enabled, 'earn_percent' => $earn,
                'min_redeem' => $minRedeem, 'max_redeem_pct' => $maxPct,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        jsonSuccess('Loyalty settings saved.');
    }

    if ($action === 'members') {
        $rows = db_all("SELECT l.customer_mobile,
                               COALESCE(SUM(l.points),0) AS balance,
                               COALESCE(SUM(CASE WHEN l.points > 0 THEN l.points ELSE 0 END),0) AS earned,
                               COALESCE(-SUM(CASE WHEN l.points < 0 THEN l.points ELSE 0 END),0) AS redeemed,
                               MAX(l.created_at) AS last_activity,
                               (SELECT o.customer_name FROM " . tbl('orders') . " o
                                WHERE o.tenant_id = l.tenant_id AND o.customer_mobile = l.customer_mobile
                                ORDER BY o.id DESC LIMIT 1) AS customer_name
                        FROM " . tbl('loyalty_ledger') . " l
                        WHERE l.tenant_id = :t
                        GROUP BY l.customer_mobile
                        HAVING balance > 0 OR earned > 0
                        ORDER BY balance DESC
                        LIMIT 200", [':t' => $tid]);
        $totals = db_one("SELECT COUNT(DISTINCT customer_mobile) AS members,
                                 COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END),0) AS total_earned,
                                 COALESCE(SUM(points),0) AS outstanding
                          FROM " . tbl('loyalty_ledger') . " WHERE tenant_id = :t", [':t' => $tid]);
        jsonSuccess('', ['members' => $rows, 'totals' => $totals]);
    }

    jsonError('Unknown action.', 404);

} catch (Throwable $e) {
    error_log('api/loyalty.php: ' . $e->getMessage());
    jsonError('Server error.', 500);
}
