<?php
/**
 * Table service requests (waiter call / bill / water / cleaning).
 *   request (PUBLIC)        : {slug|table, type, note?} — a diner asks for service.
 *   list    (client|staff)  : pending requests for the current tenant.
 *   done    (client|staff)  : mark a request resolved.
 * Additive; independent of the order flow.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$TYPES  = ['call', 'bill', 'water', 'clean'];

try {
    // ---------------- PUBLIC: raise a request ------------------------------
    if ($action === 'request') {
        $slug  = trim((string)($_REQUEST['slug'] ?? ''));
        $token = trim((string)($_REQUEST['table'] ?? ''));
        $type  = in_array(($_REQUEST['type'] ?? ''), $TYPES, true) ? $_REQUEST['type'] : 'call';
        $note  = mb_substr(trim((string)($_REQUEST['note'] ?? '')), 0, 160);

        $tenant = null; $table = null;
        if ($token !== '') {
            $table = db_one('SELECT * FROM ' . tbl('tables') . ' WHERE qr_token = :t', [':t' => $token]);
            if ($table) { $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $table['tenant_id']]); }
        } elseif ($slug !== '') {
            $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
        }
        if (!$tenant) { jsonError('Restaurant not found.', 404); }
        if ($tenant['status'] !== 'active') { jsonError('Service is unavailable right now.', 503); }
        $tid = (int)$tenant['id'];
        if ($table && (int)$table['tenant_id'] !== $tid) { $table = null; }

        // Simple anti-spam: one request per 15s per browser session.
        $now = time();
        if (($now - (int)($_SESSION['svc_last'] ?? 0)) < 15) {
            jsonSuccess('', ['queued' => true, 'message' => 'Your request is on the way.']);
        }
        $_SESSION['svc_last'] = $now;

        db_insert('service_requests', [
            'tenant_id'  => $tid,
            'table_id'   => $table['id'] ?? null,
            'table_no'   => $table['table_no'] ?? null,
            'type'       => $type,
            'note'       => $note !== '' ? $note : null,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        jsonSuccess('', ['ok' => true]);
    }

    // ---------------- client OR staff below --------------------------------
    if (!(isClient() || isStaff())) { jsonError('Unauthorized.', 401); }
    $tid = (int)currentTenantId();

    if ($action === 'list') {
        $rows = db_all("SELECT * FROM " . tbl('service_requests') . "
                        WHERE tenant_id = :t AND status = 'pending'
                        ORDER BY id ASC LIMIT 100", [':t' => $tid]);
        jsonSuccess('', ['requests' => $rows]);
    }

    if ($action === 'done') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
        $id = (int)($_POST['id'] ?? 0);
        db_query("UPDATE " . tbl('service_requests') . " SET status = 'done', resolved_at = :r
                  WHERE id = :i AND tenant_id = :t",
            [':r' => date('Y-m-d H:i:s'), ':i' => $id, ':t' => $tid]);
        jsonSuccess('Done.');
    }

    jsonError('Unknown action.', 404);

} catch (Throwable $e) {
    error_log('api/service.php: ' . $e->getMessage());
    jsonError('Server error.', 500);
}
