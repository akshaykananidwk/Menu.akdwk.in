<?php
/**
 * Client — Staff management (waiter / kitchen). PINs stored hashed via api/staff.php.
 * Shows login instructions + per-waiter order counts.
 */
$pageTitle = 'Staff';
$activeNav = 'staff';
require __DIR__ . '/_header.php';

$tid   = (int)currentTenantId();
$limit = checkPlanLimit($tid, 'waiters');
$propCode = propertyCode($tid);

// Staff list with lifetime order counts (tenant-isolated join).
$rows = db_all('SELECT s.*, (SELECT COUNT(*) FROM ' . tbl('orders') . ' o
                             WHERE o.staff_id = s.id AND o.tenant_id = :t2) AS order_count
                FROM ' . tbl('staff') . ' s WHERE s.tenant_id = :t ORDER BY s.id DESC',
    [':t' => $tid, ':t2' => $tid]);
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-muted small">Staff used: <strong><?= (int)$limit['used'] ?> / <?= (int)$limit['max'] ?></strong></span>
      <button class="btn btn-primary btn-sm" id="addBtn"><i class="bi bi-plus-lg"></i> Add Staff</button>
    </div>
    <?php if (!$limit['allowed']): ?>
      <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> <?= e($limit['message']) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle" id="staffTable" style="width:100%">
        <thead><tr><th>Name</th><th>Mobile</th><th>Role</th><th>Orders</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><strong><?= e($r['name']) ?></strong></td>
              <td><?= e($r['mobile'] ?: '-') ?></td>
              <td><span class="badge bg-<?= $r['role'] === 'kitchen' ? 'dark' : ($r['role'] === 'reception' ? 'primary' : 'info') ?>"><?= e(ucfirst($r['role'])) ?></span></td>
              <td><?= (int)$r['order_count'] ?></td>
              <td>
                <span class="badge bg-<?= (int)$r['status'] === 1 ? 'success' : 'secondary' ?>">
                  <?= (int)$r['status'] === 1 ? 'Active' : 'Disabled' ?></span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary toggleBtn" data-id="<?= (int)$r['id'] ?>" title="Enable/Disable"><i class="bi bi-power"></i></button>
                <button class="btn btn-sm btn-outline-primary editBtn"
                  data-id="<?= (int)$r['id'] ?>" data-name="<?= e($r['name']) ?>"
                  data-mobile="<?= e($r['mobile']) ?>" data-role="<?= e($r['role']) ?>"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger delBtn" data-id="<?= (int)$r['id'] ?>"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-primary"><div class="card-body text-center">
      <h6 class="card-title"><i class="bi bi-phone"></i> Staff App Login</h6>
      <p class="small text-muted mb-2">Your restaurant's <strong>Property Code</strong> — staff enter this + their PIN in the app:</p>
      <div class="display-5 fw-bold text-primary" style="letter-spacing:.35rem"><?= e($propCode) ?></div>
      <a href="<?= e(BASE_URL) ?>/app/" target="_blank" class="btn btn-primary btn-sm mt-3 w-100"><i class="bi bi-box-arrow-up-right"></i> Open Staff App</a>
      <p class="small text-muted mt-2 mb-0">Open this link on the phone/tablet, tap the role (Waiter / Kitchen / Reception), then <strong>Add to Home Screen</strong> to install it like an app.</p>
    </div></div>
    <div class="card mt-3"><div class="card-body">
      <h6 class="card-title"><i class="bi bi-info-circle"></i> Roles</h6>
      <ul class="small mb-0">
        <li><strong>Waiter</strong> — takes orders at tables.</li>
        <li><strong>Kitchen</strong> — the KOT display screen.</li>
        <li><strong>Reception</strong> — takes orders for any table, runs the floor (live orders + service calls + reservations). No payment access.</li>
      </ul>
    </div></div>
  </div>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="staffModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="staffForm">
    <div class="modal-header py-2"><h6 class="modal-title" id="smTitle">Add Staff</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="fId">
      <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input name="name" id="fName" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Mobile</label>
        <input name="mobile" id="fMobile" class="form-control" inputmode="numeric"></div>
      <div class="mb-3"><label class="form-label">Role <span class="text-danger">*</span></label>
        <select name="role" id="fRole" class="form-select" required>
          <option value="waiter">Waiter</option>
          <option value="kitchen">Kitchen</option>
          <option value="reception">Reception</option>
        </select></div>
      <div class="mb-3"><label class="form-label">4-digit PIN <span id="pinReq" class="text-danger">*</span></label>
        <input name="pin" id="fPin" class="form-control" inputmode="numeric" maxlength="4" pattern="\d{4}">
        <div class="form-text" id="pinHint">Leave blank to keep the existing PIN when editing.</div></div>
    </div>
    <div class="modal-footer py-2"><button class="btn btn-primary btn-sm">Save</button></div>
  </form>
</div></div></div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
new DataTable('#staffTable', {order:[[0,'asc']], pageLength:25});
const modal = new bootstrap.Modal(document.getElementById('staffModal'));

document.getElementById('addBtn').addEventListener('click', ()=>{
  document.getElementById('staffForm').reset();
  document.getElementById('fId').value='';
  document.getElementById('smTitle').textContent='Add Staff';
  document.getElementById('fPin').required = true;
  document.getElementById('pinHint').style.display='none';
  modal.show();
});
document.querySelectorAll('.editBtn').forEach(b=>b.addEventListener('click', ()=>{
  const f = document.getElementById('staffForm'); f.reset();
  document.getElementById('fId').value=b.dataset.id;
  document.getElementById('fName').value=b.dataset.name;
  document.getElementById('fMobile').value=b.dataset.mobile;
  document.getElementById('fRole').value=b.dataset.role;
  document.getElementById('smTitle').textContent='Edit Staff';
  document.getElementById('fPin').required=false;
  document.getElementById('pinHint').style.display='';
  modal.show();
}));
document.getElementById('staffForm').addEventListener('submit', e=>{
  e.preventDefault();
  AK.post(B+'/api/staff.php?action=staff_save', new FormData(e.target)).then(res=>{
    AK.handle(res, ()=>location.reload());
  });
});
document.querySelectorAll('.toggleBtn').forEach(b=>b.addEventListener('click', ()=>{
  AK.post(B+'/api/staff.php?action=staff_toggle', {id:b.dataset.id}).then(res=>{
    AK.handle(res, ()=>location.reload());
  });
}));
document.querySelectorAll('.delBtn').forEach(b=>b.addEventListener('click', ()=>{
  AK.confirm('Delete this staff member?').then(ok=>{
    if(!ok) return;
    AK.post(B+'/api/staff.php?action=staff_delete', {id:b.dataset.id}).then(res=>{
      AK.handle(res, ()=>location.reload());
    });
  });
}));
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
