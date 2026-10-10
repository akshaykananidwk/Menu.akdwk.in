<?php
/**
 * Admin › E-mail Log.
 *
 * Every message the platform tried to send, with the exact SMTP reply, so a
 * delivery problem can be diagnosed without server log access. Admin-only —
 * this log spans all tenants.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$status  = $_GET['status'] ?? '';
$trigger = trim((string)($_GET['trigger'] ?? ''));
$q       = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 40;

$where = []; $args = [];
if (in_array($status, ['pending', 'sent', 'failed'], true)) {
    $where[] = 'status = :st'; $args[':st'] = $status;
}
if ($trigger !== '') { $where[] = 'trigger_key = :tg'; $args[':tg'] = $trigger; }
if ($q !== '') {
    $where[] = '(to_email LIKE :q OR subject LIKE :q)';
    $args[':q'] = '%' . $q . '%';
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = 0; $rows = []; $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0]; $triggers = [];
$tableReady = true;
try {
    $total = (int)db_val('SELECT COUNT(*) FROM ' . tbl('email_logs') . $sqlWhere, $args);
    $rows  = db_all(
        'SELECT * FROM ' . tbl('email_logs') . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
        $args
    );
    foreach (db_all('SELECT status, COUNT(*) c FROM ' . tbl('email_logs') . ' GROUP BY status') as $r) {
        $counts[$r['status']] = (int)$r['c'];
    }
    $triggers = db_all('SELECT DISTINCT trigger_key FROM ' . tbl('email_logs') . ' WHERE trigger_key IS NOT NULL ORDER BY trigger_key');
} catch (Throwable $e) {
    // The 1.11.0 migration has not run yet (it applies on the next panel load).
    $tableReady = false;
}
$pages = max(1, (int)ceil($total / $per));

$badge = function (string $s): string {
    $map = ['sent' => 'success', 'pending' => 'warning', 'failed' => 'danger'];
    return '<span class="badge bg-' . ($map[$s] ?? 'secondary') . '">' . e(ucfirst($s)) . '</span>';
};

$pageTitle = 'E-mail Log';
$activeNav = 'emails';
require __DIR__ . '/_header.php';
?>

<?php if (!$tableReady): ?>
  <div class="alert alert-warning">
    <i class="bi bi-hourglass-split"></i> The e-mail tables are not created yet.
    Reload this page once — the 1.11.0 database update applies automatically.
  </div>
<?php else: ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="text-muted small">Transport</div>
      <div class="fw-semibold" style="word-break:break-word"><?= e(mailerTransportLabel()) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="text-muted small">Sent</div>
      <div class="fs-4 fw-bold text-success"><?= number_format($counts['sent']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="text-muted small">Queued</div>
      <div class="fs-4 fw-bold text-warning"><?= number_format($counts['pending']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
      <div class="text-muted small">Failed</div>
      <div class="fs-4 fw-bold text-danger"><?= number_format($counts['failed']) ?></div>
    </div></div>
  </div>
</div>

<?php if (!mailerEnabled()): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> E-mail sending is off or incomplete.
    <a href="<?= e(BASE_URL) ?>/admin/settings.php#tab-smtp" class="alert-link">Open Settings → SMTP</a>
    and set at least a <strong>From E-mail</strong>.
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <form class="row g-2 align-items-end mb-3" method="get">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['pending' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Type</label>
        <select name="trigger" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($triggers as $t): ?>
            <option value="<?= e((string)$t['trigger_key']) ?>" <?= $trigger === $t['trigger_key'] ? 'selected' : '' ?>>
              <?= e((string)$t['trigger_key']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label small mb-1">Search recipient or subject</label>
        <input name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="name@example.com">
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
        <a href="<?= e(BASE_URL) ?>/admin/emails.php" class="btn btn-sm btn-light">Reset</a>
      </div>
    </form>

    <?php if ($counts['pending'] > 0): ?>
      <div class="alert alert-light border d-flex flex-wrap gap-2 align-items-center py-2 small">
        <span><strong><?= number_format($counts['pending']) ?></strong> message(s) waiting. The master cron sends them every minute.</span>
        <button class="btn btn-sm btn-outline-primary ms-auto" onclick="flushMail(this)">
          <i class="bi bi-lightning-charge"></i> Send them all now
        </button>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>When</th><th>To</th><th>Subject</th>
            <th>Type</th><th>Status</th><th>Server reply</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">
            No e-mails logged yet<?= ($status || $trigger || $q) ? ' for this filter' : '' ?>.
          </td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-muted small"><?= (int)$r['id'] ?></td>
            <td class="small text-nowrap"><?= e(date('d M, H:i', strtotime((string)$r['created_at']))) ?></td>
            <td class="small" style="word-break:break-all"><?= e((string)$r['to_email']) ?></td>
            <td class="small"><?= e((string)$r['subject']) ?></td>
            <td class="small text-muted"><?= e((string)($r['trigger_key'] ?? '-')) ?></td>
            <td><?= $badge((string)$r['status']) ?>
              <?php if ((int)$r['retry_count'] > 0): ?>
                <span class="text-muted small">×<?= (int)$r['retry_count'] + 1 ?></span>
              <?php endif; ?>
            </td>
            <td class="small text-muted" style="max-width:320px;word-break:break-word">
              <?= e(mb_strimwidth((string)($r['response'] ?? ''), 0, 160, '…')) ?>
            </td>
            <td class="text-end">
              <?php if ($r['status'] !== 'sent'): ?>
                <button class="btn btn-sm btn-outline-secondary" onclick="retryMail(<?= (int)$r['id'] ?>, this)"
                        title="Try sending again"><i class="bi bi-arrow-repeat"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-3"><ul class="pagination pagination-sm mb-0 flex-wrap">
        <?php
        $qs = function (int $p) use ($status, $trigger, $q) {
            return BASE_URL . '/admin/emails.php?' . http_build_query([
                'status' => $status, 'trigger' => $trigger, 'q' => $q, 'page' => $p,
            ]);
        };
        $from = max(1, $page - 3); $to = min($pages, $page + 3);
        ?>
        <?php if ($page > 1): ?>
          <li class="page-item"><a class="page-link" href="<?= e($qs($page - 1)) ?>">‹</a></li>
        <?php endif; ?>
        <?php for ($p = $from; $p <= $to; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($qs($p)) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <li class="page-item"><a class="page-link" href="<?= e($qs($page + 1)) ?>">›</a></li>
        <?php endif; ?>
      </ul></nav>
    <?php endif; ?>
  </div>
</div>

<script>
const MAIL_API = '<?= e(BASE_URL) ?>/api/mail.php';

function retryMail(id, btn){
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
  AK.post(MAIL_API + '?action=retry', {id: id}).then(r => {
    const d = (r && r.data) || {};
    AK.toast(r && r.status === 'success' ? 'success' : 'error',
             (d.detail || (r && r.message) || 'Done').slice(0, 220));
    setTimeout(() => location.reload(), 1200);
  }).catch(() => {
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
    AK.toast('error', 'Request failed.');
  });
}

function flushMail(btn){
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending…';
  AK.post(MAIL_API + '?action=flush', {}).then(r => {
    AK.toast(r && r.status === 'success' ? 'success' : 'error', (r && r.message) || 'Done');
    setTimeout(() => location.reload(), 1200);
  }).catch(() => {
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-lightning-charge"></i> Send them all now';
    AK.toast('error', 'Request failed.');
  });
}
</script>

<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
