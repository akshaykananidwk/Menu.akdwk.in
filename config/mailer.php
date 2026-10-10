<?php
/**
 * AK Menu System — E-mail engine (dependency-free).
 *
 * WHY HAND-ROLLED SMTP
 * --------------------
 * This project has no Composer and no build step (see DEV_CONTRACT.md), so
 * PHPMailer/Symfony Mailer are not available. Everything below speaks SMTP
 * directly over a socket: EHLO → STARTTLS → AUTH → MAIL FROM → RCPT TO → DATA.
 * If no SMTP host is configured we fall back to PHP's mail() so a freshly
 * installed cPanel account still sends something.
 *
 * ARCHITECTURE (mirrors the WhatsApp side on purpose)
 * ---------------------------------------------------
 *   mailQueue()        → writes an email_logs row with status='pending'
 *   dispatchEmailLog() → actually delivers one row and records the outcome
 *   mailSend()         → queue + try to deliver right now (used for OTP /
 *                        password-reset mails, where waiting is not an option)
 *   processEmailQueue()→ drained every minute by the master cron
 *                        (cron job key: email_queue, see config/scheduler.php)
 *
 * Nothing here ever throws into the caller. A mail failure must never break a
 * signup, a login or an order — it is logged and the user flow continues.
 *
 * SECURITY NOTES
 * --------------
 * - Header injection: every address / subject passes through mailStripCrlf().
 * - Credentials live in the `settings` table and are never echoed back; the
 *   admin test tool reports the SMTP conversation with AUTH lines redacted.
 * - Password-reset tokens are stored as SHA-256 hashes only.
 */

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

/**
 * Current e-mail configuration, read from the settings table.
 * Keys: enabled, host, port, user, pass, secure(tls|ssl|none), from_email,
 *       from_name, reply_to.
 */
function mailerCfg(): array {
    static $cfg = null;
    if ($cfg !== null) { return $cfg; }

    $host = trim((string)getSetting('smtp_host', ''));
    $port = (int)getSetting('smtp_port', '587');
    if ($port <= 0) { $port = 587; }

    $secure = strtolower(trim((string)getSetting('smtp_secure', '')));
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
        // Sensible default from the port when the admin never picked one.
        $secure = $port === 465 ? 'ssl' : ($port === 25 ? 'none' : 'tls');
    }

    $fromEmail = trim((string)getSetting('smtp_from_email', ''));
    if ($fromEmail === '') { $fromEmail = trim((string)getSetting('contact_email', '')); }
    if ($fromEmail === '') { $fromEmail = trim((string)getSetting('smtp_user', '')); }

    $fromName = trim((string)getSetting('smtp_from_name', ''));
    if ($fromName === '') { $fromName = (string)getSetting('site_name', APP_NAME); }

    $cfg = [
        'enabled'    => getSetting('smtp_enabled', '1') !== '0',
        'host'       => $host,
        'port'       => $port,
        'user'       => trim((string)getSetting('smtp_user', '')),
        'pass'       => (string)getSetting('smtp_pass', ''),
        'secure'     => $secure,
        'from_email' => $fromEmail,
        'from_name'  => $fromName,
        'reply_to'   => trim((string)getSetting('smtp_reply_to', '')) ?: $fromEmail,
    ];
    return $cfg;
}

/** True when the platform is allowed to send mail at all. */
function mailerEnabled(): bool {
    $c = mailerCfg();
    if (!$c['enabled']) { return false; }
    // We can send either via SMTP (host set) or via the local mail() transport.
    return $c['from_email'] !== '' || $c['host'] !== '';
}

/** True when a real SMTP relay is configured (as opposed to mail()). */
function mailerHasSmtp(): bool {
    $c = mailerCfg();
    return $c['enabled'] && $c['host'] !== '';
}

/** Human-readable description of the active transport, for the admin UI. */
function mailerTransportLabel(): string {
    $c = mailerCfg();
    if (!$c['enabled']) { return 'Disabled'; }
    if ($c['host'] !== '') {
        return 'SMTP · ' . $c['host'] . ':' . $c['port'] . ' (' . strtoupper($c['secure']) . ')';
    }
    return 'PHP mail() — server default';
}

/** Strip CR/LF so a value can never inject extra mail headers. */
function mailStripCrlf(string $v): string {
    return trim(str_replace(["\r", "\n", "\0"], ' ', $v));
}

/** Validate an address without throwing. */
function mailValidAddress(string $email): bool {
    $email = trim($email);
    return $email !== '' && (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** RFC 2047 encode a header value only when it contains non-ASCII bytes. */
function mailEncodeHeader(string $v): string {
    $v = mailStripCrlf($v);
    if (!preg_match('/[^\x20-\x7E]/', $v)) { return $v; }   // plain ASCII — as-is
    return '=?UTF-8?B?' . base64_encode($v) . '?=';
}

/** "Name <email>" with the display name safely encoded. */
function mailFormatAddress(string $email, string $name = ''): string {
    $email = mailStripCrlf($email);
    $name  = mailStripCrlf($name);
    return $name === '' ? $email : mailEncodeHeader($name) . ' <' . $email . '>';
}

// -----------------------------------------------------------------------------
// HTML layout — one branded shell every system e-mail is poured into
// -----------------------------------------------------------------------------

/**
 * Wrap body HTML in a responsive, table-based e-mail shell using the
 * platform's own brand colours. Inline CSS only (Gmail strips <style>).
 *
 * @param array $opts cta_url, cta_label, preheader, footer_note
 */
function mailLayout(string $heading, string $bodyHtml, array $opts = []): string {
    $site      = (string)getSetting('site_name', APP_NAME);
    $primary   = (string)getSetting('primary_color', '#e63946');
    $secondary = (string)getSetting('secondary_color', '#1d3557');
    $support   = (string)getSetting('support_whatsapp', '');
    $poweredBy = (string)getSetting('powered_by', POWERED_BY);
    $preheader = (string)($opts['preheader'] ?? '');
    $ctaUrl    = (string)($opts['cta_url'] ?? '');
    $ctaLabel  = (string)($opts['cta_label'] ?? '');
    $footNote  = (string)($opts['footer_note'] ?? '');
    $year      = date('Y');

    $cta = '';
    if ($ctaUrl !== '' && $ctaLabel !== '') {
        $cta = '<tr><td style="padding:8px 32px 28px;">'
             . '<a href="' . e($ctaUrl) . '" style="display:inline-block;background:' . e($primary) . ';color:#ffffff;'
             . 'text-decoration:none;font-weight:600;font-size:15px;padding:13px 28px;border-radius:8px;">'
             . e($ctaLabel) . '</a></td></tr>';
    }

    $pre = $preheader !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($preheader) . '</div>'
        : '';

    $note = $footNote !== ''
        ? '<p style="margin:0 0 10px;font-size:12px;color:#8a94a6;">' . $footNote . '</p>'
        : '';

    $supportLine = $support !== ''
        ? '<p style="margin:0 0 6px;font-size:12px;color:#8a94a6;">Need help? WhatsApp us on ' . e($support) . '</p>'
        : '';

    return '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f6fa;">'
        . $pre
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fa;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;'
        . 'border-radius:14px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;'
        . 'box-shadow:0 2px 12px rgba(16,24,40,.06);">'
        . '<tr><td style="background:' . e($secondary) . ';padding:22px 32px;">'
        . '<span style="color:#ffffff;font-size:19px;font-weight:700;letter-spacing:.2px;">' . e($site) . '</span>'
        . '</td></tr>'
        . '<tr><td style="padding:30px 32px 6px;">'
        . '<h1 style="margin:0 0 14px;font-size:21px;line-height:1.35;color:#101828;font-weight:700;">' . e($heading) . '</h1>'
        . '<div style="font-size:15px;line-height:1.65;color:#475467;">' . $bodyHtml . '</div>'
        . '</td></tr>'
        . $cta
        . '<tr><td style="padding:0 32px 26px;">'
        . '<hr style="border:none;border-top:1px solid #eaecf0;margin:0 0 16px;">'
        . $note
        . $supportLine
        . '<p style="margin:0;font-size:12px;color:#aeb6c4;">© ' . $year . ' ' . e($site) . ' · ' . e($poweredBy) . '</p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Crude but effective HTML → plain-text fallback for the multipart part. */
function mailHtmlToText(string $html): string {
    // Drop <head> wholesale: its <title> and the hidden preheader are chrome,
    // not content, and strip_tags() would otherwise paste them onto line 1.
    $t = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html);
    $t = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string)$t);
    $t = preg_replace('#<div[^>]*display:\s*none.*?</div>#is', '', (string)$t);
    $t = preg_replace('#<br\s*/?>#i', "\n", (string)$t);
    $t = preg_replace('#</(p|div|tr|h1|h2|h3|li)>#i', "\n", (string)$t);
    // Keep link targets visible in the text part.
    $t = preg_replace('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', (string)$t);
    $t = strip_tags((string)$t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/\n{3,}/", "\n\n", (string)$t);
    return trim((string)$t);
}

// -----------------------------------------------------------------------------
// Queue + dispatch
// -----------------------------------------------------------------------------

/** How many delivery attempts a queued e-mail gets before it is given up on. */
if (!defined('MAIL_MAX_TRIES')) { define('MAIL_MAX_TRIES', 3); }

/**
 * Queue one e-mail. Returns the email_logs id, or 0 when it could not even be
 * queued (bad address / mail disabled / table missing).
 *
 * @param array $opts tenant_id, trigger, name
 */
function mailQueue(string $to, string $subject, string $html, array $opts = []): int {
    if (!mailValidAddress($to)) { return 0; }
    try {
        return (int)db_insert('email_logs', [
            'tenant_id'   => isset($opts['tenant_id']) ? (int)$opts['tenant_id'] : null,
            'to_email'    => mailStripCrlf($to),
            'to_name'     => isset($opts['name']) ? mailStripCrlf((string)$opts['name']) : null,
            'subject'     => mb_substr(mailStripCrlf($subject), 0, 250),
            'body'        => $html,
            'trigger_key' => isset($opts['trigger']) ? (string)$opts['trigger'] : null,
            'status'      => mailerEnabled() ? 'pending' : 'failed',
            'response'    => mailerEnabled() ? null : 'E-mail sending is disabled or not configured.',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('mailQueue failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Queue AND deliver immediately. Use for anything the user is waiting on
 * (OTP, password reset). Returns true when the relay accepted the message.
 */
function mailSend(string $to, string $subject, string $html, array $opts = []): bool {
    $id = mailQueue($to, $subject, $html, $opts);
    if ($id <= 0) { return false; }
    return dispatchEmailLog($id, (int)($opts['timeout'] ?? 20));
}

/**
 * Deliver a single queued row and record the outcome on it.
 * Never throws; a transport failure marks the row 'failed' with the reason.
 */
function dispatchEmailLog(int $id, int $timeout = 20): bool {
    if ($id <= 0) { return false; }
    try {
        $log = db_one('SELECT * FROM ' . tbl('email_logs') . ' WHERE id = :id', [':id' => $id]);
    } catch (Throwable $e) {
        error_log('dispatchEmailLog read failed: ' . $e->getMessage());
        return false;
    }
    if (!$log) { return false; }

    if (!mailerEnabled()) {
        mailMarkLog($id, 'failed', 'E-mail sending is disabled or not configured.');
        return false;
    }

    $err = '';
    $ok  = mailTransport(
        (string)$log['to_email'],
        (string)($log['to_name'] ?? ''),
        (string)$log['subject'],
        (string)$log['body'],
        $timeout,
        $err
    );

    if ($ok) {
        mailMarkLog($id, 'sent', $err !== '' ? $err : 'Accepted by server.');
        return true;
    }

    // A relay being briefly unreachable must not lose the message. Stay
    // 'pending' so the next queue tick tries again; only after MAIL_MAX_TRIES
    // attempts does the row become permanently 'failed'. (processEmailQueue
    // selects on retry_count < MAIL_MAX_TRIES, so this terminates.)
    $tries = (int)($log['retry_count'] ?? 0) + 1;
    mailMarkLog(
        $id,
        $tries >= MAIL_MAX_TRIES ? 'failed' : 'pending',
        ($err !== '' ? $err : 'Unknown error.') . ' (attempt ' . $tries . ' of ' . MAIL_MAX_TRIES . ')',
        true
    );
    return false;
}

/** Update a log row's status / response (and bump retry_count on failure). */
function mailMarkLog(int $id, string $status, string $response, bool $bumpRetry = false): void {
    try {
        $data = [
            'status'   => $status,
            'response' => mb_substr($response, 0, 2000),
        ];
        if ($status === 'sent') { $data['sent_at'] = date('Y-m-d H:i:s'); }
        if ($bumpRetry) {
            db_query('UPDATE ' . tbl('email_logs') . ' SET retry_count = retry_count + 1 WHERE id = :id', [':id' => $id]);
        }
        db_update('email_logs', $data, ['id' => $id]);
    } catch (Throwable $e) {
        error_log('mailMarkLog failed: ' . $e->getMessage());
    }
}

/**
 * Drain the pending queue. Called every minute by the master cron
 * (config/scheduler.php → job key `email_queue`).
 *
 * @return int number of messages accepted by the relay
 */
function processEmailQueue(int $limit = 25, int $timeout = 20): int {
    if (!mailerEnabled()) { return 0; }
    try {
        $rows = db_all(
            'SELECT id FROM ' . tbl('email_logs') . "
             WHERE status = 'pending' AND retry_count < " . MAIL_MAX_TRIES . "
             ORDER BY id ASC LIMIT " . max(1, min(200, $limit))
        );
    } catch (Throwable $e) {
        return 0;
    }
    $sent = 0;
    foreach ($rows as $r) {
        if (dispatchEmailLog((int)$r['id'], $timeout)) { $sent++; }
    }
    return $sent;
}

// -----------------------------------------------------------------------------
// Transport
// -----------------------------------------------------------------------------

/**
 * Send one message through the configured transport.
 * Writes a human-readable reason into $err on failure.
 */
function mailTransport(string $to, string $toName, string $subject, string $html, int $timeout, string &$err): bool {
    $cfg = mailerCfg();
    if (!mailValidAddress($to)) { $err = 'Invalid recipient address.'; return false; }
    if ($cfg['from_email'] === '') {
        $err = 'No "From" address configured (Admin → Settings → SMTP).';
        return false;
    }

    if ($cfg['host'] !== '') {
        return smtpDeliver($cfg, $to, $toName, $subject, $html, $timeout, $err);
    }

    // ---- Fallback: PHP mail() -----------------------------------------------
    if (!function_exists('mail')) {
        $err = 'No SMTP host configured and PHP mail() is unavailable.';
        return false;
    }
    [$headers, $body] = mailBuildMime($cfg, $to, $toName, $subject, $html, false);
    $ok = @mail(
        mailStripCrlf($to),
        mailEncodeHeader($subject),
        $body,
        implode("\r\n", $headers),
        '-f' . mailStripCrlf($cfg['from_email'])
    );
    if (!$ok) { $err = 'PHP mail() returned false — the server rejected the message.'; }
    else { $err = 'Handed to PHP mail() (server default transport).'; }
    return (bool)$ok;
}

/**
 * Build the MIME headers + body for a multipart/alternative message.
 *
 * @param bool $withToAndSubject include To:/Subject: in the header list
 *                               (SMTP DATA needs them; mail() adds them itself)
 * @return array{0:string[],1:string}
 */
function mailBuildMime(array $cfg, string $to, string $toName, string $subject, string $html, bool $withToAndSubject): array {
    $boundary = 'akm_' . bin2hex(random_bytes(12));
    $text     = mailHtmlToText($html);
    $hostPart = $cfg['host'] !== '' ? $cfg['host'] : ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $headers = [];
    if ($withToAndSubject) {
        $headers[] = 'To: ' . mailFormatAddress($to, $toName);
        $headers[] = 'Subject: ' . mailEncodeHeader($subject);
    }
    $headers[] = 'From: ' . mailFormatAddress($cfg['from_email'], $cfg['from_name']);
    if ($cfg['reply_to'] !== '' && $cfg['reply_to'] !== $cfg['from_email']) {
        $headers[] = 'Reply-To: ' . mailFormatAddress($cfg['reply_to']);
    }
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . mailStripCrlf($hostPart) . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'X-Mailer: ' . APP_NAME;
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body  = "This is a multi-part message in MIME format.\r\n\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text), 76, "\r\n") . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html), 76, "\r\n") . "\r\n";
    $body .= '--' . $boundary . "--\r\n";

    return [$headers, $body];
}

/**
 * Read one SMTP reply (handles multi-line 250-FOO ... 250 BAR replies).
 * @param resource $fp
 */
function smtpReadReply($fp, int &$code): string {
    $data = '';
    $code = 0;
    while (($line = fgets($fp, 2048)) !== false) {
        $data .= $line;
        // A reply is finished when the 4th character is a space (not '-').
        if (strlen($line) < 4 || $line[3] !== '-') { break; }
    }
    if (preg_match('/^(\d{3})/', $data, $m)) { $code = (int)$m[1]; }
    return rtrim($data, "\r\n");
}

/**
 * Speak SMTP to the configured relay. Returns true when the server accepted
 * the message (250 after the final dot).
 *
 * The full conversation is collected into $err so the admin test tool can show
 * exactly where it broke. AUTH payloads are never included.
 */
function smtpDeliver(array $cfg, string $to, string $toName, string $subject, string $html, int $timeout, string &$err): bool {
    $timeout = max(5, min(60, $timeout));
    $trace   = [];
    $fail    = function (string $why) use (&$err, &$trace): bool {
        $err = $why . ($trace ? ' | ' . implode(' | ', array_slice($trace, -6)) : '');
        return false;
    };

    $transport = $cfg['secure'] === 'ssl' ? 'ssl://' : '';
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
    ]);

    $errNo = 0; $errStr = '';
    $fp = @stream_socket_client(
        $transport . $cfg['host'] . ':' . $cfg['port'],
        $errNo, $errStr, $timeout, STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$fp) {
        return $fail('Cannot connect to ' . $cfg['host'] . ':' . $cfg['port'] . ' — ' . ($errStr ?: 'connection failed') . ' (' . $errNo . ')');
    }
    stream_set_timeout($fp, $timeout);

    $code = 0;
    $say = function (string $cmd, string $label = '') use ($fp, &$code, &$trace): string {
        fwrite($fp, $cmd . "\r\n");
        $reply = smtpReadReply($fp, $code);
        $trace[] = ($label !== '' ? $label : explode(' ', $cmd)[0]) . ' → ' . preg_replace('/\s+/', ' ', $reply);
        return $reply;
    };

    try {
        // ---- Greeting --------------------------------------------------------
        $greet = smtpReadReply($fp, $code);
        $trace[] = 'CONNECT → ' . preg_replace('/\s+/', ' ', $greet);
        if ($code !== 220) { fclose($fp); return $fail('Server did not greet us (expected 220).'); }

        $helo = mailStripCrlf($_SERVER['HTTP_HOST'] ?? 'localhost');
        $helo = preg_replace('/[^A-Za-z0-9.\-]/', '', $helo) ?: 'localhost';

        $say('EHLO ' . $helo);
        if ($code !== 250) {
            $say('HELO ' . $helo);
            if ($code !== 250) { fclose($fp); return $fail('EHLO/HELO refused.'); }
        }

        // ---- STARTTLS --------------------------------------------------------
        if ($cfg['secure'] === 'tls') {
            $say('STARTTLS');
            if ($code !== 220) { fclose($fp); return $fail('Server refused STARTTLS — try port 465 with SSL, or port 25 with None.'); }
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                fclose($fp);
                return $fail('TLS handshake failed (certificate or protocol mismatch).');
            }
            $trace[] = 'STARTTLS → handshake ok';
            // RFC 3207: re-issue EHLO inside the encrypted channel.
            $say('EHLO ' . $helo, 'EHLO(tls)');
            if ($code !== 250) { fclose($fp); return $fail('EHLO after STARTTLS refused.'); }
        }

        // ---- AUTH ------------------------------------------------------------
        if ($cfg['user'] !== '') {
            $say('AUTH LOGIN');
            if ($code === 334) {
                fwrite($fp, base64_encode($cfg['user']) . "\r\n");
                smtpReadReply($fp, $code);
                if ($code === 334) {
                    fwrite($fp, base64_encode($cfg['pass']) . "\r\n");
                    smtpReadReply($fp, $code);
                }
                $trace[] = 'AUTH LOGIN → ' . $code;
            }
            if ($code !== 235) {
                // Fall back to AUTH PLAIN (some relays only offer this).
                $plain = base64_encode("\0" . $cfg['user'] . "\0" . $cfg['pass']);
                fwrite($fp, 'AUTH PLAIN ' . $plain . "\r\n");
                smtpReadReply($fp, $code);
                $trace[] = 'AUTH PLAIN → ' . $code;
            }
            if ($code !== 235) {
                fclose($fp);
                return $fail('Authentication failed (' . $code . ') — check the SMTP username and password.');
            }
            $trace[] = 'AUTH → accepted';
        }

        // ---- Envelope --------------------------------------------------------
        $say('MAIL FROM:<' . mailStripCrlf($cfg['from_email']) . '>');
        if ($code !== 250) { $say('QUIT'); fclose($fp); return $fail('MAIL FROM rejected — the "From" address must belong to this SMTP account.'); }

        $say('RCPT TO:<' . mailStripCrlf($to) . '>');
        if ($code !== 250 && $code !== 251) { $say('QUIT'); fclose($fp); return $fail('Recipient rejected (' . $code . ').'); }

        $say('DATA');
        if ($code !== 354) { $say('QUIT'); fclose($fp); return $fail('Server refused DATA (' . $code . ').'); }

        [$headers, $body] = mailBuildMime($cfg, $to, $toName, $subject, $html, true);
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // Dot-stuffing (RFC 5321 §4.5.2): a line of a single "." ends DATA.
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($fp, $payload . "\r\n.\r\n");
        $reply = smtpReadReply($fp, $code);
        $trace[] = 'DATA → ' . preg_replace('/\s+/', ' ', $reply);

        $accepted = ($code === 250);
        $say('QUIT');
        fclose($fp);

        if (!$accepted) { return $fail('Message rejected after DATA (' . $code . ').'); }
        $err = 'Accepted: ' . preg_replace('/\s+/', ' ', $reply);
        return true;

    } catch (Throwable $e) {
        if (is_resource($fp)) { @fclose($fp); }
        return $fail('SMTP error: ' . $e->getMessage());
    }
}

/**
 * Admin "Send test e-mail" helper.
 * @return array{ok:bool,message:string,transport:string,log_id:int}
 */
function mailerTest(string $to): array {
    $transport = mailerTransportLabel();
    if (!mailValidAddress($to)) {
        return ['ok' => false, 'message' => 'Please enter a valid e-mail address.', 'transport' => $transport, 'log_id' => 0];
    }
    if (!mailerEnabled()) {
        return ['ok' => false, 'message' => 'E-mail is turned off, or no From address is set. Fill in the SMTP tab and save first.', 'transport' => $transport, 'log_id' => 0];
    }

    $site = (string)getSetting('site_name', APP_NAME);
    $html = mailLayout('Your e-mail settings work 🎉',
        '<p style="margin:0 0 12px;">This is a test message from <strong>' . e($site) . '</strong>.</p>'
        . '<p style="margin:0 0 12px;">If you are reading this, registration e-mails, password-reset links and e-mail OTPs will all be delivered correctly.</p>'
        . '<p style="margin:0;font-size:13px;color:#667085;">Transport: ' . e($transport) . '<br>Sent at: ' . e(date('d M Y, h:i A')) . '</p>',
        ['preheader' => 'SMTP test from ' . $site]
    );

    $id  = mailQueue($to, 'Test e-mail from ' . $site, $html, ['trigger' => 'smtp_test', 'name' => 'Admin']);
    $ok  = $id > 0 ? dispatchEmailLog($id, 25) : false;
    $row = $id > 0 ? db_one('SELECT status, response FROM ' . tbl('email_logs') . ' WHERE id = :id', [':id' => $id]) : null;

    return [
        'ok'        => $ok,
        'message'   => (string)($row['response'] ?? 'Could not queue the message.'),
        'transport' => $transport,
        'log_id'    => (int)$id,
    ];
}

// -----------------------------------------------------------------------------
// Application e-mails
// -----------------------------------------------------------------------------

/**
 * Welcome / registration e-mail.
 * Deliberately contains NO password — only a link to the login page.
 */
function sendWelcomeEmail(array $tenant): int {
    $email = trim((string)($tenant['email'] ?? ''));
    if (!mailValidAddress($email)) { return 0; }

    $site     = (string)getSetting('site_name', APP_NAME);
    $name     = (string)($tenant['owner_name'] ?? $tenant['restaurant_name'] ?? 'there');
    $rest     = (string)($tenant['restaurant_name'] ?? '');
    $slug     = (string)($tenant['slug'] ?? '');
    $loginUrl = BASE_URL . '/client/login.php';
    $menuUrl  = $slug !== '' && function_exists('publicMenuUrl') ? publicMenuUrl($slug) : '';

    $body  = '<p style="margin:0 0 14px;">Hi ' . e($name) . ',</p>';
    $body .= '<p style="margin:0 0 14px;">Your <strong>' . e($site) . '</strong> account is ready'
           . ($rest !== '' ? ' for <strong>' . e($rest) . '</strong>' : '') . '. '
           . 'You can sign in with this e-mail address or your mobile number.</p>';
    $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:14px;color:#475467;">'
           . '<tr><td style="padding:3px 12px 3px 0;color:#98a2b3;">Login e-mail</td><td><strong>' . e($email) . '</strong></td></tr>'
           . ($tenant['mobile'] ?? '' ? '<tr><td style="padding:3px 12px 3px 0;color:#98a2b3;">Mobile</td><td><strong>' . e((string)$tenant['mobile']) . '</strong></td></tr>' : '')
           . ($menuUrl !== '' ? '<tr><td style="padding:3px 12px 3px 0;color:#98a2b3;">Your menu link</td><td><a href="' . e($menuUrl) . '" style="color:#1d3557;">' . e($menuUrl) . '</a></td></tr>' : '')
           . '</table>';
    $body .= '<p style="margin:0 0 8px;font-weight:600;color:#101828;">Your first 4 steps</p>';
    $body .= '<ol style="margin:0 0 14px;padding-left:20px;">'
           . '<li style="margin-bottom:5px;">Add your categories and dishes in <strong>Menu</strong>.</li>'
           . '<li style="margin-bottom:5px;">Add your tables in <strong>Tables &amp; QR</strong> and print the QR codes.</li>'
           . '<li style="margin-bottom:5px;">Turn on <strong>Direct Ordering</strong> in Settings if you want diners to order.</li>'
           . '<li>Open <strong>Help &amp; Guide</strong> in your panel — it walks you through every feature step by step.</li>'
           . '</ol>';
    $body .= '<p style="margin:0;">Forgot your password at any time? Use <em>Forgot password</em> on the login page and we will e-mail you a reset link.</p>';

    $html = mailLayout('Welcome to ' . $site . ' 🎉', $body, [
        'cta_url'     => $loginUrl,
        'cta_label'   => 'Open my dashboard',
        'preheader'   => 'Your account is ready — here is how to get started.',
        'footer_note' => 'We never send passwords by e-mail. If you did not create this account, please ignore this message.',
    ]);

    // Queued, not sent inline: signup must stay fast. The master cron delivers
    // it within a minute.
    return mailQueue($email, 'Welcome to ' . $site . ' — your account is ready', $html, [
        'tenant_id' => (int)($tenant['id'] ?? 0) ?: null,
        'trigger'   => 'welcome',
        'name'      => $name,
    ]);
}

/**
 * Create a single-use reset token, e-mail the link, and return true when the
 * relay accepted it. Old unused tokens for the same e-mail are invalidated.
 */
function sendPasswordResetEmail(array $tenant, int $validMinutes = 60): bool {
    $email = trim((string)($tenant['email'] ?? ''));
    if (!mailValidAddress($email)) { return false; }

    $token = bin2hex(random_bytes(32));
    try {
        // One live token per address.
        db_query(
            'UPDATE ' . tbl('password_resets') . ' SET used_at = :n WHERE email = :e AND used_at IS NULL',
            [':n' => date('Y-m-d H:i:s'), ':e' => $email]
        );
        db_insert('password_resets', [
            'user_type'  => 'tenant',
            'user_id'    => (int)($tenant['id'] ?? 0),
            'email'      => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + max(5, $validMinutes) * 60),
            'ip'         => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('sendPasswordResetEmail store failed: ' . $e->getMessage());
        return false;
    }

    $site = (string)getSetting('site_name', APP_NAME);
    $name = (string)($tenant['owner_name'] ?? 'there');
    $url  = BASE_URL . '/client/reset_password.php?token=' . $token;

    $body  = '<p style="margin:0 0 14px;">Hi ' . e($name) . ',</p>';
    $body .= '<p style="margin:0 0 14px;">We received a request to reset the password for your <strong>'
           . e($site) . '</strong> account (<strong>' . e($email) . '</strong>).</p>';
    $body .= '<p style="margin:0 0 14px;">Click the button below to choose a new password. '
           . 'This link works <strong>once</strong> and expires in ' . (int)$validMinutes . ' minutes.</p>';
    $body .= '<p style="margin:0 0 6px;font-size:13px;color:#667085;">If the button does not work, copy this link:</p>';
    $body .= '<p style="margin:0;font-size:12px;word-break:break-all;"><a href="' . e($url) . '" style="color:#1d3557;">' . e($url) . '</a></p>';

    $html = mailLayout('Reset your password', $body, [
        'cta_url'     => $url,
        'cta_label'   => 'Choose a new password',
        'preheader'   => 'Your password reset link (valid for ' . (int)$validMinutes . ' minutes).',
        'footer_note' => 'Did not request this? You can safely ignore this e-mail — your password has not changed.',
    ]);

    return mailSend($email, 'Reset your ' . $site . ' password', $html, [
        'tenant_id' => (int)($tenant['id'] ?? 0) ?: null,
        'trigger'   => 'password_reset',
        'name'      => $name,
        'timeout'   => 25,
    ]);
}

/**
 * Look up a reset token and return the row when it is still usable.
 */
function passwordResetLookup(string $token): ?array {
    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) { return null; }
    try {
        $row = db_one(
            'SELECT * FROM ' . tbl('password_resets') . ' WHERE token_hash = :h',
            [':h' => hash('sha256', $token)]
        );
    } catch (Throwable $e) {
        return null;
    }
    if (!$row) { return null; }
    if (!empty($row['used_at'])) { return null; }
    if (strtotime((string)$row['expires_at']) < time()) { return null; }
    return $row;
}

/** Burn a reset token so the link cannot be replayed. */
function passwordResetConsume(int $id): void {
    try {
        db_update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    } catch (Throwable $e) {
        error_log('passwordResetConsume failed: ' . $e->getMessage());
    }
}

/**
 * Send a login/verification OTP to an e-mail address.
 * The OTP itself is generated and rate-limited by the caller.
 */
function sendOtpEmail(string $email, string $otp, string $purpose = 'login', ?string $name = null): bool {
    if (!mailValidAddress($email)) { return false; }
    $site  = (string)getSetting('site_name', APP_NAME);
    $what  = $purpose === 'reset' ? 'reset your password' : 'sign in';

    $body  = '<p style="margin:0 0 14px;">Hi ' . e($name ?: 'there') . ',</p>';
    $body .= '<p style="margin:0 0 18px;">Use this code to ' . e($what) . ' on <strong>' . e($site) . '</strong>:</p>';
    $body .= '<p style="margin:0 0 18px;"><span style="display:inline-block;font-size:30px;letter-spacing:8px;'
           . 'font-weight:700;color:#101828;background:#f2f4f7;border-radius:10px;padding:14px 22px;">' . e($otp) . '</span></p>';
    $body .= '<p style="margin:0;">The code is valid for <strong>5 minutes</strong> and can be used once.</p>';

    $html = mailLayout('Your verification code', $body, [
        'preheader'   => $otp . ' is your ' . $site . ' code (valid 5 minutes).',
        'footer_note' => 'Never share this code with anyone — our team will never ask you for it.',
    ]);

    return mailSend($email, $otp . ' is your ' . $site . ' code', $html, [
        'trigger' => 'otp_' . $purpose,
        'name'    => $name ?: '',
        'timeout' => 25,
    ]);
}

/**
 * Generate + store an OTP for an e-mail identifier and mail it out.
 * Reuses the otp_verifications table (its `mobile` column holds "mobile OR
 * e-mail" since 1.11.0), so verifyOtp() works unchanged for both channels.
 */
function generateEmailOtp(string $email, string $purpose = 'login', ?string $name = null): bool {
    $email = strtolower(trim($email));
    if (!mailValidAddress($email)) { return false; }
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    try {
        db_insert('otp_verifications', [
            'mobile'     => $email,
            'otp'        => $otp,
            'purpose'    => $purpose,
            'expires_at' => date('Y-m-d H:i:s', time() + 300),
        ]);
    } catch (Throwable $e) {
        error_log('generateEmailOtp store failed: ' . $e->getMessage());
        return false;
    }
    return sendOtpEmail($email, $otp, $purpose, $name);
}
