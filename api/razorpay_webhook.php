<?php
/**
 * Razorpay webhook (PUBLIC — no login, no CSRF) for SUBSCRIPTION payments
 * made to the platform's own Razorpay account.
 *
 * Set up in Razorpay Dashboard → Settings → Webhooks:
 *   URL     : {BASE_URL}/api/razorpay_webhook.php
 *   Secret  : same value as Admin → Settings → Payments → Webhook Secret
 *   Events  : payment.captured, order.paid
 *
 * Why: the browser normally confirms the payment (api/plan.php razorpay_verify).
 * If the client closes the tab or loses internet right after paying, this
 * webhook still activates the plan, so money is never taken without activation.
 *
 * Security: the raw body is verified with HMAC-SHA256 against the webhook
 * secret (X-Razorpay-Signature). The tenant + plan come from the order notes
 * that only our server could have set (orders are created with our API secret).
 * A payment id is never applied twice (shared check with razorpay_verify).
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

function rzpHookReply(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['status' => $code < 300 ? 'success' : 'error', 'message' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rzpHookReply(200, 'Razorpay webhook endpoint is ready.');
}

$raw    = (string)file_get_contents('php://input');
$sig    = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
$secret = trim((string)getSetting('razorpay_webhook_secret', ''));

if ($secret === '') {
    error_log('razorpay_webhook: webhook secret not set in Admin → Settings → Payments');
    rzpHookReply(503, 'Webhook secret not configured.');
}
if ($sig === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
    rzpHookReply(401, 'Invalid signature.');
}

$event = json_decode($raw, true);
if (!is_array($event)) { rzpHookReply(400, 'Bad payload.'); }

$type = (string)($event['event'] ?? '');
if (!in_array($type, ['payment.captured', 'order.paid'], true)) {
    rzpHookReply(200, 'Ignored event: ' . $type);
}

$payment = $event['payload']['payment']['entity'] ?? [];
$order   = $event['payload']['order']['entity'] ?? [];
$payId   = (string)($payment['id'] ?? '');
$notes   = ($order['notes'] ?? null) ?: ($payment['notes'] ?? []);
$tenantId = (int)($notes['tenant_id'] ?? 0);
$planId   = (int)($notes['plan_id'] ?? 0);

// Only subscription orders carry plan_id; anything else is not ours to handle.
if ($payId === '' || $tenantId <= 0 || $planId <= 0) {
    rzpHookReply(200, 'Not a subscription payment.');
}
if (($payment['status'] ?? '') !== 'captured') {
    rzpHookReply(200, 'Payment not captured yet.');
}

try {
    $dup = db_val('SELECT id FROM ' . tbl('plan_requests') . " WHERE method = 'online' AND txn_ref = :p LIMIT 1", [':p' => $payId]);
    if ($dup) { rzpHookReply(200, 'Already processed.'); }

    $plan   = db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $planId]);
    $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
    if (!$plan || !$tenant) {
        error_log("razorpay_webhook: unknown tenant $tenantId / plan $planId for $payId");
        rzpHookReply(200, 'Unknown tenant or plan.');
    }

    $paid = ((int)($payment['amount'] ?? 0)) / 100;
    [$invId, $invNo, $total] = planInvoice($tenantId, $plan, 'paid', [
        'total'          => $paid,
        'list_total'     => (float)($notes['list_total'] ?? planGross($plan)),
        'discount_label' => !empty($notes['coupon']) ? 'Coupon ' . $notes['coupon'] : '',
        'coupon_code'    => $notes['coupon'] ?? null,
        'payment_mode'   => 'online',
        'payment_ref'    => $payId,
    ]);
    db_insert('plan_requests', [
        'tenant_id'    => $tenantId,
        'plan_id'      => $planId,
        'amount'       => $paid,
        'method'       => 'online',
        'txn_ref'      => $payId,
        'status'       => 'approved',
        'note'         => 'Razorpay webhook ' . $invNo,
        'processed_at' => date('Y-m-d H:i:s'),
    ]);
    activatePlanForTenant($tenantId, $planId);
    logActivity('tenant', $tenantId, 'Paid online for ' . $plan['name'] . ' (' . $payId . ', webhook)');
    notifyPlanPaid($tenantId, (int)$invId, $plan, (float)$total);

    rzpHookReply(200, 'Plan activated.');
} catch (Throwable $e) {
    error_log('razorpay_webhook: ' . $e->getMessage());
    rzpHookReply(500, 'Server error.'); // Razorpay will retry.
}
