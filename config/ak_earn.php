<?php
/**
 * AK Earn (https://earn.akdwk.in) — partner / commission tracking bridge.
 *
 * This file only REPORTS events to the AK Earn master; it never changes how
 * this site works. Every call is fire-and-forget: it runs after the response
 * has been sent to the user (fastcgi_finish_request) and any error is only
 * written to the PHP error log, so tracking can never break or slow a flow.
 *
 * Credentials (server-side only, never in git, never sent to the browser):
 *   1. config/ak_earn.local.php (git-ignored) may define AK_MASTER_URL,
 *      AK_SITE_KEY and AK_SITE_SECRET itself, or
 *   2. Super Admin → AK Earn saves the key + secret in the settings table.
 * The SDK lives in libs/ak-connect/ (web access denied by libs/.htaccess).
 *
 * Events sent (see the AK Earn API docs):
 *   signup           restaurant account created (self sign-up or by admin)
 *   order_created    plan invoice raised but not paid yet (offline UPI request, admin unpaid invoice)
 *   payment_success  first PAID plan invoice of a restaurant  ← creates commission
 *   renewal          any later PAID plan invoice of the same restaurant
 *   refund           a PAID invoice is marked unpaid or deleted by the admin
 *   cancel           an UNPAID invoice is rejected or deleted
 * order_id = invoice_no for all of them; amount = invoice total actually paid (INR, incl. GST);
 * product_code = "plan-<plan id>" (stable even if the plan is renamed).
 */

/** Load credentials + SDK once. Returns true when AK Earn is configured. */
function akEarnBoot(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    $ok = false;
    try {
        $local = __DIR__ . '/ak_earn.local.php';
        if (is_file($local)) { require_once $local; }
        if (!defined('AK_MASTER_URL')) { define('AK_MASTER_URL', trim((string)getSetting('ak_earn_url', '')) ?: 'https://earn.akdwk.in'); }
        if (!defined('AK_SITE_KEY'))   { define('AK_SITE_KEY', trim((string)getSetting('ak_earn_site_key', ''))); }
        if (!defined('AK_SITE_SECRET')) { define('AK_SITE_SECRET', trim((string)getSetting('ak_earn_site_secret', ''))); }
        if (!defined('AK_QUEUE_DIR')) {
            $q = ROOT_PATH . '/libs/ak-connect/queue';
            if (!is_dir($q)) { @mkdir($q, 0750, true); }
            define('AK_QUEUE_DIR', is_dir($q) && is_writable($q) ? $q : sys_get_temp_dir());
        }
        if (AK_SITE_KEY === '' || AK_SITE_SECRET === '') return $ok = false;
        require_once ROOT_PATH . '/libs/ak-connect/ak-connect.php';
        akEarnGuardRef();
        return $ok = function_exists('ak_track_order');
    } catch (Throwable $e) {
        error_log('ak_earn boot: ' . $e->getMessage());
        return $ok = false;
    }
}

/** Public site key for the browser script ('' when not configured). The secret never leaves the server. */
function akEarnSiteKey(): string {
    if (defined('AK_SITE_KEY')) return (string)AK_SITE_KEY;
    try {
        $local = __DIR__ . '/ak_earn.local.php';
        if (is_file($local)) { require_once $local; if (defined('AK_SITE_KEY')) return (string)AK_SITE_KEY; }
        return trim((string)getSetting('ak_earn_site_key', ''));
    } catch (Throwable $e) { return ''; }
}

/**
 * This site has its own restaurant Refer & Earn codes on the same ?ref= parameter
 * (and the same "AK" + 5 characters shape) as AK Earn partner codes. A
 * restaurant's code is not an AK Earn partner, so never pass it on as one.
 */
function akEarnIsLocalReferralCode(string $code): bool {
    $code = strtoupper(trim($code));
    if ($code === '') return false;
    try { return (bool)db_val('SELECT id FROM ' . tbl('tenants') . ' WHERE referral_code = :c LIMIT 1', [':c' => $code]); }
    catch (Throwable $e) { return false; }
}
function akEarnGuardRef(): void {
    foreach ([['_GET', 'ref'], ['_POST', 'ak_ref'], ['_COOKIE', 'ak_ref']] as [$g, $k]) {
        if (isset($GLOBALS[$g][$k]) && akEarnIsLocalReferralCode((string)$GLOBALS[$g][$k])) { unset($GLOBALS[$g][$k]); }
    }
}

/**
 * <script> tag for public pages. Not printed when AK Earn is not configured,
 * or when the URL carries a restaurant's own referral code (so it can't
 * overwrite a real partner's ak_ref cookie on .akdwk.in).
 */
function akEarnScriptTag(): string {
    try {
        $key = akEarnSiteKey();
        if ($key === '') return '';
        if (!empty($_GET['ref']) && akEarnIsLocalReferralCode((string)$_GET['ref'])) return '';
        return '<script src="https://earn.akdwk.in/connector/ak-connect.js" data-site-key="'
             . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    } catch (Throwable $e) { return ''; }
}

/**
 * Run a tracking call after the user already has the response. Never throws.
 * Request data the SDK needs (ref cookie, visitor id) is still available then.
 */
function akEarn(callable $fn, string $label = 'event'): void {
    if (!akEarnBoot()) return;
    static $registered = false, $jobs = [];
    $jobs[] = [$fn, $label];
    if ($registered) return;
    $registered = true;
    register_shutdown_function(function () use (&$jobs) {
        try {
            if (function_exists('fastcgi_finish_request') && PHP_SAPI !== 'cli') {
                if (session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
                @fastcgi_finish_request();
            }
        } catch (Throwable $e) { /* ignore */ }
        foreach ($jobs as [$job, $lbl]) {
            try {
                $r = $job();
                if (is_array($r) && ($r['status'] ?? '') === 'error' && empty($r['queued'])) {
                    error_log('ak_earn ' . $lbl . ': ' . ($r['message'] ?? 'error'));
                }
            } catch (Throwable $e) { error_log('ak_earn ' . $lbl . ': ' . $e->getMessage()); }
        }
    });
}

/** 10-digit Indian mobile (last 10 digits), '' if none. */
function akEarnMobile(?string $m): string {
    $d = preg_replace('/\D/', '', (string)$m);
    return strlen($d) >= 10 ? substr($d, -10) : '';
}

/** Stable product code for a plan. */
function akEarnPlanCode(?int $planId): ?string { return $planId ? 'plan-' . $planId : null; }

/** Restaurant account created. */
function akEarnSignup(int $tenantId): void {
    akEarn(function () use ($tenantId) {
        $t = db_one('SELECT owner_name, restaurant_name, mobile, email FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $tenantId]);
        if (!$t) return null;
        return ak_track_signup((string)($t['owner_name'] ?: $t['restaurant_name']), akEarnMobile($t['mobile']), $t['email'] ?: null,
            ['restaurant' => $t['restaurant_name'], 'tenant_id' => $tenantId]);
    }, 'signup');
}

/** Load an invoice with its restaurant (null when missing). */
function akEarnInvoice(int $invoiceId): ?array {
    return db_one('SELECT i.*, t.owner_name, t.restaurant_name, t.mobile FROM ' . tbl('invoices') . ' i
                   JOIN ' . tbl('tenants') . ' t ON t.id = i.tenant_id WHERE i.id = :i', [':i' => $invoiceId]) ?: null;
}

/** Plan invoice raised, not paid yet → order_created. */
function akEarnInvoiceCreated(int $invoiceId): void {
    akEarn(function () use ($invoiceId) {
        $inv = akEarnInvoice($invoiceId);
        if (!$inv || $inv['status'] !== 'unpaid' || (float)$inv['total'] <= 0) return null;
        return ak_track_order((string)$inv['invoice_no'], (float)$inv['total'], akEarnPlanCode((int)$inv['plan_id']),
            (string)($inv['owner_name'] ?: $inv['restaurant_name']), akEarnMobile($inv['mobile']), 'created');
    }, 'order_created');
}

/** Plan invoice PAID → payment_success (first paid invoice) or renewal (later ones). */
function akEarnInvoicePaid(int $invoiceId): void {
    akEarn(function () use ($invoiceId) {
        $inv = akEarnInvoice($invoiceId);
        if (!$inv || $inv['status'] !== 'paid' || (float)$inv['total'] <= 0) return null;   // never for unpaid / free
        $earlier = (int)db_val('SELECT COUNT(*) FROM ' . tbl('invoices') . " WHERE tenant_id = :t AND status = 'paid' AND total > 0 AND id < :i",
            [':t' => (int)$inv['tenant_id'], ':i' => $invoiceId]);
        $name = (string)($inv['owner_name'] ?: $inv['restaurant_name']);
        $code = akEarnPlanCode((int)$inv['plan_id']);
        $meta = ['restaurant' => $inv['restaurant_name'], 'tenant_id' => (int)$inv['tenant_id']];
        return $earlier > 0
            ? ak_track_renewal((string)$inv['invoice_no'], (float)$inv['total'], $code, $name, akEarnMobile($inv['mobile']), $meta)
            : ak_track_order((string)$inv['invoice_no'], (float)$inv['total'], $code, $name, akEarnMobile($inv['mobile']), 'paid', $meta);
    }, 'payment');
}

/**
 * Invoice reversed: a PAID one marked unpaid / deleted → refund; an UNPAID one
 * rejected / deleted → cancel. Pass the invoice row as it was BEFORE the change.
 */
function akEarnInvoiceReversed(array $invoiceBefore, string $reason): void {
    $no = (string)($invoiceBefore['invoice_no'] ?? '');
    if ($no === '' || (float)($invoiceBefore['total'] ?? 0) <= 0) return;
    $wasPaid = ($invoiceBefore['status'] ?? '') === 'paid';
    $amount  = (float)$invoiceBefore['total'];
    akEarn(fn() => $wasPaid ? ak_track_refund($no, $reason, $amount) : ak_track_cancel($no, $reason), $wasPaid ? 'refund' : 'cancel');
}

/** Product list for AK Earn (all plans, active or not). */
function akEarnPlanProducts(): array {
    $out = [];
    foreach (db_all('SELECT * FROM ' . tbl('plans') . ' ORDER BY price') as $p) {
        $days = (int)$p['validity_days'];
        $billing = $days >= 360 ? 'yearly' : ($days >= 85 && $days <= 95 ? 'quarterly' : ($days >= 28 && $days <= 31 ? 'monthly' : 'one_time'));
        $gross = function_exists('planGross') ? planGross($p) : (float)$p['price'];
        $out[] = [
            'code'          => akEarnPlanCode((int)$p['id']),
            'name'          => $p['name'] . ' Plan',
            'price'         => $gross,
            'renewal_price' => $gross,
            'billing'       => $billing,
            'description'   => $days . ' days · ' . ((int)$p['max_items'] >= 100000 ? 'unlimited' : (int)$p['max_items']) . ' menu items · price incl. GST',
            'image'         => rtrim(BASE_URL, '/') . '/assets/img/icon-512.png',
            'url'           => rtrim(BASE_URL, '/') . '/#pricing',
            'status'        => (int)$p['status'] === 1 && (float)$p['price'] > 0 ? 'active' : 'inactive',
        ];
    }
    return $out;
}

/** Push the plan list (after plans are saved / deleted / toggled). */
function akEarnSyncPlans(): void {
    akEarn(fn() => ak_sync_products(akEarnPlanProducts()), 'sync_products');
}
