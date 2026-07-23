<?php
/**
 * Public menu router — /r/{slug} (or /t/{token} for a specific table).
 * Loads the tenant, builds the $menu array, and renders the selected design template.
 */
require_once dirname(__DIR__) . '/config/config.php';
checkMaintenance();

// ---- Resolve tenant by slug or table QR token -------------------------------
$slug   = $_GET['slug'] ?? '';
$token  = $_GET['table'] ?? '';
$tenant = null; $tableRow = null;

if ($token !== '') {
    $tableRow = db_one('SELECT * FROM ' . tbl('tables') . ' WHERE qr_token = :t', [':t' => $token]);
    if ($tableRow) { $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tableRow['tenant_id']]); }
} elseif ($slug !== '') {
    $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
}

if (!$tenant) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;text-align:center;padding:60px">'
       . '<h1>Menu not found</h1><p>This restaurant menu does not exist.</p></div>';
    exit;
}

// Suspended / expired tenants show a friendly notice, not the menu.
if ($tenant['status'] !== 'active' || ($tenant['expiry_date'] && $tenant['expiry_date'] < date('Y-m-d'))) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;text-align:center;padding:60px">'
       . '<h1>Menu temporarily unavailable</h1><p>Please check back later.</p></div>';
    exit;
}

// ---- Language switch ---------------------------------------------------------
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'gu'])) {
    $_SESSION['public_lang'] = $_GET['lang'];
    // Redirect to clean URL preserving context.
    $back = strtok($_SERVER['REQUEST_URI'], '?');
    redirect($back . ($token ? '?table=' . urlencode($token) : ''));
}
$lang = $_SESSION['public_lang'] ?? ($tenant['language'] ?: 'en');
$_SESSION['lang'] = $lang;

// ---- Record the menu open for scan analytics (once per device/day) ----------
recordMenuView((int)$tenant['id']);

// ---- Build menu + template context ------------------------------------------
$menuData     = getTenantMenu((int)$tenant['id'], true);
$menu         = $menuData;                       // templates read $menu['tenant'], $menu['categories']
$categories   = $menuData['categories'];
$orderingMode = $tenant['ordering_mode'];
$tableToken   = $token ?: null;
$tableNo      = $tableRow['table_no'] ?? null;
$currency     = $tenant['currency'] ?: getSetting('currency', '₹');
$showBranding = !planHasFeature((int)$tenant['id'], 'remove_branding');
$chatbotOn    = getSetting('ai_chatbot_enabled', '0') === '1'
             && trim((string)getSetting('gemini_api_key', '')) !== '';
$folder       = tenantTemplateFolder($tenant);
$tplFile      = TEMPLATE_PATH . '/' . $folder . '/index.php';
if (!file_exists($tplFile)) { $tplFile = TEMPLATE_PATH . '/modern/index.php'; }

// Expose everything the template needs, then render it.
require $tplFile;
