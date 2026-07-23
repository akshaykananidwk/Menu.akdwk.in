<?php
/**
 * Diner online payment (Razorpay), routed to the RESTAURANT's own keys.
 *   create_order (PUBLIC) : {slug|table, order_id} → a Razorpay order for the bill.
 *   verify       (PUBLIC) : {slug, order_id, razorpay_*} → verify signature, mark paid.
 *   save         (CLIENT) : upsert this restaurant's Razorpay keys.
 * The amount always comes from the stored order total (never the client). The
 * key secret never leaves the server. Failures never affect the placed order —
 * it simply stays payable at the counter.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

/** Resolve a tenant from slug or table token (public helper). */
function payResolveTenant(): ?array {
    $slug  = trim((string)($_REQUEST['slug'] ?? ''));
    $token = trim((string)($_REQUEST['table'] ?? ''));
    if ($token !== '') {
        $row = db_one('SELECT tenant_id FROM ' . tbl('tables') . ' WHERE qr_token = :t', [':t' => $token]);
        if ($row) { return db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $row['tenant_id']]); }
    }
    if ($slug !== '') { return db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]); }
    return null;
}

try {
    // ---------------- PUBLIC: create a Razorpay order ---------------------
    if ($action === 'create_order') {
        $tenant = payResolveTenant();
        if (!$tenant) { jsonError('Restaurant not found.', 404); }
        $tid = (int)$tenant['id'];
        $cfg = tenantPaymentConfig($tid);
        if (!$cfg['razorpay_enabled']) { jsonError('Online payment is not enabled.', 400); }

        $orderId = (int)($_REQUEST['order_id'] ?? 0);
        $order = db_one('SELECT * FROM ' . tbl('orders') . ' WHERE id = :i AND tenant_id = :t',
            [':i' => $orderId, ':t' => $tid]);
        if (!$order) { jsonError('Order not found.', 404); }
        if ($order['payment_status'] === 'paid') { jsonError('This order is already paid.', 400); }
        $paise = (int)round((float)$order['total'] * 100);
        if ($paise < 100) { jsonError('Nothing to pay online for this order.', 400); }

        $payload = json_encode([
            'amount'   => $paise,
            'currency' => 'INR',
            'receipt'  => 'order_' . $order['order_no'],
            'notes'    => ['tenant_id' => (string)$tid, 'order_id' => (string)$orderId],
        ]);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERPWD        => $cfg['key_id'] . ':' . $cfg['key_secret'],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $rzp = json_decode((string)$resp, true);
        if ($http < 200 || $http >= 300 || empty($rzp['id'])) {
            $err = $rzp['error']['description'] ?? ('Gateway error (HTTP ' . $http . ')');
            jsonError('Could not start online payment: ' . $err, 502);
        }

        jsonSuccess('', [
            'key_id'   => $cfg['key_id'],
            'order_id' => $rzp['id'],
            'amount'   => $paise,
            'currency' => 'INR',
            'name'     => $tenant['restaurant_name'],
            'prefill'  => [
                'name'    => $order['customer_name'] ?: '',
                'contact' => $order['customer_mobile'] ?: '',
            ],
        ]);
    }

    // ---------------- PUBLIC: verify + mark paid --------------------------
    if ($action === 'verify') {
        $tenant = payResolveTenant();
        if (!$tenant) { jsonError('Restaurant not found.', 404); }
        $tid = (int)$tenant['id'];
        $cfg = tenantPaymentConfig($tid);
        if (!$cfg['razorpay_enabled']) { jsonError('Online payment is not enabled.', 400); }

        $orderId = (int)($_REQUEST['order_id'] ?? 0);
        $rzpOrder = trim((string)($_REQUEST['razorpay_order_id'] ?? ''));
        $rzpPay   = trim((string)($_REQUEST['razorpay_payment_id'] ?? ''));
        $sig      = trim((string)($_REQUEST['razorpay_signature'] ?? ''));
        if ($orderId <= 0 || $rzpOrder === '' || $rzpPay === '' || $sig === '') {
            jsonError('Incomplete payment response.', 400);
        }
        $order = db_one('SELECT * FROM ' . tbl('orders') . ' WHERE id = :i AND tenant_id = :t',
            [':i' => $orderId, ':t' => $tid]);
        if (!$order) { jsonError('Order not found.', 404); }

        $expected = hash_hmac('sha256', $rzpOrder . '|' . $rzpPay, $cfg['key_secret']);
        if (!hash_equals($expected, $sig)) {
            jsonError('Payment could not be verified. If money was deducted, contact the restaurant.', 400);
        }
        if ($order['payment_status'] !== 'paid') {
            db_update('orders', [
                'payment_status' => 'paid',
                'payment_mode'   => 'online',
                'payment_ref'    => $rzpPay,
            ], ['id' => $orderId]);
        }
        jsonSuccess('Payment successful.', ['paid' => true]);
    }

    // ---------------- CLIENT: save keys -----------------------------------
    requireClient();
    $tid = (int)currentTenantId();

    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
        $enabled = ($_POST['razorpay_enabled'] ?? '0') === '1' ? 1 : 0;
        $keyId   = trim((string)($_POST['razorpay_key_id'] ?? ''));
        $secretIn = trim((string)($_POST['razorpay_key_secret'] ?? ''));

        // Keep the stored secret if the field was left blank (masked in the UI).
        $existing = db_one('SELECT * FROM ' . tbl('tenant_payment_settings') . ' WHERE tenant_id = :t', [':t' => $tid]);
        $secret = $secretIn !== '' ? $secretIn : (string)($existing['razorpay_key_secret'] ?? '');

        if ($enabled && ($keyId === '' || $secret === '')) {
            jsonError('Enter both the Key ID and Key Secret to enable online payment.');
        }
        $data = [
            'razorpay_enabled'    => $enabled,
            'razorpay_key_id'     => $keyId ?: null,
            'razorpay_key_secret' => $secret ?: null,
            'updated_at'          => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            db_update('tenant_payment_settings', $data, ['tenant_id' => $tid]);
        } else {
            $data['tenant_id'] = $tid;
            db_insert('tenant_payment_settings', $data);
        }
        jsonSuccess('Payment settings saved.');
    }

    jsonError('Unknown action.', 404);

} catch (Throwable $e) {
    error_log('api/pay.php: ' . $e->getMessage());
    jsonError('Server error.', 500);
}
