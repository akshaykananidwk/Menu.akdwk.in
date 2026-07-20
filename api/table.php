<?php
/**
 * Tables API (client only). Tenant-isolated CRUD for dining tables.
 * Actions: table_save, table_delete, table_status.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
requireClient();

$tid    = (int)currentTenantId();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // Create or update a table. qr_token auto-generated on create.
        case 'table_save': {
            $id      = (int)($_POST['id'] ?? 0);
            $tableNo = trim((string)($_POST['table_no'] ?? ''));
            $section = trim((string)($_POST['section'] ?? ''));
            if ($tableNo === '') { jsonError('Table number is required.'); }

            if ($id > 0) {
                // Update — verify ownership first.
                $existing = db_one('SELECT * FROM ' . tbl('tables') . ' WHERE id = :i AND tenant_id = :t',
                    [':i' => $id, ':t' => $tid]);
                if (!$existing) { jsonError('Table not found.', 404); }
                db_update('tables', [
                    'table_no' => $tableNo,
                    'section'  => $section ?: null,
                ], ['id' => $id]);
                jsonSuccess('Table updated.', ['id' => $id]);
            } else {
                // Create — enforce plan limit server-side.
                $limit = checkPlanLimit($tid, 'tables');
                if (!$limit['allowed']) { jsonError($limit['message']); }

                // Unique qr_token (retry on the rare collision).
                do { $token = randomToken(20); }
                while (db_val('SELECT COUNT(*) FROM ' . tbl('tables') . ' WHERE qr_token = :q', [':q' => $token]) > 0);

                $newId = db_insert('tables', [
                    'tenant_id' => $tid,
                    'table_no'  => $tableNo,
                    'section'   => $section ?: null,
                    'qr_token'  => $token,
                    'status'    => 'free',
                ]);
                logActivity('client', $tid, 'Added table ' . $tableNo);
                jsonSuccess('Table added.', ['id' => $newId, 'qr_token' => $token]);
            }
            break;
        }

        // Delete a table (tenant-isolated).
        case 'table_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $t  = db_one('SELECT * FROM ' . tbl('tables') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            if (!$t) { jsonError('Table not found.', 404); }
            db_query('DELETE FROM ' . tbl('tables') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            logActivity('client', $tid, 'Deleted table ' . $t['table_no']);
            jsonSuccess('Table deleted.');
            break;
        }

        // Change a table's occupancy status.
        case 'table_status': {
            $id     = (int)($_POST['id'] ?? 0);
            $status = trim((string)($_POST['status'] ?? ''));
            if (!in_array($status, ['free', 'occupied', 'billed'], true)) { jsonError('Invalid status.'); }
            $t = db_one('SELECT id FROM ' . tbl('tables') . ' WHERE id = :i AND tenant_id = :t',
                [':i' => $id, ':t' => $tid]);
            if (!$t) { jsonError('Table not found.', 404); }
            db_update('tables', ['status' => $status], ['id' => $id]);
            jsonSuccess('Table status updated.');
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/table.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
