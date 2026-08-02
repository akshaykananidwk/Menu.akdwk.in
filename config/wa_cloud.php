<?php
/**
 * Meta WhatsApp Cloud API integration (official Graph API).
 * No webhook is used — everything here is a direct request TO Meta:
 *   - send messages (text / media / template / interactive)
 *   - full message-template lifecycle (create / edit / delete / live status)
 *   - connection test (verified name, number, quality rating)
 *
 * Config lives in the existing whatsapp_settings key/value store:
 *   wa_provider        'gateway' (legacy bulk sender) | 'cloud' (Meta)
 *   wa_cloud_token     Permanent Access Token
 *   wa_cloud_phone_id  Phone Number ID  (for sending)
 *   wa_cloud_waba_id   WhatsApp Business Account ID (for templates)
 *   wa_cloud_number    display business number (info only)
 *   wa_cloud_version   Graph API version (default v21.0)
 *
 * Limitation (Meta platform, not a choice): delivery/read receipts and INCOMING
 * customer messages are delivered ONLY via webhook — Meta has no endpoint to poll
 * them. Without a webhook we can log/track OUTBOUND sends (accepted vs failed +
 * the exact error) and manage templates in full. That's what this module does.
 */

/** Current Cloud API configuration. */
function waCloudCfg(): array {
    return [
        'token'    => trim((string)getWaSetting('wa_cloud_token', '')),
        'phone_id' => trim((string)getWaSetting('wa_cloud_phone_id', '')),
        'waba_id'  => trim((string)getWaSetting('wa_cloud_waba_id', '')),
        'number'   => trim((string)getWaSetting('wa_cloud_number', '')),
        'version'  => trim((string)getWaSetting('wa_cloud_version', '')) ?: 'v21.0',
    ];
}

/** True when Meta Cloud API is the selected provider AND minimally configured. */
function waCloudEnabled(): bool {
    if (getWaSetting('wa_provider', 'gateway') !== 'cloud') { return false; }
    $c = waCloudCfg();
    return $c['token'] !== '' && $c['phone_id'] !== '';
}

/**
 * Low-level Graph API call. Returns:
 *   ['ok'=>bool,'http'=>int,'json'=>array|null,'error'=>string,'error_code'=>string]
 */
function waGraph(string $method, string $path, ?array $body = null, ?array $query = null, int $timeout = 25): array {
    $c = waCloudCfg();
    if ($c['token'] === '') {
        return ['ok' => false, 'http' => 0, 'json' => null, 'error' => 'No access token configured.', 'error_code' => ''];
    }
    $url = 'https://graph.facebook.com/' . $c['version'] . '/' . ltrim($path, '/');
    if ($query) { $url .= '?' . http_build_query($query); }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $c['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => $timeout,
    ];
    if ($body !== null) { $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    curl_setopt_array($ch, $opts);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'http' => 0, 'json' => null, 'error' => $curlErr ?: 'Network error contacting Meta.', 'error_code' => ''];
    }
    $json = json_decode((string)$resp, true);
    if ($http >= 200 && $http < 300) {
        return ['ok' => true, 'http' => $http, 'json' => is_array($json) ? $json : [], 'error' => '', 'error_code' => ''];
    }
    // Extract Meta's structured error.
    $emsg = 'HTTP ' . $http;
    $ecode = '';
    if (is_array($json) && isset($json['error'])) {
        $e = $json['error'];
        $emsg = $e['message'] ?? $emsg;
        $ecode = (string)($e['code'] ?? '');
        if (!empty($e['error_user_msg'])) { $emsg = $e['error_user_msg']; }
        if (!empty($e['error_data']['details'])) { $emsg .= ' — ' . $e['error_data']['details']; }
    }
    return ['ok' => false, 'http' => $http, 'json' => is_array($json) ? $json : null, 'error' => $emsg, 'error_code' => $ecode];
}

/** Verify the connection: returns the phone's verified name, number and quality. */
function waCloudTest(): array {
    $c = waCloudCfg();
    if ($c['phone_id'] === '') { return ['ok' => false, 'error' => 'Enter the Phone Number ID first.']; }
    $r = waGraph('GET', $c['phone_id'], null, ['fields' => 'verified_name,display_phone_number,quality_rating,code_verification_status']);
    if (!$r['ok']) { return ['ok' => false, 'error' => $r['error']]; }
    return ['ok' => true, 'info' => $r['json']];
}

/**
 * Send a fully-formed message object via /{phone_id}/messages.
 * $message is the part AFTER messaging_product/to (e.g. ['type'=>'text','text'=>[...]]).
 * Returns ['ok'=>bool,'wamid'=>string,'error'=>string,'error_code'=>string,'raw'=>array|null].
 */
function waCloudSendRaw(string $to, array $message): array {
    $c = waCloudCfg();
    if ($c['phone_id'] === '') { return ['ok' => false, 'wamid' => '', 'error' => 'Cloud API not configured.', 'error_code' => '', 'raw' => null]; }
    $payload = array_merge(['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to], $message);
    $r = waGraph('POST', $c['phone_id'] . '/messages', $payload);
    if ($r['ok']) {
        $wamid = $r['json']['messages'][0]['id'] ?? '';
        return ['ok' => true, 'wamid' => $wamid, 'error' => '', 'error_code' => '', 'raw' => $r['json']];
    }
    return ['ok' => false, 'wamid' => '', 'error' => $r['error'], 'error_code' => $r['error_code'], 'raw' => $r['json']];
}

// ---- Message payload builders --------------------------------------------

function waMsgText(string $body, bool $previewUrl = true): array {
    return ['type' => 'text', 'text' => ['preview_url' => $previewUrl, 'body' => $body]];
}
/** $type: image|document|video|audio. Media by public link. */
function waMsgMedia(string $type, string $link, string $caption = '', string $filename = ''): array {
    $obj = ['link' => $link];
    if ($caption !== '' && $type !== 'audio') { $obj['caption'] = $caption; }
    if ($type === 'document' && $filename !== '') { $obj['filename'] = $filename; }
    return ['type' => $type, $type => $obj];
}
/** Interactive reply buttons. $buttons = [['id'=>,'title'=>], ...] (max 3). */
function waMsgButtons(string $body, array $buttons, string $header = '', string $footer = ''): array {
    $btns = [];
    foreach (array_slice($buttons, 0, 3) as $i => $b) {
        $btns[] = ['type' => 'reply', 'reply' => ['id' => (string)($b['id'] ?? ('btn_' . $i)), 'title' => mb_substr((string)($b['title'] ?? ''), 0, 20)]];
    }
    $interactive = ['type' => 'button', 'body' => ['text' => $body], 'action' => ['buttons' => $btns]];
    if ($header !== '') { $interactive['header'] = ['type' => 'text', 'text' => mb_substr($header, 0, 60)]; }
    if ($footer !== '') { $interactive['footer'] = ['text' => mb_substr($footer, 0, 60)]; }
    return ['type' => 'interactive', 'interactive' => $interactive];
}
/** Interactive list. $sections = [['title'=>,'rows'=>[['id','title','description'],...]],...]. */
function waMsgList(string $body, string $buttonText, array $sections, string $header = '', string $footer = ''): array {
    $interactive = ['type' => 'list', 'body' => ['text' => $body],
        'action' => ['button' => mb_substr($buttonText ?: 'Menu', 0, 20), 'sections' => $sections]];
    if ($header !== '') { $interactive['header'] = ['type' => 'text', 'text' => mb_substr($header, 0, 60)]; }
    if ($footer !== '') { $interactive['footer'] = ['text' => mb_substr($footer, 0, 60)]; }
    return ['type' => 'interactive', 'interactive' => $interactive];
}
/** Template send. $components already in Cloud API shape (may be empty). */
function waMsgTemplate(string $name, string $lang, array $components = []): array {
    $t = ['name' => $name, 'language' => ['code' => $lang ?: 'en']];
    if ($components) { $t['components'] = $components; }
    return ['type' => 'template', 'template' => $t];
}

// ---- Message Templates (WABA) --------------------------------------------

/** Live list of templates from Meta (real status: APPROVED/PENDING/REJECTED). */
function waTemplatesList(int $limit = 200): array {
    $c = waCloudCfg();
    if ($c['waba_id'] === '') { return ['ok' => false, 'error' => 'Enter the WhatsApp Business Account (WABA) ID to manage templates.', 'templates' => []]; }
    $r = waGraph('GET', $c['waba_id'] . '/message_templates', null, [
        'fields' => 'name,status,category,language,components,quality_score,rejected_reason',
        'limit'  => $limit,
    ]);
    if (!$r['ok']) { return ['ok' => false, 'error' => $r['error'], 'templates' => []]; }
    return ['ok' => true, 'error' => '', 'templates' => $r['json']['data'] ?? []];
}

/**
 * Create (submit for approval) a template.
 * $def = ['name','language','category','components'=>[...]] already in Meta shape.
 */
function waTemplateCreate(array $def): array {
    $c = waCloudCfg();
    if ($c['waba_id'] === '') { return ['ok' => false, 'error' => 'WABA ID not configured.']; }
    $r = waGraph('POST', $c['waba_id'] . '/message_templates', $def);
    if (!$r['ok']) { return ['ok' => false, 'error' => $r['error']]; }
    return ['ok' => true, 'id' => $r['json']['id'] ?? '', 'status' => $r['json']['status'] ?? 'PENDING'];
}

/** Edit an existing template's components/category (by its template id). */
function waTemplateEdit(string $templateId, array $def): array {
    if ($templateId === '') { return ['ok' => false, 'error' => 'Missing template id.']; }
    $r = waGraph('POST', $templateId, $def);
    if (!$r['ok']) { return ['ok' => false, 'error' => $r['error']]; }
    return ['ok' => true];
}

/** Delete a template by name (optionally a specific version by id). */
function waTemplateDelete(string $name, string $templateId = ''): array {
    $c = waCloudCfg();
    if ($c['waba_id'] === '') { return ['ok' => false, 'error' => 'WABA ID not configured.']; }
    $q = ['name' => $name];
    if ($templateId !== '') { $q['hsm_id'] = $templateId; }
    $r = waGraph('DELETE', $c['waba_id'] . '/message_templates', null, $q);
    if (!$r['ok']) { return ['ok' => false, 'error' => $r['error']]; }
    return ['ok' => true];
}

/**
 * Transport used by dispatchWhatsAppLog() when provider=cloud. Sends the queued
 * row as a text (or media) message and writes the result back to whatsapp_logs.
 * Returns bool ok. Never throws.
 */
function waCloudDispatch(array $log, int $logId): bool {
    try {
        $to = preg_replace('/[^0-9]/', '', (string)$log['number']);
        if ($to === '') {
            db_update('whatsapp_logs', ['status' => 'failed', 'response' => 'Invalid number', 'retry_count' => (int)$log['retry_count'] + 1], ['id' => $logId]);
            return false;
        }
        $media = trim((string)($log['media_url'] ?? ''));
        if ($media !== '') {
            // Guess media type from the extension.
            $ext = strtolower(pathinfo(parse_url($media, PHP_URL_PATH) ?: $media, PATHINFO_EXTENSION));
            $type = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? 'image'
                  : (in_array($ext, ['mp4', '3gp'], true) ? 'video'
                  : (in_array($ext, ['mp3', 'ogg', 'aac', 'amr'], true) ? 'audio' : 'document'));
            $message = waMsgMedia($type, $media, (string)$log['message'], $type === 'document' ? ('attachment.' . ($ext ?: 'pdf')) : '');
        } else {
            $message = waMsgText((string)$log['message']);
        }
        $res = waCloudSendRaw($to, $message);
        $resp = json_encode([
            'provider' => 'cloud',
            'wamid'    => $res['wamid'],
            'error'    => $res['error'],
            'code'     => $res['error_code'],
        ], JSON_UNESCAPED_UNICODE);
        db_update('whatsapp_logs', [
            'status'      => $res['ok'] ? 'sent' : 'failed',
            'response'    => $resp,
            'retry_count' => (int)$log['retry_count'] + 1,
            'sent_at'     => $res['ok'] ? date('Y-m-d H:i:s') : null,
        ], ['id' => $logId]);
        return $res['ok'];
    } catch (Throwable $e) {
        error_log('waCloudDispatch: ' . $e->getMessage());
        db_update('whatsapp_logs', ['status' => 'failed', 'response' => 'Cloud error: ' . $e->getMessage(), 'retry_count' => (int)$log['retry_count'] + 1], ['id' => $logId]);
        return false;
    }
}

/**
 * Log an outbound Cloud message that was sent directly (template / interactive /
 * media test) so it shows up in the Conversations view, then send it.
 * Returns the send result array from waCloudSendRaw plus 'log_id'.
 */
function waCloudSendLogged(string $to, array $message, string $summary, ?int $tenantId = null, string $triggerKey = 'cloud'): array {
    $to = preg_replace('/[^0-9]/', '', $to);
    $logId = db_insert('whatsapp_logs', [
        'tenant_id'   => $tenantId,
        'trigger_key' => $triggerKey,
        'number'      => $to,
        'message'     => $summary,
        'media_url'   => null,
        'status'      => 'pending',
    ]);
    $res = waCloudSendRaw($to, $message);
    db_update('whatsapp_logs', [
        'status'   => $res['ok'] ? 'sent' : 'failed',
        'response' => json_encode(['provider' => 'cloud', 'wamid' => $res['wamid'], 'error' => $res['error'], 'code' => $res['error_code']], JSON_UNESCAPED_UNICODE),
        'sent_at'  => $res['ok'] ? date('Y-m-d H:i:s') : null,
    ], ['id' => $logId]);
    $res['log_id'] = $logId;
    return $res;
}
