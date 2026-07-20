<?php
/**
 * Admin › Activity Log.
 * DataTable of activity_logs (newest first) with an optional date range filter.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// Date range filter (server-side).
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$where = [];
$params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'created_at >= :from'; $params[':from'] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'created_at <= :to';   $params[':to']   = $to . ' 23:59:59'; }
$sql = 'SELECT * FROM ' . tbl('activity_logs');
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY id DESC LIMIT 2000';
$rows = db_all($sql, $params);

$badge = [
    'super_admin' => 'bg-danger',
    'client'      => 'bg-primary',
    'staff'       => 'bg-info text-dark',
];

$pageTitle = 'Activity Log';
$activeNav = 'activity';
require __DIR__ . '/_header.php';
?>
<h5 class="mb-3">Activity Log</h5>

<form class="row g-2 align-items-end mb-3" method="get">
  <div class="col-auto">
    <label class="form-label small mb-0">From</label>
    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">To</label>
    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
    <a href="<?= e(BASE_URL) ?>/admin/activity.php" class="btn btn-sm btn-light">Reset</a>
  </div>
</form>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="actTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>#</th><th>User Type</th><th>User ID</th><th>Action</th><th>IP</th><th>When</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><span class="badge <?= $badge[$r['user_type']] ?? 'bg-secondary' ?>"><?= e($r['user_type'] ?? '—') ?></span></td>
          <td><?= $r['user_id'] !== null ? (int)$r['user_id'] : '—' ?></td>
          <td><?= e($r['action']) ?></td>
          <td class="small text-muted"><?= e($r['ip']) ?></td>
          <td class="small text-muted"><?= e(date('d-m-Y H:i', strtotime($r['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$pageScript = '<script>$(function(){ $("#actTable").DataTable({order:[[0,"desc"]],pageLength:50}); });</script>';
require __DIR__ . '/_footer.php';
?>
