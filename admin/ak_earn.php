<?php
/**
 * Admin › AK Earn — connect this site to the AK Earn partner / commission master.
 * Saves the site key + secret server-side (settings table; the secret is never
 * shown again or sent to the browser), tests the connection (ak_ping) and
 * pushes the plan list (ak_sync_products).
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $key = trim((string)($_POST['site_key'] ?? ''));
        setSetting('ak_earn_site_key', preg_replace('/[^A-Za-z0-9_\-]/', '', $key));
        $secret = trim((string)($_POST['site_secret'] ?? ''));
        if ($secret !== '') { setSetting('ak_earn_site_secret', $secret); }   // blank = keep the saved one
        if (!empty($_POST['clear_secret'])) { setSetting('ak_earn_site_secret', ''); }
        logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Updated AK Earn connection');
        flash('success', 'AK Earn settings saved. Press "Test connection" to check them.');
        redirect(BASE_URL . '/admin/ak_earn.php');
    }
    if ($action === 'ping' || $action === 'sync') {
        if (!akEarnBoot()) {
            $result = ['status' => 'error', 'message' => 'Enter and save the Site Key and Site Secret first.'];
        } else {
            try {
                $result = $action === 'ping' ? ak_ping() : ak_sync_products(akEarnPlanProducts());
            } catch (Throwable $e) {
                $result = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }
        $result['_action'] = $action;
    }
}

$pageTitle = 'AK Earn';
$activeNav = 'ak_earn';
require __DIR__ . '/_header.php';

$localFile  = is_file(CONFIG_PATH . '/ak_earn.local.php');
$siteKey    = akEarnSiteKey();
$hasSecret  = $localFile || trim((string)getSetting('ak_earn_site_secret', '')) !== '';
$queueFile  = (akEarnBoot() && function_exists('ak_queue_file')) ? ak_queue_file() : '';
$queued     = ($queueFile && is_file($queueFile)) ? count(file($queueFile, FILE_SKIP_EMPTY_LINES) ?: []) : 0;
?>
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" class="card border-0 shadow-sm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3"></i> Connect to AK Earn (earn.akdwk.in)</div>
      <div class="card-body">
        <p class="text-muted small">AK Earn pays partners commission on restaurants they bring. This site reports sign-ups, plan orders, payments, renewals and refunds to it. Get the keys in <b>AK Earn admin → Sites → this site</b>.</p>
        <?php if ($localFile): ?>
          <div class="alert alert-info small">Credentials come from <code>config/ak_earn.local.php</code> on the server. The fields below are ignored while that file exists.</div>
        <?php endif; ?>
        <div class="mb-3">
          <label class="form-label">Site Key <span class="text-muted small">(public, starts with sk_)</span></label>
          <input name="site_key" class="form-control" value="<?= e($localFile ? '' : (string)getSetting('ak_earn_site_key', '')) ?>" placeholder="sk_xxxxxxxx" autocomplete="off" <?= $localFile ? 'disabled' : '' ?>>
        </div>
        <div class="mb-2">
          <label class="form-label">Site Secret <span class="text-muted small">(private — stays on this server)</span></label>
          <input type="password" name="site_secret" class="form-control" value="" autocomplete="new-password"
                 placeholder="<?= $hasSecret ? '•••••••• saved — leave blank to keep' : 'Paste the secret' ?>" <?= $localFile ? 'disabled' : '' ?>>
        </div>
        <?php if ($hasSecret && !$localFile): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="clear_secret" value="1" id="clrSec">
          <label class="form-check-label small" for="clrSec">Remove the saved secret (turns tracking off)</label>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <span class="small align-self-center">
          Status: <?= akEarnBoot() ? '<span class="badge bg-success">Configured</span>' : '<span class="badge bg-secondary">Not configured — nothing is sent</span>' ?>
          <?= $queued ? ' · <span class="badge bg-warning text-dark">' . (int)$queued . ' event(s) waiting to retry</span>' : '' ?>
        </span>
        <button class="btn btn-primary" <?= $localFile ? 'disabled' : '' ?>><i class="bi bi-save"></i> Save</button>
      </div>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-plug"></i> Test &amp; sync</div>
      <div class="card-body d-flex flex-wrap gap-2">
        <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="ping">
          <button class="btn btn-outline-primary"><i class="bi bi-wifi"></i> Test connection (ak_ping)</button></form>
        <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="sync">
          <button class="btn btn-outline-success"><i class="bi bi-arrow-repeat"></i> Sync plans now</button></form>
      </div>
      <?php if ($result): ?>
      <div class="card-footer bg-white">
        <div class="small fw-semibold mb-1"><?= $result['_action'] === 'ping' ? 'ak_ping()' : 'ak_sync_products()' ?> result:
          <?= ($result['status'] ?? '') === 'ok' ? '<span class="badge bg-success">ok</span>' : '<span class="badge bg-danger">' . e($result['status'] ?? 'error') . '</span>' ?></div>
        <?php unset($result['_action']); ?>
        <pre class="bg-dark text-light p-2 rounded small mb-0" style="max-height:220px;overflow:auto"><?= e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
      </div>
      <?php endif; ?>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check"></i> What is reported</div>
      <div class="card-body small">
        <ul class="mb-0 ps-3">
          <li><b>signup</b> — restaurant account created (self sign-up or by admin)</li>
          <li><b>order_created</b> — plan invoice raised, not paid yet</li>
          <li><b>payment_success</b> — first paid plan invoice (creates commission)</li>
          <li><b>renewal</b> — later paid plan invoices</li>
          <li><b>refund</b> / <b>cancel</b> — paid invoice reversed / unpaid one rejected or deleted</li>
          <li>Plans are synced as products <code>plan-&lt;id&gt;</code> whenever a plan is saved</li>
          <li>Page views on the public website (not on restaurant menus)</li>
        </ul>
        <div class="text-muted mt-2">Commission is calculated by AK Earn, never by this site. Order id = invoice number.</div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_footer.php';
