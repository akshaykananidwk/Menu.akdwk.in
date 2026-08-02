<?php
/**
 * Meta WhatsApp Cloud API — admin JSON endpoint (Super Admin only).
 * Actions: save_setup, test, tpl_list, tpl_create, tpl_delete, send,
 *          convo_list, convo_thread.
 * CSRF on every POST. All Meta calls go through config/wa_cloud.php.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isSuperAdmin()) { jsonError('Unauthorized.', 401); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

$action = $_GET['action'] ?? '';
function ws(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }

/** Build the components[] for a template-creation request from posted fields. */
function waBuildComponents(): array {
    $category = strtoupper(ws('category', 'UTILITY'));

    // Authentication templates use Meta's fixed OTP structure.
    if ($category === 'AUTHENTICATION') {
        $exp = (int)ws('code_expiry', '10');
        $btnText = ws('otp_button_text', 'Copy Code') ?: 'Copy Code';
        $comp = [
            ['type' => 'BODY', 'add_security_recommendation' => true],
            ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => $btnText]]],
        ];
        if ($exp > 0) { array_splice($comp, 1, 0, [['type' => 'FOOTER', 'code_expiration_minutes' => $exp]]); }
        return $comp;
    }

    $components = [];

    // ---- Header ----
    $ht = ws('header_type', 'none');
    if ($ht === 'text') {
        $text = ws('header_text');
        if ($text !== '') {
            $h = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $text];
            if (preg_match('/\{\{\d+\}\}/', $text)) {
                $sample = ws('header_sample') ?: 'Sample';
                $h['example'] = ['header_text' => [$sample]];
            }
            $components[] = $h;
        }
    } elseif (in_array($ht, ['image', 'document', 'video'], true)) {
        $link = ws('header_sample');
        $h = ['type' => 'HEADER', 'format' => strtoupper($ht)];
        if ($link !== '') { $h['example'] = ['header_handle' => [$link]]; }
        $components[] = $h;
    }

    // ---- Body (required) ----
    $body = ws('body_text');
    $b = ['type' => 'BODY', 'text' => $body];
    if (preg_match_all('/\{\{(\d+)\}\}/', $body, $m)) {
        $count = count(array_unique($m[1]));
        $samples = array_filter(array_map('trim', explode('|', ws('body_samples'))));
        while (count($samples) < $count) { $samples[] = 'Sample'; }
        $b['example'] = ['body_text' => [array_slice(array_values($samples), 0, $count)]];
    }
    $components[] = $b;

    // ---- Footer ----
    $footer = ws('footer_text');
    if ($footer !== '') { $components[] = ['type' => 'FOOTER', 'text' => $footer]; }

    // ---- Buttons ----
    $btnsRaw = json_decode((string)($_POST['buttons'] ?? '[]'), true);
    if (is_array($btnsRaw) && $btnsRaw) {
        $buttons = [];
        foreach (array_slice($btnsRaw, 0, 10) as $btn) {
            $t = strtoupper((string)($btn['type'] ?? ''));
            $text = trim((string)($btn['text'] ?? ''));
            if ($text === '') { continue; }
            if ($t === 'QUICK_REPLY') {
                $buttons[] = ['type' => 'QUICK_REPLY', 'text' => $text];
            } elseif ($t === 'URL') {
                $u = ['type' => 'URL', 'text' => $text, 'url' => trim((string)($btn['url'] ?? ''))];
                if (preg_match('/\{\{\d+\}\}/', $u['url'])) { $u['example'] = [trim((string)($btn['sample'] ?? 'https://example.com'))]; }
                $buttons[] = $u;
            } elseif ($t === 'PHONE_NUMBER') {
                $buttons[] = ['type' => 'PHONE_NUMBER', 'text' => $text, 'phone_number' => trim((string)($btn['phone'] ?? ''))];
            } elseif ($t === 'COPY_CODE') {
                $buttons[] = ['type' => 'COPY_CODE', 'example' => trim((string)($btn['sample'] ?? '12345'))];
            }
        }
        if ($buttons) { $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons]; }
    }

    return $components;
}

try {
    switch ($action) {

        case 'save_setup': {
            setWaSetting('wa_provider', ws('wa_provider', 'gateway') === 'cloud' ? 'cloud' : 'gateway');
            setWaSetting('wa_cloud_token', ws('wa_cloud_token'));
            setWaSetting('wa_cloud_phone_id', ws('wa_cloud_phone_id'));
            setWaSetting('wa_cloud_waba_id', ws('wa_cloud_waba_id'));
            setWaSetting('wa_cloud_number', ws('wa_cloud_number'));
            $ver = ws('wa_cloud_version', 'v21.0');
            setWaSetting('wa_cloud_version', preg_match('/^v\d+\.\d+$/', $ver) ? $ver : 'v21.0');
            jsonSuccess('Cloud API settings saved.');
        }

        case 'test': {
            $r = waCloudTest();
            if (!$r['ok']) { jsonError($r['error'] ?: 'Connection failed.'); }
            jsonSuccess('Connected to Meta WhatsApp Cloud API.', ['info' => $r['info']]);
        }

        case 'tpl_list': {
            $r = waTemplatesList();
            if (!$r['ok']) { jsonError($r['error'], 200, ['templates' => []]); }
            jsonSuccess('', ['templates' => $r['templates']]);
        }

        case 'tpl_create': {
            $name = strtolower(ws('name'));
            $name = preg_replace('/[^a-z0-9_]/', '_', $name);
            if ($name === '') { jsonError('Enter a template name (letters, numbers, underscores).'); }
            $category = strtoupper(ws('category', 'UTILITY'));
            if (!in_array($category, ['UTILITY', 'MARKETING', 'AUTHENTICATION'], true)) { $category = 'UTILITY'; }
            $lang = ws('language', 'en') ?: 'en';
            if ($category !== 'AUTHENTICATION' && ws('body_text') === '') { jsonError('Body text is required.'); }

            $def = ['name' => $name, 'language' => $lang, 'category' => $category, 'components' => waBuildComponents()];
            $r = waTemplateCreate($def);
            if (!$r['ok']) { jsonError($r['error']); }
            jsonSuccess('Template submitted to Meta for approval.', ['id' => $r['id'] ?? '', 'status' => $r['status'] ?? 'PENDING']);
        }

        case 'tpl_delete': {
            $name = ws('name');
            if ($name === '') { jsonError('Missing template name.'); }
            $r = waTemplateDelete($name, ws('id'));
            if (!$r['ok']) { jsonError($r['error']); }
            jsonSuccess('Template deleted.');
        }

        case 'send': {
            $to = preg_replace('/[^0-9]/', '', ws('to'));
            if (strlen($to) < 10) { jsonError('Enter a valid number with country code.'); }
            if (!waCloudEnabled()) { jsonError('Turn on the Meta Cloud API provider and save your credentials first.'); }
            $type = ws('type', 'text');

            if ($type === 'text') {
                $body = ws('body');
                if ($body === '') { jsonError('Message text is empty.'); }
                $res = waCloudSendLogged($to, waMsgText($body), mb_substr($body, 0, 160), null, 'cloud_text');

            } elseif ($type === 'media') {
                $mtype = ws('media_type', 'image');
                $link = ws('media_link');
                if ($link === '') { jsonError('Enter the media link (public URL).'); }
                $msg = waMsgMedia($mtype, $link, ws('caption'), ws('filename'));
                $res = waCloudSendLogged($to, $msg, strtoupper($mtype) . ': ' . ($link), null, 'cloud_media');

            } elseif ($type === 'buttons') {
                $body = ws('body');
                $btns = json_decode((string)($_POST['buttons'] ?? '[]'), true) ?: [];
                if ($body === '' || !$btns) { jsonError('Body and at least one button are required.'); }
                $msg = waMsgButtons($body, $btns, ws('header'), ws('footer'));
                $res = waCloudSendLogged($to, $msg, mb_substr($body, 0, 160), null, 'cloud_buttons');

            } elseif ($type === 'template') {
                $name = ws('template_name');
                $lang = ws('language', 'en') ?: 'en';
                if ($name === '') { jsonError('Choose a template.'); }
                $components = [];
                // Header media parameter.
                $hlink = ws('header_link');
                $hmtype = ws('header_media_type', 'image');
                if ($hlink !== '') {
                    $components[] = ['type' => 'header', 'parameters' => [['type' => $hmtype, $hmtype => ['link' => $hlink]]]];
                }
                // Body variable parameters (pipe-separated).
                $bodyParams = array_filter(array_map('trim', explode('|', ws('body_params'))), fn($v) => $v !== '');
                if ($bodyParams) {
                    $components[] = ['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => $v], array_values($bodyParams))];
                }
                $msg = waMsgTemplate($name, $lang, $components);
                $res = waCloudSendLogged($to, $msg, 'Template: ' . $name, null, 'cloud_template');

            } else {
                jsonError('Unknown message type.');
            }

            if (!$res['ok']) { jsonError('Send failed: ' . $res['error'], 200, ['error' => $res['error'], 'code' => $res['error_code']]); }
            jsonSuccess('Message sent.', ['wamid' => $res['wamid'], 'log_id' => $res['log_id']]);
        }

        case 'convo_list': {
            $rows = db_all("SELECT w.number, w.message, w.status, w.created_at, c.cnt
                            FROM " . tbl('whatsapp_logs') . " w
                            JOIN (SELECT number, MAX(id) mid, COUNT(*) cnt FROM " . tbl('whatsapp_logs') . "
                                  WHERE number IS NOT NULL AND number <> '' GROUP BY number) c ON c.mid = w.id
                            ORDER BY w.id DESC LIMIT 200");
            jsonSuccess('', ['conversations' => $rows]);
        }

        case 'convo_thread': {
            $num = preg_replace('/[^0-9]/', '', ws('number'));
            if ($num === '') { jsonError('Missing number.'); }
            $rows = db_all("SELECT id, trigger_key, message, media_url, status, response, created_at, sent_at
                            FROM " . tbl('whatsapp_logs') . "
                            WHERE number = :n ORDER BY id ASC LIMIT 400", [':n' => $num]);
            foreach ($rows as &$r) {
                $meta = json_decode((string)$r['response'], true);
                $r['wamid'] = is_array($meta) ? ($meta['wamid'] ?? '') : '';
                $r['error'] = is_array($meta) ? ($meta['error'] ?? ($r['status'] === 'failed' ? (string)$r['response'] : '')) : ($r['status'] === 'failed' ? (string)$r['response'] : '');
            }
            unset($r);
            jsonSuccess('', ['messages' => $rows]);
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/wa_cloud.php: ' . $e->getMessage());
    jsonError('Server error: ' . $e->getMessage(), 500);
}
