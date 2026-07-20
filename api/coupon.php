<?php
/**
 * Coupons API.
 *   Client actions (requireClient, csrf, tenant-isolated): coupon_save, coupon_delete, coupon_toggle.
 *   Public action: apply — validate a code for a slug + subtotal (no login/csrf).
 * Contract: JSON {status,message,data}.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/_coupon_lib.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

/** Read a JSON body (fallback to request params). */
function couponBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $d = json_decode($raw, true);
        if (is_array($d)) return $d;
    }
    return array_merge($_GET, $_POST);
}

try {
    // =================================================================
    // PUBLIC — apply a coupon (no login, no csrf, but validated).
    // {slug, code, subtotal}
    // =================================================================
    if ($action === 'apply') {
        $body     = couponBody();
        $slug     = trim((string)($body['slug'] ?? ''));
        $code     = (string)($body['code'] ?? '');
        $subtotal = (float)($body['subtotal'] ?? 0);

        $tenant = db_one('SELECT id, currency FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
        if (!$tenant) { jsonError('Restaurant not found.', 404); }

        $res = couponEvaluate((int)$tenant['id'], $code, $subtotal, $tenant['currency'] ?: '₹');
        if (!$res['valid']) {
            jsonSuccess('', ['valid' => false, 'message' => $res['message']]);
        }
        jsonSuccess('', [
            'valid'    => true,
            'discount' => $res['discount'],
            'type'     => $res['type'],
            'code'     => $res['code'],
            'message'  => $res['message'],
        ]);
    }

    // =================================================================
    // CLIENT actions — require login + csrf on POST.
    // =================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
    requireClient();
    $tid = (int)currentTenantId();

    switch ($action) {

        // Create or update a coupon.
        case 'coupon_save': {
            $id          = (int)($_POST['id'] ?? 0);
            $code        = strtoupper(trim((string)($_POST['code'] ?? '')));
            $type        = in_array($_POST['type'] ?? '', ['flat', 'percent'], true) ? $_POST['type'] : '';
            $value       = round((float)($_POST['value'] ?? 0), 2);
            $minOrder    = round((float)($_POST['min_order'] ?? 0), 2);
            $maxDiscount = round((float)($_POST['max_discount'] ?? 0), 2);
            $usageLimit  = max(0, (int)($_POST['usage_limit'] ?? 0));
            $expiry      = trim((string)($_POST['expiry_date'] ?? ''));
            $status      = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;

            if ($code === '' || !preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
                jsonError('Code must be 2–40 chars: letters, numbers, - or _.');
            }
            if ($type === '') { jsonError('Please choose a discount type.'); }
            if ($value <= 0) { jsonError('Discount value must be greater than 0.'); }
            if ($type === 'percent' && $value > 100) { jsonError('Percentage cannot exceed 100.'); }
            $expiryVal = ($expiry !== '' && date_create($expiry)) ? date('Y-m-d', strtotime($expiry)) : null;

            // Unique code per tenant.
            $dupe = db_one('SELECT id FROM ' . tbl('coupons') . ' WHERE tenant_id = :t AND code = :c AND id <> :id',
                [':t' => $tid, ':c' => $code, ':id' => $id]);
            if ($dupe) { jsonError('A coupon with this code already exists.'); }

            $data = [
                'code'         => $code,
                'type'         => $type,
                'value'        => $value,
                'min_order'    => $minOrder,
                'max_discount' => $maxDiscount,
                'usage_limit'  => $usageLimit,
                'expiry_date'  => $expiryVal,
                'status'       => $status,
            ];

            if ($id > 0) {
                $existing = db_one('SELECT id FROM ' . tbl('coupons') . ' WHERE id = :i AND tenant_id = :t',
                    [':i' => $id, ':t' => $tid]);
                if (!$existing) { jsonError('Coupon not found.', 404); }
                db_update('coupons', $data, ['id' => $id]);
                jsonSuccess('Coupon updated.', ['id' => $id]);
            } else {
                $data['tenant_id'] = $tid;
                $data['used_count'] = 0;
                $newId = db_insert('coupons', $data);
                logActivity('client', $tid, 'Created coupon ' . $code);
                jsonSuccess('Coupon created.', ['id' => $newId]);
            }
            break;
        }

        // Delete a coupon (tenant-isolated).
        case 'coupon_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $c  = db_one('SELECT code FROM ' . tbl('coupons') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            if (!$c) { jsonError('Coupon not found.', 404); }
            db_query('DELETE FROM ' . tbl('coupons') . ' WHERE id = :i AND tenant_id = :t', [':i' => $id, ':t' => $tid]);
            logActivity('client', $tid, 'Deleted coupon ' . $c['code']);
            jsonSuccess('Coupon deleted.');
            break;
        }

        // Enable/disable a coupon.
        case 'coupon_toggle': {
            $id = (int)($_POST['id'] ?? 0);
            $c  = db_one('SELECT status FROM ' . tbl('coupons') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            if (!$c) { jsonError('Coupon not found.', 404); }
            $new = (int)$c['status'] === 1 ? 0 : 1;
            db_update('coupons', ['status' => $new], ['id' => $id]);
            jsonSuccess($new ? 'Coupon enabled.' : 'Coupon disabled.', ['status' => $new]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/coupon.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
