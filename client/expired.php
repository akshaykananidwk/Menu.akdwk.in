<?php
/**
 * Plan expired / account suspended page. Standalone (does NOT use _header.php,
 * since ensureTenantActive() would otherwise redirect back here in a loop).
 * Reachable whenever a tenant session exists; else back to login.
 */
require_once dirname(__DIR__) . '/config/config.php';

// Lighter guard: need a tenant session, but do NOT call ensureTenantActive().
if (empty($_SESSION['tenant_id'])) { redirect(BASE_URL . '/client/login.php'); }
$tid = (int)$_SESSION['tenant_id'];
$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $tid]);
if (!$tenant) { session_unset(); redirect(BASE_URL . '/client/login.php'); }
$plan = tenantPlan($tid);

// Self-contained upgrade/renew request (creates a support ticket).
$sent = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    db_insert('tickets', [
        'tenant_id' => $tid,
        'subject'   => 'Plan Renewal / Reactivation Request',
        'message'   => 'Account is expired/suspended. Requesting renewal or reactivation.',
    ]);
    logActivity('tenant', $tid, 'Requested renewal (expired page)');
    $support = getWaSetting('support_number', getSetting('support_mobile', ''));
    if ($support) {
        sendWhatsApp($support, 'Renewal request from ' . $tenant['restaurant_name'] . ' (ID ' . $tid . ').', null, $tid, 'manual');
    }
    $sent = true;
}

$isSuspended = $tenant['status'] === 'suspended';
$siteName = getSetting('site_name', 'AK Menu System');
$waSupport = getWaSetting('support_number', getSetting('support_mobile', ''));
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Account Expired · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#e63946,#f1a208);min-height:100vh;display:flex;align-items:center;font-family:system-ui}
.exp-card{max-width:520px;margin:auto;border:0;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3)}</style>
</head><body>
<div class="container"><div class="card exp-card p-4 text-center">
  <i class="bi bi-<?= $isSuspended ? 'shield-lock' : 'calendar-x' ?> text-danger" style="font-size:3rem"></i>
  <h4 class="mt-2"><?= $isSuspended ? 'Account Suspended' : 'Your Plan Has Expired' ?></h4>
  <p class="text-muted">
    <?= $isSuspended
      ? 'Your account has been temporarily suspended. Please contact support to reactivate.'
      : 'Your subscription has ended. Renew now to keep your digital menu live.' ?>
  </p>

  <div class="bg-light rounded p-3 text-start mb-3">
    <div class="d-flex justify-content-between"><span>Restaurant</span><strong><?= e($tenant['restaurant_name']) ?></strong></div>
    <div class="d-flex justify-content-between"><span>Plan</span><strong><?= e($plan['name'] ?? '—') ?></strong></div>
    <div class="d-flex justify-content-between"><span>Expiry Date</span><strong><?= e($tenant['expiry_date'] ?: '—') ?></strong></div>
    <div class="d-flex justify-content-between"><span>Status</span><strong class="text-danger"><?= e(ucfirst($tenant['status'])) ?></strong></div>
  </div>

  <?php if ($sent): ?>
    <div class="alert alert-success">Your renewal request has been sent. Our team will contact you shortly.</div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <button class="btn btn-danger w-100"><i class="bi bi-arrow-up-circle"></i> Request Renewal / Upgrade</button>
    </form>
  <?php endif; ?>

  <?php if ($waSupport): ?>
    <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $waSupport)) ?>?text=<?= rawurlencode('Hi, I want to renew my plan for ' . $tenant['restaurant_name']) ?>" target="_blank" class="btn btn-outline-success w-100 mt-2"><i class="bi bi-whatsapp"></i> Chat with Support</a>
  <?php endif; ?>

  <a href="<?= e(BASE_URL) ?>/client/logout.php" class="btn btn-link mt-2 text-muted">Logout</a>
</div></div>
</body></html>
