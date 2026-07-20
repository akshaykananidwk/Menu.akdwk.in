<?php
/**
 * AK Menu System — Design/Branding + Restaurant Settings API.
 * action=save_design : template, colors, fonts, toggles, banners, social/hours.
 * action=settings    : profile / gst / ordering fields + change_password.
 * SECURITY: always UPDATE ... WHERE id = $tid (tenant from session only).
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

requireClient();
$tid    = currentTenantId();
$action = $_GET['action'] ?? '';

/** Validate a hex color, fall back to a default if malformed. */
function safeColor(string $v, string $default): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $default;
}

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // SAVE DESIGN — template selection + branding.
        // ---------------------------------------------------------------------
        case 'save_design': {
            $data = [];

            // Template selection: verify it exists & is allowed for this plan.
            if (isset($_POST['template_id'])) {
                $templateId = (int)$_POST['template_id'];
                $tpl = db_one('SELECT * FROM ' . tbl('templates') . ' WHERE id = :i AND status = 1', [':i' => $templateId]);
                if (!$tpl) { jsonError('Invalid template.'); }
                // Premium templates require the remove_branding plan feature.
                if ((int)$tpl['is_premium'] === 1 && !planHasFeature($tid, 'remove_branding')) {
                    jsonError('This premium template requires a higher plan.', 403);
                }
                $data['template_id'] = $templateId;
            }

            // Colors / fonts.
            if (isset($_POST['primary_color']))   { $data['primary_color']   = safeColor($_POST['primary_color'], '#e63946'); }
            if (isset($_POST['secondary_color'])) { $data['secondary_color'] = safeColor($_POST['secondary_color'], '#1d3557'); }
            if (isset($_POST['accent_color']))    { $data['accent_color']    = safeColor($_POST['accent_color'], '#f1a208'); }
            if (isset($_POST['font_family']))     { $data['font_family']      = substr(trim($_POST['font_family']), 0, 80) ?: 'Poppins'; }

            // Display toggles (checkbox present => 1).
            foreach (['show_prices', 'show_images', 'show_veg_marker', 'show_descriptions'] as $tg) {
                if (array_key_exists('has_' . $tg, $_POST)) { // sentinel so unchecked = 0
                    $data[$tg] = !empty($_POST[$tg]) ? 1 : 0;
                }
            }

            // Social / maps / hours.
            $textFields = ['facebook_url', 'instagram_url', 'google_review_url', 'address', 'maps_url'];
            foreach ($textFields as $f) {
                if (isset($_POST[$f])) { $data[$f] = trim($_POST[$f]) ?: null; }
            }
            if (isset($_POST['opening_time'])) { $data['opening_time'] = trim($_POST['opening_time']) ?: null; }
            if (isset($_POST['closing_time'])) { $data['closing_time'] = trim($_POST['closing_time']) ?: null; }

            // Banner / cover uploads.
            if (!empty($_FILES['banner_image']['name'])) {
                $b = uploadImage($_FILES['banner_image'], 'logos');
                if ($b) { $data['banner_image'] = $b; }
            }
            if (!empty($_FILES['cover_image']['name'])) {
                $c = uploadImage($_FILES['cover_image'], 'logos');
                if ($c) { $data['cover_image'] = $c; }
            }

            if (!$data) { jsonError('Nothing to update.'); }
            db_update('tenants', $data, ['id' => $tid]);
            jsonSuccess('Design saved.');
            break;
        }

        // ---------------------------------------------------------------------
        // SETTINGS — profile / gst / ordering / password.
        // ---------------------------------------------------------------------
        case 'settings': {
            $sub = $_POST['section'] ?? '';

            if ($sub === 'change_password') {
                $current = $_POST['current_password'] ?? '';
                $new     = $_POST['new_password'] ?? '';
                if (strlen($new) < 6) { jsonError('New password must be at least 6 characters.'); }
                $tenant = currentTenant();
                if (!$tenant || !password_verify($current, $tenant['password'])) {
                    jsonError('Current password is incorrect.', 403);
                }
                db_update('tenants', ['password' => password_hash($new, PASSWORD_DEFAULT)], ['id' => $tid]);
                logActivity('tenant', $tid, 'Changed password');
                jsonSuccess('Password updated.');
                break;
            }

            $data = [];
            if ($sub === 'profile' || $sub === '') {
                if (isset($_POST['restaurant_name'])) {
                    $rn = trim($_POST['restaurant_name']);
                    if ($rn === '') { jsonError('Restaurant name is required.'); }
                    $data['restaurant_name'] = $rn;
                }
                foreach (['owner_name', 'mobile', 'email', 'address', 'city'] as $f) {
                    if (isset($_POST[$f])) { $data[$f] = trim($_POST[$f]) ?: null; }
                }
                if (!empty($_FILES['logo']['name'])) {
                    $logo = uploadImage($_FILES['logo'], 'logos');
                    if ($logo) { $data['logo'] = $logo; }
                }
            }
            if ($sub === 'gst' || $sub === '') {
                if (isset($_POST['gst_no']))         { $data['gst_no']         = trim($_POST['gst_no']) ?: null; }
                if (isset($_POST['cgst']))           { $data['cgst']           = (float)$_POST['cgst']; }
                if (isset($_POST['sgst']))           { $data['sgst']           = (float)$_POST['sgst']; }
                if (isset($_POST['service_charge'])) { $data['service_charge'] = (float)$_POST['service_charge']; }
            }
            if ($sub === 'ordering' || $sub === '') {
                if (isset($_POST['ordering_mode'])) {
                    $mode = in_array($_POST['ordering_mode'], ['direct', 'waiter', 'view_only'], true) ? $_POST['ordering_mode'] : 'view_only';
                    $data['ordering_mode'] = $mode;
                }
                if (isset($_POST['min_order_value'])) { $data['min_order_value'] = (float)$_POST['min_order_value']; }
                if (isset($_POST['delivery_charge'])) { $data['delivery_charge'] = (float)$_POST['delivery_charge']; }
                if (isset($_POST['whatsapp_no']))     { $data['whatsapp_no']     = trim($_POST['whatsapp_no']) ?: null; }
            }

            if (!$data) { jsonError('Nothing to update.'); }
            db_update('tenants', $data, ['id' => $tid]);
            logActivity('tenant', $tid, 'Updated settings (' . ($sub ?: 'all') . ')');
            jsonSuccess('Settings saved.');
            break;
        }

        // ---------------------------------------------------------------------
        // UPGRADE REQUEST — raise a support ticket for plan renewal/upgrade.
        // ---------------------------------------------------------------------
        case 'upgrade_request': {
            $tenant = currentTenant();
            $msg = trim($_POST['message'] ?? '') ?: 'Requesting a plan upgrade / renewal.';
            db_insert('tickets', [
                'tenant_id' => $tid,
                'subject'   => 'Plan Upgrade / Renewal Request',
                'message'   => $msg,
            ]);
            logActivity('tenant', $tid, 'Requested plan upgrade');
            // Notify support via WhatsApp (best-effort, queued).
            $support = getWaSetting('support_number', getSetting('support_mobile', ''));
            if ($support) {
                sendWhatsApp($support, 'Upgrade request from ' . ($tenant['restaurant_name'] ?? '') . ' (ID ' . $tid . ').', null, $tid, 'manual');
            }
            jsonSuccess('Your upgrade request has been sent. Our team will contact you shortly.');
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $ex) {
    jsonError('Server error: ' . $ex->getMessage(), 500);
}
