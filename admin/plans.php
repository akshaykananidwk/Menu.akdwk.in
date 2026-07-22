<?php
/**
 * Super Admin — Subscription Plans.
 * DataTable listing + add/edit modal (POSTs to api/admin.php) with feature flags.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// Save Trial & Offer settings.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'trial_offer') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    setSetting('trial_plan_id', (int)($_POST['trial_plan_id'] ?? 0));
    setSetting('trial_days', max(1, (int)($_POST['trial_days'] ?? 7)));
    setSetting('launch_offer_enabled', !empty($_POST['launch_offer_enabled']) ? '1' : '0');
    setSetting('launch_offer_percent', max(0, min(100, (int)($_POST['launch_offer_percent'] ?? 50))));
    setSetting('launch_offer_hours', max(1, (int)($_POST['launch_offer_hours'] ?? 24)));
    logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Updated trial & offer settings');
    redirect(BASE_URL . '/admin/plans.php?saved=1');
}

$plans = db_all('SELECT * FROM ' . tbl('plans') . ' ORDER BY price ASC, id ASC');

// Feature flag keys (order = display order in the modal).
$featureKeys = [
    'direct_ordering' => 'Direct Ordering',
    'waiter_ordering' => 'Waiter Ordering',
    'kot_screen'      => 'KOT Screen',
    'payment_gateway' => 'Payment Gateway',
    'analytics'       => 'Analytics',
    'whatsapp'        => 'WhatsApp',
    'multi_language'  => 'Multi-Language',
    'remove_branding' => 'Remove Branding',
    'custom_domain'   => 'Custom Domain',
    'ai_photo'        => 'AI Photo',
];

$pageTitle = 'Plans';
$activeNav = 'plans';
require __DIR__ . '/_header.php';
$trialPlanId = (int)getSetting('trial_plan_id', 0);
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2 small">Saved.</div><?php endif; ?>

<!-- Trial & Launch Offer settings -->
<div class="card mb-3"><div class="card-body">
  <h6 class="fw-semibold mb-3"><i class="bi bi-gift text-primary"></i> Trial &amp; Launch Offer</h6>
  <form method="post" class="row g-3 align-items-end">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="form" value="trial_offer">
    <div class="col-md-4">
      <label class="form-label small">Trial plan (features given during trial)</label>
      <select name="trial_plan_id" class="form-select form-select-sm">
        <option value="0">Auto (best / unlimited)</option>
        <?php foreach ($plans as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= $trialPlanId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?><?= (float)$p['price'] > 0 ? ' (₹' . (int)$p['price'] . ')' : ' (free)' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Trial days</label>
      <input type="number" min="1" name="trial_days" class="form-control form-control-sm" value="<?= (int)getSetting('trial_days', 7) ?>">
    </div>
    <div class="col-md-6">
      <div class="border rounded p-2">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="loEnabled" name="launch_offer_enabled" value="1" <?= getSetting('launch_offer_enabled','1')==='1'?'checked':'' ?>>
          <label class="form-check-label small fw-semibold" for="loEnabled">Launch offer — discount if they upgrade quickly</label>
        </div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label small mb-0">Discount %</label><input type="number" min="0" max="100" name="launch_offer_percent" class="form-control form-control-sm" value="<?= (int)getSetting('launch_offer_percent',50) ?>"></div>
          <div class="col-6"><label class="form-label small mb-0">Within (hours of signup)</label><input type="number" min="1" name="launch_offer_hours" class="form-control form-control-sm" value="<?= (int)getSetting('launch_offer_hours',24) ?>"></div>
        </div>
      </div>
    </div>
    <div class="col-12"><button class="btn btn-primary btn-sm">Save Trial &amp; Offer</button>
      <span class="text-muted small ms-2">New signups get the trial plan for the trial days (all its features, free). The launch offer auto-applies a discount when a client upgrades within the window.</span></div>
  </form>
</div></div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h6 class="mb-0 fw-semibold"><i class="bi bi-box-seam text-primary"></i> Subscription Plans</h6>
  <button class="btn btn-primary btn-sm" id="btnAddPlan"><i class="bi bi-plus-lg"></i> Add Plan</button>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="plansTable" class="table table-hover align-middle w-100">
        <thead>
          <tr>
            <th>Name</th><th>Price</th><th>Validity</th><th>Items</th>
            <th>Categories</th><th>Tables</th><th>Waiters</th><th>AI</th>
            <th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($plans as $p):
            $features = json_decode($p['features_json'] ?? '{}', true) ?: [];
            $payload = [
                'id'             => $p['id'],
                'name'           => $p['name'],
                'price'          => $p['price'],
                'validity_days'  => $p['validity_days'],
                'max_items'      => $p['max_items'],
                'max_categories' => $p['max_categories'],
                'max_outlets'    => $p['max_outlets'],
                'max_waiters'    => $p['max_waiters'],
                'max_tables'     => $p['max_tables'],
                'ai_credits'     => $p['ai_credits'],
                'status'         => $p['status'],
                'features'       => $features,
            ];
        ?>
          <tr>
            <td class="fw-semibold"><?= e($p['name']) ?></td>
            <td class="small"><?= e(money($p['price'])) ?></td>
            <td class="small"><?= (int)$p['validity_days'] ?>d</td>
            <td class="small"><?= (int)$p['max_items'] ?></td>
            <td class="small"><?= (int)$p['max_categories'] ?></td>
            <td class="small"><?= (int)$p['max_tables'] ?></td>
            <td class="small"><?= (int)$p['max_waiters'] ?></td>
            <td class="small"><?= (int)$p['ai_credits'] ?></td>
            <td>
              <?php if ((int)$p['status'] === 1): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary btn-edit" data-plan='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-outline-secondary btn-toggle" data-id="<?= (int)$p['id'] ?>" title="Toggle status"><i class="bi bi-toggle-on"></i></button>
              <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add/Edit Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="planForm">
        <div class="modal-header">
          <h6 class="modal-title" id="planModalTitle">Add Plan</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="p_id" value="">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name *</label>
              <input name="name" id="p_name" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Price</label>
              <input name="price" id="p_price" type="number" step="0.01" min="0" class="form-control" value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Validity (days)</label>
              <input name="validity_days" id="p_validity_days" type="number" min="1" class="form-control" value="365">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Items</label>
              <input name="max_items" id="p_max_items" type="number" min="0" class="form-control" value="100">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Categories</label>
              <input name="max_categories" id="p_max_categories" type="number" min="0" class="form-control" value="20">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Outlets</label>
              <input name="max_outlets" id="p_max_outlets" type="number" min="0" class="form-control" value="1">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Waiters</label>
              <input name="max_waiters" id="p_max_waiters" type="number" min="0" class="form-control" value="3">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Tables</label>
              <input name="max_tables" id="p_max_tables" type="number" min="0" class="form-control" value="20">
            </div>
            <div class="col-md-3">
              <label class="form-label">AI Credits</label>
              <input name="ai_credits" id="p_ai_credits" type="number" min="0" class="form-control" value="5">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="status" id="p_status" value="1" checked>
                <label class="form-check-label" for="p_status">Active</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Features</label>
              <div class="row g-2">
                <?php foreach ($featureKeys as $fk => $label): ?>
                  <div class="col-md-4 col-6">
                    <div class="form-check">
                      <input class="form-check-input feature-chk" type="checkbox" name="<?= e($fk) ?>" id="p_<?= e($fk) ?>" value="1">
                      <label class="form-check-label small" for="p_<?= e($fk) ?>"><?= e($label) ?></label>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$apiUrl = BASE_URL . '/api/admin.php';
$pageScript = '<script>
(function(){
  var API = ' . json_encode($apiUrl) . ';
  var modal = new bootstrap.Modal(document.getElementById("planModal"));

  $("#plansTable").DataTable({ order: [], pageLength: 25, columnDefs: [{ orderable:false, targets: -1 }] });

  function resetForm(){
    document.getElementById("planForm").reset();
    document.getElementById("p_id").value = "";
    document.querySelectorAll(".feature-chk").forEach(function(c){ c.checked = false; });
  }

  // ---- Add ----
  document.getElementById("btnAddPlan").addEventListener("click", function(){
    resetForm();
    document.getElementById("planModalTitle").textContent = "Add Plan";
    document.getElementById("p_status").checked = true;
    modal.show();
  });

  // ---- Edit ----
  document.querySelectorAll(".btn-edit").forEach(function(el){
    el.addEventListener("click", function(){
      resetForm();
      var d = JSON.parse(this.getAttribute("data-plan"));
      document.getElementById("planModalTitle").textContent = "Edit Plan";
      document.getElementById("p_id").value = d.id || "";
      document.getElementById("p_name").value = d.name || "";
      document.getElementById("p_price").value = d.price || 0;
      document.getElementById("p_validity_days").value = d.validity_days || 365;
      document.getElementById("p_max_items").value = d.max_items || 0;
      document.getElementById("p_max_categories").value = d.max_categories || 0;
      document.getElementById("p_max_outlets").value = d.max_outlets || 0;
      document.getElementById("p_max_waiters").value = d.max_waiters || 0;
      document.getElementById("p_max_tables").value = d.max_tables || 0;
      document.getElementById("p_ai_credits").value = d.ai_credits || 0;
      document.getElementById("p_status").checked = (parseInt(d.status,10) === 1);
      var f = d.features || {};
      document.querySelectorAll(".feature-chk").forEach(function(c){ c.checked = !!f[c.name]; });
      modal.show();
    });
  });

  // ---- Save ----
  document.getElementById("planForm").addEventListener("submit", function(e){
    e.preventDefault();
    var data = {};
    // Send unchecked switches/checkboxes explicitly as 0.
    data.status = document.getElementById("p_status").checked ? 1 : 0;
    document.querySelectorAll(".feature-chk").forEach(function(c){ data[c.name] = c.checked ? 1 : 0; });
    new FormData(this).forEach(function(v,k){ if(!(k in data)) data[k]=v; });
    AK.post(API + "?action=plan_save", data).then(function(res){
      AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 700); });
    });
  });

  // ---- Toggle status ----
  document.querySelectorAll(".btn-toggle").forEach(function(el){
    el.addEventListener("click", function(){
      var id = this.getAttribute("data-id");
      AK.post(API + "?action=plan_toggle", { id: id }).then(function(res){
        AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 500); });
      });
    });
  });

  // ---- Delete ----
  document.querySelectorAll(".btn-delete").forEach(function(el){
    el.addEventListener("click", function(){
      var id = this.getAttribute("data-id");
      var name = this.getAttribute("data-name") || "this plan";
      AK.confirm("Delete plan \"" + name + "\"?", "Delete Plan").then(function(ok){
        if(!ok) return;
        AK.post(API + "?action=plan_delete", { id: id }).then(function(res){
          AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 500); });
        });
      });
    });
  });
})();
</script>';
require __DIR__ . '/_footer.php';
