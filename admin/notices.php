<?php
/**
 * Admin › Notice Board CRUD.
 * Active notices are shown in client panels (wired in client/_header.php).
 * Self-contained POST handler.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// ---- POST handler -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $status  = isset($_POST['status']) ? 1 : 0;

        if ($title !== '' && $message !== '') {
            $data = ['title' => $title, 'message' => $message, 'status' => $status];
            if ($id > 0) {
                db_update('notices', $data, ['id' => $id]);
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Updated notice #$id");
            } else {
                db_insert('notices', $data);
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Added notice: $title");
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('UPDATE ' . tbl('notices') . ' SET status = 1 - status WHERE id = :id', [':id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Toggled notice #$id");
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('DELETE FROM ' . tbl('notices') . ' WHERE id = :id', [':id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Deleted notice #$id");
        }
    }
    redirect(BASE_URL . '/admin/notices.php');
}

$rows = db_all('SELECT * FROM ' . tbl('notices') . ' ORDER BY id DESC');

$pageTitle = 'Notice Board';
$activeNav = 'notices';
require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Notice Board</h5>
  <button class="btn btn-primary btn-sm" onclick="openNotice()"><i class="bi bi-plus-lg"></i> Add Notice</button>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="noticeTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>Title</th><th>Message</th><th>Status</th><th>Created</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= e($r['title']) ?></td>
          <td class="text-muted small"><?= e(mb_strimwidth($r['message'], 0, 80, '…')) ?></td>
          <td><?= $r['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
          <td class="small text-muted"><?= e(date('d-m-Y', strtotime($r['created_at']))) ?></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" onclick="doAct('toggle', <?= (int)$r['id'] ?>)" title="Toggle status"><i class="bi bi-power"></i></button>
            <button class="btn btn-sm btn-outline-primary" onclick='openNotice(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="delNotice(<?= (int)$r['id'] ?>)"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="noticeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="n_id">
      <div class="modal-header"><h6 class="modal-title" id="noticeModalTitle">Add Notice</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title</label>
          <input name="title" id="n_title" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Message</label>
          <textarea name="message" id="n_message" class="form-control" rows="4" required></textarea></div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="status" id="n_status" checked>
          <label class="form-check-label" for="n_status">Active</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden action form -->
<form method="post" id="actForm" class="d-none">
  <?= csrfField() ?>
  <input type="hidden" name="action" id="act_action">
  <input type="hidden" name="id" id="act_id">
</form>
<?php
$pageScript = <<<'HTML'
<script>
const noticeModal = new bootstrap.Modal(document.getElementById('noticeModal'));
function openNotice(row) {
  document.getElementById('noticeModalTitle').textContent = row ? 'Edit Notice' : 'Add Notice';
  document.getElementById('n_id').value = row ? row.id : '';
  document.getElementById('n_title').value = row ? row.title : '';
  document.getElementById('n_message').value = row ? row.message : '';
  document.getElementById('n_status').checked = row ? row.status == 1 : true;
  noticeModal.show();
}
function doAct(action, id) {
  document.getElementById('act_action').value = action;
  document.getElementById('act_id').value = id;
  document.getElementById('actForm').submit();
}
function delNotice(id) {
  AK.confirm('Delete this notice?').then(ok => { if (ok) doAct('delete', id); });
}
$(function(){ $('#noticeTable').DataTable({order:[],pageLength:25}); });
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
