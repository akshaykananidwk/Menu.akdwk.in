<?php
/**
 * Subscription / plan-purchase API (client-side, tenant-isolated).
 * Contract: JSON {status,message,data}.
 *
 * Actions (all POST + requireClient + csrf):
 *   request_offline  {plan_id, txn_ref?, screenshot(file)?}
 *        Records a pending upgrade request paid by UPI/GPay, raises an unpaid
 *        invoice and pings the super admin on WhatsApp. Admin approves to activate.
 *   razorpay_order   {plan_id}
 *        Creates a Razorpay order (needs razorpay_key_id + secret) and returns
 *        the checkout params.
 *   razorpay_verify  {plan_id, razorpay_order_id, razorpay_payment_id, razorpay_signature}
 *        Verifies the payment signature, then activates the plan instantly and
 *        writes a paid invoice + approved request.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

requireClient();
csrfCheck();

$action   = $_GET['action'] ?? ($_POST['action'] ?? '');
$tenantId = (int)currentTenantId();
$tenant   = currentTenant();
if (!$tenant) { jsonError('Session expired.', 401); }

/** Raise an invoice for a plan purchase and return [invoiceId, invoiceNo, total]. */
function planInvoice(int $tenantId, array $plan, string $status): array {
    $amount = (float)$plan['price'];
    $rate   = defined('INVOICE_TAX_RATE') ? INVOICE_TAX_RATE : 0;
    $tax    = round($amount * $rate / 100, 2);
    $total  = $amount + $tax;
    // Launch/welcome offer: auto-discount if the tenant is within the offer window.
    $total  = applyLaunchDiscount($tenantId, $total);
    $seq    = (int)db_val('SELECT COUNT(*) FROM ' . tbl('invoices')) + 1;
    $invNo  = 'INV-' . date('Ym') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    $id = db_insert('invoices', [
        'tenant_id'  => $tenantId,
        'invoice_no' => $invNo,
        'plan_id'    => (int)$plan['id'],
        'amount'     => $amount,
        'tax'        => $tax,
        'total'      => $total,
        'status'     => $status,
        'paid_on'    => $status === 'paid' ? date('Y-m-d') : null,
    ]);
    return [$id, $invNo, $total];
}

/** Notify the super admin about a new subscription request via WhatsApp (best effort). */
function notifyAdminPlanRequest(array $tenant, array $plan, string $method, ?string $txn): void {
    $to = preg_replace('/\D/', '', (string)getWaSetting('super_admin_number',
          getWaSetting('support_number', getSetting('support_whatsapp', ''))));
    if ($to === '') return;
    $msg = "New plan request\n"
         . 'Restaurant: ' . $tenant['restaurant_name'] . " (ID {$tenant['id']})\n"
         . 'Plan: ' . $plan['name'] . ' (₹' . rtrim(rtrim(number_format((float)$plan['price'], 2), '0'), '.') . ")\n"
         . 'Method: ' . strtoupper($method) . "\n"
         . ($txn ? "Ref: $txn\n" : '')
         . 'Approve in Admin → Plan Requests.';
    sendWhatsApp($to, $msg, null, null, 'manual');
}

try {
    // =====================================================================
    // OFFLINE — client paid by UPI/GPay, awaits admin verification.
    // =====================================================================
    if ($action === 'request_offline') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $txn    = trim((string)($_POST['txn_ref'] ?? ''));
        $plan   = $planId ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id AND status = 1', [':id' => $planId]) : null;
        if (!$plan) { jsonError('Plan not found.'); }

        // Block duplicate spamming: one pending request per tenant at a time.
        $open = db_val('SELECT id FROM ' . tbl('plan_requests') . " WHERE tenant_id = :t AND status = 'pending' ORDER BY id DESC LIMIT 1", [':t' => $tenantId]);
        if ($open) { jsonError('You already have a request awaiting verification.'); }

        $shot = null;
        if (!empty($_FILES['screenshot']['name'])) {
            $shot = uploadImage($_FILES['screenshot'], 'payments');
        }

        [$invId, $invNo, $total] = planInvoice($tenantId, $plan, 'unpaid');

        db_insert('plan_requests', [
            'tenant_id'  => $tenantId,
            'plan_id'    => $planId,
            'amount'     => $total,
            'method'     => 'offline',
            'txn_ref'    => $txn ?: null,
            'screenshot' => $shot,
            'status'     => 'pending',
            'note'       => 'Invoice ' . $invNo,
        ]);

        logActivity('tenant', $tenantId, 'Requested ' . $plan['name'] . ' plan (offline / ' . $invNo . ')');
        notifyAdminPlanRequest($tenant, $plan, 'offline', $txn);

        jsonSuccess('Payment noted. Our team will verify and activate your plan shortly.');
    }

    // =====================================================================
    // ONLINE — create a Razorpay order.
    // =====================================================================
    if ($action === 'razorpay_order') {
        if (!razorpayEnabled()) { jsonError('Online payment is not available right now.'); }
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan   = $planId ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id AND status = 1', [':id' => $planId]) : null;
        if (!$plan) { jsonError('Plan not found.'); }

        $keyId   = trim((string)getSetting('razorpay_key_id', ''));
        $secret  = trim((string)getSetting('razorpay_secret', ''));
        $rate    = defined('INVOICE_TAX_RATE') ? INVOICE_TAX_RATE : 0;
        $amount  = (float)$plan['price'];
        $total   = applyLaunchDiscount($tenantId, $amount + round($amount * $rate / 100, 2));
        $paise   = (int)round($total * 100);
        if ($paise < 100) { jsonError('This plan is free — no payment needed. Contact support to activate.'); }

        $payload = json_encode([
            'amount'   => $paise,
            'currency' => 'INR',
            'receipt'  => 'plan_' . $tenantId . '_' . $planId,
            'notes'    => ['tenant_id' => (string)$tenantId, 'plan_id' => (string)$planId],
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERPWD        => $keyId . ':' . $secret,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $order = json_decode((string)$resp, true);
        if ($http < 200 || $http >= 300 || empty($order['id'])) {
            $err = $order['error']['description'] ?? ('Gateway error (HTTP ' . $http . ')');
            jsonError('Could not start online payment: ' . $err);
        }

        jsonSuccess('', [
            'key_id'    => $keyId,
            'order_id'  => $order['id'],
            'amount'    => $paise,
            'currency'  => 'INR',
            'name'      => getSetting('site_name', 'AK Menu System'),
            'plan_name' => $plan['name'],
            'prefill'   => [
                'name'    => $tenant['owner_name'] ?: $tenant['restaurant_name'],
                'email'   => $tenant['email'] ?: '',
                'contact' => $tenant['mobile'] ?: '',
            ],
        ]);
    }

    // =====================================================================
    // ONLINE — verify signature and activate.
    // =====================================================================
    if ($action === 'razorpay_verify') {
        if (!razorpayEnabled()) { jsonError('Online payment is not available right now.'); }
        $planId  = (int)($_POST['plan_id'] ?? 0);
        $orderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
        $payId   = trim((string)($_POST['razorpay_payment_id'] ?? ''));
        $sig     = trim((string)($_POST['razorpay_signature'] ?? ''));
        $plan    = $planId ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id AND status = 1', [':id' => $planId]) : null;
        if (!$plan) { jsonError('Plan not found.'); }
        if ($orderId === '' || $payId === '' || $sig === '') { jsonError('Incomplete payment response.'); }

        $secret   = trim((string)getSetting('razorpay_secret', ''));
        $expected = hash_hmac('sha256', $orderId . '|' . $payId, $secret);
        if (!hash_equals($expected, $sig)) {
            logActivity('tenant', $tenantId, 'Razorpay signature mismatch for plan ' . $plan['name']);
            jsonError('Payment could not be verified. If money was deducted, contact support.');
        }

        [$invId, $invNo, $total] = planInvoice($tenantId, $plan, 'paid');
        db_insert('plan_requests', [
            'tenant_id'    => $tenantId,
            'plan_id'      => $planId,
            'amount'       => $total,
            'method'       => 'online',
            'txn_ref'      => $payId,
            'status'       => 'approved',
            'note'         => 'Razorpay ' . $invNo,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
        activatePlanForTenant($tenantId, $planId);
        logActivity('tenant', $tenantId, 'Paid online for ' . $plan['name'] . ' (' . $payId . ')');

        jsonSuccess('Payment successful! Your plan is now active.');
    }

    jsonError('Unknown action.', 404);
} catch (Throwable $e) {
    error_log('api/plan.php: ' . $e->getMessage());
    jsonError('Something went wrong. Please try again.', 500);
}
