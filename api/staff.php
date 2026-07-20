<?php
/**
 * Staff API (client only). Tenant-isolated CRUD for waiter/kitchen users.
 * PINs are stored hashed (password_hash). Actions: staff_save, staff_delete, staff_toggle.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
requireClient();

$tid    = (int)currentTenantId();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // Create or update a staff member.
        case 'staff_save': {
            $id     = (int)($_POST['id'] ?? 0);
            $name   = trim((string)($_POST['name'] ?? ''));
            $mobile = preg_replace('/[^0-9]/', '', (string)($_POST['mobile'] ?? ''));
            $role   = in_array($_POST['role'] ?? '', ['waiter', 'kitchen'], true) ? $_POST['role'] : '';
            $pin    = trim((string)($_POST['pin'] ?? ''));

            if ($name === '') { jsonError('Name is required.'); }
            if ($role === '') { jsonError('Please select a valid role.'); }
            if ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) { jsonError('PIN must be exactly 4 digits.'); }

            if ($id > 0) {
                // Update — verify ownership.
                $existing = db_one('SELECT * FROM ' . tbl('staff') . ' WHERE id = :i AND tenant_id = :t',
                    [':i' => $id, ':t' => $tid]);
                if (!$existing) { jsonError('Staff member not found.', 404); }
                $data = ['name' => $name, 'mobile' => $mobile ?: null, 'role' => $role];
                // Only rehash the PIN when a new one is supplied.
                if ($pin !== '') { $data['pin'] = password_hash($pin, PASSWORD_DEFAULT); }
                db_update('staff', $data, ['id' => $id]);
                jsonSuccess('Staff updated.', ['id' => $id]);
            } else {
                // Create — PIN mandatory + plan limit enforced.
                if ($pin === '') { jsonError('PIN is required.'); }
                $limit = checkPlanLimit($tid, 'waiters');
                if (!$limit['allowed']) { jsonError($limit['message']); }

                $newId = db_insert('staff', [
                    'tenant_id' => $tid,
                    'name'      => $name,
                    'mobile'    => $mobile ?: null,
                    'pin'       => password_hash($pin, PASSWORD_DEFAULT),
                    'role'      => $role,
                    'status'    => 1,
                ]);
                logActivity('client', $tid, 'Added ' . $role . ' ' . $name);
                jsonSuccess('Staff added.', ['id' => $newId]);
            }
            break;
        }

        // Delete a staff member (tenant-isolated).
        case 'staff_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $s  = db_one('SELECT * FROM ' . tbl('staff') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            if (!$s) { jsonError('Staff member not found.', 404); }
            db_query('DELETE FROM ' . tbl('staff') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            logActivity('client', $tid, 'Deleted staff ' . $s['name']);
            jsonSuccess('Staff deleted.');
            break;
        }

        // Enable/disable a staff login.
        case 'staff_toggle': {
            $id = (int)($_POST['id'] ?? 0);
            $s  = db_one('SELECT status FROM ' . tbl('staff') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            if (!$s) { jsonError('Staff member not found.', 404); }
            $new = (int)$s['status'] === 1 ? 0 : 1;
            db_update('staff', ['status' => $new], ['id' => $id]);
            jsonSuccess($new ? 'Staff enabled.' : 'Staff disabled.', ['status' => $new]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/staff.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
