<?php
/**
 * Super Admin API endpoint.
 * Handles all write operations for clients (tenants) and plans.
 * Contract: JSON responses via jsonSuccess/jsonError. CSRF required on POST.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
}
requireAdmin();

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$action  = $_GET['action'] ?? '';

/** Small helper: read a trimmed POST string. */
function pstr(string $k, string $default = ''): string {
    return trim((string)($_POST[$k] ?? $default));
}

/** Ensure a slug is unique across tenants, excluding a given tenant id. */
function uniqueSlugExcept(string $slug, int $exceptId): string {
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') { $slug = 'r'; }
    $base = $slug; $i = 1;
    while ((int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE slug = :s AND id <> :id',
                       [':s' => $slug, ':id' => $exceptId]) > 0) {
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}

try {
    switch ($action) {

        // -----------------------------------------------------------------
        // CLIENT (tenant) create / update
        // -----------------------------------------------------------------
        case 'client_save': {
            $id             = (int)($_POST['id'] ?? 0);
            $restaurantName = pstr('restaurant_name');
            $ownerName      = pstr('owner_name');
            $mobile         = pstr('mobile');
            $email          = pstr('email');
            $address        = pstr('address');
            $city           = pstr('city');
            $slugInput      = pstr('slug');
            $planId         = (int)($_POST['plan_id'] ?? 0);
            $startDate      = pstr('start_date');
            $expiryDate     = pstr('expiry_date');
            $orderingMode   = pstr('ordering_mode', 'view_only');
            $status         = pstr('status', 'active');

            // --- Validation ---
            if ($restaurantName === '') { jsonError('Restaurant name is required.'); }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { jsonError('Invalid email address.'); }
            if (!in_array($orderingMode, ['direct', 'waiter', 'view_only'], true)) { $orderingMode = 'view_only'; }
            if (!in_array($status, ['active', 'suspended', 'expired'], true)) { $status = 'active'; }

            // Resolve the plan (for validity + welcome message).
            $plan = $planId ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $planId]) : null;
            if ($planId && !$plan) { jsonError('Selected plan does not exist.'); }

            // Normalise dates.
            if ($startDate === '' || !strtotime($startDate)) { $startDate = date('Y-m-d'); }
            else { $startDate = date('Y-m-d', strtotime($startDate)); }
            if ($expiryDate === '' || !strtotime($expiryDate)) {
                $days = $plan ? (int)$plan['validity_days'] : 365;
                $expiryDate = date('Y-m-d', strtotime($startDate . ' +' . $days . ' days'));
            } else {
                $expiryDate = date('Y-m-d', strtotime($expiryDate));
            }

            $data = [
                'restaurant_name' => $restaurantName,
                'owner_name'      => $ownerName ?: null,
                'mobile'          => $mobile ?: null,
                'email'           => $email ?: null,
                'address'         => $address ?: null,
                'city'            => $city ?: null,
                'plan_id'         => $planId ?: null,
                'start_date'      => $startDate,
                'expiry_date'     => $expiryDate,
                'ordering_mode'   => $orderingMode,
                'status'          => $status,
            ];

            if ($id > 0) {
                // ---- UPDATE ----
                $existing = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $id]);
                if (!$existing) { jsonError('Client not found.', 404); }

                // Slug: keep current when blank; otherwise re-validate uniqueness (excluding self).
                if ($slugInput === '') {
                    $data['slug'] = $existing['slug'];
                } else {
                    $data['slug'] = uniqueSlugExcept($slugInput, $id);
                }

                db_update('tenants', $data, ['id' => $id]);
                logActivity('super_admin', $adminId, 'Updated client #' . $id . ' (' . $restaurantName . ')');
                jsonSuccess('Client updated successfully.', ['id' => $id]);
            }

            // ---- CREATE ----
            $password = (string)($_POST['password'] ?? '');
            if (strlen($password) < 6) { jsonError('Password must be at least 6 characters.'); }

            // Slug: auto-generate from name when blank.
            $slug = makeSlug($slugInput !== '' ? $slugInput : $restaurantName);
            $data['slug']     = $slug;
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);

            $newId = db_insert('tenants', $data);
            logActivity('super_admin', $adminId, 'Created client #' . $newId . ' (' . $restaurantName . ')');

            // Best-effort onboarding: generate QR + fire welcome WhatsApp. Never fail signup on these.
            $qrPath = null;
            try {
                $qrPath = generateQr(publicMenuUrl($slug), $slug);
            } catch (Throwable $e) {
                error_log('Client QR generation failed: ' . $e->getMessage());
            }
            try {
                if ($mobile !== '') {
                    sendWaTemplate('signup_welcome', $mobile, [
                        'owner_name'      => $ownerName ?: $restaurantName,
                        'restaurant_name' => $restaurantName,
                        'menu_url'        => publicMenuUrl($slug),
                        'mobile'          => $mobile,
                        'password'        => $password,
                        'plan_name'       => $plan ? $plan['name'] : '-',
                        'expiry_date'     => date('d-m-Y', strtotime($expiryDate)),
                    ], null, $newId, true);
                }
            } catch (Throwable $e) {
                error_log('Client welcome WhatsApp failed: ' . $e->getMessage());
            }

            jsonSuccess('Client created successfully.', [
                'id'        => $newId,
                'slug'      => $slug,
                'menu_url'  => publicMenuUrl($slug),
                'qr'        => $qrPath ? (BASE_URL . '/' . $qrPath) : null,
            ]);
        }

        // -----------------------------------------------------------------
        // CLIENT delete
        // -----------------------------------------------------------------
        case 'client_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $t  = db_one('SELECT restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $id]);
            if (!$t) { jsonError('Client not found.', 404); }
            db_query('DELETE FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $id]);
            logActivity('super_admin', $adminId, 'Deleted client #' . $id . ' (' . $t['restaurant_name'] . ')');
            jsonSuccess('Client deleted.');
        }

        // -----------------------------------------------------------------
        // CLIENT reset password
        // -----------------------------------------------------------------
        case 'client_reset_password': {
            $id = (int)($_POST['id'] ?? 0);
            $t  = db_one('SELECT restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $id]);
            if (!$t) { jsonError('Client not found.', 404); }

            // Use a provided password, or generate a random one.
            $newPass = trim((string)($_POST['password'] ?? ''));
            if ($newPass === '') {
                $newPass = substr(preg_replace('/[^A-Za-z0-9]/', '', base64_encode(random_bytes(9))), 0, 10);
            } elseif (strlen($newPass) < 6) {
                jsonError('Password must be at least 6 characters.');
            }
            db_update('tenants', ['password' => password_hash($newPass, PASSWORD_DEFAULT)], ['id' => $id]);
            logActivity('super_admin', $adminId, 'Reset password for client #' . $id);
            jsonSuccess('Password reset.', ['password' => $newPass]);
        }

        // -----------------------------------------------------------------
        // CLIENT suspend / activate toggle
        // -----------------------------------------------------------------
        case 'client_toggle_status': {
            $id = (int)($_POST['id'] ?? 0);
            $t  = db_one('SELECT status FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $id]);
            if (!$t) { jsonError('Client not found.', 404); }
            $new = $t['status'] === 'active' ? 'suspended' : 'active';
            db_update('tenants', ['status' => $new], ['id' => $id]);
            logActivity('super_admin', $adminId, 'Set client #' . $id . ' status to ' . $new);
            jsonSuccess('Status updated to ' . $new . '.', ['status' => $new]);
        }

        // -----------------------------------------------------------------
        // PLAN create / update
        // -----------------------------------------------------------------
        case 'plan_save': {
            $id   = (int)($_POST['id'] ?? 0);
            $name = pstr('name');
            if ($name === '') { jsonError('Plan name is required.'); }

            // Feature flags (checkboxes) → features_json.
            $featureKeys = ['direct_ordering', 'waiter_ordering', 'kot_screen', 'payment_gateway',
                            'analytics', 'whatsapp', 'multi_language', 'remove_branding',
                            'custom_domain', 'ai_photo'];
            $features = [];
            foreach ($featureKeys as $fk) {
                $features[$fk] = !empty($_POST[$fk]) && ($_POST[$fk] === '1' || $_POST[$fk] === 'on' || $_POST[$fk] === 'true');
            }

            $data = [
                'name'           => $name,
                'price'          => (float)($_POST['price'] ?? 0),
                'validity_days'  => max(1, (int)($_POST['validity_days'] ?? 365)),
                'max_items'      => max(0, (int)($_POST['max_items'] ?? 0)),
                'max_categories' => max(0, (int)($_POST['max_categories'] ?? 0)),
                'max_outlets'    => max(0, (int)($_POST['max_outlets'] ?? 1)),
                'max_waiters'    => max(0, (int)($_POST['max_waiters'] ?? 0)),
                'max_tables'     => max(0, (int)($_POST['max_tables'] ?? 0)),
                'ai_credits'     => max(0, (int)($_POST['ai_credits'] ?? 0)),
                'features_json'  => json_encode($features),
                'status'         => !empty($_POST['status']) ? 1 : 0,
            ];

            if ($id > 0) {
                if (!db_one('SELECT id FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $id])) {
                    jsonError('Plan not found.', 404);
                }
                db_update('plans', $data, ['id' => $id]);
                logActivity('super_admin', $adminId, 'Updated plan #' . $id . ' (' . $name . ')');
                jsonSuccess('Plan updated.', ['id' => $id]);
            }

            $newId = db_insert('plans', $data);
            logActivity('super_admin', $adminId, 'Created plan #' . $newId . ' (' . $name . ')');
            jsonSuccess('Plan created.', ['id' => $newId]);
        }

        // -----------------------------------------------------------------
        // PLAN delete
        // -----------------------------------------------------------------
        case 'plan_delete': {
            $id = (int)($_POST['id'] ?? 0);
            $p  = db_one('SELECT name FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $id]);
            if (!$p) { jsonError('Plan not found.', 404); }
            // Block deletion when tenants still reference this plan.
            $inUse = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE plan_id = :id', [':id' => $id]);
            if ($inUse > 0) { jsonError('Cannot delete: ' . $inUse . ' client(s) are on this plan.'); }
            db_query('DELETE FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $id]);
            logActivity('super_admin', $adminId, 'Deleted plan #' . $id . ' (' . $p['name'] . ')');
            jsonSuccess('Plan deleted.');
        }

        // -----------------------------------------------------------------
        // PLAN status toggle
        // -----------------------------------------------------------------
        case 'plan_toggle': {
            $id = (int)($_POST['id'] ?? 0);
            $p  = db_one('SELECT status FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => $id]);
            if (!$p) { jsonError('Plan not found.', 404); }
            $new = (int)$p['status'] === 1 ? 0 : 1;
            db_update('plans', ['status' => $new], ['id' => $id]);
            logActivity('super_admin', $adminId, 'Set plan #' . $id . ' status to ' . $new);
            jsonSuccess('Plan status updated.', ['status' => $new]);
        }

        default:
            jsonError('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('api/admin.php error: ' . $e->getMessage());
    jsonError('Server error. Please try again.', 500);
}
