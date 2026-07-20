<?php
/**
 * Super Admin — Subscription Plans.
 * DataTable listing + add/edit modal (POSTs to api/admin.php) with feature flags.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

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
?>
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
