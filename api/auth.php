<?php
/**
 * Public authentication API — WhatsApp OTP flows.
 *
 * PUBLIC endpoint: no login guard (customers/owners request OTPs before login).
 * Used by client/login.php (action=send_otp) and, optionally, password reset.
 *
 * Contract: JSON via jsonSuccess/jsonError. CSRF required on POST.
 * Security notes:
 *  - Rate-limited per mobile (max 3 OTPs in 5 minutes) to prevent abuse.
 *  - Does NOT reveal whether a mobile exists (anti-enumeration): always returns
 *    a generic success, but only actually generates+sends an OTP when a tenant
 *    with that mobile exists.
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

try {
    switch ($action) {

        // -----------------------------------------------------------------
        // send_otp — generate + WhatsApp a login OTP (PUBLIC)
        // -----------------------------------------------------------------
        case 'send_otp': {
            $mobile = reqStr('mobile');
            if ($mobile === '' || !preg_match('/\d{10}/', $mobile)) {
                jsonError('Please enter a valid mobile number.');
            }

            // Rate limit per number (max 3 in 5 min).
            if (otpRateLimited($mobile, 'login')) {
                jsonError('Too many OTP requests. Please wait a few minutes and try again.', 429);
            }

            // Only actually send when a tenant with this mobile exists — but always
            // report success so we never leak which numbers are registered.
            $tenant = db_one('SELECT id FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $mobile]);
            if ($tenant) {
                // generateOtp() stores the OTP row and fires the 'otp' WhatsApp template.
                // Wrapped so a gateway failure never breaks the response.
                try {
                    generateOtp($mobile, 'login');
                } catch (Throwable $e) {
                    error_log('send_otp dispatch failed: ' . $e->getMessage());
                }
            }
            jsonSuccess('OTP sent. Please check your WhatsApp.');
        }

        // -----------------------------------------------------------------
        // password_reset_otp — generate + WhatsApp a reset OTP (PUBLIC)
        // -----------------------------------------------------------------
        case 'password_reset_otp': {
            $mobile = reqStr('mobile');
            if ($mobile === '' || !preg_match('/\d{10}/', $mobile)) {
                jsonError('Please enter a valid mobile number.');
            }
            if (otpRateLimited($mobile, 'reset')) {
                jsonError('Too many OTP requests. Please wait a few minutes and try again.', 429);
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
            $mobile  = reqStr('mobile');
            $otp     = reqStr('otp');
            $purpose = reqStr('purpose', 'login');
            if (!in_array($purpose, ['login', 'reset'], true)) { $purpose = 'login'; }
            if ($mobile === '' || $otp === '') { jsonError('Mobile and OTP are required.'); }
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
