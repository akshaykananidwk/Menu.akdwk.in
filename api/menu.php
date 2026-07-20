<?php
/**
 * AK Menu System — Client Menu Management API.
 * Handles categories & items CRUD, reordering, bulk actions, CSV import/export.
 * SECURITY: every query is scoped to tenant_id = currentTenantId(); a tenant_id
 * is NEVER accepted from the request. Ownership is verified in every WHERE.
 */
require_once dirname(__DIR__) . '/config/config.php';

requireClient();
$tid    = currentTenantId();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// -----------------------------------------------------------------------------
// CSV EXPORT — streamed directly (GET, no JSON envelope). Handle before headers.
// -----------------------------------------------------------------------------
if ($action === 'export_csv') {
    // Build a CSV of the tenant's menu so it can be edited in Excel / re-imported.
    $rows = db_all(
        'SELECT c.name AS category, i.name, i.name_gu, i.description, i.price,
                i.discount_price, i.is_veg, i.is_jain, i.spice_level, i.prep_time,
                i.is_available, i.is_bestseller, i.is_new, i.tags
         FROM ' . tbl('items') . ' i
         LEFT JOIN ' . tbl('categories') . ' c ON c.id = i.category_id
         WHERE i.tenant_id = :t AND i.status = 1
         ORDER BY c.sort_order, i.sort_order, i.id',
        [':t' => $tid]
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="menu-export-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['category', 'name', 'name_gu', 'description', 'price', 'discount_price',
        'is_veg', 'is_jain', 'spice_level', 'prep_time', 'is_available', 'is_bestseller', 'is_new', 'tags']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['category'], $r['name'], $r['name_gu'], $r['description'], $r['price'],
            $r['discount_price'], $r['is_veg'], $r['is_jain'], $r['spice_level'], $r['prep_time'],
            $r['is_available'], $r['is_bestseller'], $r['is_new'], $r['tags'],
        ]);
    }
    fclose($out);
    exit;
}

// -----------------------------------------------------------------------------
// All other actions speak JSON. POST actions require a valid CSRF token.
// -----------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

/** Confirm a category belongs to the current tenant. */
function ownCategory(int $tid, int $catId): bool {
    return (bool)db_val('SELECT COUNT(*) FROM ' . tbl('categories') . ' WHERE id = :c AND tenant_id = :t',
        [':c' => $catId, ':t' => $tid]);
}
/** Confirm an item belongs to the current tenant. */
function ownItem(int $tid, int $itemId): bool {
    return (bool)db_val('SELECT COUNT(*) FROM ' . tbl('items') . ' WHERE id = :i AND tenant_id = :t',
        [':i' => $itemId, ':t' => $tid]);
}

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // LIST (categories + items for the panel; also used to refresh UI)
        // ---------------------------------------------------------------------
        case 'list': {
            $cats = db_all('SELECT * FROM ' . tbl('categories') . '
                            WHERE tenant_id = :t ORDER BY sort_order, id', [':t' => $tid]);
            $items = db_all('SELECT * FROM ' . tbl('items') . '
                             WHERE tenant_id = :t ORDER BY sort_order, id', [':t' => $tid]);
            foreach ($items as &$it) {
                $it['variants'] = db_all('SELECT id,label,price FROM ' . tbl('item_variants') . ' WHERE item_id = :i', [':i' => $it['id']]);
                $it['addons']   = db_all('SELECT id,name,price FROM ' . tbl('item_addons') . ' WHERE item_id = :i', [':i' => $it['id']]);
            }
            unset($it);
            $lim = checkPlanLimit($tid, 'items');
            $climb = checkPlanLimit($tid, 'categories');
            jsonSuccess('', ['categories' => $cats, 'items' => $items,
                'items_limit' => $lim, 'categories_limit' => $climb]);
            break;
        }

        // ---------------------------------------------------------------------
        // CATEGORY SAVE (create / update)
        // ---------------------------------------------------------------------
        case 'category_save': {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') { jsonError('Category name is required.'); }

            $data = [
                'name'           => $name,
                'name_gu'        => trim($_POST['name_gu'] ?? '') ?: null,
                'icon'           => trim($_POST['icon'] ?? '') ?: null,
                'available_from' => trim($_POST['available_from'] ?? '') ?: null,
                'available_to'   => trim($_POST['available_to'] ?? '') ?: null,
            ];
            // Optional image upload.
            if (!empty($_FILES['image']['name'])) {
                $img = uploadImage($_FILES['image'], 'items');
                if ($img) { $data['image'] = $img; }
            }

            if ($id > 0) {
                if (!ownCategory($tid, $id)) { jsonError('Category not found.', 404); }
                db_update('categories', $data, ['id' => $id, 'tenant_id' => $tid]);
                jsonSuccess('Category updated.', ['id' => $id]);
            } else {
                // Enforce category plan limit on create (server-side).
                $lim = checkPlanLimit($tid, 'categories');
                if (!$lim['allowed']) { jsonError($lim['message'], 403); }
                $data['tenant_id']  = $tid;
                $data['sort_order'] = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . tbl('categories') . ' WHERE tenant_id = :t', [':t' => $tid]);
                $newId = db_insert('categories', $data);
                jsonSuccess('Category added.', ['id' => $newId]);
            }
            break;
        }

        // ---------------------------------------------------------------------
        // CATEGORY DELETE
        // ---------------------------------------------------------------------
        case 'category_delete': {
            $id = (int)($_POST['id'] ?? 0);
            if (!ownCategory($tid, $id)) { jsonError('Category not found.', 404); }
            // Items keep their rows but lose the category link (FK ON DELETE SET NULL).
            db_query('DELETE FROM ' . tbl('categories') . ' WHERE id = :c AND tenant_id = :t', [':c' => $id, ':t' => $tid]);
            jsonSuccess('Category deleted.');
            break;
        }

        // ---------------------------------------------------------------------
        // CATEGORY REORDER (array of ids in new order)
        // ---------------------------------------------------------------------
        case 'category_reorder': {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) { $ids = json_decode((string)$ids, true) ?: []; }
            $order = 1;
            foreach ($ids as $cid) {
                $cid = (int)$cid;
                // tenant_id in WHERE guarantees no cross-tenant writes.
                db_query('UPDATE ' . tbl('categories') . ' SET sort_order = :s WHERE id = :c AND tenant_id = :t',
                    [':s' => $order++, ':c' => $cid, ':t' => $tid]);
            }
            jsonSuccess('Order saved.');
            break;
        }

        // ---------------------------------------------------------------------
        // ITEM SAVE (create / update) with variants + addons
        // ---------------------------------------------------------------------
        case 'item_save': {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $catId   = (int)($_POST['category_id'] ?? 0);
            if ($name === '') { jsonError('Item name is required.'); }
            if ($catId <= 0 || !ownCategory($tid, $catId)) { jsonError('Please choose a valid category.'); }

            // Derive is_veg / is_jain from a single food_type selector.
            $foodType = $_POST['food_type'] ?? ($_POST['is_veg'] ?? 'veg');
            switch ($foodType) {
                case 'nonveg': $isVeg = 0; $isJain = 0; break;
                case 'egg':    $isVeg = 0; $isJain = 0; break;
                case 'jain':   $isVeg = 1; $isJain = 1; break;
                case '0':      $isVeg = 0; $isJain = 0; break;
                default:       $isVeg = 1; $isJain = 0; break; // veg / '1'
            }

            $data = [
                'category_id'    => $catId,
                'name'           => $name,
                'name_gu'        => trim($_POST['name_gu'] ?? '') ?: null,
                'description'    => trim($_POST['description'] ?? '') ?: null,
                'description_gu' => trim($_POST['description_gu'] ?? '') ?: null,
                'price'          => (float)($_POST['price'] ?? 0),
                'discount_price' => ($_POST['discount_price'] ?? '') !== '' ? (float)$_POST['discount_price'] : null,
                'is_veg'         => $isVeg,
                'is_jain'        => $isJain,
                'spice_level'    => max(0, min(3, (int)($_POST['spice_level'] ?? 0))),
                'prep_time'      => ($_POST['prep_time'] ?? '') !== '' ? (int)$_POST['prep_time'] : null,
                'is_available'   => !empty($_POST['is_available']) ? 1 : 0,
                'is_bestseller'  => !empty($_POST['is_bestseller']) ? 1 : 0,
                'is_new'         => !empty($_POST['is_new']) ? 1 : 0,
                'tags'           => trim($_POST['tags'] ?? '') ?: null,
            ];
            if (!empty($_FILES['image']['name'])) {
                $img = uploadImage($_FILES['image'], 'items');
                if ($img) { $data['image'] = $img; }
            }

            if ($id > 0) {
                if (!ownItem($tid, $id)) { jsonError('Item not found.', 404); }
                db_update('items', $data, ['id' => $id, 'tenant_id' => $tid]);
            } else {
                // Enforce item plan limit on create.
                $lim = checkPlanLimit($tid, 'items');
                if (!$lim['allowed']) { jsonError($lim['message'], 403); }
                $data['tenant_id']  = $tid;
                $data['sort_order'] = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . tbl('items') . ' WHERE tenant_id = :t AND category_id = :c', [':t' => $tid, ':c' => $catId]);
                $id = db_insert('items', $data);
            }

            // Variants + addons: delete then re-insert (item already tenant-verified).
            $variants = json_decode($_POST['variants_json'] ?? '[]', true) ?: [];
            $addons   = json_decode($_POST['addons_json'] ?? '[]', true) ?: [];
            db_query('DELETE FROM ' . tbl('item_variants') . ' WHERE item_id = :i', [':i' => $id]);
            db_query('DELETE FROM ' . tbl('item_addons') . ' WHERE item_id = :i', [':i' => $id]);
            foreach ($variants as $v) {
                $label = trim($v['label'] ?? '');
                if ($label === '') { continue; }
                db_insert('item_variants', ['item_id' => $id, 'label' => $label, 'price' => (float)($v['price'] ?? 0)]);
            }
            foreach ($addons as $a) {
                $an = trim($a['name'] ?? '');
                if ($an === '') { continue; }
                db_insert('item_addons', ['item_id' => $id, 'name' => $an, 'price' => (float)($a['price'] ?? 0)]);
            }
            jsonSuccess('Item saved.', ['id' => $id]);
            break;
        }

        // ---------------------------------------------------------------------
        // ITEM DELETE
        // ---------------------------------------------------------------------
        case 'item_delete': {
            $id = (int)($_POST['id'] ?? 0);
            if (!ownItem($tid, $id)) { jsonError('Item not found.', 404); }
            db_query('DELETE FROM ' . tbl('items') . ' WHERE id = :i AND tenant_id = :t', [':i' => $id, ':t' => $tid]);
            jsonSuccess('Item deleted.');
            break;
        }

        // ---------------------------------------------------------------------
        // ITEM REORDER (within a category)
        // ---------------------------------------------------------------------
        case 'item_reorder': {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) { $ids = json_decode((string)$ids, true) ?: []; }
            $order = 1;
            foreach ($ids as $iid) {
                db_query('UPDATE ' . tbl('items') . ' SET sort_order = :s WHERE id = :i AND tenant_id = :t',
                    [':s' => $order++, ':i' => (int)$iid, ':t' => $tid]);
            }
            jsonSuccess('Order saved.');
            break;
        }

        // ---------------------------------------------------------------------
        // ITEM TOGGLE AVAILABILITY
        // ---------------------------------------------------------------------
        case 'item_toggle_available': {
            $id = (int)($_POST['id'] ?? 0);
            if (!ownItem($tid, $id)) { jsonError('Item not found.', 404); }
            $val = !empty($_POST['is_available']) ? 1 : 0;
            db_query('UPDATE ' . tbl('items') . ' SET is_available = :a WHERE id = :i AND tenant_id = :t',
                [':a' => $val, ':i' => $id, ':t' => $tid]);
            jsonSuccess('Availability updated.', ['is_available' => $val]);
            break;
        }

        // ---------------------------------------------------------------------
        // BULK ACTIONS on selected item ids
        // ---------------------------------------------------------------------
        case 'bulk_action': {
            $op  = $_POST['op'] ?? '';
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids)) { $ids = json_decode((string)$ids, true) ?: []; }
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) { jsonError('No items selected.'); }

            // Whitelist only ids owned by this tenant.
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $owned = db_all('SELECT id FROM ' . tbl('items') . " WHERE tenant_id = ? AND id IN ($ph)",
                array_merge([$tid], $ids));
            $ownedIds = array_column($owned, 'id');
            if (!$ownedIds) { jsonError('No valid items.'); }
            $oph = implode(',', array_fill(0, count($ownedIds), '?'));

            switch ($op) {
                case 'unavailable':
                    db_query('UPDATE ' . tbl('items') . " SET is_available = 0 WHERE tenant_id = ? AND id IN ($oph)",
                        array_merge([$tid], $ownedIds));
                    break;
                case 'available':
                    db_query('UPDATE ' . tbl('items') . " SET is_available = 1 WHERE tenant_id = ? AND id IN ($oph)",
                        array_merge([$tid], $ownedIds));
                    break;
                case 'delete':
                    db_query('DELETE FROM ' . tbl('items') . " WHERE tenant_id = ? AND id IN ($oph)",
                        array_merge([$tid], $ownedIds));
                    break;
                case 'price_pct':
                    $pct = (float)($_POST['pct'] ?? 0);
                    // Adjust price by percentage (positive = increase, negative = decrease).
                    $factor = 1 + ($pct / 100);
                    db_query('UPDATE ' . tbl('items') . " SET price = ROUND(price * ?, 2) WHERE tenant_id = ? AND id IN ($oph)",
                        array_merge([$factor, $tid], $ownedIds));
                    break;
                default:
                    jsonError('Unknown bulk operation.');
            }
            jsonSuccess('Bulk action applied to ' . count($ownedIds) . ' item(s).', ['count' => count($ownedIds)]);
            break;
        }

        // ---------------------------------------------------------------------
        // CSV IMPORT (upload a CSV; insert items respecting plan limits)
        // ---------------------------------------------------------------------
        case 'import_csv': {
            if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                jsonError('Please upload a CSV file.');
            }
            $fh = fopen($_FILES['file']['tmp_name'], 'r');
            if (!$fh) { jsonError('Could not read the file.'); }

            $header = fgetcsv($fh);
            if (!$header) { fclose($fh); jsonError('Empty CSV.'); }
            $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
            $col = array_flip($header);
            $get = function (array $row, string $key) use ($col) {
                return isset($col[$key]) ? trim((string)($row[$col[$key]] ?? '')) : '';
            };

            // Cache existing categories by lower-name to avoid duplicates.
            $catMap = [];
            foreach (db_all('SELECT id,name FROM ' . tbl('categories') . ' WHERE tenant_id = :t', [':t' => $tid]) as $c) {
                $catMap[mb_strtolower($c['name'])] = (int)$c['id'];
            }

            $inserted = 0; $skipped = 0;
            while (($row = fgetcsv($fh)) !== false) {
                $name = $get($row, 'name');
                if ($name === '') { continue; }

                // Resolve / create category (respect category limit).
                $catName = $get($row, 'category') ?: 'General';
                $ckey = mb_strtolower($catName);
                if (!isset($catMap[$ckey])) {
                    $climb = checkPlanLimit($tid, 'categories');
                    if (!$climb['allowed']) { $skipped++; continue; }
                    $catMap[$ckey] = db_insert('categories', [
                        'tenant_id' => $tid, 'name' => $catName,
                        'sort_order' => count($catMap) + 1,
                    ]);
                }

                // Respect item limit on every insert.
                $lim = checkPlanLimit($tid, 'items');
                if (!$lim['allowed']) { $skipped++; continue; }

                db_insert('items', [
                    'tenant_id'      => $tid,
                    'category_id'    => $catMap[$ckey],
                    'name'           => $name,
                    'name_gu'        => $get($row, 'name_gu') ?: null,
                    'description'    => $get($row, 'description') ?: null,
                    'price'          => (float)$get($row, 'price'),
                    'discount_price' => $get($row, 'discount_price') !== '' ? (float)$get($row, 'discount_price') : null,
                    'is_veg'         => $get($row, 'is_veg') === '0' ? 0 : 1,
                    'is_jain'        => $get($row, 'is_jain') === '1' ? 1 : 0,
                    'spice_level'    => (int)$get($row, 'spice_level'),
                    'prep_time'      => $get($row, 'prep_time') !== '' ? (int)$get($row, 'prep_time') : null,
                    'is_available'   => $get($row, 'is_available') === '0' ? 0 : 1,
                    'is_bestseller'  => $get($row, 'is_bestseller') === '1' ? 1 : 0,
                    'is_new'         => $get($row, 'is_new') === '1' ? 1 : 0,
                    'tags'           => $get($row, 'tags') ?: null,
                ]);
                $inserted++;
            }
            fclose($fh);
            jsonSuccess("Imported $inserted item(s), skipped $skipped.", ['inserted' => $inserted, 'skipped' => $skipped]);
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $ex) {
    jsonError('Server error: ' . $ex->getMessage(), 500);
}
