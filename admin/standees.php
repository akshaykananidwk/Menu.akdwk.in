<?php
/**
 * Admin › Standee Templates manager.
 * Same self-contained pattern as templates.php. All writes via top POST handler.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$sizes = ['A4', 'A5', 'Table Tent 4x6', 'Sticker'];

// ---- POST handler -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $folder = trim($_POST['folder'] ?? '');
        $size   = trim($_POST['size'] ?? 'A4');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($name !== '' && $folder !== '') {
            $data = ['name' => $name, 'folder' => $folder, 'size' => $size, 'status' => $status];
            if (!empty($_FILES['preview_image']['name'])) {
                $p = uploadImage($_FILES['preview_image'], 'standees');
                if ($p) { $data['preview_image'] = $p; }
            }
            if ($id > 0) {
                db_update('standee_templates', $data, ['id' => $id]);
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Updated standee template #$id");
            } else {
                db_insert('standee_templates', $data);
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Added standee template $name");
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('DELETE FROM ' . tbl('standee_templates') . ' WHERE id = :id', [':id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Deleted standee template #$id");
        }
    }
    redirect(BASE_URL . '/admin/standees.php');
}

$rows = db_all('SELECT * FROM ' . tbl('standee_templates') . ' ORDER BY id DESC');

$pageTitle = 'Standee Templates';
$activeNav = 'standees';
require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Standee Templates</h5>
  <button class="btn btn-primary btn-sm" onclick="openStd()"><i class="bi bi-plus-lg"></i> Add Standee</button>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="stdTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>Preview</th><th>Name</th><th>Folder</th><th>Size</th><th>Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <?php if (!empty($r['preview_image'])): ?>
              <img src="<?= e(BASE_URL . '/' . $r['preview_image']) ?>" alt="" style="height:36px;border-radius:4px">
            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
          </td>
          <td><?= e($r['name']) ?></td>
          <td><code><?= e($r['folder']) ?></code></td>
          <td><?= e($r['size']) ?></td>
          <td><?= $r['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-primary" onclick='openStd(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="delStd(<?= (int)$r['id'] ?>)"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="stdModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="std_id">
      <div class="modal-header"><h6 class="modal-title" id="stdModalTitle">Add Standee</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name</label>
          <input name="name" id="std_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Folder</label>
          <input name="folder" id="std_folder" class="form-control" required placeholder="e.g. a4"></div>
        <div class="mb-3"><label class="form-label">Size</label>
          <select name="size" id="std_size" class="form-select">
            <?php foreach ($sizes as $s): ?><option value="<?= e($s) ?>"><?= e($s) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label">Preview Image</label>
          <input type="file" name="preview_image" accept="image/*" class="form-control"></div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="status" id="std_status" checked>
          <label class="form-check-label" for="std_status">Active</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden delete form -->
<form method="post" id="delForm" class="d-none">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="del_id">
</form>
<?php
$pageScript = <<<'HTML'
<script>
const stdModal = new bootstrap.Modal(document.getElementById('stdModal'));
function openStd(row) {
  document.getElementById('stdModalTitle').textContent = row ? 'Edit Standee' : 'Add Standee';
  document.getElementById('std_id').value = row ? row.id : '';
  document.getElementById('std_name').value = row ? row.name : '';
  document.getElementById('std_folder').value = row ? row.folder : '';
  document.getElementById('std_size').value = row ? row.size : 'A4';
  document.getElementById('std_status').checked = row ? row.status == 1 : true;
  stdModal.show();
}
function delStd(id) {
  AK.confirm('Delete this standee template?').then(ok => {
    if (ok) { document.getElementById('del_id').value = id; document.getElementById('delForm').submit(); }
  });
}
$(function(){ $('#stdTable').DataTable({order:[],pageLength:25}); });
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
