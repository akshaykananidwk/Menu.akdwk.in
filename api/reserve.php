<?php
/**
 * Table reservations.
 *   create (PUBLIC)       : {slug|table, name, mobile, party_size, date, time, note?}
 *   list   (client|staff) : reservations for the current tenant (optional ?date, ?scope).
 *   status (client|staff) : {id, status} confirm / seated / cancelled (+ optional WA to guest).
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    // ---------------- PUBLIC: create a reservation ------------------------
    if ($action === 'create') {
        $body = $_POST + $_GET;
        $slug  = trim((string)($body['slug'] ?? ''));
        $token = trim((string)($body['table'] ?? ''));
        $tenant = null;
        if ($token !== '') {
            $row = db_one('SELECT tenant_id FROM ' . tbl('tables') . ' WHERE qr_token = :t', [':t' => $token]);
            if ($row) { $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $row['tenant_id']]); }
        } elseif ($slug !== '') {
            $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
        }
        if (!$tenant) { jsonError('Restaurant not found.', 404); }
        if ($tenant['status'] !== 'active') { jsonError('Reservations are unavailable right now.', 503); }
        $tid = (int)$tenant['id'];

        $name   = trim((string)($body['name'] ?? ''));
        $mobile = preg_replace('/[^0-9]/', '', (string)($body['mobile'] ?? ''));
        $party  = max(1, min(50, (int)($body['party_size'] ?? 2)));
        $date   = trim((string)($body['date'] ?? ''));
        $time   = trim((string)($body['time'] ?? ''));
        $note   = mb_substr(trim((string)($body['note'] ?? '')), 0, 200);

        if ($name === '') { jsonError('Please enter your name.'); }
        if (strlen($mobile) < 10) { jsonError('Please enter a valid mobile number.'); }
        $d = date_create($date);
        if (!$d) { jsonError('Please choose a valid date.'); }
        $date = $d->format('Y-m-d');
        if ($date < date('Y-m-d')) { jsonError('Please choose today or a future date.'); }
        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) { jsonError('Please choose a time.'); }
        $time = date('H:i:s', strtotime($time));

        // Anti-spam: one reservation per 30s per session.
        if ((time() - (int)($_SESSION['resv_last'] ?? 0)) < 30) {
            jsonError('You just sent a request — please wait a moment.');
        }
        $_SESSION['resv_last'] = time();

        db_insert('reservations', [
            'tenant_id'       => $tid,
            'customer_name'   => $name,
            'customer_mobile' => $mobile,
            'party_size'      => $party,
            'reserve_date'    => $date,
            'reserve_time'    => $time,
            'note'            => $note !== '' ? $note : null,
            'status'          => 'pending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Notify the owner (non-blocking).
        try {
            $to = $tenant['whatsapp_no'] ?: $tenant['mobile'];
            if ($to) {
                $msg = "New table reservation\n" . $tenant['restaurant_name'] . "\n"
                     . "Name: $name\nMobile: $mobile\nGuests: $party\n"
                     . "When: " . date('d M Y', strtotime($date)) . " at " . date('g:i A', strtotime($time))
                     . ($note !== '' ? "\nNote: $note" : '');
                sendWhatsApp($to, $msg, null, $tid, 'reservation');
            }
        } catch (Throwable $e) { error_log('reservation WA: ' . $e->getMessage()); }

        jsonSuccess('Your table request has been sent! The restaurant will confirm shortly.');
    }

    // ---------------- client OR staff below -------------------------------
    if (!(isClient() || isStaff())) { jsonError('Unauthorized.', 401); }
    $tid = (int)currentTenantId();

    if ($action === 'list') {
        $scope = $_GET['scope'] ?? 'upcoming';
        $date  = trim((string)($_GET['date'] ?? ''));
        $params = [':t' => $tid];
        $where = 'tenant_id = :t';
        if ($date !== '' && ($dd = date_create($date))) {
            $where .= ' AND reserve_date = :d'; $params[':d'] = $dd->format('Y-m-d');
        } elseif ($scope === 'upcoming') {
            $where .= " AND reserve_date >= CURDATE() AND status <> 'cancelled'";
        }
        $rows = db_all("SELECT * FROM " . tbl('reservations') . " WHERE $where
                        ORDER BY reserve_date ASC, reserve_time ASC LIMIT 300", $params);
        $pending = (int)db_val("SELECT COUNT(*) FROM " . tbl('reservations') . "
                                WHERE tenant_id = :t AND status = 'pending' AND reserve_date >= CURDATE()", [':t' => $tid]);
        jsonSuccess('', ['reservations' => $rows, 'pending' => $pending]);
    }

    if ($action === 'status') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['confirmed', 'seated', 'cancelled'], true)) { jsonError('Invalid status.'); }
        $resv = db_one("SELECT * FROM " . tbl('reservations') . " WHERE id = :i AND tenant_id = :t", [':i' => $id, ':t' => $tid]);
        if (!$resv) { jsonError('Reservation not found.', 404); }
        db_update('reservations', ['status' => $status], ['id' => $id]);

        // Tell the guest when confirmed or cancelled (non-blocking).
        if (in_array($status, ['confirmed', 'cancelled'], true) && $resv['customer_mobile']) {
            try {
                $tenant = currentTenant();
                $when = date('d M Y', strtotime($resv['reserve_date'])) . ' at ' . date('g:i A', strtotime($resv['reserve_time']));
                $msg = $status === 'confirmed'
                    ? ("Your table at " . $tenant['restaurant_name'] . " is CONFIRMED for $when for {$resv['party_size']} guest(s). See you soon!")
                    : ("Sorry, your table request at " . $tenant['restaurant_name'] . " for $when could not be confirmed. Please call us.");
                sendWhatsApp($resv['customer_mobile'], $msg, null, $tid, 'reservation');
            } catch (Throwable $e) { error_log('reservation status WA: ' . $e->getMessage()); }
        }
        jsonSuccess('Reservation updated.');
    }

    jsonError('Unknown action.', 404);

} catch (Throwable $e) {
    error_log('api/reserve.php: ' . $e->getMessage());
    jsonError('Server error.', 500);
}
