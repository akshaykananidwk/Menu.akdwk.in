<?php
/**
 * Public authentication API — OTP + password-reset flows over WhatsApp OR e-mail.
 *
 * PUBLIC endpoint: no login guard (owners request OTPs before they are logged in).
 * Used by client/login.php (send_otp) and client/forgot_password.php (forgot_password).
 *
 * CHANNEL DETECTION
 * -----------------
 * Every action takes one `login` / `mobile` field and works out the channel
 * itself: anything containing '@' is treated as an e-mail address, everything
 * else as an Indian mobile number. That way one field on the login form serves
 * both, and the owner never has to pick a channel.
 *
 * Contract: JSON via jsonSuccess/jsonError. CSRF required on POST.
 * Security notes:
 *  - Rate-limited per identifier (30s cooldown + max 3 in 5 minutes).
 *  - Anti-enumeration: the response never reveals whether an account exists.
 *    We always answer with the same generic success text and only actually
 *    send when a matching tenant is found.
 *  - Reset links are single-use, 60-minute, SHA-256-hashed tokens.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}

$action = $_GET['action'] ?? '';

/** Read a trimmed request value (POST or GET). */
function reqStr(string $k, string $default = ''): string {
    return trim((string)($_POST[$k] ?? ($_GET[$k] ?? $default)));
}

/**
 * True if this mobile has requested too many OTPs recently.
 * Counts otp_verifications rows created in the last 5 minutes.
 */
function otpRateLimited(string $mobile, string $purpose, int $max = 3, int $windowSec = 300): bool {
    // $windowSec is a controlled constant cast to int — safe to inline.
    $windowSec = (int)$windowSec;
    $count = (int)db_val(
        'SELECT COUNT(*) FROM ' . tbl('otp_verifications') . '
         WHERE mobile = :m AND purpose = :p AND created_at > (NOW() - INTERVAL ' . $windowSec . ' SECOND)',
        [':m' => $mobile, ':p' => $purpose]
    );
    return $count >= $max;
}

/**
 * Work out which channel a typed identifier belongs to.
 *
 * @return array{type:string,id:string,valid:bool}
 *         type 'email' or 'mobile'; id is the canonical form actually stored
 *         in otp_verifications.mobile (lowercased e-mail, or 10 digits).
 */
function authIdentify(string $raw): array {
    $raw = trim($raw);
    if (strpos($raw, '@') !== false) {
        $email = strtolower($raw);
        return ['type' => 'email', 'id' => $email, 'valid' => mailValidAddress($email)];
    }
    $mobile = normalizeMobile($raw);
    return ['type' => 'mobile', 'id' => $mobile, 'valid' => (bool)preg_match('/^[6-9]\d{9}$/', $mobile)];
}

/**
 * Shared 30s-cooldown + 3-per-5-minutes guard. Exits with 429 when tripped.
 */
function authThrottle(string $id, string $purpose): void {
    $since = otpSecondsSinceLast($id, $purpose);
    if ($since !== null && $since < 30) {
        $wait = 30 - $since;
        jsonError("Please wait {$wait}s before requesting another code.", 429, ['cooldown' => $wait]);
    }
    if (otpRateLimited($id, $purpose)) {
        jsonError('Too many requests. Please wait a few minutes and try again.', 429, ['cooldown' => 30]);
    }
}

try {
    switch ($action) {

        // -----------------------------------------------------------------
        // send_otp — login OTP over WhatsApp (mobile) or e-mail (PUBLIC)
        // -----------------------------------------------------------------
        case 'send_otp': {
            // One field, both channels: `login` is preferred, `mobile` kept for
            // backward compatibility with the older login page.
            $raw = reqStr('login') !== '' ? reqStr('login') : reqStr('mobile');
            $who = authIdentify($raw);
            if (!$who['valid']) {
                jsonError($who['type'] === 'email'
                    ? 'Please enter a valid e-mail address.'
                    : 'Please enter a valid 10-digit mobile number or your e-mail address.');
            }

            authThrottle($who['id'], 'login');

            if ($who['type'] === 'email') {
                if (!mailerEnabled()) {
                    jsonError('E-mail login is not available yet. Please use your mobile number, or ask support to finish the e-mail setup.');
                }
                // Only send when the address is registered — but answer the same either way.
                $tenant = db_one('SELECT id, owner_name FROM ' . tbl('tenants') . ' WHERE email = :e', [':e' => $who['id']]);
                if ($tenant) {
                    try {
                        generateEmailOtp($who['id'], 'login', (string)($tenant['owner_name'] ?? ''));
                    } catch (Throwable $e) {
                        error_log('send_otp (email) dispatch failed: ' . $e->getMessage());
                    }
                }
                jsonSuccess('OTP sent to your e-mail. It is valid for 5 minutes. (Check spam if you do not see it.)', [
                    'cooldown' => 30, 'channel' => 'email',
                ]);
            }

            $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $who['id']]);
            if ($tenant) {
                // generateOtp() stores the OTP row and fires the 'otp' WhatsApp template.
                // Wrapped so a gateway failure never breaks the response.
                try {
                    generateOtp($who['id'], 'login');
                } catch (Throwable $e) {
                    error_log('send_otp dispatch failed: ' . $e->getMessage());
                }
            }
            jsonSuccess('OTP sent to your WhatsApp. It is valid for 5 minutes.', [
                'cooldown' => 30, 'channel' => 'whatsapp',
            ]);
        }

        // -----------------------------------------------------------------
        // forgot_password — one entry point for both channels (PUBLIC)
        //   e-mail  → single-use reset LINK by e-mail
        //   mobile  → 6-digit reset OTP on WhatsApp
        // -----------------------------------------------------------------
        case 'forgot_password': {
            $raw = reqStr('login');
            $who = authIdentify($raw);
            if ($raw === '') { jsonError('Please enter your e-mail address or mobile number.'); }
            if (!$who['valid']) {
                jsonError($who['type'] === 'email'
                    ? 'Please enter a valid e-mail address.'
                    : 'Please enter a valid 10-digit mobile number or your e-mail address.');
            }

            authThrottle($who['id'], 'reset');

            if ($who['type'] === 'email') {
                if (!mailerEnabled()) {
                    jsonError('E-mail reset is not available yet. Please use your mobile number instead.');
                }
                $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE email = :e', [':e' => $who['id']]);
                if ($tenant) {
                    try {
                        sendPasswordResetEmail($tenant, 60);
                    } catch (Throwable $e) {
                        error_log('forgot_password (email) failed: ' . $e->getMessage());
                    }
                }
                // Record the attempt so the throttle also covers unknown addresses
                // (otherwise an attacker could probe freely without hitting the cap).
                if (!$tenant) {
                    try {
                        db_insert('otp_verifications', [
                            'mobile' => $who['id'], 'otp' => '000000', 'purpose' => 'reset',
                            'is_used' => 1, 'expires_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (Throwable $e) { /* throttle bookkeeping only */ }
                }
                jsonSuccess('If that e-mail is registered, a password-reset link is on its way. It is valid for 60 minutes.', [
                    'channel' => 'email', 'cooldown' => 30,
                ]);
            }

            $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $who['id']]);
            if ($tenant) {
                try {
                    generateOtp($who['id'], 'reset');
                } catch (Throwable $e) {
                    error_log('forgot_password (mobile) failed: ' . $e->getMessage());
                }
            }
            jsonSuccess('If that number is registered, a 6-digit code has been sent on WhatsApp. It is valid for 5 minutes.', [
                'channel' => 'whatsapp', 'cooldown' => 30,
            ]);
        }

        // -----------------------------------------------------------------
        // reset_with_otp — finish the WhatsApp reset: OTP + new password
        // -----------------------------------------------------------------
        case 'reset_with_otp': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST required.', 405); }
            $mobile = normalizeMobile(reqStr('mobile'));
            $otp    = reqStr('otp');
            $pass   = (string)($_POST['password'] ?? '');

            if (!preg_match('/^[6-9]\d{9}$/', $mobile)) { jsonError('Please enter a valid 10-digit mobile number.'); }
            if ($otp === '')        { jsonError('Please enter the 6-digit code.'); }
            if (strlen($pass) < 6)  { jsonError('The new password must be at least 6 characters.'); }

            $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $mobile]);
            // verifyOtp() burns the OTP on success, so a code works only once.
            if (!$tenant || !verifyOtp($mobile, $otp, 'reset')) {
                jsonError('That code is wrong or has expired. Please request a new one.');
            }

            db_update('tenants', ['password' => password_hash($pass, PASSWORD_DEFAULT)], ['id' => (int)$tenant['id']]);
            logActivity('tenant', (int)$tenant['id'], 'Password reset via WhatsApp OTP');
            jsonSuccess('Password changed. You can sign in with your new password now.');
        }

        // -----------------------------------------------------------------
        // password_reset_otp — generate + WhatsApp a reset OTP (PUBLIC)
        // -----------------------------------------------------------------
        case 'password_reset_otp': {
            $mobile = normalizeMobile(reqStr('mobile'));
            if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
                jsonError('Please enter a valid 10-digit mobile number.');
            }
            $since = otpSecondsSinceLast($mobile, 'reset');
            if ($since !== null && $since < 30) {
                $wait = 30 - $since;
                jsonError("Please wait {$wait}s before requesting another OTP.", 429, ['cooldown' => $wait]);
            }
            if (otpRateLimited($mobile, 'reset')) {
                jsonError('Too many OTP requests. Please wait a few minutes and try again.', 429, ['cooldown' => 30]);
            }
            $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $mobile]);
            if ($tenant) {
                try {
                    generateOtp($mobile, 'reset');
                } catch (Throwable $e) {
                    error_log('password_reset_otp dispatch failed: ' . $e->getMessage());
                }
            }
            jsonSuccess('If this number is registered, an OTP has been sent.');
        }

        // -----------------------------------------------------------------
        // verify_otp — optional AJAX verification (PUBLIC)
        // client/login.php verifies server-side, but this supports AJAX flows.
        // -----------------------------------------------------------------
        case 'verify_otp': {
            $raw     = reqStr('login') !== '' ? reqStr('login') : reqStr('mobile');
            $who     = authIdentify($raw);
            $mobile  = $who['id'];           // e-mail or 10-digit mobile
            $otp     = reqStr('otp');
            $purpose = reqStr('purpose', 'login');
            if (!in_array($purpose, ['login', 'reset'], true)) { $purpose = 'login'; }
            if ($mobile === '' || $otp === '') { jsonError('Mobile/e-mail and OTP are required.'); }
            if (verifyOtp($mobile, $otp, $purpose)) {
                jsonSuccess('OTP verified.');
            }
            jsonError('Invalid or expired OTP.');
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/auth.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
