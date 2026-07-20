<?php
/**
 * Admin › WhatsApp control centre.
 *
 * Tabs: Settings | Templates | Logs | Inbox | Usage report.
 *  - Settings are stored in the whatsapp_settings table (setWaSetting on POST).
 *  - Gateway credentials are NEVER hardcoded — read via getWaSetting().
 *  - Templates/Logs/Inbox interact with api/whatsapp.php (AJAX).
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// ---- POST handler: save WhatsApp settings -----------------------------------
$savedSettings = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'settings') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }

    // 'enabled' is a checkbox — store 1/0 explicitly.
    setWaSetting('enabled', !empty($_POST['enabled']) ? '1' : '0');

    foreach (['base_url', 'api_key', 'session_id', 'webhook_url', 'send_delay', 'daily_limit'] as $k) {
        if (array_key_exists($k, $_POST)) {
            setWaSetting($k, trim((string)$_POST[$k]));
        }
    }
    logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Updated WhatsApp settings');
    redirect(BASE_URL . '/admin/whatsapp.php?saved=1#settings');
}

$savedSettings = isset($_GET['saved']);

// ---- Load data for the page -------------------------------------------------
$wa = function (string $k, $d = '') { return getWaSetting($k, $d); };

$templates = db_all('SELECT * FROM ' . tbl('whatsapp_templates') . ' ORDER BY id');

// Logs (most recent first, capped for the DataTable).
$logs = db_all(
    'SELECT l.*, t.restaurant_name
     FROM ' . tbl('whatsapp_logs') . ' l
     LEFT JOIN ' . tbl('tenants') . ' t ON t.id = l.tenant_id
     ORDER BY l.id DESC LIMIT 500'
);

// Inbox (unread first).
$inbox = db_all(
    'SELECT i.*, t.restaurant_name
     FROM ' . tbl('whatsapp_inbox') . ' i
     LEFT JOIN ' . tbl('tenants') . ' t ON t.id = i.tenant_id
     ORDER BY i.is_read ASC, i.id DESC LIMIT 300'
);
$unreadCount = (int)db_val('SELECT COUNT(*) FROM ' . tbl('whatsapp_inbox') . ' WHERE is_read = 0');

// Usage report: messages per tenant per month.
$usage = db_all(
    "SELECT DATE_FORMAT(l.created_at, '%Y-%m') AS ym,
            COALESCE(t.restaurant_name, '— System / Broadcast') AS tenant_name,
            COUNT(*) AS total,
            SUM(l.status = 'sent')    AS sent,
            SUM(l.status = 'failed')  AS failed,
            SUM(l.status = 'pending') AS pending
     FROM " . tbl('whatsapp_logs') . " l
     LEFT JOIN " . tbl('tenants') . " t ON t.id = l.tenant_id
     GROUP BY ym, l.tenant_id
     ORDER BY ym DESC, total DESC
     LIMIT 500"
);

// Available placeholders shown in the template editor helper.
$placeholders = ['{owner_name}','{restaurant_name}','{menu_url}','{mobile}','{password}','{plan_name}',
                 '{expiry_date}','{order_no}','{table_no}','{total}','{items}','{qr_url}','{invoice_url}','{otp}'];

$apiUrl = BASE_URL . '/api/whatsapp.php';

$pageTitle = 'WhatsApp';
$activeNav = 'whatsapp';
require __DIR__ . '/_header.php';
?>
<?php if ($savedSettings): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> WhatsApp settings saved.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-settings" type="button"><i class="bi bi-gear"></i> Settings</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-templates" type="button"><i class="bi bi-chat-square-text"></i> Templates</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button"><i class="bi bi-list-check"></i> Logs</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inbox" type="button"><i class="bi bi-inbox"></i> Inbox <?php if ($unreadCount): ?><span class="badge bg-danger"><?= $unreadCount ?></span><?php endif; ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-usage" type="button"><i class="bi bi-bar-chart"></i> Usage Report</button></li>
</ul>

<div class="tab-content">

  <!-- ===================== SETTINGS ===================== -->
  <div class="tab-pane fade show active" id="tab-settings">
    <div class="row g-3">
      <div class="col-lg-7">
        <form method="post" class="card border-0 shadow-sm">
          <?= csrfField() ?>
          <input type="hidden" name="form" value="settings">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-whatsapp text-success"></i> Gateway Configuration</div>
          <div class="card-body">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="waEnabled" name="enabled" value="1" <?= $wa('enabled','1')==='1'?'checked':'' ?>>
              <label class="form-check-label" for="waEnabled">WhatsApp sending enabled</label>
            </div>
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label">Gateway Base URL</label>
                <input name="base_url" class="form-control" value="<?= e($wa('base_url')) ?>" placeholder="https://bulk.akdwk.in/api.php">
              </div>
              <div class="col-md-6">
                <label class="form-label">API Key</label>
                <input name="api_key" class="form-control" value="<?= e($wa('api_key')) ?>" autocomplete="off">
              </div>
              <div class="col-md-6">
                <label class="form-label">Session ID</label>
                <input name="session_id" class="form-control" value="<?= e($wa('session_id')) ?>" autocomplete="off">
              </div>
              <div class="col-md-12">
                <label class="form-label">Inbound Webhook URL</label>
                <input name="webhook_url" class="form-control" value="<?= e($wa('webhook_url')) ?>">
                <small class="text-muted">Point the gateway's inbound webhook to <code><?= e(BASE_URL) ?>/api/whatsapp_webhook.php</code></small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Send Delay (seconds)</label>
                <input type="number" min="0" name="send_delay" class="form-control" value="<?= e($wa('send_delay','3')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Daily Limit</label>
                <input type="number" min="0" name="daily_limit" class="form-control" value="<?= e($wa('daily_limit','500')) ?>">
              </div>
            </div>
          </div>
          <div class="card-footer bg-white text-end">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
          </div>
        </form>
      </div>

      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-send"></i> Send Test Message</div>
          <div class="card-body">
            <div class="mb-2">
              <label class="form-label">Number</label>
              <input id="testNumber" class="form-control" placeholder="9876543210">
            </div>
            <div class="mb-2">
              <label class="form-label">Message</label>
              <textarea id="testMessage" class="form-control" rows="3">Test message from AK Menu System.</textarea>
            </div>
            <button class="btn btn-success" id="btnTest"><i class="bi bi-whatsapp"></i> Send Test</button>
            <div class="mt-3 d-none" id="testResultWrap">
              <label class="form-label mb-1">Raw API Response</label>
              <pre class="bg-dark text-light p-2 rounded small mb-0" id="testResult" style="max-height:220px;overflow:auto"></pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== TEMPLATES ===================== -->
  <div class="tab-pane fade" id="tab-templates">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <table class="table table-hover align-middle" id="tplTable" style="width:100%">
          <thead><tr><th>Trigger</th><th>Title</th><th>Media</th><th>Status</th><th class="text-end">Action</th></tr></thead>
          <tbody>
          <?php foreach ($templates as $t): ?>
            <tr>
              <td><code><?= e($t['trigger_key']) ?></code></td>
              <td><?= e($t['title']) ?></td>
              <td><span class="badge bg-secondary"><?= e($t['media_type'] ?: 'text') ?></span></td>
              <td>
                <?php if ((int)$t['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary btnEditTpl"
                        data-tpl='<?= e(json_encode($t, JSON_UNESCAPED_UNICODE)) ?>'>
                  <i class="bi bi-pencil"></i> Edit
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ===================== LOGS ===================== -->
  <div class="tab-pane fade" id="tab-logs">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex gap-2 mb-2 flex-wrap">
          <select id="logStatusFilter" class="form-select form-select-sm" style="width:auto">
            <option value="">All statuses</option>
            <option value="sent">Sent</option>
            <option value="pending">Pending</option>
            <option value="failed">Failed</option>
          </select>
        </div>
        <table class="table table-hover align-middle" id="logTable" style="width:100%">
          <thead><tr><th>ID</th><th>Tenant</th><th>Trigger</th><th>Number</th><th>Status</th><th>Retry</th><th>Created</th><th class="text-end">Action</th></tr></thead>
          <tbody>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= (int)$l['id'] ?></td>
              <td><?= e($l['restaurant_name'] ?? '—') ?></td>
              <td><code><?= e($l['trigger_key']) ?></code></td>
              <td><?= e($l['number']) ?></td>
              <td data-status="<?= e($l['status']) ?>">
                <?php
                  $badge = ['sent'=>'success','pending'=>'warning text-dark','failed'=>'danger'][$l['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $badge ?>"><?= e($l['status']) ?></span>
              </td>
              <td><?= (int)$l['retry_count'] ?></td>
              <td><?= e($l['created_at']) ?></td>
              <td class="text-end">
                <?php if ($l['status'] === 'failed'): ?>
                  <button class="btn btn-sm btn-outline-warning btnRetry" data-id="<?= (int)$l['id'] ?>"><i class="bi bi-arrow-clockwise"></i> Retry</button>
                <?php endif; ?>
                <?php if (!empty($l['response'])): ?>
                  <button class="btn btn-sm btn-outline-secondary btnLogResp" data-resp="<?= e($l['response']) ?>"><i class="bi bi-eye"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ===================== INBOX ===================== -->
  <div class="tab-pane fade" id="tab-inbox">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <table class="table table-hover align-middle" id="inboxTable" style="width:100%">
          <thead><tr><th>Number</th><th>Message</th><th>Tenant</th><th>Received</th><th>Status</th><th class="text-end">Action</th></tr></thead>
          <tbody>
          <?php foreach ($inbox as $m): ?>
            <tr class="<?= (int)$m['is_read']===0 ? 'table-warning' : '' ?>" data-row="<?= (int)$m['id'] ?>">
              <td><?= e($m['number']) ?></td>
              <td><?= e($m['message']) ?></td>
              <td><?= e($m['restaurant_name'] ?? '—') ?></td>
              <td><?= e($m['created_at']) ?></td>
              <td class="cellRead"><?= (int)$m['is_read']===0 ? '<span class="badge bg-danger">Unread</span>' : '<span class="badge bg-success">Read</span>' ?></td>
              <td class="text-end">
                <?php if ((int)$m['is_read']===0): ?>
                  <button class="btn btn-sm btn-outline-primary btnRead" data-id="<?= (int)$m['id'] ?>"><i class="bi bi-check2"></i> Mark read</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ===================== USAGE REPORT ===================== -->
  <div class="tab-pane fade" id="tab-usage">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <table class="table table-hover align-middle" id="usageTable" style="width:100%">
          <thead><tr><th>Month</th><th>Tenant</th><th>Total</th><th>Sent</th><th>Failed</th><th>Pending</th></tr></thead>
          <tbody>
          <?php foreach ($usage as $u): ?>
            <tr>
              <td><?= e($u['ym']) ?></td>
              <td><?= e($u['tenant_name']) ?></td>
              <td><strong><?= (int)$u['total'] ?></strong></td>
              <td class="text-success"><?= (int)$u['sent'] ?></td>
              <td class="text-danger"><?= (int)$u['failed'] ?></td>
              <td class="text-warning"><?= (int)$u['pending'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- ===================== TEMPLATE EDIT MODAL ===================== -->
<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Template</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tpl_trigger_key">
        <div class="mb-2">
          <label class="form-label">Trigger Key</label>
          <input class="form-control" id="tpl_trigger_display" disabled>
        </div>
        <div class="mb-2">
          <label class="form-label">Title</label>
          <input class="form-control" id="tpl_title">
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Message (English)</label>
            <textarea class="form-control" id="tpl_message_en" rows="6"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Message (Gujarati)</label>
            <textarea class="form-control" id="tpl_message_gu" rows="6"></textarea>
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-6">
            <label class="form-label">Media Type</label>
            <select class="form-select" id="tpl_media_type">
              <?php foreach (['text','image','document','video'] as $mt): ?>
                <option value="<?= $mt ?>"><?= ucfirst($mt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="tpl_is_active">
              <label class="form-check-label" for="tpl_is_active">Active</label>
            </div>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label mb-1">Available placeholders <small class="text-muted">(click to copy)</small></label>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($placeholders as $p): ?>
              <button type="button" class="btn btn-sm btn-outline-secondary phChip"><?= e($p) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveTpl"><i class="bi bi-save"></i> Save Template</button>
      </div>
    </div>
  </div>
</div>

<?php
$pageScript = <<<HTML
<script>
const WA_API = '{$apiUrl}';

$(function () {
  // DataTables
  const logDt = $('#logTable').DataTable({ order: [[0,'desc']], pageLength: 25 });
  $('#tplTable').DataTable({ pageLength: 25, order: [] });
  $('#inboxTable').DataTable({ pageLength: 25, order: [] });
  $('#usageTable').DataTable({ pageLength: 25, order: [] });

  // Log status filter (column index 4 holds the status badge).
  $('#logStatusFilter').on('change', function () {
    const v = this.value;
    logDt.column(4).search(v ? '\\\\b' + v + '\\\\b' : '', true, false).draw();
  });

  // ---- Send test message ----
  $('#btnTest').on('click', function () {
    const number = $('#testNumber').val().trim();
    const message = $('#testMessage').val();
    if (!number || !message) { AK.toast('error', 'Enter number and message'); return; }
    const btn = this; btn.disabled = true;
    AK.post(WA_API + '?action=test', { number, message }).then(function (res) {
      btn.disabled = false;
      if (res.status === 'success') {
        $('#testResultWrap').removeClass('d-none');
        const d = res.data || {};
        $('#testResult').text('status: ' + d.status + '\\nnumber: ' + d.number + '\\n\\n' + (d.response || '(empty)'));
        AK.toast(d.status === 'sent' ? 'success' : 'warning', 'Dispatched (' + d.status + ')');
      } else { AK.toast('error', res.message || 'Failed'); }
    }).catch(function () { btn.disabled = false; AK.toast('error', 'Request failed'); });
  });

  // ---- Template edit ----
  $('#tplTable').on('click', '.btnEditTpl', function () {
    const t = JSON.parse(this.getAttribute('data-tpl'));
    $('#tpl_trigger_key').val(t.trigger_key);
    $('#tpl_trigger_display').val(t.trigger_key);
    $('#tpl_title').val(t.title || '');
    $('#tpl_message_en').val(t.message_en || '');
    $('#tpl_message_gu').val(t.message_gu || '');
    $('#tpl_media_type').val(t.media_type || 'text');
    $('#tpl_is_active').prop('checked', String(t.is_active) === '1');
    new bootstrap.Modal('#tplModal').show();
  });

  // Placeholder chips: copy to clipboard.
  $('.phChip').on('click', function () {
    const txt = this.textContent;
    if (navigator.clipboard) navigator.clipboard.writeText(txt);
    AK.toast('info', 'Copied ' + txt);
  });

  $('#btnSaveTpl').on('click', function () {
    const payload = {
      trigger_key: $('#tpl_trigger_key').val(),
      title: $('#tpl_title').val(),
      message_en: $('#tpl_message_en').val(),
      message_gu: $('#tpl_message_gu').val(),
      media_type: $('#tpl_media_type').val(),
      is_active: $('#tpl_is_active').is(':checked') ? '1' : '0'
    };
    AK.post(WA_API + '?action=template_save', payload).then(function (res) {
      AK.handle(res, function () { setTimeout(() => location.reload(), 700); });
    });
  });

  // ---- Retry a failed log ----
  $('#logTable').on('click', '.btnRetry', function () {
    const id = this.getAttribute('data-id');
    const btn = this; btn.disabled = true;
    AK.post(WA_API + '?action=retry', { log_id: id }).then(function (res) {
      btn.disabled = false;
      if (res.status === 'success') {
        AK.toast(res.data && res.data.status === 'sent' ? 'success' : 'warning', res.message);
        setTimeout(() => location.reload(), 800);
      } else { AK.toast('error', res.message || 'Failed'); }
    }).catch(function () { btn.disabled = false; });
  });

  // View raw log response.
  $('#logTable').on('click', '.btnLogResp', function () {
    Swal.fire({ title: 'Gateway response', html: '<pre style="text-align:left;white-space:pre-wrap">' +
      $('<div>').text(this.getAttribute('data-resp')).html() + '</pre>' });
  });

  // ---- Mark inbox read ----
  $('#inboxTable').on('click', '.btnRead', function () {
    const id = this.getAttribute('data-id');
    const btn = this;
    AK.post(WA_API + '?action=inbox_read', { id }).then(function (res) {
      if (res.status === 'success') {
        const row = $('tr[data-row="' + id + '"]');
        row.removeClass('table-warning');
        row.find('.cellRead').html('<span class="badge bg-success">Read</span>');
        $(btn).remove();
        AK.toast('success', 'Marked read');
      } else { AK.toast('error', res.message); }
    });
  });
});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
