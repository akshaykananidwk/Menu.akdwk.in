<?php
/**
 * Client › WhatsApp settings (tenant-isolated).
 *
 *  - Set the restaurant's own WhatsApp number for order alerts (tenants.whatsapp_no)
 *    plus optional extra numbers (stored as a comma list in setting wa_extra_{tid}).
 *  - Enable/disable per notification type (setting wa_{tid}_{trigger}); toggled live
 *    via api/whatsapp.php?action=toggle_notify.
 *  - "Send my menu link to a customer" quick tool → api/whatsapp.php?action=send_menu_link.
 *  - This restaurant's own WhatsApp send log (whatsapp_logs WHERE tenant_id = me).
 *
 * Everything is scoped to currentTenantId(); no tenant_id is ever read from the request.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();

$tenant = currentTenant();
$tid    = (int)currentTenantId();

// ---- POST handler: save own numbers -----------------------------------------
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'numbers') {
    csrfCheck();
    $primary = trim((string)($_POST['whatsapp_no'] ?? ''));
    // Persist primary number on the tenant row (tenant-isolated update).
    db_update('tenants', ['whatsapp_no' => $primary ?: null], ['id' => $tid]);

    // Extra numbers: comma-separated, normalised + de-duplicated.
    $extraRaw = (string)($_POST['extra_numbers'] ?? '');
    $extra = [];
    foreach (preg_split('/[,\n]+/', $extraRaw) as $n) {
        $n = trim($n);
        if ($n !== '') { $extra[] = $n; }
    }
    setSetting('wa_extra_' . $tid, implode(',', array_unique($extra)));

    logActivity('tenant', $tid, 'Updated WhatsApp numbers');
    redirect(BASE_URL . '/client/whatsapp.php?saved=1');
}

$saved = isset($_GET['saved']);

// ---- Load current state ------------------------------------------------------
$primaryNo = $tenant['whatsapp_no'] ?? '';
$extraNos  = getSetting('wa_extra_' . $tid, '');

// Notification toggles this client can control. Default ON when unset.
$notifyTypes = [
    'new_order'       => 'New order received',
    'order_ready'     => 'Order ready',
    'order_completed' => 'Order completed / bill',
    'feedback'        => 'New customer feedback',
    'daily_summary'   => 'Daily sales summary',
];
$notifyState = [];
foreach ($notifyTypes as $key => $label) {
    $notifyState[$key] = getSetting('wa_' . $tid . '_' . $key, '1') === '1';
}

// This tenant's own log (isolated).
$logs = db_all(
    'SELECT * FROM ' . tbl('whatsapp_logs') . ' WHERE tenant_id = :t ORDER BY id DESC LIMIT 200',
    [':t' => $tid]
);

$apiUrl  = BASE_URL . '/api/whatsapp.php';
$menuUrl = publicMenuUrl($tenant['slug']);

$pageTitle = 'WhatsApp';
$activeNav = 'whatsapp';
require __DIR__ . '/_header.php';
?>
<?php if ($saved): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> WhatsApp settings saved.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-3">
  <!-- Numbers -->
  <div class="col-lg-6">
    <form method="post" class="card border-0 shadow-sm h-100">
      <?= csrfField() ?>
      <input type="hidden" name="form" value="numbers">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-whatsapp text-success"></i> Alert Numbers</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Primary WhatsApp Number</label>
          <input name="whatsapp_no" class="form-control" value="<?= e($primaryNo) ?>" placeholder="9876543210">
          <small class="text-muted">Order alerts are sent here.</small>
        </div>
        <div class="mb-1">
          <label class="form-label">Extra Numbers (optional)</label>
          <textarea name="extra_numbers" class="form-control" rows="3" placeholder="Comma-separated, e.g. 9876543211, 9876543212"><?= e($extraNos) ?></textarea>
          <small class="text-muted">Additional staff numbers to also receive alerts.</small>
        </div>
      </div>
      <div class="card-footer bg-white text-end">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Numbers</button>
      </div>
    </form>
  </div>

  <!-- Notification toggles -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-bell"></i> Notifications</div>
      <div class="card-body">
        <p class="text-muted small">Choose which WhatsApp alerts you want to receive.</p>
        <?php foreach ($notifyTypes as $key => $label): ?>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input notifyToggle" type="checkbox" role="switch"
                   id="n_<?= e($key) ?>" data-trigger="<?= e($key) ?>" <?= $notifyState[$key] ? 'checked' : '' ?>>
            <label class="form-check-label" for="n_<?= e($key) ?>"><?= e($label) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Send menu link tool -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-send"></i> Send Menu Link to a Customer</div>
      <div class="card-body">
        <p class="small text-muted mb-2">Sends your menu (<a href="<?= e($menuUrl) ?>" target="_blank"><?= e($menuUrl) ?></a>) to any number.</p>
        <div class="input-group">
          <input id="custNumber" class="form-control" placeholder="Customer number e.g. 9876543210">
          <button class="btn btn-success" id="btnSendMenu"><i class="bi bi-whatsapp"></i> Send</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Own log -->
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check"></i> My WhatsApp Log</div>
  <div class="card-body">
    <table class="table table-hover align-middle" id="myLogTable" style="width:100%">
      <thead><tr><th>Trigger</th><th>Number</th><th>Message</th><th>Status</th><th>Created</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><code><?= e($l['trigger_key']) ?></code></td>
          <td><?= e($l['number']) ?></td>
          <td class="text-truncate" style="max-width:280px"><?= e($l['message']) ?></td>
          <td>
            <?php $badge = ['sent'=>'success','pending'=>'warning text-dark','failed'=>'danger'][$l['status']] ?? 'secondary'; ?>
            <span class="badge bg-<?= $badge ?>"><?= e($l['status']) ?></span>
          </td>
          <td><?= e($l['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$pageScript = <<<HTML
<script>
const WA_API = '{$apiUrl}';

$(function () {
  $('#myLogTable').DataTable({ order: [], pageLength: 25 });

  // Notification toggles (persist live).
  $('.notifyToggle').on('change', function () {
    const trigger = this.getAttribute('data-trigger');
    const on = this.checked ? '1' : '0';
    AK.post(WA_API + '?action=toggle_notify', { trigger, on }).then(function (res) {
      if (res.status === 'success') AK.toast('success', 'Saved');
      else AK.toast('error', res.message || 'Failed');
    });
  });

  // Send menu link.
  $('#btnSendMenu').on('click', function () {
    const number = $('#custNumber').val().trim();
    if (!number) { AK.toast('error', 'Enter a number'); return; }
    const btn = this; btn.disabled = true;
    AK.post(WA_API + '?action=send_menu_link', { number }).then(function (res) {
      btn.disabled = false;
      AK.handle(res, function () { $('#custNumber').val(''); });
    }).catch(function () { btn.disabled = false; AK.toast('error', 'Request failed'); });
  });
});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
