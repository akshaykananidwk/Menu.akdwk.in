<?php
/**
 * AK Menu System — Standee gallery API (client panel).
 *
 * Actions:
 *   send_whatsapp : render the chosen design to a PNG file, then queue/send it to
 *                   a WhatsApp number via the gateway (as a media message).
 *   save_default  : remember the tenant's preferred standee design.
 *
 * SECURITY: requireClient(); csrfCheck() on POST; the tenant is ALWAYS taken from
 * the session (currentTenantId) — a tenant_id is never accepted from the request.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/standee/engine.php';   // se_render() + designs.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
requireClient();

$tid    = currentTenantId();
$tenant = currentTenant();
$action = $_GET['action'] ?? '';

if (!$tenant) { jsonError('Session expired. Please log in again.', 401); }
$slug = (string)$tenant['slug'];

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // SEND TO WHATSAPP — render PNG file then dispatch via the gateway.
        // ---------------------------------------------------------------------
        case 'send_whatsapp': {
            $designId = trim((string)($_POST['design'] ?? ''));
            $rawNum   = trim((string)($_POST['number'] ?? ''));

            $design = standee_resolve($designId);
            if (!$design) { jsonError('Please choose a valid design.'); }

            $number = formatWaNumber($rawNum);
            if (!$number) { jsonError('Please enter a valid mobile number.'); }

            // Ensure the tenant's QR exists (named by slug, same as the gallery).
            $qrAbs = UPLOAD_PATH . '/qr/' . $slug . '.png';
            if (!is_file($qrAbs)) {
                $rel = generateQr(publicMenuUrl($slug), $slug);
                $qrAbs = $rel ? ROOT_PATH . '/' . $rel : '';
            }
            if (!$qrAbs || !is_file($qrAbs)) { jsonError('Could not prepare the QR code. Please try again.', 500); }

            // Render the full-size standee and save it as a shareable media file.
            $dir = UPLOAD_PATH . '/standee';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $fileName = $slug . '-' . $designId . '.png';
            $absFile  = $dir . '/' . $fileName;
            $relFile  = 'uploads/standee/' . $fileName;

            // Resolve config: saved per-tenant settings, with optional POST
            // overrides so what the owner sees in the preview is what is sent.
            $cfg = se_resolve_config($tenant, $tid, $_POST);
            $img = se_render($tenant, $qrAbs, $design, 1080, 1920, $cfg);
            $ok  = imagepng($img, $absFile);
            imagedestroy($img);
            if (!$ok || !is_file($absFile)) { jsonError('Could not create the standee image.', 500); }

            // Queue the media message (the cron worker actually POSTs to the
            // gateway; a gateway failure never breaks this request).
            $mediaUrl = BASE_URL . '/' . $relFile;
            $caption  = 'Scan for our menu — ' . $tenant['restaurant_name'] . "\n" . publicMenuUrl($slug);
            $logId    = sendWhatsApp($number, $caption, $mediaUrl, $tid, 'standee');

            logActivity('client', $tid, 'Sent standee ' . $designId . ' to WhatsApp ' . $number);

            jsonSuccess('Standee queued for WhatsApp delivery.', [
                'log_id'    => $logId,
                'media_url' => $mediaUrl,
                'file'      => $relFile,
            ]);
            break;
        }

        // ---------------------------------------------------------------------
        // SAVE DEFAULT — persist the tenant's preferred design (per-tenant setting).
        // ---------------------------------------------------------------------
        case 'save_default': {
            $designId = trim((string)($_POST['design'] ?? ''));
            if (!standee_resolve($designId)) { jsonError('Please choose a valid design.'); }
            setSetting('standee_design_' . $tid, $designId);
            logActivity('client', $tid, 'Set default standee design ' . $designId);
            jsonSuccess('Saved as your default design.', ['design' => $designId]);
            break;
        }

        // ---------------------------------------------------------------------
        // SAVE CONFIG — persist the tenant's editable standee text/language.
        // ---------------------------------------------------------------------
        case 'save_cfg': {
            // se_extract_overrides sanitises + length-caps every field.
            $cfg = array_merge(se_config_defaults($tenant), se_extract_overrides($_POST));
            setSetting('standee_cfg_' . $tid, json_encode($cfg, JSON_UNESCAPED_UNICODE));
            logActivity('client', $tid, 'Saved standee customisation');
            jsonSuccess('Standee text saved.', ['config' => $cfg]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $ex) {
    jsonError('Server error: ' . $ex->getMessage(), 500);
}
