<?php
/**
 * Inbound WhatsApp webhook (PUBLIC — no login, no CSRF).
 *
 * The gateway (bulk.akdwk.in) POSTs inbound customer messages here. We:
 *   1. Verify the payload api_key matches getWaSetting('api_key') (else 403).
 *   2. Extract sender number + message text (liberal about field names).
 *   3. Store the message in whatsapp_inbox (best-effort tenant resolution by
 *      matching the sender's most recent order mobile).
 *   4. Auto-reply to simple keywords:
 *        MENU   -> the menu link of the customer's last-ordered tenant
 *        STATUS -> latest order status for that mobile
 *        HELP   -> support contact from getSetting('support_whatsapp')
 *      Unrecognised messages are stored only (no reply).
 *
 * Always returns jsonSuccess so the gateway does not retry endlessly.
 * Gateway credentials are never hardcoded — all reads go through getWaSetting().
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

// ---- Parse the incoming payload (JSON body or form-encoded) ------------------
$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '' && $raw !== false) {
    $json = json_decode($raw, true);
    if (is_array($json)) { $data = $json; }
}
// Merge form fields (some gateways post application/x-www-form-urlencoded).
if (!empty($_POST)) { $data = array_merge($data, $_POST); }
if (!empty($_GET))  { $data = array_merge($_GET, $data); }

/** Pick the first present, non-empty value among candidate keys. */
function pick(array $d, array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        if (isset($d[$k]) && trim((string)$d[$k]) !== '') { return trim((string)$d[$k]); }
    }
    return $default;
}

// ---- 1. Verify api_key -------------------------------------------------------
$incomingKey = pick($data, ['api_key', 'apikey', 'key', 'token']);
$expectedKey = (string)getWaSetting('api_key', '');
if ($expectedKey === '' || !hash_equals($expectedKey, $incomingKey)) {
    http_response_code(403);
    jsonError('Forbidden.', 403);
}

try {
    // ---- 2. Extract sender + message -----------------------------------------
    $number  = pick($data, ['number', 'from', 'sender', 'mobile', 'phone', 'msisdn']);
    $message = pick($data, ['message', 'text', 'body', 'msg', 'content']);

    if ($number === '') {
        // Nothing actionable — acknowledge so the gateway stops retrying.
        jsonSuccess('No sender number; ignored.');
    }

    $formatted = formatWaNumber($number) ?: $number;
    // Bare 10-digit form for matching against stored order mobiles.
    $bare = preg_replace('/\D/', '', $formatted);
    $last10 = substr($bare, -10);

    // ---- 3. Resolve tenant by the sender's most recent order -----------------
    $order = db_one(
        'SELECT o.tenant_id, o.order_no, o.status, o.total, t.slug, t.restaurant_name
         FROM ' . tbl('orders') . ' o
         JOIN ' . tbl('tenants') . ' t ON t.id = o.tenant_id
         WHERE o.customer_mobile LIKE :m
         ORDER BY o.id DESC LIMIT 1',
        [':m' => '%' . $last10]
    );
    $tenantId = $order ? (int)$order['tenant_id'] : null;

    // Store the inbound message.
    db_insert('whatsapp_inbox', [
        'tenant_id' => $tenantId,
        'number'    => $formatted,
        'message'   => $message,
        'is_read'   => 0,
    ]);

    // ---- 4. Auto-reply on recognised keywords --------------------------------
    $cmd = strtoupper(trim($message));

    if ($cmd === 'MENU') {
        if ($order) {
            $url = publicMenuUrl($order['slug']);
            sendWhatsApp($formatted, "Here is the menu for " . $order['restaurant_name'] . " 📖\n" . $url, null, $tenantId, 'inbound_reply', true);
        } else {
            sendWhatsApp($formatted, "We couldn't find a recent order for your number. Please scan the restaurant's QR code to view its menu.", null, null, 'inbound_reply', true);
        }
    } elseif ($cmd === 'STATUS') {
        if ($order) {
            sendWhatsApp($formatted, "Your latest order " . $order['order_no'] . " status: " . strtoupper($order['status']) . ".", null, $tenantId, 'inbound_reply', true);
        } else {
            sendWhatsApp($formatted, "No recent order found for your number.", null, null, 'inbound_reply', true);
        }
    } elseif ($cmd === 'HELP') {
        $support = getSetting('support_whatsapp', '');
        $msg = $support !== ''
            ? "Need help? Contact our support on WhatsApp: " . $support
            : "Need help? Please reply here and our team will assist you.";
        sendWhatsApp($formatted, $msg, null, $tenantId, 'inbound_reply', true);
    }
    // Unrecognised messages: stored only, no reply.

    jsonSuccess('Received.');
} catch (Throwable $e) {
    error_log('api/whatsapp_webhook.php error: ' . $e->getMessage());
    // Still acknowledge so the gateway does not hammer us with retries.
    jsonSuccess('Received.');
}
