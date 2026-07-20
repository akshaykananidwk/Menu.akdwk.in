<?php
/**
 * Orders API — MIXED auth.
 *   place    : PUBLIC (customer ordering, no login/csrf) — server re-prices from DB.
 *   feedback : PUBLIC (customer rating) — tenant resolved by slug.
 *   list     : client OR staff session — live orders for current tenant.
 *   status   : client OR staff session (csrf) — advance order status.
 * Contract: JSON {status,message,data}. Multi-tenant isolation everywhere.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

/** Decode a JSON request body into an array (fallback to $_POST). */
function jsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return $_POST;
}

/** Resolve the current tenant id for a client OR staff session; 0 if neither. */
function orderTenantId(): int {
    if (isClient() || isStaff()) { return (int)currentTenantId(); }
    return 0;
}

try {
    switch ($action) {

        // =================================================================
        // PLACE (PUBLIC) — no login, no csrf, but strictly validated.
        // =================================================================
        case 'place': {
            $body = jsonBody();
            $slug        = trim((string)($body['slug'] ?? ''));
            $tableToken  = trim((string)($body['table_token'] ?? ''));
            $staffId     = (int)($body['staff_id'] ?? 0);
            $custName    = trim((string)($body['customer_name'] ?? ''));
            $custMobile  = preg_replace('/[^0-9]/', '', (string)($body['customer_mobile'] ?? ''));
            $orderType   = in_array($body['order_type'] ?? '', ['dinein', 'takeaway', 'delivery'], true)
                           ? $body['order_type'] : 'dinein';
            $items       = $body['items'] ?? [];

            if (!is_array($items) || count($items) === 0) { jsonError('Your cart is empty.'); }
            if ($custName === '') { jsonError('Please enter your name.'); }

            // --- Resolve tenant server-side: prefer table token, else slug. ---
            $tenant = null; $table = null;
            if ($tableToken !== '') {
                $table = db_one('SELECT * FROM ' . tbl('tables') . ' WHERE qr_token = :q', [':q' => $tableToken]);
                if ($table) { $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $table['tenant_id']]); }
            }
            if (!$tenant && $slug !== '') {
                $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
            }
            if (!$tenant) { jsonError('Restaurant not found.', 404); }
            // A table token from another tenant must never be honoured.
            if ($table && (int)$table['tenant_id'] !== (int)$tenant['id']) { $table = null; }

            if ($tenant['status'] !== 'active') { jsonError('Ordering is currently unavailable.'); }
            if (($tenant['ordering_mode'] ?? 'view_only') === 'view_only') { jsonError('Online ordering is disabled.'); }

            $tid = (int)$tenant['id'];

            // --- Validate staff (optional; waiter of this tenant only). ---
            $validStaffId = null;
            if ($staffId > 0) {
                $st = db_one('SELECT id FROM ' . tbl('staff') . ' WHERE id = :i AND tenant_id = :t',
                    [':i' => $staffId, ':t' => $tid]);
                if ($st) { $validStaffId = (int)$st['id']; }
            }

            // --- Re-price every line from the DB (never trust client prices). ---
            $subtotal = 0.0;
            $lines = [];
            foreach ($items as $ci) {
                $itemId = (int)($ci['id'] ?? 0);
                if ($itemId <= 0) continue;
                $dbItem = db_one('SELECT * FROM ' . tbl('items') . '
                                  WHERE id = :i AND tenant_id = :t AND status = 1',
                    [':i' => $itemId, ':t' => $tid]);
                if (!$dbItem) { jsonError('One or more items are no longer available.'); }
                if ((int)$dbItem['is_available'] === 0) { jsonError('"' . $dbItem['name'] . '" is sold out.'); }

                // Base unit price = discount price when set, else regular price.
                $unit = ($dbItem['discount_price'] !== null && (float)$dbItem['discount_price'] > 0)
                        ? (float)$dbItem['discount_price'] : (float)$dbItem['price'];

                // Variant: replace base price with the DB variant price if matched.
                $variantLabel = trim((string)($ci['variant'] ?? ''));
                if ($variantLabel !== '') {
                    $v = db_one('SELECT label, price FROM ' . tbl('item_variants') . '
                                 WHERE item_id = :i AND label = :l', [':i' => $itemId, ':l' => $variantLabel]);
                    if ($v) { $unit = (float)$v['price']; $variantLabel = $v['label']; }
                    else { $variantLabel = ''; }
                }

                // Add-ons: re-read each chosen add-on price from the DB.
                $chosenAddons = [];
                if (!empty($ci['addons']) && is_array($ci['addons'])) {
                    foreach ($ci['addons'] as $ad) {
                        $adName = trim((string)($ad['name'] ?? ''));
                        if ($adName === '') continue;
                        $dbAd = db_one('SELECT name, price FROM ' . tbl('item_addons') . '
                                        WHERE item_id = :i AND name = :n', [':i' => $itemId, ':n' => $adName]);
                        if ($dbAd) {
                            $unit += (float)$dbAd['price'];
                            $chosenAddons[] = ['name' => $dbAd['name'], 'price' => (float)$dbAd['price']];
                        }
                    }
                }

                $qty = max(1, (int)($ci['qty'] ?? 1));
                $lineTotal = round($unit * $qty, 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'item_id'       => $itemId,
                    'item_name'     => $dbItem['name'],
                    'variant_label' => $variantLabel !== '' ? $variantLabel : null,
                    'addons_json'   => $chosenAddons ? json_encode($chosenAddons, JSON_UNESCAPED_UNICODE) : null,
                    'qty'           => $qty,
                    'price'         => round($unit, 2),
                    'total'         => $lineTotal,
                    'notes'         => trim((string)($ci['notes'] ?? '')) ?: null,
                ];
            }
            if (!$lines) { jsonError('No valid items in your order.'); }

            // --- Taxes & charges from tenant settings. ---
            $subtotal = round($subtotal, 2);
            $tax     = round($subtotal * ((float)$tenant['cgst'] + (float)$tenant['sgst']) / 100, 2);
            $service = round($subtotal * (float)$tenant['service_charge'] / 100, 2);
            $total   = round($subtotal + $tax + $service, 2);

            // --- Persist order + items. ---
            $orderNo = nextOrderNo($tid);
            $orderId = db_insert('orders', [
                'tenant_id'       => $tid,
                'order_no'        => $orderNo,
                'table_id'        => $table['id'] ?? null,
                'staff_id'        => $validStaffId,
                'customer_name'   => $custName,
                'customer_mobile' => $custMobile ?: null,
                'order_type'      => $orderType,
                'subtotal'        => $subtotal,
                'tax'             => $tax,
                'service_charge'  => $service,
                'discount'        => 0,
                'total'           => $total,
                'payment_mode'    => 'counter',
                'payment_status'  => 'pending',
                'status'          => 'new',
            ]);
            foreach ($lines as $ln) {
                $ln['order_id'] = $orderId;
                db_insert('order_items', $ln);
            }
            // Mark the table occupied for dine-in orders.
            if ($table && $orderType === 'dinein') {
                db_update('tables', ['status' => 'occupied'], ['id' => $table['id']]);
            }

            // --- Notify owner via WhatsApp (non-blocking, must not break the order). ---
            try {
                $notify = $tenant['whatsapp_no'] ?: $tenant['mobile'];
                if ($notify) {
                    $summary = implode(', ', array_map(fn($l) => $l['qty'] . '× ' . $l['item_name'], $lines));
                    sendWaTemplate('new_order', $notify, [
                        'order_no' => $orderNo,
                        'table_no' => $table['table_no'] ?? '-',
                        'items'    => $summary,
                        'total'    => money($total, $tenant['currency'] ?? null),
                    ], null, $tid);
                }
            } catch (Throwable $e) {
                error_log('new_order WA failed: ' . $e->getMessage());
            }

            jsonSuccess('Order placed successfully.', ['order_id' => $orderId, 'order_no' => $orderNo]);
            break;
        }

        // =================================================================
        // FEEDBACK (PUBLIC) — {order_id, slug, rating, comment?}
        // =================================================================
        case 'feedback': {
            $body   = jsonBody();
            $orderId = (int)($body['order_id'] ?? 0);
            $slug    = trim((string)($body['slug'] ?? ''));
            $rating  = (int)($body['rating'] ?? 0);
            $comment = trim((string)($body['comment'] ?? ''));

            if ($rating < 1 || $rating > 5) { jsonError('Please provide a rating between 1 and 5.'); }
            $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
            if (!$tenant) { jsonError('Restaurant not found.', 404); }
            $tid = (int)$tenant['id'];

            // Verify the order belongs to this tenant before storing feedback.
            $order = db_one('SELECT * FROM ' . tbl('orders') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $orderId, ':t' => $tid]);
            if (!$order) { jsonError('Order not found.', 404); }

            db_insert('feedback', [
                'tenant_id'     => $tid,
                'order_id'      => $orderId,
                'customer_name' => $order['customer_name'],
                'mobile'        => $order['customer_mobile'],
                'rating'        => $rating,
                'comment'       => $comment ?: null,
            ]);
            jsonSuccess('Thank you for your feedback!');
            break;
        }

        // =================================================================
        // LIST (client OR staff) — live orders for the current tenant.
        // =================================================================
        case 'list': {
            $tid = orderTenantId();
            if (!$tid) { jsonError('Unauthorized.', 401); }

            // Optional status filter; supports comma list (e.g. new,accepted,preparing).
            $statusFilter = trim((string)($_GET['status'] ?? ''));
            $params = [':t' => $tid];
            $where  = 'o.tenant_id = :t';
            if ($statusFilter !== '') {
                $allowed = ['new', 'accepted', 'preparing', 'ready', 'served', 'completed', 'cancelled'];
                $wanted  = array_values(array_intersect(array_map('trim', explode(',', $statusFilter)), $allowed));
                if ($wanted) {
                    $in = [];
                    foreach ($wanted as $k => $s) { $in[] = ":s$k"; $params[":s$k"] = $s; }
                    $where .= ' AND o.status IN (' . implode(',', $in) . ')';
                }
            }

            $orders = db_all('SELECT o.*, t.table_no, s.name AS staff_name
                              FROM ' . tbl('orders') . ' o
                              LEFT JOIN ' . tbl('tables') . ' t ON t.id = o.table_id
                              LEFT JOIN ' . tbl('staff') . ' s ON s.id = o.staff_id
                              WHERE ' . $where . '
                              ORDER BY o.id DESC LIMIT 300', $params);

            foreach ($orders as &$o) {
                $o['items'] = db_all('SELECT item_name, variant_label, addons_json, qty, price, total, notes
                                      FROM ' . tbl('order_items') . ' WHERE order_id = :o', [':o' => $o['id']]);
            }
            unset($o);
            jsonSuccess('', ['orders' => $orders]);
            break;
        }

        // =================================================================
        // STATUS (client OR staff, csrf) — {order_id, status}
        // =================================================================
        case 'status': {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
            $tid = orderTenantId();
            if (!$tid) { jsonError('Unauthorized.', 401); }

            $orderId = (int)($_POST['order_id'] ?? 0);
            $newStatus = trim((string)($_POST['status'] ?? ''));
            $allowed = ['new', 'accepted', 'preparing', 'ready', 'served', 'completed', 'cancelled'];
            if (!in_array($newStatus, $allowed, true)) { jsonError('Invalid status.'); }

            // Tenant isolation: the order must belong to the current tenant.
            $order = db_one('SELECT * FROM ' . tbl('orders') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $orderId, ':t' => $tid]);
            if (!$order) { jsonError('Order not found.', 404); }

            db_update('orders', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $orderId]);

            // Free the table once the order is fully done.
            if (in_array($newStatus, ['completed', 'cancelled'], true) && $order['table_id']) {
                db_update('tables', ['status' => 'free'], ['id' => $order['table_id']]);
            }

            // Customer WhatsApp notifications on key transitions (non-blocking).
            if (in_array($newStatus, ['ready', 'completed'], true) && !empty($order['customer_mobile'])) {
                try {
                    $tpl = $newStatus === 'ready' ? 'order_ready' : 'order_completed';
                    sendWaTemplate($tpl, $order['customer_mobile'], [
                        'order_no' => $order['order_no'],
                        'total'    => money($order['total']),
                    ], null, $tid);
                } catch (Throwable $e) {
                    error_log('status WA failed: ' . $e->getMessage());
                }
            }
            jsonSuccess('Order updated.', ['order_id' => $orderId, 'status' => $newStatus]);
            break;
        }

        // =================================================================
        // COLLECT (client OR staff, csrf) — POS payment collection.
        // {order_id, payment_mode, payment_status?, amount_received?}
        // Marks the order completed + records how payment was collected.
        // =================================================================
        case 'collect': {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
            $tid = orderTenantId();
            if (!$tid) { jsonError('Unauthorized.', 401); }

            $orderId = (int)($_POST['order_id'] ?? 0);
            $mode    = strtolower(trim((string)($_POST['payment_mode'] ?? '')));
            $allowedModes = ['cash', 'upi', 'card', 'bank', 'online', 'due'];
            if (!in_array($mode, $allowedModes, true)) { jsonError('Invalid payment mode.'); }

            // Tenant isolation: the order must belong to the current tenant.
            $order = db_one('SELECT * FROM ' . tbl('orders') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $orderId, ':t' => $tid]);
            if (!$order) { jsonError('Order not found.', 404); }

            // "Due" means collected later -> keep it pending; everything else is paid.
            $payStatus = ($mode === 'due') ? 'pending'
                       : (in_array($_POST['payment_status'] ?? '', ['pending', 'paid'], true) ? $_POST['payment_status'] : 'paid');
            // Store 'due' as an unpaid cash-style record so the mode column stays meaningful.
            $storeMode = ($mode === 'due') ? 'cash' : $mode;

            // Advance a still-open order to completed; leave already-final states as-is.
            $newStatus = in_array($order['status'], ['completed', 'cancelled'], true) ? $order['status'] : 'completed';

            db_update('orders', [
                'payment_mode'   => $storeMode,
                'payment_status' => $payStatus,
                'status'         => $newStatus,
                'updated_at'     => date('Y-m-d H:i:s'),
            ], ['id' => $orderId]);

            // Free the table once the order is completed.
            if ($newStatus === 'completed' && $order['table_id']) {
                db_update('tables', ['status' => 'free'], ['id' => $order['table_id']]);
            }

            // Fire order_completed only on the first transition into completed.
            if ($newStatus === 'completed' && $order['status'] !== 'completed' && !empty($order['customer_mobile'])) {
                try {
                    sendWaTemplate('order_completed', $order['customer_mobile'], [
                        'order_no' => $order['order_no'],
                        'total'    => money($order['total']),
                    ], null, $tid);
                } catch (Throwable $e) {
                    error_log('collect WA failed: ' . $e->getMessage());
                }
            }

            jsonSuccess('Payment recorded.', [
                'order_id' => $orderId, 'payment_mode' => $storeMode,
                'payment_status' => $payStatus, 'status' => $newStatus,
            ]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/order.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
