<?php
/**
 * Client — Discounts / Coupons management. CRUD via api/coupon.php (tenant-isolated).
 */
$pageTitle = 'Coupons';
$activeNav = 'coupons';
require __DIR__ . '/_header.php';

$tid      = (int)currentTenantId();
$currency = $tenant['currency'] ?: '₹';
$rows = db_all('SELECT * FROM ' . tbl('coupons') . ' WHERE tenant_id = :t ORDER BY id DESC', [':t' => $tid]);
$today = date('Y-m-d');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div class="text-muted small">Create promo codes customers can apply at checkout.</div>
  <button class="btn btn-primary btn-sm" id="addBtn"><i class="bi bi-plus-lg"></i> Add Coupon</button>
</div>

<div class="table-responsive">
  <table class="table table-hover align-middle" id="couponsTable" style="width:100%">
    <thead><tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Usage</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r):
        $disc = $r['type'] === 'percent'
          ? rtrim(rtrim(number_format($r['value'], 2), '0'), '.') . '% off' . ((float)$r['max_discount'] > 0 ? ' (max ' . e($currency) . number_format($r['max_discount'], 0) . ')' : '')
          : e($currency) . number_format($r['value'], 0) . ' off';
        $expired = $r['expiry_date'] && $r['expiry_date'] < $today;
        $limitReached = (int)$r['usage_limit'] > 0 && (int)$r['used_count'] >= (int)$r['usage_limit'];
      ?>
        <tr>
          <td><code class="fw-bold"><?= e($r['code']) ?></code></td>
          <td><?= $disc ?></td>
          <td><?= (float)$r['min_order'] > 0 ? e($currency) . number_format($r['min_order'], 0) : '—' ?></td>
          <td><?= (int)$r['used_count'] ?><?= (int)$r['usage_limit'] > 0 ? ' / ' . (int)$r['usage_limit'] : ' / ∞' ?>
              <?= $limitReached ? '<span class="badge bg-warning text-dark ms-1">full</span>' : '' ?></td>
          <td><?= $r['expiry_date'] ? e($r['expiry_date']) . ($expired ? ' <span class="badge bg-danger">expired</span>' : '') : '—' ?></td>
          <td><span class="badge bg-<?= (int)$r['status'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$r['status'] === 1 ? 'Active' : 'Disabled' ?></span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary toggleBtn" data-id="<?= (int)$r['id'] ?>" title="Enable/Disable"><i class="bi bi-power"></i></button>
            <button class="btn btn-sm btn-outline-primary editBtn"
              data-json='<?= e(json_encode([
                'id' => (int)$r['id'], 'code' => $r['code'], 'type' => $r['type'],
                'value' => (float)$r['value'], 'min_order' => (float)$r['min_order'],
                'max_discount' => (float)$r['max_discount'], 'usage_limit' => (int)$r['usage_limit'],
                'expiry_date' => $r['expiry_date'], 'status' => (int)$r['status'],
              ], JSON_UNESCAPED_UNICODE)) ?>'><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger delBtn" data-id="<?= (int)$r['id'] ?>"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="couponModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="couponForm">
    <div class="modal-header py-2"><h6 class="modal-title" id="cmTitle">Add Coupon</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="fId">
      <div class="row g-2">
        <div class="col-12"><label class="form-label">Code <span class="text-danger">*</span></label>
          <input name="code" id="fCode" class="form-control text-uppercase" placeholder="WELCOME50" maxlength="40" required></div>
        <div class="col-6"><label class="form-label">Type <span class="text-danger">*</span></label>
          <select name="type" id="fType" class="form-select">
            <option value="flat">Flat (<?= e($currency) ?>)</option>
            <option value="percent">Percent (%)</option>
          </select></div>
        <div class="col-6"><label class="form-label">Value <span class="text-danger">*</span></label>
          <input name="value" id="fValue" type="number" step="0.01" min="0" class="form-control" required></div>
        <div class="col-6"><label class="form-label">Min order</label>
          <input name="min_order" id="fMin" type="number" step="0.01" min="0" class="form-control" value="0"></div>
        <div class="col-6"><label class="form-label">Max discount cap <small class="text-muted">(percent)</small></label>
          <input name="max_discount" id="fMax" type="number" step="0.01" min="0" class="form-control" value="0">
          <div class="form-text">0 = no cap</div></div>
        <div class="col-6"><label class="form-label">Usage limit</label>
          <input name="usage_limit" id="fLimit" type="number" min="0" class="form-control" value="0">
          <div class="form-text">0 = unlimited</div></div>
        <div class="col-6"><label class="form-label">Expiry date</label>
          <input name="expiry_date" id="fExpiry" type="date" class="form-control"></div>
        <div class="col-12"><div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" id="fStatus" name="status" value="1" checked>
          <label class="form-check-label" for="fStatus">Active</label></div></div>
      </div>
    </div>
    <div class="modal-footer py-2"><button class="btn btn-primary btn-sm">Save Coupon</button></div>
  </form>
</div></div></div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
new DataTable('#couponsTable', {order:[[0,'asc']], pageLength:25});
const modal = new bootstrap.Modal(document.getElementById('couponModal'));
document.getElementById('fCode').addEventListener('input', function(){ this.value = this.value.toUpperCase(); });

document.getElementById('addBtn').addEventListener('click', ()=>{
  const f = document.getElementById('couponForm'); f.reset();
  document.getElementById('fId').value=''; document.getElementById('fStatus').checked=true;
  document.getElementById('cmTitle').textContent='Add Coupon';
  modal.show();
});
document.querySelectorAll('.editBtn').forEach(b=>b.addEventListener('click', ()=>{
  const d = JSON.parse(b.dataset.json);
  const f = document.getElementById('couponForm'); f.reset();
  document.getElementById('fId').value=d.id;
  document.getElementById('fCode').value=d.code;
  document.getElementById('fType').value=d.type;
  document.getElementById('fValue').value=d.value;
  document.getElementById('fMin').value=d.min_order;
  document.getElementById('fMax').value=d.max_discount;
  document.getElementById('fLimit').value=d.usage_limit;
  document.getElementById('fExpiry').value=d.expiry_date||'';
  document.getElementById('fStatus').checked = d.status===1;
  document.getElementById('cmTitle').textContent='Edit Coupon';
  modal.show();
}));
document.getElementById('couponForm').addEventListener('submit', e=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  // Unchecked switch would be absent; normalise to 0/1.
  fd.set('status', document.getElementById('fStatus').checked ? 1 : 0);
  AK.post(B+'/api/coupon.php?action=coupon_save', fd).then(res=>AK.handle(res, ()=>location.reload()));
});
document.querySelectorAll('.toggleBtn').forEach(b=>b.addEventListener('click', ()=>{
  AK.post(B+'/api/coupon.php?action=coupon_toggle', {id:b.dataset.id}).then(res=>AK.handle(res, ()=>location.reload()));
}));
document.querySelectorAll('.delBtn').forEach(b=>b.addEventListener('click', ()=>{
  AK.confirm('Delete this coupon?').then(ok=>{ if(!ok) return;
    AK.post(B+'/api/coupon.php?action=coupon_delete', {id:b.dataset.id}).then(res=>AK.handle(res, ()=>location.reload()));
  });
}));
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
