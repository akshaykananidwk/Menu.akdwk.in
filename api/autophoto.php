<?php
/**
 * AK Menu System — Bulk auto-photo endpoint.
 * action=fill : for the CURRENT tenant's items that have no image, assign a
 *               relevant bundled food photo via guessFoodImage(). Returns the
 *               number of items updated.
 *
 * SECURITY: tenant resolved from session only (currentTenantId()); every read
 *           and write is scoped to tenant_id. CSRF-checked on POST.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/food_icons.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrfCheck(); }

requireClient();
$tid    = currentTenantId();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // -----------------------------------------------------------------
        // FILL — set image on this tenant's items where it is NULL or empty.
        // -----------------------------------------------------------------
        case 'fill': {
            // Join category name so the matcher can use it as a fallback.
            $rows = db_all(
                'SELECT i.id, i.name, c.name AS cat_name
                   FROM ' . tbl('items') . ' i
                   LEFT JOIN ' . tbl('categories') . ' c ON c.id = i.category_id
                  WHERE i.tenant_id = :t AND (i.image IS NULL OR i.image = \'\')',
                [':t' => $tid]
            );

            $updated = 0;
            foreach ($rows as $r) {
                $img = guessFoodImage((string)$r['name'], (string)($r['cat_name'] ?? ''));
                if (!$img) { continue; } // pool missing — skip
                // Tenant-isolated update (id + tenant_id both scoped).
                db_query(
                    'UPDATE ' . tbl('items') . ' SET image = :img WHERE id = :id AND tenant_id = :t',
                    [':img' => $img, ':id' => (int)$r['id'], ':t' => $tid]
                );
                $updated++;
            }

            jsonSuccess(
                $updated > 0
                    ? "Added photos to $updated item(s) without images."
                    : 'All items already have photos.',
                ['updated' => $updated]
            );
            break;
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $ex) {
    jsonError('Server error: ' . $ex->getMessage(), 500);
}
