<?php
/** Restaurant Settings: profile, GST, ordering, password, plan details. */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid = currentTenantId();

$pageTitle = 'Settings';
$activeNav = 'settings';
$plan = tenantPlan($tid);

require __DIR__ . '/_header.php';
?>
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProfile">Profile</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGst">GST &amp; Tax</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOrder">Ordering</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPass">Password</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPlan">Plan</button></li>
</ul>

<div class="tab-content">
  <!-- PROFILE -->
  <div class="tab-pane fade show active" id="tabProfile">
    <div class="card"><div class="card-body">
      <form class="settingsForm" data-section="profile" onsubmit="saveSettings(event)">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Restaurant Name *</label><input name="restaurant_name" class="form-control" value="<?= e($tenant['restaurant_name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Owner Name</label><input name="owner_name" class="form-control" value="<?= e($tenant['owner_name']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= e($tenant['mobile']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= e($tenant['email']) ?>"></div>
          <div class="col-md-8"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($tenant['address']) ?>"></div>
          <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($tenant['city']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Logo</label><input type="file" name="logo" class="form-control" accept="image/*">
            <?php if ($tenant['logo']): ?><img src="<?= e(BASE_URL.'/'.$tenant['logo']) ?>" class="mt-2 border rounded" style="height:48px"><?php endif; ?>
          </div>
        </div>
        <button class="btn btn-primary mt-3">Save Profile</button>
      </form>
    </div></div>
  </div>

  <!-- GST -->
  <div class="tab-pane fade" id="tabGst">
    <div class="card"><div class="card-body">
      <form class="settingsForm" data-section="gst" onsubmit="saveSettings(event)">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">GST Number</label><input name="gst_no" class="form-control" value="<?= e($tenant['gst_no']) ?>"></div>
          <div class="col-md-2"><label class="form-label">CGST %</label><input name="cgst" type="number" step="0.01" class="form-control" value="<?= e($tenant['cgst']) ?>"></div>
          <div class="col-md-2"><label class="form-label">SGST %</label><input name="sgst" type="number" step="0.01" class="form-control" value="<?= e($tenant['sgst']) ?>"></div>
          <div class="col-md-2"><label class="form-label">Service %</label><input name="service_charge" type="number" step="0.01" class="form-control" value="<?= e($tenant['service_charge']) ?>"></div>
        </div>
        <button class="btn btn-primary mt-3">Save Tax Settings</button>
      </form>
    </div></div>
  </div>

  <!-- ORDERING -->
  <div class="tab-pane fade" id="tabOrder">
    <div class="card"><div class="card-body">
      <form class="settingsForm" data-section="ordering" onsubmit="saveSettings(event)">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Ordering Mode</label>
            <select name="ordering_mode" class="form-select">
              <option value="view_only" <?= $tenant['ordering_mode']==='view_only'?'selected':'' ?>>View Only (menu only)</option>
              <option value="direct" <?= $tenant['ordering_mode']==='direct'?'selected':'' ?>>Direct Ordering (customer places order)</option>
              <option value="waiter" <?= $tenant['ordering_mode']==='waiter'?'selected':'' ?>>Waiter Ordering</option>
            </select></div>
          <div class="col-md-6"><label class="form-label">WhatsApp No. (order forwarding)</label><input name="whatsapp_no" class="form-control" value="<?= e($tenant['whatsapp_no']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Minimum Order Value</label><input name="min_order_value" type="number" step="0.01" class="form-control" value="<?= e($tenant['min_order_value']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Delivery Charge</label><input name="delivery_charge" type="number" step="0.01" class="form-control" value="<?= e($tenant['delivery_charge']) ?>"></div>
        </div>
        <button class="btn btn-primary mt-3">Save Ordering</button>
      </form>
      <hr class="my-4">
      <form class="settingsForm" data-section="seo" onsubmit="saveSettings(event)">
        <h6 class="fw-semibold"><i class="bi bi-search"></i> Search Engine Visibility</h6>
        <div class="form-check form-switch mt-2">
          <input type="hidden" name="allow_indexing" value="0">
          <input class="form-check-input" type="checkbox" role="switch" id="allowIndexing" name="allow_indexing" value="1" <?= (int)($tenant['allow_indexing'] ?? 1) === 1 ? 'checked' : '' ?>>
          <label class="form-check-label" for="allowIndexing">Allow Google &amp; search engines to list my menu</label>
        </div>
        <p class="text-muted small mt-1">When on, your menu can appear in Google search and is added to the sitemap. Turn off to keep it private (won't be indexed).</p>
        <button class="btn btn-primary mt-1">Save</button>
      </form>
    </div></div>
  </div>

  <!-- PASSWORD -->
  <div class="tab-pane fade" id="tabPass">
    <div class="card"><div class="card-body">
      <form class="settingsForm" data-section="change_password" onsubmit="saveSettings(event)">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Current Password</label><input name="current_password" type="password" class="form-control" required></div>
          <div class="col-md-6"></div>
          <div class="col-md-6"><label class="form-label">New Password</label><input name="new_password" type="password" class="form-control" minlength="6" required></div>
          <div class="col-md-6"><label class="form-label">Confirm New Password</label><input id="confirmPass" type="password" class="form-control" required></div>
        </div>
        <button class="btn btn-primary mt-3">Change Password</button>
      </form>
    </div></div>
  </div>

  <!-- PLAN -->
  <div class="tab-pane fade" id="tabPlan">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="mb-1"><span class="badge bg-primary"><?= e($plan['name'] ?? 'No plan') ?></span></h5>
          <div class="text-muted small">Expires: <strong><?= e($tenant['expiry_date'] ?: '—') ?></strong></div>
          <div class="text-muted small">Max Items: <?= (int)($plan['max_items'] ?? 0) ?> · Max Categories: <?= (int)($plan['max_categories'] ?? 0) ?> · AI Credits: <?= (int)($plan['ai_credits'] ?? 0) ?></div>
        </div>
        <button class="btn btn-warning" onclick="requestUpgrade()"><i class="bi bi-arrow-up-circle"></i> Request Upgrade</button>
      </div>
    </div></div>
  </div>
</div>

<?php
$pageScript = <<<'HTML'
<script>
const API = document.querySelector('meta[name="base-url"]').content + '/api/design.php';

function saveSettings(ev){
  ev.preventDefault();
  const form = ev.target;
  if(form.dataset.section === 'change_password'){
    if(form.new_password.value !== document.getElementById('confirmPass').value){
      AK.toast('error','Passwords do not match.'); return;
    }
  }
  const fd = new FormData(form);
  fd.set('section', form.dataset.section);
  AK.post(API + '?action=settings', fd).then(r => AK.handle(r, () => {
    if(form.dataset.section === 'change_password') form.reset();
  }));
}
function requestUpgrade(){
  AK.confirm('Send an upgrade/renewal request to support?','Request Upgrade').then(ok => { if(!ok) return;
    AK.post(API + '?action=upgrade_request', {}).then(r => AK.handle(r));
  });
}
</script>
HTML;
require __DIR__ . '/_footer.php';
