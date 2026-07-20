<?php
/**
 * Super Admin — Clients (tenants) management.
 * DataTable listing + add/edit modal (POSTs to api/admin.php) + row actions.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$today = date('Y-m-d');

// Load all tenants with their plan name.
$clients = db_all(
    'SELECT t.*, p.name AS plan_name
     FROM ' . tbl('tenants') . ' t
     LEFT JOIN ' . tbl('plans') . ' p ON p.id = t.plan_id
     ORDER BY t.id DESC'
);

// Plans for the modal dropdown.
$plans = db_all('SELECT id, name, validity_days FROM ' . tbl('plans') . " WHERE status = 1 ORDER BY price ASC");

/** Render a Bootstrap badge for a tenant's effective status. */
function statusBadge(array $t, string $today): string {
    $status = $t['status'];
    if ($status === 'active' && !empty($t['expiry_date']) && $t['expiry_date'] < $today) {
        return '<span class="badge bg-danger">Expired</span>';
    }
    $map = [
        'active'    => '<span class="badge bg-success">Active</span>',
        'suspended' => '<span class="badge bg-secondary">Suspended</span>',
        'expired'   => '<span class="badge bg-danger">Expired</span>',
    ];
    return $map[$status] ?? e($status);
}

$pageTitle = 'Clients';
$activeNav = 'clients';
require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h6 class="mb-0 fw-semibold"><i class="bi bi-shop text-primary"></i> Restaurants / Clients</h6>
  <button class="btn btn-primary btn-sm" id="btnAddClient"><i class="bi bi-plus-lg"></i> Add Client</button>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table id="clientsTable" class="table table-hover align-middle w-100">
        <thead>
          <tr>
            <th>Restaurant</th><th>Owner</th><th>Mobile</th><th>City</th>
            <th>Plan</th><th>Expiry</th><th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c):
            $menuUrl = publicMenuUrl($c['slug']);
            $qrRel   = 'uploads/qr/' . $c['slug'] . '.png';
            $qrExists = file_exists(ROOT_PATH . '/' . $qrRel);
            // Data payload used to populate the edit modal.
            $payload = [
                'id'             => $c['id'],
                'restaurant_name'=> $c['restaurant_name'],
                'owner_name'     => $c['owner_name'],
                'mobile'         => $c['mobile'],
                'email'          => $c['email'],
                'address'        => $c['address'],
                'city'           => $c['city'],
                'slug'           => $c['slug'],
                'plan_id'        => $c['plan_id'],
                'start_date'     => $c['start_date'],
                'expiry_date'    => $c['expiry_date'],
                'ordering_mode'  => $c['ordering_mode'],
                'status'         => $c['status'],
            ];
        ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($c['restaurant_name']) ?></div>
              <div class="small">
                <a href="<?= e($menuUrl) ?>" target="_blank" class="text-decoration-none">
                  <i class="bi bi-box-arrow-up-right"></i> /r/<?= e($c['slug']) ?>
                </a>
              </div>
            </td>
            <td class="small"><?= e($c['owner_name'] ?? '-') ?></td>
            <td class="small"><?= e($c['mobile'] ?? '-') ?></td>
            <td class="small"><?= e($c['city'] ?? '-') ?></td>
            <td class="small"><?= e($c['plan_name'] ?? '-') ?></td>
            <td class="small text-nowrap"><?= $c['expiry_date'] ? e(date('d M Y', strtotime($c['expiry_date']))) : '-' ?></td>
            <td><?= statusBadge($c, $today) ?></td>
            <td class="text-end text-nowrap">
              <?php if ($qrExists): ?>
                <a href="<?= e(BASE_URL . '/' . $qrRel) ?>" target="_blank" title="QR Code">
                  <img src="<?= e(BASE_URL . '/' . $qrRel) ?>" alt="QR" width="34" height="34" class="border rounded">
                </a>
              <?php else: ?>
                <a href="<?= e($menuUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View menu"><i class="bi bi-qr-code"></i></a>
              <?php endif; ?>
              <div class="dropdown d-inline-block">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item btn-edit" href="#" data-client='<?= e(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'><i class="bi bi-pencil"></i> Edit</a></li>
                  <li><a class="dropdown-item" href="<?= e(BASE_URL . '/admin/impersonate.php?tenant_id=' . (int)$c['id']) ?>"><i class="bi bi-person-badge"></i> Impersonate</a></li>
                  <li><a class="dropdown-item btn-reset" href="#" data-id="<?= (int)$c['id'] ?>"><i class="bi bi-key"></i> Reset Password</a></li>
                  <li><a class="dropdown-item btn-toggle" href="#" data-id="<?= (int)$c['id'] ?>"><i class="bi bi-toggle-on"></i> <?= $c['status'] === 'active' ? 'Suspend' : 'Activate' ?></a></li>
                  <li><a class="dropdown-item" href="<?= e($menuUrl) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Menu</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item text-danger btn-delete" href="#" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['restaurant_name']) ?>"><i class="bi bi-trash"></i> Delete</a></li>
                </ul>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add/Edit Client Modal -->
<div class="modal fade" id="clientModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="clientForm">
        <div class="modal-header">
          <h6 class="modal-title" id="clientModalTitle">Add Client</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="f_id" value="">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Restaurant Name *</label>
              <input name="restaurant_name" id="f_restaurant_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Owner Name</label>
              <input name="owner_name" id="f_owner_name" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile</label>
              <input name="mobile" id="f_mobile" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input name="email" id="f_email" type="email" class="form-control">
            </div>
            <div class="col-md-8">
              <label class="form-label">Address</label>
              <input name="address" id="f_address" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">City</label>
              <input name="city" id="f_city" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Slug <small class="text-muted">(auto if blank)</small></label>
              <input name="slug" id="f_slug" class="form-control" placeholder="auto-generated">
            </div>
            <div class="col-md-6">
              <label class="form-label">Plan</label>
              <select name="plan_id" id="f_plan_id" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($plans as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Start Date</label>
              <input name="start_date" id="f_start_date" type="date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Expiry Date <small class="text-muted">(auto if blank)</small></label>
              <input name="expiry_date" id="f_expiry_date" type="date" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Ordering Mode</label>
              <select name="ordering_mode" id="f_ordering_mode" class="form-select">
                <option value="view_only">View Only</option>
                <option value="direct">Direct Ordering</option>
                <option value="waiter">Waiter Ordering</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" id="f_status" class="form-select">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="expired">Expired</option>
              </select>
            </div>
            <div class="col-md-6" id="passwordWrap">
              <label class="form-label">Password *</label>
              <input name="password" id="f_password" class="form-control" autocomplete="new-password">
              <small class="text-muted">Required on create (min 6 chars).</small>
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
  var modalEl = document.getElementById("clientModal");
  var modal = new bootstrap.Modal(modalEl);

  $("#clientsTable").DataTable({ order: [], pageLength: 25, columnDefs: [{ orderable:false, targets: -1 }] });

  function resetForm(){
    document.getElementById("clientForm").reset();
    document.getElementById("f_id").value = "";
  }

  // ---- Add ----
  document.getElementById("btnAddClient").addEventListener("click", function(){
    resetForm();
    document.getElementById("clientModalTitle").textContent = "Add Client";
    document.getElementById("passwordWrap").style.display = "";
    document.getElementById("f_password").required = true;
    modal.show();
  });

  // ---- Edit ----
  document.querySelectorAll(".btn-edit").forEach(function(el){
    el.addEventListener("click", function(e){
      e.preventDefault();
      resetForm();
      var d = JSON.parse(this.getAttribute("data-client"));
      document.getElementById("clientModalTitle").textContent = "Edit Client";
      document.getElementById("f_id").value = d.id || "";
      document.getElementById("f_restaurant_name").value = d.restaurant_name || "";
      document.getElementById("f_owner_name").value = d.owner_name || "";
      document.getElementById("f_mobile").value = d.mobile || "";
      document.getElementById("f_email").value = d.email || "";
      document.getElementById("f_address").value = d.address || "";
      document.getElementById("f_city").value = d.city || "";
      document.getElementById("f_slug").value = d.slug || "";
      document.getElementById("f_plan_id").value = d.plan_id || "";
      document.getElementById("f_start_date").value = d.start_date || "";
      document.getElementById("f_expiry_date").value = d.expiry_date || "";
      document.getElementById("f_ordering_mode").value = d.ordering_mode || "view_only";
      document.getElementById("f_status").value = d.status || "active";
      // Password not required when editing.
      document.getElementById("passwordWrap").style.display = "none";
      document.getElementById("f_password").required = false;
      modal.show();
    });
  });

  // ---- Save (create/update) ----
  document.getElementById("clientForm").addEventListener("submit", function(e){
    e.preventDefault();
    var data = {};
    new FormData(this).forEach(function(v,k){ data[k]=v; });
    AK.post(API + "?action=client_save", data).then(function(res){
      AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 700); });
    });
  });

  // ---- Reset password ----
  document.querySelectorAll(".btn-reset").forEach(function(el){
    el.addEventListener("click", function(e){
      e.preventDefault();
      var id = this.getAttribute("data-id");
      AK.confirm("Generate a new password for this client?", "Reset Password").then(function(ok){
        if(!ok) return;
        AK.post(API + "?action=client_reset_password", { id: id }).then(function(res){
          if(res && res.status === "success"){
            Swal.fire({ title:"New Password", html:"<code style=\'font-size:1.2rem\'>"+res.data.password+"</code>", icon:"success" });
          } else { AK.toast("error", (res&&res.message)||"Failed"); }
        });
      });
    });
  });

  // ---- Suspend / Activate ----
  document.querySelectorAll(".btn-toggle").forEach(function(el){
    el.addEventListener("click", function(e){
      e.preventDefault();
      var id = this.getAttribute("data-id");
      AK.post(API + "?action=client_toggle_status", { id: id }).then(function(res){
        AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 600); });
      });
    });
  });

  // ---- Delete ----
  document.querySelectorAll(".btn-delete").forEach(function(el){
    el.addEventListener("click", function(e){
      e.preventDefault();
      var id = this.getAttribute("data-id");
      var name = this.getAttribute("data-name") || "this client";
      AK.confirm("Delete \"" + name + "\"? This removes all its data permanently.", "Delete Client").then(function(ok){
        if(!ok) return;
        AK.post(API + "?action=client_delete", { id: id }).then(function(res){
          AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 600); });
        });
      });
    });
  });
})();
</script>';
require __DIR__ . '/_footer.php';
