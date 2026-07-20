<?php
/**
 * AK Menu System — Cron: check for a newer GitHub release (NOTIFY ONLY).
 *
 * This job NEVER installs anything. It queries the GitHub latest release and,
 * if a newer version exists, sets update_available=1 so the admin sees a badge.
 *
 * Usage:
 *   Web: /cron/check_update.php?key=<cron_secret>
 *   CLI: php cron/check_update.php <cron_secret>
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');

// -----------------------------------------------------------------------------
// Cron authentication.
// -----------------------------------------------------------------------------
$provided = $_GET['key'] ?? ($argv[1] ?? '');
$secret   = (string)getSetting('cron_secret', '');
if ($secret === '' || !is_string($provided) || !hash_equals($secret, (string)$provided)) {
    http_response_code(403);
    echo "Forbidden: invalid or missing cron key.\n";
    exit;
}

// -----------------------------------------------------------------------------
// Token de-obfuscation (mirrors api/update.php).
// -----------------------------------------------------------------------------
function cron_dec_token(string $stored): string {
    if ($stored === '' || strncmp($stored, 'enc:', 4) !== 0) return $stored;
    if (!function_exists('openssl_decrypt')) return '';
    $key = hash('sha256', (defined('DB_PASS') ? DB_PASS : '') . '|' . (defined('DB_NAME') ? DB_NAME : '') . '|ak-menu-update', true);
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 17) return '';
    $iv = substr($raw, 0, 16);
    $pt = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $pt === false ? '' : $pt;
}

$current = (string)getSetting('app_version', APP_VERSION);
$owner   = trim((string)getSetting('github_owner', ''));
$repo    = trim((string)getSetting('github_repo', ''));
$token   = cron_dec_token((string)getSetting('github_token', ''));

echo "AK Menu System — update check @ " . date('c') . "\n";
echo "Current version: $current\n";

if ($owner === '' || $repo === '') {
    echo "GitHub owner/repo not configured. Nothing to do.\n";
    exit;
}
if (!function_exists('curl_init')) {
    echo "cURL not available. Cannot check.\n";
    exit;
}

$url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/releases/latest';
$headers = ['User-Agent: AK-Menu-System', 'Accept: application/vnd.github+json'];
if ($token !== '') { $headers[] = 'Authorization: token ' . $token; }

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
    echo "Network error: " . ($err ?: 'unknown') . "\n";
    exit;
}
if ($http < 200 || $http >= 300) {
    echo "GitHub API returned HTTP $http.\n";
    exit;
}

$rel    = json_decode($resp, true);
$latest = ltrim(trim((string)($rel['tag_name'] ?? '')), 'vV');
if ($latest === '') {
    echo "No release tag found.\n";
    exit;
}

$newer = version_compare($latest, $current, '>');
setSetting('update_available', $newer ? '1' : '0');

if ($newer) {
    echo "Update AVAILABLE: v$latest (current v$current).\n";
    logActivity('cron', null, "Update available: v$latest (current v$current)");
} else {
    echo "Up to date (latest v$latest).\n";
}
