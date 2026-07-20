<?php
/**
 * Admin › Broadcast tool.
 *
 * Compose a message (EN/GU) with optional media, pick a target audience, and
 * enqueue ONE whatsapp_logs row (trigger_key='broadcast') per matching tenant's
 * owner mobile via sendWhatsApp(). Rows are queued as 'pending'; the cron worker
 * (cron/whatsapp_queue.php) sends them respecting the configured send delay and
 * daily limit. Live progress polls api/whatsapp.php?action=broadcast_status.
 *
 * Scheduling note: this implementation enqueues immediately as 'pending', which
 * the cron worker drains on its next run. A scheduled_at column is NOT present on
 * whatsapp_logs, so true future scheduling would need a schema change; for now a
 * "send now / queue" model is used and documented here.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// ---- POST handler: enqueue the broadcast -------------------------------------
$queued = null;
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'broadcast') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }

    $messageEn = trim((string)($_POST['message_en'] ?? ''));
    $messageGu = trim((string)($_POST['message_gu'] ?? ''));
    $mediaUrl  = trim((string)($_POST['media_url'] ?? '')) ?: null;
    $target    = $_POST['target'] ?? 'all';
    $planId    = (int)($_POST['plan_id'] ?? 0);

    if ($messageEn === '' && $messageGu === '') {
        $errorMsg = 'Please enter a message.';
    } else {
        // Build the tenant query per target filter. Only tenants with a mobile.
        $today = date('Y-m-d');
        $sql = 'SELECT id, owner_name, restaurant_name, mobile, language FROM ' . tbl('tenants') . ' WHERE mobile IS NOT NULL AND mobile <> ""';
        $params = [];
        switch ($target) {
            case 'active':
                $sql .= ' AND status = "active" AND (expiry_date IS NULL OR expiry_date >= :today)';
                $params[':today'] = $today;
                break;
            case 'expired':
                $sql .= ' AND (status = "expired" OR (expiry_date IS NOT NULL AND expiry_date < :today))';
                $params[':today'] = $today;
                break;
            case 'plan':
                $sql .= ' AND plan_id = :pid';
                $params[':pid'] = $planId;
                break;
            case 'all':
            default:
                // no extra filter
                break;
        }

        $recipients = db_all($sql, $params);
        $count = 0;
        foreach ($recipients as $r) {
            // Pick language-specific body; fall back to whichever is filled.
            $body = ($r['language'] === 'gu' && $messageGu !== '') ? $messageGu : ($messageEn !== '' ? $messageEn : $messageGu);
            // Queue (pending) — cron worker will send respecting delay + daily limit.
            sendWhatsApp($r['mobile'], $body, $mediaUrl, (int)$r['id'], 'broadcast');
            $count++;
        }
        logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Queued broadcast to $count recipients ($target)");
        $queued = $count;
    }
}

// Plans for the target dropdown.
$plans = db_all('SELECT id, name FROM ' . tbl('plans') . ' ORDER BY price');

// Quick audience counts for the UI.
$today = date('Y-m-d');
$countAll     = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE mobile IS NOT NULL AND mobile <> ""');
$countActive  = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE mobile IS NOT NULL AND mobile <> "" AND status="active" AND (expiry_date IS NULL OR expiry_date >= :t)', [':t'=>$today]);
$countExpired = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . ' WHERE mobile IS NOT NULL AND mobile <> "" AND (status="expired" OR (expiry_date IS NOT NULL AND expiry_date < :t))', [':t'=>$today]);

$apiUrl = BASE_URL . '/api/whatsapp.php';

$pageTitle = 'Broadcast';
$activeNav = 'broadcast';
require __DIR__ . '/_header.php';
?>
<?php if ($queued !== null): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-megaphone"></i> Broadcast queued to <strong><?= (int)$queued ?></strong> recipient(s).
    The cron worker will deliver them respecting the send delay and daily limit.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php elseif ($errorMsg): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?= e($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" class="card border-0 shadow-sm">
      <?= csrfField() ?>
      <input type="hidden" name="form" value="broadcast">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-megaphone"></i> Compose Broadcast</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Message (English)</label>
            <textarea name="message_en" class="form-control" rows="6" placeholder="Message to English-language tenants"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Message (Gujarati)</label>
            <textarea name="message_gu" class="form-control" rows="6" placeholder="ગુજરાતી ભાષાના ટેનન્ટ માટે સંદેશ"></textarea>
          </div>
          <div class="col-12">
            <small class="text-muted">Each recipient gets the message in their own panel language; if only one is filled, that one is used for everyone.</small>
          </div>
          <div class="col-md-12">
            <label class="form-label">Media URL (optional)</label>
            <input name="media_url" class="form-control" placeholder="https://…/image.jpg">
          </div>
          <div class="col-md-6">
            <label class="form-label">Target Audience</label>
            <select name="target" id="targetSel" class="form-select">
              <option value="all">All clients (<?= $countAll ?>)</option>
              <option value="active">Active only (<?= $countActive ?>)</option>
              <option value="expired">Expired only (<?= $countExpired ?>)</option>
              <option value="plan">By plan…</option>
            </select>
          </div>
          <div class="col-md-6 d-none" id="planWrap">
            <label class="form-label">Plan</label>
            <select name="plan_id" class="form-select">
              <?php foreach ($plans as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white text-end">
        <button class="btn btn-primary" onclick="return confirm('Queue this broadcast?')"><i class="bi bi-send"></i> Queue Broadcast</button>
      </div>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-activity"></i> Last Broadcast Progress</span>
        <button class="btn btn-sm btn-outline-secondary" id="btnRefresh"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-1"><span>Sent</span><strong id="cSent" class="text-success">0</strong></div>
        <div class="d-flex justify-content-between mb-1"><span>Pending</span><strong id="cPending" class="text-warning">0</strong></div>
        <div class="d-flex justify-content-between mb-2"><span>Failed</span><strong id="cFailed" class="text-danger">0</strong></div>
        <div class="progress" style="height:20px">
          <div class="progress-bar bg-success" id="barSent" style="width:0%">0%</div>
        </div>
        <small class="text-muted d-block mt-2">Auto-refreshes every 5s. Total: <span id="cTotal">0</span></small>
      </div>
    </div>
  </div>
</div>

<?php
$pageScript = <<<HTML
<script>
const WA_API = '{$apiUrl}';

$(function () {
  // Toggle plan dropdown.
  $('#targetSel').on('change', function () {
    $('#planWrap').toggleClass('d-none', this.value !== 'plan');
  });

  // Poll broadcast status.
  function refresh() {
    AK.get(WA_API + '?action=broadcast_status').then(function (res) {
      if (res.status !== 'success') return;
      const d = res.data || {};
      $('#cSent').text(d.sent || 0);
      $('#cPending').text(d.pending || 0);
      $('#cFailed').text(d.failed || 0);
      $('#cTotal').text(d.total || 0);
      const total = d.total || 0;
      const pct = total ? Math.round((d.sent / total) * 100) : 0;
      $('#barSent').css('width', pct + '%').text(pct + '%');
    });
  }
  refresh();
  $('#btnRefresh').on('click', refresh);
  setInterval(refresh, 5000);
});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
