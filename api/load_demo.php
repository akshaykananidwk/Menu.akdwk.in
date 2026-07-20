<?php
/**
 * Load the vegetarian demo menu into the current tenant's menu.
 * Client-scoped and tenant-isolated. Useful for creating a shareable demo.
 * POST action=load  (params: replace=1 to wipe existing menu first)
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

requireClient();
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }
$tid = currentTenantId();
if (!$tid) { jsonError('No tenant in session.', 403); }

$action = $_GET['action'] ?? 'load';
if ($action !== 'load') { jsonError('Unknown action.'); }

$demo = require dirname(__DIR__) . '/install/demo_menu.php';
if (!is_array($demo)) { jsonError('Demo menu data missing.'); }

$replace = !empty($_POST['replace']);

try {
    global $pdo;
    $pdo->beginTransaction();

    // Optionally clear the existing menu (tenant-isolated) before loading.
    if ($replace) {
        // order_items reference items; null them out first is unnecessary because
        // items are only soft-linked in orders via item_name. Safe to delete.
        db_query('DELETE FROM ' . tbl('item_variants') . ' WHERE item_id IN (SELECT id FROM ' . tbl('items') . ' WHERE tenant_id = :t)', [':t' => $tid]);
        db_query('DELETE FROM ' . tbl('item_addons') . ' WHERE item_id IN (SELECT id FROM ' . tbl('items') . ' WHERE tenant_id = :t)', [':t' => $tid]);
        db_query('DELETE FROM ' . tbl('items') . ' WHERE tenant_id = :t', [':t' => $tid]);
        db_query('DELETE FROM ' . tbl('categories') . ' WHERE tenant_id = :t', [':t' => $tid]);
    }

    // Respect the plan's item limit — insert only what fits.
    $plan = tenantPlan($tid);
    $maxItems = (int)($plan['max_items'] ?? 1000);
    $existing = (int)db_val('SELECT COUNT(*) FROM ' . tbl('items') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tid]);
    $room = max(0, $maxItems - $existing);

    $catSort = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM ' . tbl('categories') . ' WHERE tenant_id = :t', [':t' => $tid]);
    $catCount = 0; $itemCount = 0;

    foreach ($demo as $cat) {
        if ($room <= 0) break;
        $catSort++;
        $catId = db_insert('categories', [
            'tenant_id' => $tid,
            'name'      => $cat['name'],
            'name_gu'   => $cat['name_gu'] ?? null,
            'sort_order'=> $catSort,
            'status'    => 1,
        ]);
        $catCount++;
        $itemSort = 0;
        foreach ($cat['items'] as $it) {
            if ($room <= 0) break;
            $itemSort++;
            $itemId = db_insert('items', [
                'tenant_id'      => $tid,
                'category_id'    => $catId,
                'name'           => $it['name'],
                'name_gu'        => $it['name_gu'] ?? null,
                'description'    => $it['desc'] ?? null,
                'description_gu' => $it['desc_gu'] ?? null,
                'price'          => $it['price'],
                'discount_price' => !empty($it['disc']) ? $it['disc'] : null,
                'image'          => $it['img'] ?? null,     // absolute URL — mediaUrl() handles it
                'is_veg'         => 1,
                'is_jain'        => !empty($it['jain']) ? 1 : 0,
                'is_available'   => 1,
                'is_bestseller'  => !empty($it['best']) ? 1 : 0,
                'is_new'         => !empty($it['new']) ? 1 : 0,
                'sort_order'     => $itemSort,
                'status'         => 1,
            ]);
            foreach ($it['variants'] ?? [] as $v) {
                db_insert('item_variants', ['item_id' => $itemId, 'label' => $v[0], 'price' => $v[1]]);
            }
            $itemCount++; $room--;
        }
    }

    $pdo->commit();
    logActivity('tenant', $tid, "Loaded demo menu ($itemCount items)");
    jsonSuccess("Demo menu loaded: $catCount categories, $itemCount items.", [
        'categories' => $catCount, 'items' => $itemCount,
        'capped' => $room <= 0,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    jsonError('Could not load demo menu: ' . $e->getMessage());
}
