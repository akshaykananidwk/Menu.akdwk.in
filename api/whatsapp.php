<?php
/**
 * WhatsApp API — mixed admin + client actions.
 *
 * Guarding is PER ACTION: admin actions require isSuperAdmin(); client actions
 * require isClient(). This lets one endpoint serve both panels while keeping
 * strict privilege separation. CSRF is enforced on every POST.
 *
 * Contract: JSON via jsonSuccess/jsonError. Never hardcode gateway creds —
 * everything goes through the helper functions in config/functions.php.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}

$action = $_GET['action'] ?? '';

// Which actions belong to which realm. Anything not listed = unknown.
$adminActions  = ['test', 'template_save', 'retry', 'broadcast_status', 'inbox_read', 'flush_queue'];
$clientActions = ['send_menu_link', 'toggle_notify'];

/** Read a trimmed POST value. */
function wstr(string $k, string $default = ''): string {
    return trim((string)($_POST[$k] ?? $default));
}

try {
    // -------------------------------------------------------------------------
    // Route + guard
    // -------------------------------------------------------------------------
    if (in_array($action, $adminActions, true)) {
        requireAdmin();
    } elseif (in_array($action, $clientActions, true)) {
        requireClient();
    } else {
        jsonError('Unknown action.', 404);
    }

    switch ($action) {

        // =================================================================
        // ADMIN ACTIONS
        // =================================================================

        /**
         * test — send a message immediately and return the RAW gateway response.
         * We dispatch synchronously then re-read the log row to expose whatever
         * the gateway returned (or the cURL error), useful for diagnostics.
         */
        case 'test': {
            $number  = wstr('number');
            $message = wstr('message');
            if ($number === '' || $message === '') { jsonError('Number and message are required.'); }

            // Immediate send (short timeout). sendWhatsApp() logs + dispatches.
            $logId = sendWhatsApp($number, $message, null, null, 'test', true);
            $log   = db_one('SELECT status, response, number FROM ' . tbl('whatsapp_logs') . ' WHERE id = :id', [':id' => $logId]);

            jsonSuccess('Test dispatched.', [
                'log_id'   => $logId,
                'status'   => $log['status'] ?? 'unknown',
                'number'   => $log['number'] ?? $number,
                'response' => $log['response'] ?? '',
            ]);
        }

        /**
         * template_save — upsert a whatsapp_templates row keyed by trigger_key.
         * trigger_key is immutable (chosen at creation); title/messages/flags edit.
         */
        case 'template_save': {
            $triggerKey = wstr('trigger_key');
            if ($triggerKey === '') { jsonError('Trigger key is required.'); }

            $data = [
                'title'      => wstr('title') ?: $triggerKey,
                'message_en' => (string)($_POST['message_en'] ?? ''),
                'message_gu' => (string)($_POST['message_gu'] ?? ''),
                'media_type' => wstr('media_type', 'text') ?: 'text',
                'is_active'  => !empty($_POST['is_active']) ? 1 : 0,
            ];

            $existing = db_one('SELECT id FROM ' . tbl('whatsapp_templates') . ' WHERE trigger_key = :k', [':k' => $triggerKey]);
            if ($existing) {
                db_update('whatsapp_templates', $data, ['id' => $existing['id']]);
                $id = (int)$existing['id'];
            } else {
                $data['trigger_key'] = $triggerKey;
                $id = db_insert('whatsapp_templates', $data);
            }
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Saved WhatsApp template: ' . $triggerKey);
            jsonSuccess('Template saved.', ['id' => $id]);
        }

        /**
         * retry — re-dispatch a failed/pending log row via dispatchWhatsAppLog().
         */
        case 'retry': {
            $logId = (int)($_POST['log_id'] ?? 0);
            if ($logId <= 0) { jsonError('Invalid log id.'); }
            $log = db_one('SELECT id FROM ' . tbl('whatsapp_logs') . ' WHERE id = :id', [':id' => $logId]);
            if (!$log) { jsonError('Log not found.', 404); }

            $ok  = dispatchWhatsAppLog($logId, 15);
            $new = db_one('SELECT status, response, retry_count FROM ' . tbl('whatsapp_logs') . ' WHERE id = :id', [':id' => $logId]);
            jsonSuccess($ok ? 'Message sent.' : 'Retry failed — see response.', [
                'status'      => $new['status'] ?? 'unknown',
                'response'    => $new['response'] ?? '',
                'retry_count' => (int)($new['retry_count'] ?? 0),
            ]);
        }

        /**
         * flush_queue — send all currently pending messages right now (manual
         * fallback when no cron is configured).
         */
        case 'flush_queue': {
            $before = (int)db_val('SELECT COUNT(*) FROM ' . tbl('whatsapp_logs') . " WHERE status = 'pending'");
            $sent   = processWhatsAppQueue(50, 15);
            $after  = (int)db_val('SELECT COUNT(*) FROM ' . tbl('whatsapp_logs') . " WHERE status = 'pending'");
            jsonSuccess("Sent $sent of $before pending. $after still pending.", [
                'sent' => $sent, 'pending' => $after,
            ]);
        }

        /**
         * broadcast_status — sent/pending/failed counts for the last broadcast.
         * Scoped to trigger_key='broadcast' so live progress bars can poll.
         */
        case 'broadcast_status': {
            $rows = db_all(
                "SELECT status, COUNT(*) AS c FROM " . tbl('whatsapp_logs') . "
                 WHERE trigger_key = 'broadcast' GROUP BY status"
            );
            $counts = ['sent' => 0, 'pending' => 0, 'failed' => 0];
            foreach ($rows as $r) { $counts[$r['status']] = (int)$r['c']; }
            $counts['total'] = $counts['sent'] + $counts['pending'] + $counts['failed'];
            jsonSuccess('', $counts);
        }

        /**
         * inbox_read — mark an inbound message as read.
         */
        case 'inbox_read': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { jsonError('Invalid id.'); }
            db_update('whatsapp_inbox', ['is_read' => 1], ['id' => $id]);
            jsonSuccess('Marked as read.');
        }

        // =================================================================
        // CLIENT ACTIONS (tenant-isolated — resolve tenant from session only)
        // =================================================================

        /**
         * send_menu_link — send this tenant's public menu URL to a customer.
         * Tenant is taken strictly from the session; never from the request.
         */
        case 'send_menu_link': {
            $tid    = (int)currentTenantId();
            $tenant = currentTenant();
            if (!$tenant) { jsonError('Tenant not found.', 404); }

            $number = wstr('number');
            if ($number === '') { jsonError('Please enter a customer number.'); }
            if (!formatWaNumber($number)) { jsonError('Please enter a valid mobile number.'); }

            $url = publicMenuUrl($tenant['slug']);
            $msg = "Here is our digital menu 📖\n" . $tenant['restaurant_name'] . "\n" . $url;
            // Queue (cron worker sends). Non-blocking by design.
            $logId = sendWhatsApp($number, $msg, null, $tid, 'menu_link');
            jsonSuccess('Menu link queued to send.', ['log_id' => $logId]);
        }

        /**
         * toggle_notify — enable/disable a per-tenant notification type.
         * Stored as a global setting keyed by tenant + trigger: wa_{tid}_{trigger}.
         */
        case 'toggle_notify': {
            $tid     = (int)currentTenantId();
            $trigger = preg_replace('/[^a-z0-9_]/', '', strtolower(wstr('trigger')));
            if ($trigger === '') { jsonError('Invalid notification type.'); }
            $on = (!empty($_POST['on']) && ($_POST['on'] === '1' || $_POST['on'] === 'true' || $_POST['on'] === 'on')) ? '1' : '0';
            setSetting('wa_' . $tid . '_' . $trigger, $on);
            jsonSuccess('Preference saved.', ['trigger' => $trigger, 'on' => $on]);
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/whatsapp.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
