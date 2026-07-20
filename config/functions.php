<?php
/**
 * AK Menu System - Shared helper library.
 * Every reusable function lives here. All DB access is via PDO prepared statements.
 */

// =============================================================================
// DATABASE HELPERS
// =============================================================================

/** Prefix a bare table name with the configured DB prefix. */
function tbl(string $name): string {
    return (defined('DB_PREFIX') ? DB_PREFIX : '') . $name;
}

/** Run a prepared statement and return the PDOStatement. */
function db_query(string $sql, array $params = []): PDOStatement {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Fetch a single row (assoc) or null. */
function db_one(string $sql, array $params = []): ?array {
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch all rows. */
function db_all(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}

/** Fetch a single scalar value. */
function db_val(string $sql, array $params = []) {
    return db_query($sql, $params)->fetchColumn();
}

/** Execute INSERT/UPDATE/DELETE; returns last insert id for inserts. */
function db_exec(string $sql, array $params = []): int {
    global $pdo;
    db_query($sql, $params);
    return (int)$pdo->lastInsertId();
}

/**
 * Simple insert helper.
 * @return int inserted id
 */
function db_insert(string $table, array $data): int {
    $cols = array_keys($data);
    $ph   = array_map(fn($c) => ':' . $c, $cols);
    $sql  = 'INSERT INTO ' . tbl($table) . ' (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $ph) . ')';
    return db_exec($sql, $data);
}

/** Simple update helper with a WHERE clause on id. */
function db_update(string $table, array $data, array $where): void {
    $set = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($data)));
    $wh  = implode(' AND ', array_map(fn($c) => "`$c` = :w_$c", array_keys($where)));
    $params = $data;
    foreach ($where as $k => $v) { $params['w_' . $k] = $v; }
    db_query('UPDATE ' . tbl($table) . " SET $set WHERE $wh", $params);
}

// =============================================================================
// GLOBAL SETTINGS (white-label)
// =============================================================================

$__settings_cache = null;

/** Get a global setting value with optional default. */
function getSetting(string $key, $default = null) {
    global $__settings_cache;
    if ($__settings_cache === null) {
        $__settings_cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM ' . tbl('settings')) as $r) {
            $__settings_cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return array_key_exists($key, $__settings_cache) ? $__settings_cache[$key] : $default;
}

/** Persist a global setting (upsert). */
function setSetting(string $key, $value): void {
    global $__settings_cache;
    db_query('INSERT INTO ' . tbl('settings') . ' (setting_key, setting_value) VALUES (:k, :v)
              ON DUPLICATE KEY UPDATE setting_value = :v2', [':k' => $key, ':v' => $value, ':v2' => $value]);
    if ($__settings_cache !== null) { $__settings_cache[$key] = $value; }
}

$__wa_settings_cache = null;

/** Get a WhatsApp setting value. */
function getWaSetting(string $key, $default = null) {
    global $__wa_settings_cache;
    if ($__wa_settings_cache === null) {
        $__wa_settings_cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM ' . tbl('whatsapp_settings')) as $r) {
            $__wa_settings_cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return array_key_exists($key, $__wa_settings_cache) ? $__wa_settings_cache[$key] : $default;
}

/** Persist a WhatsApp setting. */
function setWaSetting(string $key, $value): void {
    global $__wa_settings_cache;
    db_query('INSERT INTO ' . tbl('whatsapp_settings') . ' (setting_key, setting_value) VALUES (:k, :v)
              ON DUPLICATE KEY UPDATE setting_value = :v2', [':k' => $key, ':v' => $value, ':v2' => $value]);
    if ($__wa_settings_cache !== null) { $__wa_settings_cache[$key] = $value; }
}

// =============================================================================
// SECURITY: escaping, CSRF, sessions
// =============================================================================

/** HTML-escape output. Use everywhere untrusted data is printed. */
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Get (or lazily create) the CSRF token for this session. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input for forms. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Validate a submitted CSRF token; dies/JSON-errors on mismatch. */
function csrfCheck(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        if (isAjax()) { jsonError('Invalid session token. Please refresh the page.', 419); }
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}

/** True when the request expects a JSON response. */
function isAjax(): bool {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

/** Regenerate session id (call right after a successful login). */
function regenerateSession(): void {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
}

/** Log the current user out of a given realm and reset the session. */
function enforceSessionTimeout(): void {
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return;
    }
    $_SESSION['last_activity'] = time();
}

/** Client IP (respects common proxy header safely). */
function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// =============================================================================
// LOGIN RATE LIMITING
// =============================================================================

/** True if this identifier is currently locked out. */
function isLoginLocked(string $identifier): bool {
    $row = db_one('SELECT locked_until FROM ' . tbl('login_attempts') . ' WHERE identifier = :i', [':i' => $identifier]);
    return $row && $row['locked_until'] && strtotime($row['locked_until']) > time();
}

/** Record a failed login; locks after MAX_LOGIN_ATTEMPTS. */
function recordFailedLogin(string $identifier): void {
    $row = db_one('SELECT id, attempts FROM ' . tbl('login_attempts') . ' WHERE identifier = :i', [':i' => $identifier]);
    $attempts = ($row['attempts'] ?? 0) + 1;
    $lockUntil = $attempts >= MAX_LOGIN_ATTEMPTS
        ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60) : null;
    if ($row) {
        db_query('UPDATE ' . tbl('login_attempts') . ' SET attempts = :a, locked_until = :l, ip = :ip WHERE id = :id',
            [':a' => $attempts, ':l' => $lockUntil, ':ip' => clientIp(), ':id' => $row['id']]);
    } else {
        db_insert('login_attempts', ['identifier' => $identifier, 'ip' => clientIp(), 'attempts' => $attempts, 'locked_until' => $lockUntil]);
    }
}

/** Clear failed-login counter after a success. */
function clearLoginAttempts(string $identifier): void {
    db_query('DELETE FROM ' . tbl('login_attempts') . ' WHERE identifier = :i', [':i' => $identifier]);
}

// =============================================================================
// AUTH GUARDS  (session shape: role + id fields)
// =============================================================================

function isSuperAdmin(): bool { return !empty($_SESSION['admin_id']); }
function isClient(): bool { return !empty($_SESSION['tenant_id']); }
function isStaff(): bool { return !empty($_SESSION['staff_id']); }

/** Currently effective tenant id (client session, or admin impersonating). */
function currentTenantId(): ?int {
    if (!empty($_SESSION['tenant_id'])) return (int)$_SESSION['tenant_id'];
    if (!empty($_SESSION['staff_tenant_id'])) return (int)$_SESSION['staff_tenant_id'];
    return null;
}

/** Require super admin, else redirect to admin login. */
function requireAdmin(): void {
    if (!isSuperAdmin()) { redirect(BASE_URL . '/admin/login.php'); }
}

/** Require a logged-in restaurant owner. */
function requireClient(): void {
    if (!isClient()) { redirect(BASE_URL . '/client/login.php'); }
    ensureTenantActive();
}

/** Require a waiter/kitchen staff session of a specific role. */
function requireStaff(string $role): void {
    if (empty($_SESSION['staff_id']) || ($_SESSION['staff_role'] ?? '') !== $role) {
        $to = $role === 'kitchen' ? '/kitchen/login.php' : '/waiter/login.php';
        redirect(BASE_URL . $to);
    }
}

/** Block a suspended/expired tenant from the client panel. */
function ensureTenantActive(): void {
    $t = currentTenant();
    if (!$t) { session_unset(); redirect(BASE_URL . '/client/login.php'); }
    if ($t['status'] !== 'active' || ($t['expiry_date'] && $t['expiry_date'] < date('Y-m-d'))) {
        // Allow only the billing/upgrade page.
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($script, 'expired.php') === false && strpos($script, 'logout.php') === false) {
            redirect(BASE_URL . '/client/expired.php');
        }
    }
}

/** Load the current tenant row (cached per request). */
function currentTenant(): ?array {
    static $cache = null;
    $tid = currentTenantId();
    if (!$tid) return null;
    if ($cache && $cache['id'] == $tid) return $cache;
    $cache = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tid]);
    return $cache;
}

// =============================================================================
// PLAN LIMIT ENFORCEMENT (server-side)
// =============================================================================

/** Load the plan row for a tenant. */
function tenantPlan(int $tenantId): ?array {
    return db_one('SELECT p.* FROM ' . tbl('plans') . ' p
                   JOIN ' . tbl('tenants') . ' t ON t.plan_id = p.id WHERE t.id = :id', [':id' => $tenantId]);
}

/** Decode a plan feature flag. */
function planHasFeature(int $tenantId, string $feature): bool {
    $plan = tenantPlan($tenantId);
    if (!$plan) return false;
    $features = json_decode($plan['features_json'] ?? '{}', true) ?: [];
    return !empty($features[$feature]);
}

/**
 * Check whether a tenant may add one more of a resource.
 * @param string $type items|categories|tables|waiters|ai
 * @return array{allowed:bool, used:int, max:int, message:string}
 */
function checkPlanLimit(int $tenantId, string $type): array {
    $plan = tenantPlan($tenantId);
    if (!$plan) return ['allowed' => false, 'used' => 0, 'max' => 0, 'message' => 'No plan assigned.'];

    switch ($type) {
        case 'items':
            $used = (int)db_val('SELECT COUNT(*) FROM ' . tbl('items') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tenantId]);
            $max  = (int)$plan['max_items']; break;
        case 'categories':
            $used = (int)db_val('SELECT COUNT(*) FROM ' . tbl('categories') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tenantId]);
            $max  = (int)$plan['max_categories']; break;
        case 'tables':
            $used = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tables') . ' WHERE tenant_id = :t', [':t' => $tenantId]);
            $max  = (int)$plan['max_tables']; break;
        case 'waiters':
            $used = (int)db_val('SELECT COUNT(*) FROM ' . tbl('staff') . ' WHERE tenant_id = :t', [':t' => $tenantId]);
            $max  = (int)$plan['max_waiters']; break;
        case 'ai':
            $used = (int)db_val('SELECT ai_credits_used FROM ' . tbl('tenants') . ' WHERE id = :t', [':t' => $tenantId]);
            $max  = (int)$plan['ai_credits']; break;
        default:
            return ['allowed' => false, 'used' => 0, 'max' => 0, 'message' => 'Unknown limit type.'];
    }
    $allowed = $used < $max;
    return [
        'allowed' => $allowed,
        'used'    => $used,
        'max'     => $max,
        'message' => $allowed ? '' : "Plan limit reached ($used/$max). Please upgrade your plan.",
    ];
}

// =============================================================================
// FILE UPLOADS
// =============================================================================

/**
 * Validate + store an uploaded image, returning the relative path or null.
 * @param array  $file   $_FILES['x'] entry
 * @param string $folder subfolder under /uploads (e.g. 'items', 'logos')
 */
function uploadImage(array $file, string $folder = 'items', int $maxBytes = 4194304): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > $maxBytes) return null;

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'application/pdf' => 'pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) return null;

    $ext = $allowed[$mime];
    $dir = UPLOAD_PATH . '/' . preg_replace('/[^a-z0-9_]/', '', $folder);
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    // Best-effort WebP conversion for raster images to speed public menu.
    if (function_exists('imagewebp') && in_array($ext, ['jpg', 'png']) && getSetting('webp_convert', '1') === '1') {
        $webp = convertToWebp($dest, $ext);
        if ($webp) { @unlink($dest); return 'uploads/' . basename($dir) . '/' . basename($webp); }
    }
    return 'uploads/' . basename($dir) . '/' . $name;
}

/** Convert a JPG/PNG file to WebP; returns new path or null. */
function convertToWebp(string $path, string $ext): ?string {
    $img = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
    if (!$img) return null;
    if ($ext === 'png') { imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); }
    $out = preg_replace('/\.(jpg|png)$/', '.webp', $path);
    imagewebp($img, $out, 82);
    imagedestroy($img);
    return file_exists($out) ? $out : null;
}

// =============================================================================
// ACTIVITY & AI LOGGING
// =============================================================================

/** Record an audit-log entry. */
function logActivity(string $userType, ?int $userId, string $action): void {
    db_insert('activity_logs', [
        'user_type' => $userType, 'user_id' => $userId,
        'action' => $action, 'ip' => clientIp(),
    ]);
}

/** Record a Gemini AI usage row. */
function logAi(?int $tenantId, string $type, int $tokens, string $status): void {
    db_insert('ai_logs', ['tenant_id' => $tenantId, 'type' => $type, 'tokens' => $tokens, 'status' => $status]);
}

// =============================================================================
// JSON API RESPONSES  (contract: {status, message, data})
// =============================================================================

function jsonResponse(string $status, string $message = '', $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonSuccess(string $message = '', $data = []): void { jsonResponse('success', $message, $data); }
function jsonError(string $message = 'Error', int $code = 400, $data = []): void { jsonResponse('error', $message, $data, $code); }

// =============================================================================
// UTILITIES
// =============================================================================

/** Safe redirect helper. */
function redirect(string $url): void { header('Location: ' . $url); exit; }

/** Generate a URL-safe slug, unique within tenants. */
function makeSlug(string $text): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') { $slug = 'r'; }
    $base = $slug; $i = 1;
    while (db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]) > 0) {
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}

/** Random token for QR/table links. */
function randomToken(int $len = 24): string {
    return substr(bin2hex(random_bytes($len)), 0, $len);
}

/** Format currency using the tenant/global currency symbol. */
function money($amount, ?string $symbol = null): string {
    $symbol = $symbol ?? getSetting('currency', DEFAULT_CURRENCY);
    return $symbol . number_format((float)$amount, 2);
}

/** Generate a sequential-ish order number for a tenant. */
function nextOrderNo(int $tenantId): string {
    $count = (int)db_val('SELECT COUNT(*) FROM ' . tbl('orders') . ' WHERE tenant_id = :t AND DATE(created_at) = CURDATE()', [':t' => $tenantId]);
    return date('ymd') . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}

/** Whether a restaurant is currently open based on its hours. */
function isRestaurantOpen(array $tenant): bool {
    if (empty($tenant['opening_time']) || empty($tenant['closing_time'])) return true;
    $now = date('H:i:s');
    $o = $tenant['opening_time']; $c = $tenant['closing_time'];
    if ($c > $o) return $now >= $o && $now <= $c;
    return $now >= $o || $now <= $c; // overnight
}

// =============================================================================
// I18N
// =============================================================================

$__lang = null;

/** Load the active language array (en/gu). */
function loadLang(?string $code = null): array {
    global $__lang;
    if ($__lang !== null && $code === null) return $__lang;
    $code = $code ?: ($_SESSION['lang'] ?? getSetting('default_language', 'en'));
    $code = in_array($code, ['en', 'gu']) ? $code : 'en';
    $file = LANG_PATH . '/' . $code . '.php';
    $__lang = file_exists($file) ? require $file : [];
    return $__lang;
}

/** Translate a key with fallback to the key itself. */
function __(string $key): string {
    $lang = loadLang();
    return $lang[$key] ?? $key;
}

/** Active language code. */
function currentLang(): string {
    return $_SESSION['lang'] ?? getSetting('default_language', 'en');
}

// =============================================================================
// WHATSAPP INTEGRATION  (bulk.akdwk.in gateway)
// =============================================================================

/**
 * Normalise an Indian mobile number to gateway format (91XXXXXXXXXX).
 * Returns null if it cannot be validated.
 */
function formatWaNumber(string $number): ?string {
    $n = preg_replace('/[\s+\-]/', '', $number);
    $n = ltrim($n, '0');
    if (preg_match('/^\d{10}$/', $n)) { $n = '91' . $n; }
    if (preg_match('/^91\d{10}$/', $n)) { return $n; }
    return preg_match('/^\d{11,15}$/', $n) ? $n : null;
}

/**
 * Queue (or immediately send) a WhatsApp message via the gateway.
 * Non-blocking by default: inserts a 'pending' log row for the cron worker.
 *
 * @param bool $immediate send now (used by signup flow) with a short timeout.
 * @return int the whatsapp_logs id
 */
function sendWhatsApp(string $number, string $message, ?string $mediaUrl = null, ?int $tenantId = null, string $triggerKey = 'manual', bool $immediate = false): int {
    $formatted = formatWaNumber($number);
    $logId = db_insert('whatsapp_logs', [
        'tenant_id'  => $tenantId,
        'trigger_key'=> $triggerKey,
        'number'     => $formatted ?: $number,
        'message'    => $message,
        'media_url'  => $mediaUrl,
        'status'     => 'pending',
    ]);
    if (!$formatted) {
        db_update('whatsapp_logs', ['status' => 'failed', 'response' => 'Invalid number'], ['id' => $logId]);
        return $logId;
    }
    if ($immediate) {
        dispatchWhatsAppLog($logId, 10);
    }
    return $logId;
}

/** Actually POST a single queued log row to the gateway. */
function dispatchWhatsAppLog(int $logId, int $timeout = 20): bool {
    $log = db_one('SELECT * FROM ' . tbl('whatsapp_logs') . ' WHERE id = :id', [':id' => $logId]);
    if (!$log || $log['status'] === 'sent') return false;
    if (getWaSetting('enabled', '1') !== '1') {
        db_update('whatsapp_logs', ['status' => 'failed', 'response' => 'WhatsApp disabled'], ['id' => $logId]);
        return false;
    }

    $payload = [
        'api_key'    => getWaSetting('api_key', ''),
        'number'     => $log['number'],
        'message'    => $log['message'],
        'session_id' => getWaSetting('session_id', ''),
    ];
    if (!empty($log['media_url'])) { $payload['media_url'] = $log['media_url']; }

    $ch = curl_init(getWaSetting('base_url', 'https://bulk.akdwk.in/api.php'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($resp !== false && $httpCode >= 200 && $httpCode < 300 && stripos((string)$resp, 'error') === false);
    db_update('whatsapp_logs', [
        'status'      => $ok ? 'sent' : 'failed',
        'response'    => $err ?: (string)$resp,
        'retry_count' => (int)$log['retry_count'] + 1,
        'sent_at'     => $ok ? date('Y-m-d H:i:s') : null,
    ], ['id' => $logId]);
    return $ok;
}

/**
 * Render a WhatsApp template with placeholder substitution and queue it.
 */
function sendWaTemplate(string $triggerKey, string $number, array $vars = [], ?string $mediaUrl = null, ?int $tenantId = null, bool $immediate = false): ?int {
    $tpl = db_one('SELECT * FROM ' . tbl('whatsapp_templates') . ' WHERE trigger_key = :k AND is_active = 1', [':k' => $triggerKey]);
    if (!$tpl) return null;
    $lang = ($tenantId && ($t = db_one('SELECT language FROM ' . tbl('tenants') . ' WHERE id=:i', [':i'=>$tenantId]))) ? $t['language'] : getSetting('default_language', 'en');
    $body = $lang === 'gu' && !empty($tpl['message_gu']) ? $tpl['message_gu'] : $tpl['message_en'];
    $body = renderPlaceholders($body, $vars);
    return sendWhatsApp($number, $body, $mediaUrl, $tenantId, $triggerKey, $immediate);
}

/** Replace {placeholder} tokens; supports \n literal newlines. */
function renderPlaceholders(string $tpl, array $vars): string {
    $tpl = str_replace('\n', "\n", $tpl);
    foreach ($vars as $k => $v) { $tpl = str_replace('{' . $k . '}', (string)$v, $tpl); }
    return $tpl;
}

// =============================================================================
// OTP
// =============================================================================

/** Generate + store a 6-digit OTP and send via WhatsApp. */
function generateOtp(string $mobile, string $purpose = 'login'): string {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_insert('otp_verifications', [
        'mobile' => $mobile, 'otp' => $otp, 'purpose' => $purpose,
        'expires_at' => date('Y-m-d H:i:s', time() + 300),
    ]);
    sendWaTemplate('otp', $mobile, ['otp' => $otp], null, null, true);
    return $otp;
}

/** Verify an OTP; returns true on success and marks it used. */
function verifyOtp(string $mobile, string $otp, string $purpose = 'login'): bool {
    $row = db_one('SELECT * FROM ' . tbl('otp_verifications') . '
                   WHERE mobile = :m AND purpose = :p AND is_used = 0 AND expires_at > NOW()
                   ORDER BY id DESC LIMIT 1', [':m' => $mobile, ':p' => $purpose]);
    if (!$row) return false;
    if ($row['attempts'] >= 3) return false;
    if (hash_equals($row['otp'], $otp)) {
        db_update('otp_verifications', ['is_used' => 1], ['id' => $row['id']]);
        return true;
    }
    db_query('UPDATE ' . tbl('otp_verifications') . ' SET attempts = attempts + 1 WHERE id = :id', [':id' => $row['id']]);
    return false;
}

// =============================================================================
// GEMINI AI (menu OCR extraction)
// =============================================================================

/**
 * Ordered list of Gemini models to try. The configured model is tried first,
 * then known-good fallbacks — so a deprecated/renamed model never fully breaks
 * extraction. Google retires model names over time; this keeps us resilient.
 */
function geminiModelCandidates(): array {
    $configured = trim((string)getSetting('gemini_model', ''));
    $fallbacks = [
        'gemini-2.5-flash',
        'gemini-flash-latest',
        'gemini-2.0-flash-001',
        'gemini-2.5-flash-lite',
        'gemini-1.5-flash',
    ];
    $list = array_values(array_unique(array_filter(array_merge([$configured], $fallbacks))));
    return $list ?: ['gemini-2.5-flash'];
}

/**
 * Low-level Gemini generateContent call for a single model, with 429/503
 * backoff. Returns a normalised result array.
 * @return array{ok:bool, http:int, text:string, apiMsg:string, model:string}
 */
function geminiGenerate(array $parts, string $model, array $genConfig = []): array {
    $apiKey = trim((string)getSetting('gemini_api_key', ''));
    if (!$apiKey) return ['ok' => false, 'http' => 0, 'text' => '', 'apiMsg' => 'No API key configured.', 'model' => $model];

    $body = json_encode([
        'contents' => [['parts' => $parts]],
        'generationConfig' => $genConfig ?: ['temperature' => 0.2],
    ], JSON_UNESCAPED_UNICODE);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$apiKey";

    $http = 0; $resp = false; $apiMsg = ''; $curlErr = '';
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($http === 200) break;

        $ej = json_decode((string)$resp, true);
        $apiMsg = $ej['error']['message'] ?? '';
        $retryDelay = 0;
        foreach ($ej['error']['details'] ?? [] as $d) {
            if (!empty($d['retryDelay']) && preg_match('/(\d+)/', $d['retryDelay'], $m)) { $retryDelay = (int)$m[1]; }
        }
        if (in_array($http, [429, 503], true) && $attempt < 3) { sleep($retryDelay > 0 ? min($retryDelay, 30) : 2 * $attempt); continue; }
        break;
    }

    if ($http !== 200) {
        return ['ok' => false, 'http' => $http, 'text' => '', 'apiMsg' => ($apiMsg ?: $curlErr ?: "HTTP $http"), 'model' => $model];
    }
    $json = json_decode((string)$resp, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return ['ok' => true, 'http' => 200, 'text' => $text, 'apiMsg' => '', 'model' => $model];
}

/** True when an error message/status means "this model does not exist / is retired". */
function geminiIsModelGone(int $http, string $msg): bool {
    return $http === 404 || stripos($msg, 'not found') !== false
        || stripos($msg, 'no longer available') !== false || stripos($msg, 'not supported') !== false
        || stripos($msg, 'is not available') !== false;
}

/**
 * Call Gemini Vision to extract structured menu JSON from image(s).
 * Tries each candidate model until one works; retries 429/503 with backoff.
 * @param array $imagePaths absolute file paths (jpg/png/pdf)
 * @return array{ok:bool, data:array|null, error:string}
 */
function geminiExtractMenu(array $imagePaths): array {
    if (!trim((string)getSetting('gemini_api_key', ''))) {
        return ['ok' => false, 'data' => null, 'error' => 'Gemini API key not configured. Add it in Super Admin → Settings → AI.'];
    }
    $parts = [[
        'text' => "You are a menu OCR engine. Extract ALL menu items from the image(s). "
        . "Return STRICT JSON ONLY, no markdown, matching this schema: "
        . '{"restaurant_name":"","categories":[{"name":"","name_gu":"","items":[{"name":"","name_gu":"","description":"","price":0,"is_veg":true,"variants":[{"label":"","price":0}]}]}]}. '
        . "Translate names to Gujarati in name_gu. If price has half/full, use variants. Numbers only for price.",
    ]];
    foreach ($imagePaths as $p) {
        if (!file_exists($p)) continue;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($p);
        $parts[] = ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode(file_get_contents($p))]];
    }
    if (count($parts) < 2) return ['ok' => false, 'data' => null, 'error' => 'No readable image was uploaded.'];

    $genConfig = ['temperature' => 0.1, 'response_mime_type' => 'application/json'];
    $lastErr = ''; $lastHttp = 0; $usedModel = '';
    foreach (geminiModelCandidates() as $model) {
        $r = geminiGenerate($parts, $model, $genConfig);
        if ($r['ok']) { $usedModel = $model; $lastErr = ''; $rawText = $r['text']; break; }
        $lastErr = $r['apiMsg']; $lastHttp = $r['http'];
        if (geminiIsModelGone($r['http'], $r['apiMsg'])) { continue; }   // try next model
        if (in_array($r['http'], [400, 403], true)) { break; }           // key problem — stop
        // 429/other: try next candidate too (different model may have quota)
    }

    if (empty($usedModel)) {
        if ($lastHttp === 429) {
            return ['ok' => false, 'data' => null,
                'error' => 'Gemini quota reached (HTTP 429). ' . $lastErr
                . ' Wait a minute and press Retry, or use a different API key / enable billing.'];
        }
        if (in_array($lastHttp, [400, 403], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'Gemini rejected the request. ' . ($lastErr ?: 'Check the API key in Settings.')];
        }
        return ['ok' => false, 'data' => null,
            'error' => 'No available Gemini model worked. ' . ($lastErr ?: '') . ' Use the "Test Gemini" tool in Settings to pick a valid model.'];
    }

    // Remember the model that actually worked so future calls use it first.
    if ($usedModel !== trim((string)getSetting('gemini_model', ''))) { setSetting('gemini_model', $usedModel); }

    $text = trim(preg_replace('/^```json|```$/m', '', $rawText));
    $data = json_decode($text, true);
    if (!$data || empty($data['categories'])) {
        return ['ok' => false, 'data' => null, 'error' => 'Could not read a menu from the image. Try a clearer photo or enter items manually.'];
    }
    return ['ok' => true, 'data' => $data, 'error' => ''];
}

/**
 * Send a plain text prompt to Gemini (used by the Settings "Test" tool).
 * @return array{ok:bool, text:string, model:string, error:string}
 */
function geminiTestPrompt(string $prompt): array {
    if (!trim((string)getSetting('gemini_api_key', ''))) {
        return ['ok' => false, 'text' => '', 'model' => '', 'error' => 'No API key configured.'];
    }
    $parts = [['text' => $prompt !== '' ? $prompt : 'Reply with a short friendly hello in English and Gujarati.']];
    $lastErr = '';
    foreach (geminiModelCandidates() as $model) {
        $r = geminiGenerate($parts, $model);
        if ($r['ok']) { return ['ok' => true, 'text' => $r['text'], 'model' => $model, 'error' => '']; }
        $lastErr = $r['apiMsg'];
        if (geminiIsModelGone($r['http'], $r['apiMsg'])) continue;
        if (in_array($r['http'], [400, 403], true)) break;
    }
    return ['ok' => false, 'text' => '', 'model' => '', 'error' => $lastErr ?: 'All models failed.'];
}

/**
 * List models the current API key can use for generateContent.
 * @return array{ok:bool, models:array<string>, error:string}
 */
function geminiListModels(): array {
    $apiKey = trim((string)getSetting('gemini_api_key', ''));
    if (!$apiKey) return ['ok' => false, 'models' => [], 'error' => 'No API key configured.'];
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models?key=$apiKey&pageSize=100");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200) {
        $ej = json_decode((string)$resp, true);
        return ['ok' => false, 'models' => [], 'error' => $ej['error']['message'] ?? "HTTP $http"];
    }
    $json = json_decode((string)$resp, true);
    $models = [];
    foreach ($json['models'] ?? [] as $m) {
        if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
            $models[] = str_replace('models/', '', $m['name']);
        }
    }
    return ['ok' => true, 'models' => $models, 'error' => ''];
}

// =============================================================================
// QR CODE + public URLs
// =============================================================================

/**
 * Resolve an image/media path for display.
 * Absolute URLs (http/https, e.g. demo photos) are returned unchanged;
 * local upload paths get BASE_URL prefixed. Empty -> ''.
 */
function mediaUrl(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Public menu URL for a tenant slug. */
function publicMenuUrl(string $slug): string {
    return BASE_URL . '/r/' . $slug;
}

/**
 * Generate a QR PNG for a URL into /uploads/qr, return relative path.
 * Uses bundled phpqrcode if present; otherwise a Google Chart fallback URL image.
 */
function generateQr(string $data, string $filename): ?string {
    $dir = UPLOAD_PATH . '/qr';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $path = $dir . '/' . $filename . '.png';

    $lib = LIB_PATH . '/phpqrcode/qrlib.php';
    if (file_exists($lib)) {
        require_once $lib;
        QRcode::png($data, $path, QR_ECLEVEL_H, 8, 2);
        return file_exists($path) ? 'uploads/qr/' . $filename . '.png' : null;
    }
    // Fallback: fetch from a public QR service (works when allow_url_fopen/cURL available).
    $api = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($data);
    $img = @file_get_contents($api);
    if ($img) { file_put_contents($path, $img); return 'uploads/qr/' . $filename . '.png'; }
    return null;
}

// =============================================================================
// MAINTENANCE MODE
// =============================================================================

// =============================================================================
// MENU DATA LOADER  (shared by public page, templates, waiter, KOT)
// =============================================================================

/**
 * Build the canonical $menu array for a tenant. This is the SHAPE all menu
 * design templates receive. Templates must only read from this structure.
 *
 * @param bool $timeAware  when true, hide categories outside their time window.
 * @return array{
 *   tenant: array, categories: array<int,array{
 *     id:int,name:string,name_gu:string,image:?string,
 *     items: array<int,array>
 *   }>
 * }
 */
function getTenantMenu(int $tenantId, bool $timeAware = true): array {
    $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
    if (!$tenant) return ['tenant' => [], 'categories' => []];

    $now = date('H:i:s');
    $cats = db_all('SELECT * FROM ' . tbl('categories') . '
                    WHERE tenant_id = :t AND status = 1 ORDER BY sort_order, id', [':t' => $tenantId]);

    $items = db_all('SELECT * FROM ' . tbl('items') . '
                     WHERE tenant_id = :t AND status = 1 ORDER BY sort_order, id', [':t' => $tenantId]);
    // Attach variants + addons per item.
    $byCat = [];
    foreach ($items as $it) {
        $it['variants'] = db_all('SELECT label, price FROM ' . tbl('item_variants') . ' WHERE item_id = :i', [':i' => $it['id']]);
        $it['addons']   = db_all('SELECT id, name, price FROM ' . tbl('item_addons') . ' WHERE item_id = :i', [':i' => $it['id']]);
        $byCat[$it['category_id']][] = $it;
    }

    $out = [];
    foreach ($cats as $c) {
        if ($timeAware && $c['available_from'] && $c['available_to']) {
            $from = $c['available_from']; $to = $c['available_to'];
            $inWindow = ($to > $from) ? ($now >= $from && $now <= $to) : ($now >= $from || $now <= $to);
            if (!$inWindow) continue;
        }
        $c['items'] = $byCat[$c['id']] ?? [];
        if (!empty($c['items'])) { $out[] = $c; }
    }
    return ['tenant' => $tenant, 'categories' => $out];
}

/** Resolve the template folder for a tenant (falls back to default). */
function tenantTemplateFolder(array $tenant): string {
    if (!empty($tenant['template_id'])) {
        $f = db_val('SELECT folder FROM ' . tbl('templates') . ' WHERE id = :i AND status = 1', [':i' => $tenant['template_id']]);
        if ($f) return $f;
    }
    $def = db_val('SELECT folder FROM ' . tbl('templates') . ' WHERE is_default = 1 AND status = 1 LIMIT 1');
    return $def ?: 'modern';
}

/** Show the maintenance page for public/client during updates. */
function checkMaintenance(): void {
    if (getSetting('maintenance_mode', '0') === '1' && !isSuperAdmin()) {
        http_response_code(503);
        $msg = 'The system is under maintenance. Please check back shortly.';
        if (file_exists(ROOT_PATH . '/maintenance.html')) { readfile(ROOT_PATH . '/maintenance.html'); }
        else { echo "<h1>Under Maintenance</h1><p>$msg</p>"; }
        exit;
    }
}
