<?php
/**
 * Admin › Plan Coupons.
 * Coupon codes for SUBSCRIPTION purchases (not the restaurants' own diner
 * coupons). Percent or flat ₹ off the plan price incl. GST, optional plan
 * restriction, usage limit, once-per-client and expiry. Clients type the code
 * on Plans & Billing; the admin can also apply one in Invoices → Record Payment.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();
ak_run_pending_migrations(); // creates plan_coupons / invoice_details on first use

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'save') {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['code'] ?? '')));
            $type = ($_POST['type'] ?? 'percent') === 'flat' ? 'flat' : 'percent';
            $val  = max(0, (float)($_POST['value'] ?? 0));
            if ($type === 'percent') { $val = min($val, 100); }
            if ($code === '' || $val <= 0) {
                flash('error', 'Enter a code and a discount value.');
            } else {
                $data = [
                    'code'            => mb_substr($code, 0, 40),
                    'type'            => $type,
                    'value'           => $val,
                    'max_discount'    => max(0, (float)($_POST['max_discount'] ?? 0)),
                    'plan_id'         => (int)($_POST['plan_id'] ?? 0) ?: null,
                    'usage_limit'     => max(0, (int)($_POST['usage_limit'] ?? 0)),
                    'once_per_client' => !empty($_POST['once_per_client']) ? 1 : 0,
                    'expiry_date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['expiry_date'] ?? '')) ? $_POST['expiry_date'] : null,
                    'note'            => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255) ?: null,
                ];
                $clash = db_val('SELECT id FROM ' . tbl('plan_coupons') . ' WHERE code = :c AND id <> :i', [':c' => $data['code'], ':i' => $id]);
                if ($clash) {
                    flash('error', 'A coupon with code ' . $data['code'] . ' already exists.');
                } elseif ($id > 0) {
                    db_update('plan_coupons', $data, ['id' => $id]);
                    flash('success', 'Coupon ' . $data['code'] . ' updated.');
                } else {
                    db_insert('plan_coupons', $data + ['status' => 1]);
                    flash('success', 'Coupon ' . $data['code'] . ' created. Share it with your clients.');
                }
                logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Saved plan coupon ' . $data['code']);
            }
        } elseif ($action === 'toggle' && $id > 0) {
            db_query('UPDATE ' . tbl('plan_coupons') . ' SET status = 1 - status WHERE id = :i', [':i' => $id]);
        } elseif ($action === 'delete' && $id > 0) {
            db_query('DELETE FROM ' . tbl('plan_coupons') . ' WHERE id = :i', [':i' => $id]);
            flash('success', 'Coupon deleted.');
        }
    } catch (Throwable $e) {
        error_log('plan_coupons: ' . $e->getMessage());
        flash('error', 'Could not save. Open the admin dashboard once so the database update runs, then try again.');
    }
    redirect(BASE_URL . '/admin/plan_coupons.php');
}

$pageTitle = 'Plan Coupons';
$activeNav = 'plan_coupons';
require __DIR__ . '/_header.php';

try {
    $rows = db_all('SELECT c.*, p.name AS plan_name FROM ' . tbl('plan_coupons') . ' c
                    LEFT JOIN ' . tbl('plans') . ' p ON p.id = c.plan_id ORDER BY c.id DESC');
} catch (Throwable $e) { $rows = []; }
$plans = db_all('SELECT id, name FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY price');
$cur   = getSetting('currency', '₹');
$today = date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0">Plan Coupons</h5>
    <div class="text-muted small">Discount codes for subscription plans. Clients enter them on <b>Plans &amp; Billing</b> before paying.</div>
  </div>
  <button class="btn btn-primary btn-sm" onclick="editCoupon(null)"><i class="bi bi-plus-lg"></i> New Coupon</button>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="cpTable" class="table table-hover align-middle w-100">
      <thead><tr>
        <th>Code</th><th>Discount</th><th>Plan</th><th>Used</th><th>Expires</th><th>Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
        $expired = $r['expiry_date'] && $r['expiry_date'] < $today;
        $full    = (int)$r['usage_limit'] > 0 && (int)$r['used_count'] >= (int)$r['usage_limit'];
      ?>
        <tr>
          <td><code class="fs-6"><?= e($r['code']) ?></code><?= $r['note'] ? '<div class="small text-muted">' . e($r['note']) . '</div>' : '' ?></td>
          <td class="fw-semibold">
            <?= $r['type'] === 'percent'
              ? e(rtrim(rtrim(number_format((float)$r['value'], 2), '0'), '.')) . '% off' . ((float)$r['max_discount'] > 0 ? ' <span class="small text-muted">(max ' . e($cur . number_format((float)$r['max_discount'])) . ')</span>' : '')
              : e($cur . number_format((float)$r['value'], 2)) . ' off' ?>
          </td>
          <td><?= e($r['plan_name'] ?? 'Any plan') ?></td>
          <td><?= (int)$r['used_count'] ?><?= (int)$r['usage_limit'] > 0 ? ' / ' . (int)$r['usage_limit'] : ' <span class="text-muted small">/ ∞</span>' ?>
            <?= (int)$r['once_per_client'] ? '<div class="small text-muted">once per client</div>' : '' ?></td>
          <td class="small"><?= $r['expiry_date'] ? e(date('d M Y', strtotime($r['expiry_date']))) : '—' ?></td>
          <td>
            <?php if (!(int)$r['status']): ?><span class="badge bg-secondary">Off</span>
            <?php elseif ($expired): ?><span class="badge bg-warning text-dark">Expired</span>
            <?php elseif ($full): ?><span class="badge bg-warning text-dark">Used up</span>
            <?php else: ?><span class="badge bg-success">Active</span><?php endif; ?>
          </td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" onclick='editCoupon(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-<?= (int)$r['status'] ? 'warning' : 'success' ?>" onclick="cpAct('toggle', <?= (int)$r['id'] ?>)" title="<?= (int)$r['status'] ? 'Turn off' : 'Turn on' ?>"><i class="bi bi-power"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="cpDel(<?= (int)$r['id'] ?>)"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="cpModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="cp_id">
      <div class="modal-header"><h6 class="modal-title" id="cpTitle">New Coupon</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Coupon code</label>
          <input name="code" id="cp_code" class="form-control text-uppercase" maxlength="40" required placeholder="e.g. DIWALI50"></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Type</label>
            <select name="type" id="cp_type" class="form-select">
              <option value="percent">% off</option>
              <option value="flat">₹ off (fixed amount)</option>
            </select></div>
          <div class="col-6"><label class="form-label">Value</label>
            <input name="value" id="cp_value" type="number" step="0.01" min="0" class="form-control" required></div>
        </div>
        <div class="mb-3" id="cp_maxWrap"><label class="form-label">Maximum discount <?= e($cur) ?> <span class="text-muted small">(0 = no cap)</span></label>
          <input name="max_discount" id="cp_max" type="number" step="0.01" min="0" class="form-control" value="0"></div>
        <div class="mb-3"><label class="form-label">Valid for plan</label>
          <select name="plan_id" id="cp_plan" class="form-select">
            <option value="">Any plan</option>
            <?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><label class="form-label">Total uses <span class="text-muted small">(0 = unlimited)</span></label>
            <input name="usage_limit" id="cp_limit" type="number" min="0" class="form-control" value="0"></div>
          <div class="col-6"><label class="form-label">Expiry date <span class="text-muted small">(optional)</span></label>
            <input name="expiry_date" id="cp_exp" type="date" class="form-control"></div>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="once_per_client" value="1" id="cp_once" checked>
          <label class="form-check-label" for="cp_once">Each restaurant can use it only once</label>
        </div>
        <div class="mb-1"><label class="form-label">Note <span class="text-muted small">(only for you)</span></label>
          <input name="note" id="cp_note" class="form-control" maxlength="255"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Coupon</button>
      </div>
    </form>
  </div>
</div>

<form method="post" id="cpActForm" class="d-none">
  <?= csrfField() ?>
  <input type="hidden" name="action" id="cpa_action">
  <input type="hidden" name="id" id="cpa_id">
</form>
<?php
$pageScript = <<<'HTML'
<script>
const cpModal = new bootstrap.Modal(document.getElementById('cpModal'));
function maxToggle(){ document.getElementById('cp_maxWrap').style.display = document.getElementById('cp_type').value === 'percent' ? '' : 'none'; }
document.getElementById('cp_type').addEventListener('change', maxToggle);
function editCoupon(c){
  const g = id => document.getElementById(id);
  g('cpTitle').textContent = c ? 'Edit Coupon' : 'New Coupon';
  g('cp_id').value    = c ? c.id : '';
  g('cp_code').value  = c ? c.code : '';
  g('cp_type').value  = c ? c.type : 'percent';
  g('cp_value').value = c ? c.value : '';
  g('cp_max').value   = c ? c.max_discount : 0;
  g('cp_plan').value  = c && c.plan_id ? c.plan_id : '';
  g('cp_limit').value = c ? c.usage_limit : 0;
  g('cp_exp').value   = c && c.expiry_date ? c.expiry_date : '';
  g('cp_once').checked = c ? (+c.once_per_client === 1) : true;
  g('cp_note').value  = c && c.note ? c.note : '';
  maxToggle(); cpModal.show();
}
function cpAct(a, id){ document.getElementById('cpa_action').value = a; document.getElementById('cpa_id').value = id; document.getElementById('cpActForm').submit(); }
function cpDel(id){ AK.confirm('Delete this coupon?').then(ok => { if (ok) cpAct('delete', id); }); }
$(function(){ $('#cpTable').DataTable({order:[], pageLength:25}); });
</script>
HTML;
require __DIR__ . '/_footer.php';
