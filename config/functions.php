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
// SUBSCRIPTION / PLAN PURCHASE
// =============================================================================

/**
 * Activate (or renew) a subscription plan for a tenant.
 * Sets plan_id, start_date = today and expiry_date = base + validity_days, and
 * reactivates the account. When the tenant already has an unexpired subscription,
 * the new validity is stacked on top of the remaining days (a true renewal) so
 * paying early never loses time. Used by admin approval and online payment.
 */
function activatePlanForTenant(int $tenantId, int $planId): bool {
    $plan = db_one('SELECT validity_days FROM ' . tbl('plans') . ' WHERE id = :id AND status = 1', [':id' => $planId]);
    if (!$plan) return false;
    $days   = max(1, (int)$plan['validity_days']);
    $today  = date('Y-m-d');
    $tenant = db_one('SELECT plan_id, expiry_date FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
    // Extend from the current expiry when renewing the SAME plan and it's still valid.
    $base = $today;
    if ($tenant && (int)$tenant['plan_id'] === $planId
        && !empty($tenant['expiry_date']) && $tenant['expiry_date'] >= $today) {
        $base = $tenant['expiry_date'];
    }
    $expiry = date('Y-m-d', strtotime($base . ' +' . $days . ' days'));
    db_update('tenants', [
        'plan_id'     => $planId,
        'start_date'  => $today,
        'expiry_date' => $expiry,
        'status'      => 'active',
    ], ['id' => $tenantId]);
    // Reward the referrer (once) now that this restaurant has paid for a plan.
    creditReferralOnActivation($tenantId);
    return true;
}

/** Whether online (Razorpay) checkout is fully configured. */
function razorpayEnabled(): bool {
    return trim((string)getSetting('razorpay_key_id', '')) !== ''
        && trim((string)getSetting('razorpay_secret', '')) !== '';
}

/**
 * Platform UPI / manual-payment config used for offline subscription payments.
 * Falls back to the owner's default GPay number so it works out of the box.
 */
function platformUpi(): array {
    $number = trim((string)getSetting('upi_number', '9978123146'));
    return [
        'number'   => $number,
        'id'       => trim((string)getSetting('upi_id', '')),
        'name'     => trim((string)getSetting('upi_payee_name', getSetting('site_name', 'AK Menu System'))),
        'whatsapp' => preg_replace('/\D/', '', (string)getWaSetting('support_number', getSetting('support_whatsapp', $number))),
    ];
}

/** Build a UPI deep-link (upi://pay?...) for a given amount. Empty if no VPA/number. */
function upiPayLink(float $amount, string $note = 'Subscription'): string {
    $cfg = platformUpi();
    $pa  = $cfg['id'] !== '' ? $cfg['id'] : '';
    if ($pa === '') return '';   // a bare mobile number is not a valid UPI address
    $q = http_build_query([
        'pa' => $pa,
        'pn' => $cfg['name'] ?: 'AK Menu System',
        'am' => number_format($amount, 2, '.', ''),
        'cu' => 'INR',
        'tn' => $note,
    ]);
    return 'upi://pay?' . $q;
}

/** Count of pending subscription upgrade requests (for the admin badge). */
function pendingPlanRequests(): int {
    try {
        return (int)db_val('SELECT COUNT(*) FROM ' . tbl('plan_requests') . " WHERE status = 'pending'");
    } catch (Throwable $e) {
        return 0;
    }
}

// =============================================================================
// TRIAL PLAN + LAUNCH OFFER + PLAN SUGGESTION
// =============================================================================

/**
 * The plan a new signup should get for its free trial. Admin can pin one via the
 * `trial_plan_id` setting; otherwise the most feature-rich free plan is used,
 * falling back to the most feature-rich active plan (so trials get the BEST plan).
 */
function trialPlan(): ?array {
    $id = (int)getSetting('trial_plan_id', 0);
    if ($id) {
        $p = db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id AND status = 1', [':id' => $id]);
        if ($p) return $p;
    }
    // Best free plan (unlimited-ish) …
    $p = db_one('SELECT * FROM ' . tbl('plans') . ' WHERE price = 0 AND status = 1 ORDER BY max_items DESC, validity_days DESC LIMIT 1');
    if ($p) return $p;
    // … else the most generous active plan.
    return db_one('SELECT * FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY max_items DESC, price DESC LIMIT 1');
}

/** Configured trial length in days (default 7). */
function trialDays(): int {
    $d = (int)getSetting('trial_days', 0);
    return $d > 0 ? $d : 7;
}

/**
 * Launch (welcome) discount state for a tenant: a % off if they upgrade within
 * N hours of opening the account. Configurable + auto-expiring.
 * @return array{active:bool,percent:int,seconds_left:int,ends_at:?string}
 */
function launchOffer(int $tenantId): array {
    $out = ['active' => false, 'percent' => 0, 'seconds_left' => 0, 'ends_at' => null];
    if (getSetting('launch_offer_enabled', '1') !== '1') return $out;
    $percent = (int)getSetting('launch_offer_percent', 50);
    $hours   = (int)getSetting('launch_offer_hours', 24);
    if ($percent <= 0 || $hours <= 0 || $tenantId <= 0) return $out;
    $t = db_one('SELECT created_at FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
    if (!$t || empty($t['created_at'])) return $out;
    $ends = strtotime($t['created_at']) + $hours * 3600;
    $left = $ends - time();
    if ($left <= 0) return $out;
    return ['active' => true, 'percent' => min(100, $percent), 'seconds_left' => $left, 'ends_at' => date('c', $ends)];
}

/** Apply the launch discount to a gross amount for a tenant (returns the payable). */
function applyLaunchDiscount(int $tenantId, float $amount): float {
    $o = launchOffer($tenantId);
    if (!$o['active']) return round($amount, 2);
    return round($amount * (100 - $o['percent']) / 100, 2);
}

/**
 * Suggest the cheapest PAID plan that comfortably fits a tenant's current menu
 * size (items/categories/tables). Returns a plan id, or null.
 */
function suggestPlanForTenant(int $tenantId): ?int {
    try {
        $items  = (int)db_val('SELECT COUNT(*) FROM ' . tbl('items') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tenantId]);
        $cats   = (int)db_val('SELECT COUNT(*) FROM ' . tbl('categories') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tenantId]);
        $tables = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tables') . ' WHERE tenant_id = :t', [':t' => $tenantId]);
        $plans  = db_all('SELECT * FROM ' . tbl('plans') . ' WHERE status = 1 AND price > 0 ORDER BY price ASC');
        foreach ($plans as $p) {
            if ((int)$p['max_items'] >= $items && (int)$p['max_categories'] >= $cats && (int)$p['max_tables'] >= $tables) {
                return (int)$p['id'];
            }
        }
        return $plans ? (int)$plans[count($plans) - 1]['id'] : null;
    } catch (Throwable $e) { return null; }
}

// =============================================================================
// REFER & EARN
// =============================================================================

/** Bonus days a referrer earns when a referred restaurant activates a paid plan. */
function referralRewardDays(): int {
    return max(0, (int)getSetting('referral_reward_days', 30));
}

/** Get (or lazily generate + store) a tenant's own shareable referral code. */
function tenantReferralCode(int $tenantId): string {
    if ($tenantId <= 0) return '';
    try {
        $code = db_val('SELECT referral_code FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId]);
        if ($code) return $code;
        // Generate a short, unambiguous code and ensure uniqueness.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($try = 0; $try < 6; $try++) {
            $c = 'AK';
            for ($i = 0; $i < 5; $i++) { $c .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
            $exists = db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE referral_code = :c', [':c' => $c]);
            if (!$exists) {
                db_update('tenants', ['referral_code' => $c], ['id' => $tenantId]);
                return $c;
            }
        }
    } catch (Throwable $e) { /* column missing pre-migration */ }
    return '';
}

/** Resolve a referral code to the referrer tenant id (0 if invalid/self). */
function referrerIdFromCode(string $code): int {
    $code = strtoupper(trim($code));
    if ($code === '') return 0;
    try {
        return (int)db_val('SELECT id FROM ' . tbl('tenants') . " WHERE referral_code = :c AND status = 'active' LIMIT 1", [':c' => $code]);
    } catch (Throwable $e) { return 0; }
}

/** Public share link a tenant can send to invite other restaurants. */
function referralLink(int $tenantId): string {
    $code = tenantReferralCode($tenantId);
    return $code ? BASE_URL . '/signup.php?ref=' . rawurlencode($code) : BASE_URL . '/signup.php';
}

/**
 * Credit the referrer when a referred restaurant activates a PAID plan.
 * Idempotent: only rewards a 'pending' referral once. Extends the referrer's
 * expiry by referralRewardDays() and pings them on WhatsApp. Never throws.
 */
function creditReferralOnActivation(int $referredTenantId): void {
    try {
        $ref = db_one('SELECT * FROM ' . tbl('referrals') . " WHERE referred_id = :r AND status = 'pending' LIMIT 1", [':r' => $referredTenantId]);
        if (!$ref) return;
        $days = referralRewardDays();
        $referrerId = (int)$ref['referrer_id'];
        if ($days > 0 && $referrerId > 0) {
            $referrer = db_one('SELECT expiry_date, mobile, whatsapp_no, restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $referrerId]);
            if ($referrer) {
                $base = (!empty($referrer['expiry_date']) && $referrer['expiry_date'] >= date('Y-m-d')) ? $referrer['expiry_date'] : date('Y-m-d');
                $newExpiry = date('Y-m-d', strtotime($base . ' +' . $days . ' days'));
                db_update('tenants', ['expiry_date' => $newExpiry], ['id' => $referrerId]);
                $to = $referrer['whatsapp_no'] ?: $referrer['mobile'];
                if ($to) {
                    sendWhatsApp($to, "🎉 Referral reward! You earned $days extra days. Your plan is now valid till $newExpiry. Thanks for spreading the word!", null, $referrerId, 'manual');
                }
            }
        }
        db_update('referrals', ['status' => 'rewarded', 'reward_days' => $days, 'rewarded_at' => date('Y-m-d H:i:s')], ['id' => (int)$ref['id']]);
        logActivity('tenant', $referrerId, 'Earned referral reward (' . $days . ' days)');
    } catch (Throwable $e) {
        error_log('creditReferralOnActivation: ' . $e->getMessage());
    }
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
// LOYALTY POINTS  (1 point = 1 currency unit; append-only ledger, balance = SUM)
// =============================================================================

/** Per-restaurant loyalty configuration with safe defaults (works pre-migration). */
function loyaltyConfig(int $tenantId): array {
    $defaults = ['enabled' => false, 'earn_percent' => 5.0, 'min_redeem' => 50, 'max_redeem_pct' => 20.0];
    try {
        $row = db_one('SELECT * FROM ' . tbl('loyalty_settings') . ' WHERE tenant_id = :t', [':t' => $tenantId]);
    } catch (Throwable $e) { return $defaults; } // table not migrated yet → disabled
    if (!$row) { return $defaults; }
    return [
        'enabled'        => (int)$row['enabled'] === 1,
        'earn_percent'   => (float)$row['earn_percent'],
        'min_redeem'     => max(1, (int)$row['min_redeem']),
        'max_redeem_pct' => min(100, max(0, (float)$row['max_redeem_pct'])),
    ];
}

/** Current point balance for a customer at a restaurant (0 on any error). */
function loyaltyBalance(int $tenantId, string $mobile): int {
    $mobile = preg_replace('/[^0-9]/', '', $mobile);
    if ($mobile === '') { return 0; }
    try {
        return (int)db_val('SELECT COALESCE(SUM(points),0) FROM ' . tbl('loyalty_ledger') . '
                            WHERE tenant_id = :t AND customer_mobile = :m', [':t' => $tenantId, ':m' => $mobile]);
    } catch (Throwable $e) { return 0; }
}

/** Append a ledger entry (earn: +points, redeem: -points). Never throws. */
function loyaltyAdd(int $tenantId, string $mobile, int $points, string $type, ?int $orderId = null, string $note = ''): void {
    $mobile = preg_replace('/[^0-9]/', '', $mobile);
    if ($mobile === '' || $points === 0) { return; }
    try {
        db_insert('loyalty_ledger', [
            'tenant_id'       => $tenantId,
            'customer_mobile' => $mobile,
            'order_id'        => $orderId,
            'points'          => $points,
            'type'            => in_array($type, ['earn', 'redeem', 'adjust'], true) ? $type : 'adjust',
            'note'            => $note !== '' ? mb_substr($note, 0, 160) : null,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) { error_log('loyaltyAdd: ' . $e->getMessage()); }
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
    // Keep digits only — tolerate spaces, +, -, (), dots, unicode separators, etc.
    $n = preg_replace('/\D+/', '', $number);
    if ($n === '') return null;
    // Drop an international access prefix like 00 (e.g. "0091 98765 43210").
    if (strncmp($n, '00', 2) === 0) { $n = substr($n, 2); }
    // Already country-coded Indian mobile: 91 + 10-digit (6-9) — accept as-is.
    if (preg_match('/^91[6-9]\d{9}$/', $n)) { return $n; }
    // Strip a local trunk "0" prefix (e.g. "0 9876543210").
    $n = ltrim($n, '0');
    if (preg_match('/^91[6-9]\d{9}$/', $n)) { return $n; }
    // Bare 10-digit Indian mobile — add the country code.
    if (preg_match('/^[6-9]\d{9}$/', $n)) { return '91' . $n; }
    // Any other 10-digit number — still route via India's code.
    if (preg_match('/^\d{10}$/', $n)) { return '91' . $n; }
    // Any other plausible international number (11-15 digits) — send as typed.
    if (preg_match('/^\d{11,15}$/', $n)) { return $n; }
    return null;
}

/**
 * Build a working https://wa.me/ link for any stored number (adds the country
 * code, tolerates +91 / spaces / leading 0). Returns '' when the number is unusable.
 */
function waMeUrl(?string $number, string $text = ''): string {
    $n = $number !== null ? formatWaNumber($number) : null;
    if (!$n) return '';
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/**
 * Reduce any typed mobile to the canonical bare 10-digit local number used as the
 * login/lookup key (accepts +91, 0091, leading 0, spaces, dashes).
 */
function normalizeMobile(string $raw): string {
    $n = preg_replace('/\D+/', '', $raw);
    if (strncmp($n, '0091', 4) === 0) { $n = substr($n, 4); }
    if (strlen($n) === 12 && strncmp($n, '91', 2) === 0) { $n = substr($n, 2); }
    return ltrim($n, '0');
}

/**
 * Queue (or immediately send) a WhatsApp message via the gateway.
 * Non-blocking by default: inserts a 'pending' log row for the cron worker.
 *
 * @param bool $immediate send now (used by signup flow) with a short timeout.
 * @return int the whatsapp_logs id
 */
function sendWhatsApp(string $number, string $message, ?string $mediaUrl = null, ?int $tenantId = null, string $triggerKey = 'manual', bool $immediate = false): int {
    // Global kill switch — when WhatsApp is turned off in the admin panel, nothing
    // is queued or sent (orders, notifications, everything). No "pending" pile-up.
    if (getWaSetting('enabled', '1') !== '1') {
        return 0;
    }
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
    } else {
        // Best-effort: nudge the queue to drain in the background so messages
        // don't sit "pending" forever on hosts without a cron job configured.
        pokeWhatsAppQueue();
    }
    return $logId;
}

/**
 * Send up to $limit pending WhatsApp messages now. Reusable by the cron worker,
 * the "poke" self-request, and the admin "Send pending" button.
 * @return int number actually sent
 */
function processWhatsAppQueue(int $limit = 15, int $timeout = 15, int $delaySeconds = 0): int {
    if (getWaSetting('enabled', '1') !== '1') return 0;
    $rows = db_all('SELECT id FROM ' . tbl('whatsapp_logs') . "
                    WHERE status = 'pending' AND retry_count < 3
                    ORDER BY id ASC LIMIT " . max(1, $limit));
    $sent = 0;
    foreach ($rows as $i => $r) {
        if ($i > 0 && $delaySeconds > 0) { sleep($delaySeconds); }
        if (dispatchWhatsAppLog((int)$r['id'], $timeout)) { $sent++; }
    }
    return $sent;
}

/**
 * Fire-and-forget loopback request that triggers the queue worker in a separate
 * PHP process, so the current request is never blocked. This is the "poor man's
 * cron" that makes queued messages send on shared hosts without a real cron job.
 * A proper 1-minute cron is still recommended for guaranteed delivery.
 */
function pokeWhatsAppQueue(): void {
    if (!function_exists('curl_init')) return;
    $secret = getSetting('cron_secret', '');
    $url = BASE_URL . '/cron/whatsapp_queue.php?key=' . urlencode($secret) . '&poke=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS     => 300,   // don't wait for it to finish
        CURLOPT_NOSIGNAL       => true,
        CURLOPT_CONNECTTIMEOUT_MS => 300,
    ]);
    @curl_exec($ch);
    @curl_close($ch);
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

/**
 * Seconds since the most recent OTP was issued to this mobile for a purpose,
 * or null if none exists. Used to enforce a resend cooldown (cost control).
 */
function otpSecondsSinceLast(string $mobile, string $purpose = 'login'): ?int {
    $last = db_val('SELECT created_at FROM ' . tbl('otp_verifications') . '
                    WHERE mobile = :m AND purpose = :p ORDER BY id DESC LIMIT 1',
                    [':m' => $mobile, ':p' => $purpose]);
    return $last ? max(0, time() - strtotime($last)) : null;
}

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
    $t0 = microtime(true);
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
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($http !== 200) {
        // Metering: log the failed call (0 tokens, status error).
        if (function_exists('aiLogUsage')) { aiLogUsage('gemini', $model, null, 'error', $ms, ($apiMsg ?: $curlErr ?: "HTTP $http")); }
        return ['ok' => false, 'http' => $http, 'text' => '', 'apiMsg' => ($apiMsg ?: $curlErr ?: "HTTP $http"), 'model' => $model, 'usage' => null];
    }
    $json  = json_decode((string)$resp, true);
    $text  = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $usage = function_exists('aiUsageFromGemini') ? aiUsageFromGemini($json['usageMetadata'] ?? null) : null;
    // Metering: exact token counts from the provider's usageMetadata.
    if (function_exists('aiLogUsage')) { aiLogUsage('gemini', $model, $usage, 'success', $ms); }
    return ['ok' => true, 'http' => 200, 'text' => $text, 'apiMsg' => '', 'model' => $model, 'usage' => $usage];
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

    // Metering context + pre-flight runaway guard (never send an unbounded prompt).
    if (function_exists('aiSetContext')) {
        aiSetContext(['source' => 'menu_extract']);
        $est = aiEstimateInputTokens((string)($parts[0]['text'] ?? ''), count($parts) - 1);
        if (aiExceedsInputCap($est)) {
            aiLogUsage('gemini', (string)getSetting('gemini_model', 'gemini-2.5-flash'), null, 'blocked', 0,
                       "Pre-flight blocked: ~$est input tokens exceed the configured cap");
            return ['ok' => false, 'data' => null,
                'error' => 'This upload is too large to process safely. Please upload fewer or smaller images.'];
        }
    }

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
    if (function_exists('aiSetContext')) { aiSetContext(['source' => 'ai_test']); }
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

/**
 * URL for a local static asset (CSS/JS/img) with a cache-busting ?v= based on
 * the file's modification time — so browsers always pick up the newest version
 * after an update, despite the long cache headers on static files.
 */
function assetUrl(string $rel): string {
    $rel  = ltrim($rel, '/');
    $full = ROOT_PATH . '/' . $rel;
    $v    = @filemtime($full) ?: (defined('APP_VERSION') ? APP_VERSION : '1');
    return BASE_URL . '/' . $rel . '?v=' . $v;
}

/**
 * Record a public menu open/scan for analytics (aggregated per tenant per day).
 *
 * Counted ONCE PER DEVICE PER DAY per restaurant using a long-lived cookie, so
 * the same phone reopening/refreshing (or fake repeat views) does not inflate
 * the number — 100 opens from 100 different devices = 100. Works even when the
 * QR points straight at the /r/{slug} link. The menu_views table is created
 * on demand, so counting works even before the DB migration has run.
 * Never throws — analytics must not break the menu page.
 */
function recordMenuView(int $tenantId): void {
    if ($tenantId <= 0) return;
    // Don't count the owner previewing their own menu or a super admin.
    if (currentTenantId() === $tenantId || isSuperAdmin()) return;

    // Per-device, per-day dedup via a cookie that resets the next morning.
    $cookie = 'akv' . $tenantId;
    $today  = date('Y-m-d');
    if (($_COOKIE[$cookie] ?? '') === $today) return;
    if (!headers_sent()) {
        @setcookie($cookie, $today, [
            'expires'  => strtotime('tomorrow 04:00'),
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE[$cookie] = $today; // guard against a double count in this request

    $sql = 'INSERT INTO ' . tbl('menu_views') . ' (tenant_id, view_date, views) VALUES (:t, CURDATE(), 1)
            ON DUPLICATE KEY UPDATE views = views + 1';
    try {
        db_query($sql, [':t' => $tenantId]);
    } catch (Throwable $e) {
        // Table probably missing on an un-migrated install — create it and retry once.
        try {
            db_query('CREATE TABLE IF NOT EXISTS ' . tbl('menu_views') . ' (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tenant_id` INT UNSIGNED NOT NULL,
                `view_date` DATE NOT NULL,
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_tenant_date` (`tenant_id`,`view_date`),
                KEY `idx_view_date` (`view_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            db_query($sql, [':t' => $tenantId]);
        } catch (Throwable $e2) { /* give up silently */ }
    }
}

/** Total menu views for a tenant between two dates (inclusive). */
function menuViewsTotal(int $tenantId, string $from, string $to): int {
    return (int)db_val('SELECT COALESCE(SUM(views),0) FROM ' . tbl('menu_views') . '
                        WHERE tenant_id = :t AND view_date BETWEEN :a AND :b',
                        [':t' => $tenantId, ':a' => $from, ':b' => $to]);
}

/** Public menu URL for a tenant slug. */
function publicMenuUrl(string $slug): string {
    return BASE_URL . '/r/' . $slug;
}

// =============================================================================
// SITE-WIDE ANALYTICS  (whole platform traffic, deduped per device/day)
// =============================================================================

/**
 * Record one public page view for site analytics.
 * Dedup: a single cookie 'aksv' stores today's date + the set of page hashes the
 * device has already opened today, so the same visitor/refresh is not counted as
 * a new unique. Row path='*' is the site-wide daily aggregate. Never throws; the
 * site_visits table is auto-created so counting works even before the migration.
 */
function recordSiteVisit(string $path): void {
    if ($path === '') return;
    $today   = date('Y-m-d');
    $raw     = (string)($_COOKIE['aksv'] ?? '');
    $sep     = strpos($raw, '|');
    $cDate   = $sep === false ? '' : substr($raw, 0, $sep);
    $seenCsv = $sep === false ? '' : substr($raw, $sep + 1);
    $newDay  = ($cDate !== $today);
    $seen    = ($newDay || $seenCsv === '') ? [] : explode(',', $seenCsv);

    $hash      = substr(hash('crc32b', $path), 0, 6);
    $isNewPath = !in_array($hash, $seen, true);

    $sql = 'INSERT INTO ' . tbl('site_visits') . ' (visit_date, path, views, visitors)
            VALUES (CURDATE(), :p, 1, :u)
            ON DUPLICATE KEY UPDATE views = views + 1, visitors = visitors + :u2';
    $rec = function (string $p, int $u) use ($sql) {
        try {
            db_query($sql, [':p' => $p, ':u' => $u, ':u2' => $u]);
        } catch (Throwable $e) {
            try {
                db_query('CREATE TABLE IF NOT EXISTS ' . tbl('site_visits') . ' (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `visit_date` DATE NOT NULL,
                    `path` VARCHAR(190) NOT NULL,
                    `views` INT UNSIGNED NOT NULL DEFAULT 0,
                    `visitors` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_date_path` (`visit_date`,`path`),
                    KEY `idx_sv_date` (`visit_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                db_query($sql, [':p' => $p, ':u' => $u, ':u2' => $u]);
            } catch (Throwable $e2) { /* give up silently */ }
        }
    };
    $rec($path, $isNewPath ? 1 : 0);   // per-page row
    $rec('*',   $newDay ? 1 : 0);      // site-wide daily aggregate

    if ($isNewPath) { $seen[] = $hash; }
    $seen = array_slice($seen, -40);   // keep the cookie small
    $val  = $today . '|' . implode(',', $seen);
    if (!headers_sent()) {
        @setcookie('aksv', $val, ['expires' => strtotime('tomorrow 04:00'), 'path' => '/', 'samesite' => 'Lax']);
    }
    $_COOKIE['aksv'] = $val;
}

/**
 * Called from the bootstrap on every request. Decides whether the current
 * request is a real public page view worth tracking, then records it. Skips
 * internal panels, APIs, assets, bots, XHR and super-admin previews. Public
 * restaurant menus are grouped under '/r/*'. Never throws.
 */
function maybeTrackSiteVisit(): void {
    try {
        if (PHP_SAPI === 'cli') return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) return;         // AJAX
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '' || preg_match('~bot|crawl|spider|slurp|bing|preview|monitor|curl|wget|httrack|python-requests|facebookexternalhit|whatsapp~i', $ua)) return;
        if (isSuperAdmin()) return;                                    // don't count ourselves

        $uri = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
        if ($basePath && strpos($uri, $basePath) === 0) { $uri = substr($uri, strlen($basePath)); }
        $uri = '/' . ltrim($uri, '/');
        if (strlen($uri) > 1) { $uri = rtrim($uri, '/'); }

        // Skip asset files.
        if (preg_match('~\.(png|jpe?g|gif|webp|svg|ico|css|js|map|woff2?|ttf|xml|txt|json|pdf|zip)$~i', $uri)) return;
        // Skip internal / non-content areas.
        $lower = strtolower($uri);
        foreach (['/admin','/client','/waiter','/kitchen','/api','/cron','/config','/install','/standee','/assets','/uploads','/libs','/vendor'] as $ex) {
            if ($lower === $ex || strncmp($lower, $ex . '/', strlen($ex) + 1) === 0) return;
        }
        foreach (['/invoice.php','/sitemap','/robots','/favicon'] as $ex) {
            if (strncmp($lower, $ex, strlen($ex)) === 0) return;
        }
        // Group all public restaurant menus together.
        if (strncmp($uri, '/r/', 3) === 0 || strncmp($uri, '/t/', 3) === 0 || $uri === '/r' || $uri === '/t') { $uri = '/r/*'; }
        if (strlen($uri) > 180) { $uri = substr($uri, 0, 180); }

        recordSiteVisit($uri);
    } catch (Throwable $e) { /* analytics must never break a page */ }
}

/** Friendly label for a tracked path (for the analytics dashboard). */
function siteVisitLabel(string $path): string {
    $map = [
        '/'            => 'Home (landing)',
        '/signup.php'  => 'Sign-up page',
        '/r/*'         => 'Restaurant menus',
        '*'            => 'Whole site',
    ];
    return $map[$path] ?? $path;
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
