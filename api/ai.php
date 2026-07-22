<?php
/**
 * AK Menu System — AI Menu Extraction API.
 * action=extract : upload menu photos, run Gemini OCR, deduct one AI credit.
 * action=save    : persist the (edited) extracted categories/items.
 * SECURITY: tenant resolved from session only; every write scoped to tenant_id.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

requireClient();
$tid    = currentTenantId();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // EXTRACT — save uploaded images, call Gemini, deduct a credit.
        // ---------------------------------------------------------------------
        case 'extract': {
            // 1) AI credit check FIRST (server-side enforcement).
            $lim = checkPlanLimit($tid, 'ai');
            if (!$lim['allowed']) {
                jsonError($lim['message'] ?: 'AI credits exhausted. Please upgrade.', 403, ['limit' => $lim]);
            }

            // 2) Validate + store uploads to uploads/menus with random names.
            $files = $_FILES['files'] ?? null;
            if (!$files || empty($files['name'][0])) {
                jsonError('Please upload at least one menu photo.');
            }
            $dir = UPLOAD_PATH . '/menus';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $paths = [];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) { continue; }
                if ($files['size'][$i] > 8 * 1024 * 1024) { continue; } // 8MB cap
                $mime = $finfo->file($files['tmp_name'][$i]);
                if (!isset($allowed[$mime])) { continue; }
                $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                $dest = $dir . '/' . $name;
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) { $paths[] = $dest; }
            }
            if (!$paths) { jsonError('No valid image/PDF files were uploaded (jpg, png, webp, pdf only).'); }

            // 3) Call Gemini (with metering context for token/cost logging).
            aiSetContext(['user_id' => $tid, 'source' => 'menu_extract', 'key_owner' => 'platform']);
            $res = geminiExtractMenu($paths);
            if (!$res['ok']) {
                logAi($tid, 'menu_ocr', 0, 'failed');
                jsonError($res['error'] ?: 'AI extraction failed. Please try again or enter items manually.', 502);
            }

            // 4) Deduct a credit + log success.
            db_query('UPDATE ' . tbl('tenants') . ' SET ai_credits_used = ai_credits_used + 1 WHERE id = :t', [':t' => $tid]);
            logAi($tid, 'menu_ocr', 0, 'success');

            $after = checkPlanLimit($tid, 'ai');
            jsonSuccess('Menu extracted successfully.', [
                'menu'      => $res['data'],
                'remaining' => max(0, $after['max'] - $after['used']),
            ]);
            break;
        }

        // ---------------------------------------------------------------------
        // SAVE — insert reviewed categories + items (respect plan limits).
        // ---------------------------------------------------------------------
        case 'save': {
            // Auto food-photo matcher (bundled local photos for items without one).
            require_once dirname(__DIR__) . '/config/food_icons.php';
            $raw = $_POST['categories'] ?? '[]';
            $categories = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);
            if (!$categories) { jsonError('Nothing to save.'); }

            $addedCats = 0; $addedItems = 0; $skipped = 0;
            foreach ($categories as $cat) {
                $cname = trim($cat['name'] ?? '');
                if ($cname === '') { continue; }

                // Reuse an existing category with the same name, else create.
                $catId = (int)db_val('SELECT id FROM ' . tbl('categories') . '
                                      WHERE tenant_id = :t AND name = :n LIMIT 1', [':t' => $tid, ':n' => $cname]);
                if (!$catId) {
                    $climb = checkPlanLimit($tid, 'categories');
                    if (!$climb['allowed']) { $skipped += count($cat['items'] ?? []); continue; }
                    $catId = db_insert('categories', [
                        'tenant_id'  => $tid,
                        'name'       => $cname,
                        'name_gu'    => trim($cat['name_gu'] ?? '') ?: null,
                        'sort_order' => (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . tbl('categories') . ' WHERE tenant_id = :t', [':t' => $tid]),
                    ]);
                    $addedCats++;
                }

                foreach (($cat['items'] ?? []) as $it) {
                    $iname = trim($it['name'] ?? '');
                    if ($iname === '') { continue; }
                    $lim = checkPlanLimit($tid, 'items');
                    if (!$lim['allowed']) { $skipped++; continue; }

                    $isVeg = array_key_exists('is_veg', $it) ? (int)(bool)$it['is_veg'] : 1;
                    // Keep any user-provided image; otherwise auto-assign a relevant photo.
                    $image = trim((string)($it['image'] ?? '')) ?: guessFoodImage($iname, $cname);
                    $itemId = db_insert('items', [
                        'tenant_id'   => $tid,
                        'category_id' => $catId,
                        'name'        => $iname,
                        'name_gu'     => trim($it['name_gu'] ?? '') ?: null,
                        'description' => trim($it['description'] ?? '') ?: null,
                        'price'       => (float)($it['price'] ?? 0),
                        'image'       => $image ?: null,
                        'is_veg'      => $isVeg,
                        'sort_order'  => $addedItems + 1,
                    ]);
                    $addedItems++;

                    // Associate variants if provided.
                    foreach (($it['variants'] ?? []) as $v) {
                        $label = trim($v['label'] ?? '');
                        if ($label === '') { continue; }
                        db_insert('item_variants', ['item_id' => $itemId, 'label' => $label, 'price' => (float)($v['price'] ?? 0)]);
                    }
                }
            }
            $msg = "Saved $addedItems item(s) in $addedCats new category(ies).";
            if ($skipped > 0) { $msg .= " $skipped skipped (plan limit)."; }
            jsonSuccess($msg, ['items' => $addedItems, 'categories' => $addedCats, 'skipped' => $skipped]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $ex) {
    jsonError('Server error: ' . $ex->getMessage(), 500);
}
