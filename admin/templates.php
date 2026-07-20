<?php
/**
 * Admin › Menu Design Templates manager.
 * Self-contained: a POST handler at the top does all writes (CSRF-guarded),
 * then the page renders a DataTable + add/edit modal. No external API file.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$categories = ['Modern', 'Classic', 'Cafe', 'Fine-dine', 'Fast Food', 'Bakery'];

// ---- POST handler -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $folder   = trim($_POST['folder'] ?? '');
        $category = trim($_POST['category'] ?? 'Modern');
        $premium  = isset($_POST['is_premium']) ? 1 : 0;
        $status   = isset($_POST['status']) ? 1 : 0;

        if ($name !== '' && $folder !== '') {
            $data = [
                'name' => $name, 'folder' => $folder, 'category' => $category,
                'is_premium' => $premium, 'status' => $status,
            ];
            // Optional preview image upload.
            if (!empty($_FILES['preview_image']['name'])) {
                $p = uploadImage($_FILES['preview_image'], 'templates');
                if ($p) { $data['preview_image'] = $p; }
            }
            if ($id > 0) {
                db_update('templates', $data, ['id' => $id]);
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Updated menu template #$id");
            } else {
                db_insert('templates', $data);
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Added menu template $name");
            }
        }
    } elseif ($action === 'set_default') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('UPDATE ' . tbl('templates') . ' SET is_default = 0');
            db_update('templates', ['is_default' => 1, 'status' => 1], ['id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Set default menu template #$id");
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_query('DELETE FROM ' . tbl('templates') . ' WHERE id = :id AND is_default = 0', [':id' => $id]);
            logActivity('super_admin', $_SESSION['admin_id'] ?? null, "Deleted menu template #$id");
        }
    }
    redirect(BASE_URL . '/admin/templates.php');
}

// First tenant slug is used for live preview.
$previewSlug = db_val('SELECT slug FROM ' . tbl('tenants') . ' ORDER BY id LIMIT 1');
$rows = db_all('SELECT * FROM ' . tbl('templates') . ' ORDER BY id DESC');

$pageTitle = 'Menu Templates';
$activeNav = 'templates';
require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Menu Design Templates</h5>
  <button class="btn btn-primary btn-sm" onclick="openTpl()"><i class="bi bi-plus-lg"></i> Add Template</button>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="tplTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>Preview</th><th>Name</th><th>Folder</th><th>Category</th>
        <th>Premium</th><th>Default</th><th>Status</th><th class="text-end">Actions</th>
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
          <td><?= e($r['category']) ?></td>
          <td><?= $r['is_premium'] ? '<span class="badge bg-warning text-dark">Premium</span>' : '<span class="badge bg-light text-dark">Free</span>' ?></td>
          <td><?= $r['is_default'] ? '<span class="badge bg-success">Default</span>' : '' ?></td>
          <td><?= $r['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
          <td class="text-end text-nowrap">
            <?php if ($previewSlug): ?>
              <a class="btn btn-sm btn-outline-secondary" target="_blank"
                 href="<?= e(BASE_URL . '/r/?slug=' . urlencode($previewSlug)) ?>" title="Live preview"><i class="bi bi-eye"></i></a>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary" onclick='openTpl(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <?php if (!$r['is_default']): ?>
              <button class="btn btn-sm btn-outline-success" onclick="doAction('set_default', <?= (int)$r['id'] ?>)" title="Set as default"><i class="bi bi-star"></i></button>
              <button class="btn btn-sm btn-outline-danger" onclick="delTpl(<?= (int)$r['id'] ?>)"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="tpl_id">
      <div class="modal-header"><h6 class="modal-title" id="tplModalTitle">Add Template</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name</label>
          <input name="name" id="tpl_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Folder</label>
          <input name="folder" id="tpl_folder" class="form-control" required placeholder="e.g. modern">
          <div class="form-text">Must match a directory under <code>/templates</code>.</div></div>
        <div class="mb-3"><label class="form-label">Category</label>
          <select name="category" id="tpl_category" class="form-select">
            <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label">Preview Image</label>
          <input type="file" name="preview_image" accept="image/*" class="form-control"></div>
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" name="is_premium" id="tpl_premium">
          <label class="form-check-label" for="tpl_premium">Premium template</label></div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="status" id="tpl_status" checked>
          <label class="form-check-label" for="tpl_status">Active</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden action form for set_default / delete -->
<form method="post" id="actionForm" class="d-none">
  <?= csrfField() ?>
  <input type="hidden" name="action" id="af_action">
  <input type="hidden" name="id" id="af_id">
</form>
<?php
$pageScript = <<<'HTML'
<script>
const tplModal = new bootstrap.Modal(document.getElementById('tplModal'));
function openTpl(row) {
  document.getElementById('tplModalTitle').textContent = row ? 'Edit Template' : 'Add Template';
  document.getElementById('tpl_id').value = row ? row.id : '';
  document.getElementById('tpl_name').value = row ? row.name : '';
  document.getElementById('tpl_folder').value = row ? row.folder : '';
  document.getElementById('tpl_category').value = row ? row.category : 'Modern';
  document.getElementById('tpl_premium').checked = row ? row.is_premium == 1 : false;
  document.getElementById('tpl_status').checked = row ? row.status == 1 : true;
  tplModal.show();
}
function doAction(action, id) {
  document.getElementById('af_action').value = action;
  document.getElementById('af_id').value = id;
  document.getElementById('actionForm').submit();
}
function delTpl(id) {
  AK.confirm('Delete this template? This cannot be undone.').then(ok => { if (ok) doAction('delete', id); });
}
$(function(){ $('#tplTable').DataTable({order:[],pageLength:25}); });
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
