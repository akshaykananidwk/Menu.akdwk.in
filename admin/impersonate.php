<?php
/**
 * Super Admin impersonation.
 * ?tenant_id=X  -> start impersonating that tenant (opens the client panel).
 * ?stop=1       -> stop impersonating and return to the admin panel.
 * The admin_id is always kept intact so the admin session is never lost.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$adminId = (int)($_SESSION['admin_id'] ?? 0);

// ---- Stop impersonating ----
if (isset($_GET['stop'])) {
    $wasTenant = (int)($_SESSION['tenant_id'] ?? 0);
    unset($_SESSION['tenant_id'], $_SESSION['impersonated_by']);
    if ($wasTenant) {
        logActivity('super_admin', $adminId, 'Stopped impersonating client #' . $wasTenant);
    }
    redirect(BASE_URL . '/admin/clients.php');
}

// ---- Start impersonating ----
$tenantId = (int)($_GET['tenant_id'] ?? 0);
$tenant = $tenantId
    ? db_one('SELECT id, restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tenantId])
    : null;

if (!$tenant) {
    redirect(BASE_URL . '/admin/clients.php');
}

// Impersonate: set the effective tenant, tag who is impersonating, keep admin_id.
$_SESSION['tenant_id']       = (int)$tenant['id'];
$_SESSION['impersonated_by'] = $adminId;
logActivity('super_admin', $adminId, 'Started impersonating client #' . $tenant['id'] . ' (' . $tenant['restaurant_name'] . ')');

redirect(BASE_URL . '/client/index.php');
