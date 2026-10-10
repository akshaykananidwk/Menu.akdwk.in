<?php
/**
 * E-mail API — admin only.
 *
 * Every action requires isSuperAdmin(): these expose the SMTP conversation and
 * the platform-wide e-mail log, which no tenant may ever see.
 *
 * Contract: JSON via jsonSuccess/jsonError. CSRF enforced on POST.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        /**
         * test — send a test e-mail now and return the exact server reply.
         * Uses the SAVED settings (not the on-screen form), so the admin must
         * save first; the UI says so.
         */
        case 'test': {
            $to  = trim((string)($_POST['to'] ?? ''));
            $res = mailerTest($to);
            $payload = [
                'transport' => $res['transport'],
                'detail'    => $res['message'],
                'log_id'    => $res['log_id'],
            ];
            if ($res['ok']) { jsonSuccess('Test e-mail sent.', $payload); }
            jsonError($res['message'] ?: 'Could not send the test e-mail.', 200, $payload);
        }

        /**
         * retry — re-dispatch one failed/pending log row.
         */
        case 'retry': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { jsonError('Invalid id.'); }
            $row = db_one('SELECT id FROM ' . tbl('email_logs') . ' WHERE id = :id', [':id' => $id]);
            if (!$row) { jsonError('Log not found.', 404); }

            // Clear the retry counter so a manual retry is never blocked by it.
            db_update('email_logs', ['status' => 'pending', 'retry_count' => 0], ['id' => $id]);
            $ok  = dispatchEmailLog($id, 25);
            $new = db_one('SELECT status, response FROM ' . tbl('email_logs') . ' WHERE id = :id', [':id' => $id]);

            $payload = ['status' => (string)($new['status'] ?? 'unknown'), 'detail' => (string)($new['response'] ?? '')];
            if ($ok) { jsonSuccess('E-mail sent.', $payload); }
            jsonError('Retry failed — see the server reply.', 200, $payload);
        }

        /**
         * flush — drain the whole pending queue right now (manual fallback when
         * the master cron is not configured yet).
         */
        case 'flush': {
            $before = (int)db_val('SELECT COUNT(*) FROM ' . tbl('email_logs') . " WHERE status = 'pending'");
            $sent   = processEmailQueue(100, 20);
            $after  = (int)db_val('SELECT COUNT(*) FROM ' . tbl('email_logs') . " WHERE status = 'pending'");
            jsonSuccess("Sent {$sent} of {$before} pending. {$after} still queued.", [
                'sent' => $sent, 'pending' => $after,
            ]);
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/mail.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
