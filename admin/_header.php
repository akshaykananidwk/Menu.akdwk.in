<?php
/**
 * Super Admin panel layout header + sidebar.
 * Usage: set $pageTitle and $activeNav before including. Call admin_footer() after content.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$primary   = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$accent    = getSetting('accent_color', '#f1a208');
$siteName  = getSetting('site_name', 'AK Menu System');

// Update badge: is a newer version available?
$updateAvailable = getSetting('update_available', '0') === '1';

function nav_item(string $key, string $active, string $url, string $icon, string $label): string {
    $cls = $key === $active ? 'active' : '';
    return '<a class="nav-link ' . $cls . '" href="' . e(BASE_URL . $url) . '"><i class="bi ' . $icon . '"></i> ' . e($label) . '</a>';
}
?>
<!doctype html>
<html lang="en" data-theme="<?= e(getSetting('theme_mode','light')==='dark'?'dark':'light') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title><?= e($pageTitle) ?> · <?= e($siteName) ?> Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/assets/css/app.css" rel="stylesheet">
<style>:root{--primary:<?= e($primary) ?>;--secondary:<?= e($secondary) ?>;--accent:<?= e($accent) ?>;--sidebar-bg:<?= e($secondary) ?>;}</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><i class="bi bi-grid-1x2-fill"></i> <span><?= e($siteName) ?></span></div>
    <nav class="nav flex-column py-2">
      <div class="nav-section">Main</div>
      <?= nav_item('dashboard',$activeNav,'/admin/index.php','bi-speedometer2','Dashboard') ?>
      <?= nav_item('clients',$activeNav,'/admin/clients.php','bi-shop','Clients') ?>
      <?= nav_item('plans',$activeNav,'/admin/plans.php','bi-box-seam','Plans') ?>
      <?= nav_item('templates',$activeNav,'/admin/templates.php','bi-palette','Menu Templates') ?>
      <?= nav_item('standees',$activeNav,'/admin/standees.php','bi-qr-code','Standee Templates') ?>
      <div class="nav-section">Communication</div>
      <?= nav_item('whatsapp',$activeNav,'/admin/whatsapp.php','bi-whatsapp','WhatsApp') ?>
      <?= nav_item('broadcast',$activeNav,'/admin/broadcast.php','bi-megaphone','Broadcast') ?>
      <?= nav_item('notices',$activeNav,'/admin/notices.php','bi-pin-angle','Notice Board') ?>
      <?= nav_item('tickets',$activeNav,'/admin/tickets.php','bi-life-preserver','Support Tickets') ?>
      <div class="nav-section">Growth</div>
      <?= nav_item('analytics',$activeNav,'/admin/analytics.php','bi-graph-up','Website Analytics') ?>
      <?= nav_item('marketing',$activeNav,'/admin/marketing.php','bi-megaphone-fill','Marketing & Ads') ?>
      <div class="nav-section">Finance</div>
      <?php $prPending = pendingPlanRequests(); ?>
      <a class="nav-link <?= $activeNav === 'plan_requests' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/admin/plan_requests.php">
        <i class="bi bi-cart-check"></i> Plan Requests
        <?php if ($prPending > 0): ?><span class="badge bg-danger ms-1"><?= $prPending ?></span><?php endif; ?>
      </a>
      <?= nav_item('invoices',$activeNav,'/admin/invoices.php','bi-receipt','Invoices') ?>
      <?= nav_item('ai_report',$activeNav,'/admin/ai_report.php','bi-cpu','AI Usage') ?>
      <div class="nav-section">System</div>
      <?= nav_item('settings',$activeNav,'/admin/settings.php','bi-gear','Settings') ?>
      <?= nav_item('updates',$activeNav,'/admin/updates.php','bi-cloud-arrow-down','Updates') ?>
      <?= nav_item('activity',$activeNav,'/admin/activity.php','bi-clock-history','Activity Log') ?>
      <?= nav_item('backup',$activeNav,'/admin/backup.php','bi-database','Backup') ?>
      <a class="nav-link" href="<?= e(BASE_URL) ?>/admin/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>
  <div class="content">
    <header class="topbar">
      <button class="sidebar-toggle d-lg-none"><i class="bi bi-list"></i></button>
      <h6 class="mb-0 fw-semibold"><?= e($pageTitle) ?></h6>
      <div class="d-flex align-items-center gap-3">
        <?php if ($updateAvailable): ?>
          <a href="<?= e(BASE_URL) ?>/admin/updates.php" class="position-relative text-decoration-none" title="Update available">
            <i class="bi bi-cloud-arrow-down fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">!</span>
          </a>
        <?php endif; ?>
        <button class="btn btn-sm btn-light" onclick="AK.toggleTheme()"><i class="bi bi-moon-stars"></i></button>
        <div class="dropdown">
          <a class="dropdown-toggle text-decoration-none text-dark" data-bs-toggle="dropdown" href="#">
            <i class="bi bi-person-circle fs-5"></i> <?= e($adminName) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/admin/settings.php">Settings</a></li>
            <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/admin/logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="page-wrap">
