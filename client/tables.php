<?php
/**
 * Client — Table management. Add/edit/delete tables, QR download links, status badges.
 */
$pageTitle = 'Tables';
$activeNav = 'tables';
require __DIR__ . '/_header.php';

$tid   = (int)currentTenantId();
$limit = checkPlanLimit($tid, 'tables');
$rows  = db_all('SELECT * FROM ' . tbl('tables') . ' WHERE tenant_id = :t ORDER BY id DESC', [':t' => $tid]);

$badge = ['free' => 'success', 'occupied' => 'danger', 'billed' => 'warning'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <span class="text-muted small">Tables used: <strong><?= (int)$limit['used'] ?> / <?= (int)$limit['max'] ?></strong></span>
  </div>
  <button class="btn btn-primary btn-sm" id="addBtn"><i class="bi bi-plus-lg"></i> Add Table</button>
</div>

<?php if (!$limit['allowed']): ?>
  <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> <?= e($limit['message']) ?></div>
<?php endif; ?>

<div class="table-responsive">
  <table class="table table-hover align-middle" id="tablesTable" style="width:100%">
    <thead><tr><th>Table No</th><th>Section</th><th>Status</th><th>QR Token</th><th>QR</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['table_no']) ?></strong></td>
          <td><?= e($r['section'] ?: '-') ?></td>
          <td><span class="badge bg-<?= e($badge[$r['status']] ?? 'secondary') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
          <td><code class="small"><?= e($r['qr_token']) ?></code></td>
          <td><a class="btn btn-sm btn-outline-dark" target="_blank"
                 href="<?= e(BASE_URL) ?>/standee/generate.php?table=<?= e($r['qr_token']) ?>">
                 <i class="bi bi-qr-code"></i> QR</a></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary editBtn"
              data-id="<?= (int)$r['id'] ?>" data-no="<?= e($r['table_no']) ?>"
              data-section="<?= e($r['section']) ?>"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger delBtn" data-id="<?= (int)$r['id'] ?>"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="tableModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="tableForm">
    <div class="modal-header py-2"><h6 class="modal-title" id="tmTitle">Add Table</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="fId">
      <div class="mb-3"><label class="form-label">Table Number <span class="text-danger">*</span></label>
        <input name="table_no" id="fNo" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Section</label>
        <input name="section" id="fSection" class="form-control" list="sectionList" placeholder="AC / Non-AC / Rooftop / Garden">
        <datalist id="sectionList"><option>AC</option><option>Non-AC</option><option>Rooftop</option><option>Garden</option></datalist>
      </div>
    </div>
    <div class="modal-footer py-2"><button class="btn btn-primary btn-sm">Save</button></div>
  </form>
</div></div></div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
new DataTable('#tablesTable', {order:[[0,'asc']], pageLength:25});
const modalEl = document.getElementById('tableModal');
const modal = new bootstrap.Modal(modalEl);

document.getElementById('addBtn').addEventListener('click', ()=>{
  document.getElementById('tableForm').reset();
  document.getElementById('fId').value='';
  document.getElementById('tmTitle').textContent='Add Table';
  modal.show();
});
document.querySelectorAll('.editBtn').forEach(b=>b.addEventListener('click', ()=>{
  document.getElementById('fId').value=b.dataset.id;
  document.getElementById('fNo').value=b.dataset.no;
  document.getElementById('fSection').value=b.dataset.section;
  document.getElementById('tmTitle').textContent='Edit Table';
  modal.show();
}));
document.getElementById('tableForm').addEventListener('submit', e=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  AK.post(B+'/api/table.php?action=table_save', fd).then(res=>{
    AK.handle(res, ()=>location.reload());
  });
});
document.querySelectorAll('.delBtn').forEach(b=>b.addEventListener('click', ()=>{
  AK.confirm('Delete this table? This cannot be undone.').then(ok=>{
    if(!ok) return;
    AK.post(B+'/api/table.php?action=table_delete', {id:b.dataset.id}).then(res=>{
      AK.handle(res, ()=>location.reload());
    });
  });
}));
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
