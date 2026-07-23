<?php
/**
 * Client (restaurant owner) panel layout header + sidebar.
 * Usage: set $pageTitle and $activeNav before including. Call client_footer() after.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();

$tenant    = currentTenant();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$primary   = $tenant['primary_color'] ?: '#e63946';
$secondary = $tenant['secondary_color'] ?: '#1d3557';
$plan      = tenantPlan((int)$tenant['id']);
$impersonating = !empty($_SESSION['impersonated_by']);

function cnav(string $key, string $active, string $url, string $icon, string $label, bool $show = true): string {
    if (!$show) return '';
    $cls = $key === $active ? 'active' : '';
    return '<a class="nav-link ' . $cls . '" href="' . e(BASE_URL . $url) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . '</a>';
}
$canOrder = $tenant['ordering_mode'] !== 'view_only';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title><?= e($pageTitle) ?> · <?= e($tenant['restaurant_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= e(assetUrl('assets/css/app.css')) ?>" rel="stylesheet">
<style>:root{--primary:<?= e($primary) ?>;--secondary:<?= e($secondary) ?>;--sidebar-bg:<?= e($secondary) ?>;}</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">
      <?php if ($tenant['logo']): ?><img src="<?= e(BASE_URL.'/'.$tenant['logo']) ?>" style="height:30px;border-radius:6px"><?php endif; ?>
      <span class="text-truncate"><?= e($tenant['restaurant_name']) ?></span>
    </div>
    <nav class="nav flex-column py-2">
      <div class="nav-section">Main</div>
      <?= cnav('dashboard',$activeNav,'/client/index.php','bi-speedometer2','Dashboard') ?>
      <?= cnav('menu',$activeNav,'/client/menu.php','bi-card-list','Menu Management') ?>
      <?= cnav('ai',$activeNav,'/client/ai_extract.php','bi-magic','AI Menu Import') ?>
      <?= cnav('design',$activeNav,'/client/design.php','bi-palette','Design & Branding') ?>
      <?= cnav('qr',$activeNav,'/client/qr.php','bi-qr-code','QR & Standee') ?>
      <div class="nav-section">Operations</div>
      <?= cnav('orders',$activeNav,'/client/orders.php','bi-receipt-cutoff','Orders',$canOrder) ?>
      <?= cnav('coupons',$activeNav,'/client/coupons.php','bi-ticket-perforated','Coupons',$canOrder) ?>
      <?= cnav('tables',$activeNav,'/client/tables.php','bi-grid-3x3-gap','Tables',$canOrder) ?>
      <?= cnav('staff',$activeNav,'/client/staff.php','bi-people','Staff') ?>
      <?= cnav('feedback',$activeNav,'/client/feedback.php','bi-star','Feedback') ?>
      <?= cnav('customers',$activeNav,'/client/customers.php','bi-person-vcard','Customers',$canOrder) ?>
      <?= cnav('reports',$activeNav,'/client/reports.php','bi-graph-up','Reports',$canOrder) ?>
      <div class="nav-section">Account</div>
      <?= cnav('plans',$activeNav,'/client/plans.php','bi-gem','Plans & Billing') ?>
      <?= cnav('referrals',$activeNav,'/client/referrals.php','bi-gift','Refer & Earn') ?>
      <?= cnav('whatsapp',$activeNav,'/client/whatsapp.php','bi-whatsapp','WhatsApp') ?>
      <?= cnav('settings',$activeNav,'/client/settings.php','bi-gear','Settings') ?>
      <a class="nav-link" href="<?= e(BASE_URL) ?>/client/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>
  <div class="content">
    <?php if ($impersonating): ?>
      <div class="alert alert-warning rounded-0 mb-0 py-2 text-center small">
        You are impersonating this client. <a href="<?= e(BASE_URL) ?>/admin/impersonate.php?stop=1">Return to Super Admin</a>
      </div>
    <?php endif; ?>
    <?php
      // Show active broadcast notice.
      $notice = db_one('SELECT * FROM ' . tbl('notices') . ' WHERE status=1 ORDER BY id DESC LIMIT 1');
      if ($notice): ?>
      <div class="alert alert-info rounded-0 mb-0 py-2 small"><i class="bi bi-megaphone"></i> <strong><?= e($notice['title']) ?>:</strong> <?= e($notice['message']) ?></div>
    <?php endif; ?>
    <header class="topbar">
      <button class="sidebar-toggle d-lg-none"><i class="bi bi-list"></i></button>
      <h6 class="mb-0 fw-semibold"><?= e($pageTitle) ?></h6>
      <div class="d-flex align-items-center gap-3">
        <a href="<?= e(publicMenuUrl($tenant['slug'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> View Menu</a>
        <a href="<?= e(BASE_URL) ?>/client/plans.php" class="badge bg-secondary text-decoration-none" title="View plans & upgrade"><i class="bi bi-gem"></i> <?= e($plan['name'] ?? 'No plan') ?></a>
        <div class="dropdown">
          <a class="dropdown-toggle text-decoration-none text-dark" data-bs-toggle="dropdown" href="#"><i class="bi bi-person-circle fs-5"></i></a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/client/settings.php">Settings</a></li>
            <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/client/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="page-wrap">
