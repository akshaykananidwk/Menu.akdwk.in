<?php
/**
 * PUBLIC restaurant self-signup.
 * Creates a tenant on the Free Trial plan, optionally runs AI menu extraction
 * on uploaded menu photos/PDFs (category-wise), generates the QR, and fires the
 * WhatsApp welcome. No login required. Rate-limited + validated.
 *
 * POST (multipart): restaurant_name, owner_name, mobile, email, city, password,
 *                   language, menu_files[] (images/pdf, optional)
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

// Signup can be turned off globally.
if (getSetting('allow_signup', '1') !== '1') { jsonError('Registrations are currently closed.', 403); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST required.', 405); }
csrfCheck();

// ---- Anti-abuse -------------------------------------------------------------
$ip = clientIp();
if (isLoginLocked('signup:' . $ip)) { jsonError('Too many attempts. Please try again in a few minutes.', 429); }
// Honeypot: only treat as spam if the hidden field looks like injected spam
// (a URL). Mobile browsers auto-fill hidden fields, so a plain value is NOT
// enough to reject — that was blocking real signups.
$hp = trim((string)($_POST['website'] ?? ''));
if ($hp !== '' && preg_match('#https?://|www\.#i', $hp)) { jsonError('Spam detected.', 400); }

// ---- Validate ---------------------------------------------------------------
$name  = trim($_POST['restaurant_name'] ?? '');
$owner = trim($_POST['owner_name'] ?? '');
$mobile= preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
// Accept any format the user typed (+91, 0091, leading 0, spaces) and reduce it
// to the bare 10-digit Indian mobile used as the login identity.
if (strncmp($mobile, '0091', 4) === 0)                       { $mobile = substr($mobile, 4); }
if (strlen($mobile) === 12 && strncmp($mobile, '91', 2) === 0) { $mobile = substr($mobile, 2); }
$mobile = ltrim($mobile, '0');
$email = trim($_POST['email'] ?? '');
$city  = trim($_POST['city'] ?? '');
$pass  = $_POST['password'] ?? '';
$lang  = in_array($_POST['language'] ?? '', ['en', 'gu'], true) ? $_POST['language'] : getSetting('default_language', 'en');

$errors = [];
if (mb_strlen($name) < 2)       { $errors[] = 'Restaurant name is required.'; }
if ($owner === '')              { $errors[] = 'Owner name is required.'; }
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) { $errors[] = 'Enter a valid 10-digit mobile number.'; }
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email address.'; }
if (strlen($pass) < 6)          { $errors[] = 'Password must be at least 6 characters.'; }
if ($errors) { recordFailedLogin('signup:' . $ip); jsonError(implode(' ', $errors), 422); }

// Uniqueness (mobile/email must not already be registered).
if (db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $mobile]) > 0) {
    jsonError('This mobile number is already registered. Please login instead.', 409);
}
if ($email !== '' && db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE email = :e', [':e' => $email]) > 0) {
    jsonError('This email is already registered. Please login instead.', 409);
}

// ---- Pick the trial plan ----------------------------------------------------
// Every self-signup gets the FREE, unlimited trial plan. Among free plans, prefer
// the most generous (highest item limit) so new users always get the unlimited one.
$plan = db_one('SELECT * FROM ' . tbl('plans') . ' WHERE price = 0 AND status = 1 ORDER BY max_items DESC, validity_days DESC LIMIT 1');
$isFreeTrial = (bool)$plan;
if (!$plan) {
    // No free plan configured — fall back to any active plan (but keep the trial short below).
    $plan = db_one('SELECT * FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY price ASC LIMIT 1');
}
if (!$plan) { jsonError('No signup plan is configured. Please contact support.', 500); }
// Trial length = the free plan's own validity (7 days in the default install). If we
// had to fall back to a paid plan, cap it to 7 days so a paid plan is never given away.
$validity = $isFreeTrial ? max(1, (int)$plan['validity_days']) : 7;

// ---- Create the tenant ------------------------------------------------------
$slug       = makeSlug($name);
$defaultTpl = (int)(db_val('SELECT id FROM ' . tbl('templates') . ' WHERE is_default = 1 AND status = 1 LIMIT 1') ?: 0);
// Unlimited trial → give the full experience (direct customer ordering) by default.
$ordering   = getSetting('signup_default_ordering', 'direct');
$ordering   = in_array($ordering, ['direct', 'waiter', 'view_only'], true) ? $ordering : 'view_only';

$tenantId = db_insert('tenants', [
    'restaurant_name' => $name,
    'slug'            => $slug,
    'owner_name'      => $owner,
    'mobile'          => $mobile,
    'email'           => $email ?: null,
    'password'        => password_hash($pass, PASSWORD_DEFAULT),
    'city'            => $city ?: null,
    'plan_id'         => (int)$plan['id'],
    'start_date'      => date('Y-m-d'),
    'expiry_date'     => date('Y-m-d', strtotime("+$validity days")),
    'ordering_mode'   => $ordering,
    'template_id'     => $defaultTpl ?: null,
    'language'        => $lang,
    'currency'        => getSetting('currency', '₹'),
    'onboarded'       => 1,
    'status'          => 'active',
]);
recordFailedLogin('signup:' . $ip); // count this signup toward the IP throttle
logActivity('tenant', $tenantId, 'Self sign-up (' . $slug . ')');

// ---- Handle uploaded menu files + AI extraction -----------------------------
$aiOk = false; $aiError = ''; $catCount = 0; $itemCount = 0;
$paths = [];
if (!empty($_FILES['menu_files']) && is_array($_FILES['menu_files']['name'])) {
    $dir = UPLOAD_PATH . '/menus';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $count = count($_FILES['menu_files']['name']);
    for ($i = 0; $i < $count && $i < 8; $i++) {
        if (($_FILES['menu_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $tmp = $_FILES['menu_files']['tmp_name'][$i];
        if (($_FILES['menu_files']['size'][$i] ?? 0) > 8 * 1024 * 1024) continue; // 8MB cap
        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime])) continue;
        $fname = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (move_uploaded_file($tmp, $dir . '/' . $fname)) { $paths[] = $dir . '/' . $fname; }
    }
}

if (!empty($paths)) {
    $limit = checkPlanLimit($tenantId, 'ai');
    if (!$limit['allowed']) {
        $aiError = 'AI credit limit reached on the trial plan — you can add items manually.';
    } elseif (!trim((string)getSetting('gemini_api_key', ''))) {
        $aiError = 'AI is not configured — you can add your menu manually.';
    } else {
        $res = geminiExtractMenu($paths);
        if ($res['ok']) {
            [$catCount, $itemCount] = signup_insert_menu($tenantId, $res['data'], $plan);
            db_query('UPDATE ' . tbl('tenants') . ' SET ai_credits_used = ai_credits_used + 1 WHERE id = :id', [':id' => $tenantId]);
            logAi($tenantId, 'signup_menu_ocr', 0, 'success');
            $aiOk = true;
        } else {
            $aiError = $res['error'];
            logAi($tenantId, 'signup_menu_ocr', 0, 'failed');
        }
    }
}

// ---- Generate QR + fire WhatsApp welcome (best-effort, never blocks signup) --
$menuUrl = publicMenuUrl($slug);
try { generateQr($menuUrl, $slug); } catch (Throwable $e) { /* non-fatal */ }
try {
    $vars = [
        'owner_name'      => $owner,
        'restaurant_name' => $name,
        'menu_url'        => $menuUrl,
        'mobile'          => $mobile,
        'password'        => $pass,
        'plan_name'       => $plan['name'],
        'expiry_date'     => date('d-m-Y', strtotime("+$validity days")),
        'qr_url'          => BASE_URL . '/uploads/qr/' . $slug . '.png',
    ];
    sendWaTemplate('signup_welcome', $mobile, $vars, null, $tenantId, true);
    $qrRel = 'uploads/qr/' . $slug . '.png';
    if (is_file(ROOT_PATH . '/' . $qrRel)) {
        sendWaTemplate('signup_qr', $mobile, $vars, BASE_URL . '/' . $qrRel, $tenantId, true);
    }
} catch (Throwable $e) { /* WhatsApp failure must not fail signup */ }

// ---- Auto-login the new owner so they land in their panel -------------------
regenerateSession();
$_SESSION['tenant_id'] = $tenantId;
$_SESSION['lang']      = $lang;

jsonSuccess('Account created successfully!', [
    'slug'         => $slug,
    'menu_url'     => $menuUrl,
    'panel_url'    => BASE_URL . '/client/index.php',
    'login_mobile' => $mobile,
    'plan'         => $plan['name'],
    'trial_days'   => $validity,
    'expiry'       => date('d-m-Y', strtotime("+$validity days")),
    'ai_ok'        => $aiOk,
    'ai_error'     => $aiError,
    'categories'   => $catCount,
    'items'        => $itemCount,
    'qr_url'       => BASE_URL . '/uploads/qr/' . $slug . '.png',
]);

/**
 * Insert the AI-extracted menu (category-wise) for a tenant, respecting plan
 * category/item limits. Returns [categoryCount, itemCount].
 */
function signup_insert_menu(int $tenantId, array $data, array $plan): array {
    // Auto food-photo matcher (bundled local photos so menus look rich instantly).
    require_once dirname(__DIR__) . '/config/food_icons.php';
    $maxCats  = (int)$plan['max_categories'];
    $maxItems = (int)$plan['max_items'];
    $catN = 0; $itemN = 0; $sort = 0;
    foreach ($data['categories'] ?? [] as $cat) {
        if ($catN >= $maxCats) break;
        $catId = db_insert('categories', [
            'tenant_id' => $tenantId,
            'name'      => mb_substr(trim($cat['name'] ?? 'Menu'), 0, 120),
            'name_gu'   => isset($cat['name_gu']) ? mb_substr(trim($cat['name_gu']), 0, 160) : null,
            'sort_order'=> $sort++,
            'status'    => 1,
        ]);
        $catN++;
        $isort = 0;
        foreach ($cat['items'] ?? [] as $it) {
            if ($itemN >= $maxItems) break;
            $price = is_numeric($it['price'] ?? null) ? (float)$it['price'] : 0;
            $iname = mb_substr(trim($it['name'] ?? 'Item'), 0, 160);
            // Assign a relevant bundled photo when the extraction gave none.
            $image = trim((string)($it['image'] ?? '')) ?: guessFoodImage($iname, $cat['name'] ?? '');
            $itemId = db_insert('items', [
                'tenant_id'   => $tenantId,
                'category_id' => $catId,
                'name'        => $iname,
                'name_gu'     => isset($it['name_gu']) ? mb_substr(trim($it['name_gu']), 0, 200) : null,
                'description' => isset($it['description']) ? trim($it['description']) : null,
                'price'       => $price,
                'image'       => $image ?: null,
                'is_veg'      => !empty($it['is_veg']) ? 1 : 0,
                'is_available'=> 1,
                'sort_order'  => $isort++,
                'status'      => 1,
            ]);
            foreach ($it['variants'] ?? [] as $v) {
                if (empty($v['label'])) continue;
                db_insert('item_variants', [
                    'item_id' => $itemId,
                    'label'   => mb_substr(trim($v['label']), 0, 80),
                    'price'   => is_numeric($v['price'] ?? null) ? (float)$v['price'] : 0,
                ]);
            }
            $itemN++;
        }
    }
    return [$catN, $itemN];
}
