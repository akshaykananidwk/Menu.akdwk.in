<?php
/**
 * Client — Loyalty Points & Rewards.
 * Configure earn/redeem rules and see members. 1 point = 1 currency unit.
 */
$pageTitle = 'Loyalty';
$activeNav = 'loyalty';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
$currency = $tenant['currency'] ?: '₹';
$cfg = loyaltyConfig((int)$tenant['id']);
?>
<?php if (!$canOrder): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> Loyalty rewards work with online ordering — customers earn points on the orders they place. Enable ordering to use it.</div>
<?php else: ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-award"></i> Reward Rules</h6>
      <form id="loyaltyForm" onsubmit="saveLoyalty(event)">
        <div class="form-check form-switch mb-3">
          <input type="hidden" name="enabled" value="0">
          <input class="form-check-input" type="checkbox" role="switch" id="lyEnabled" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="lyEnabled">Enable loyalty points for my customers</label>
        </div>
        <div class="mb-3">
          <label class="form-label small">Points earned per order</label>
          <div class="input-group">
            <input type="number" step="0.5" min="0" max="100" name="earn_percent" class="form-control" value="<?= e($cfg['earn_percent']) ?>">
            <span class="input-group-text">% of order value</span>
          </div>
          <div class="form-text">e.g. 5 → a <?= e($currency) ?>500 order earns 25 points (1 point = <?= e($currency) ?>1).</div>
        </div>
        <div class="mb-3">
          <label class="form-label small">Minimum points before a customer can redeem</label>
          <input type="number" min="1" name="min_redeem" class="form-control" value="<?= e($cfg['min_redeem']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small">Maximum discount from points on one order</label>
          <div class="input-group">
            <input type="number" step="1" min="0" max="100" name="max_redeem_pct" class="form-control" value="<?= e($cfg['max_redeem_pct']) ?>">
            <span class="input-group-text">% of order</span>
          </div>
          <div class="form-text">Protects your margin — points can't cover more than this share of a bill.</div>
        </div>
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Save Rules</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-7">
    <div class="row g-3 mb-3">
      <div class="col-4"><div class="card"><div class="card-body py-3 text-center">
        <div class="text-muted small">Members</div><div class="h4 mb-0" id="lyMembers">—</div></div></div></div>
      <div class="col-4"><div class="card"><div class="card-body py-3 text-center">
        <div class="text-muted small">Points Given</div><div class="h4 mb-0" id="lyEarned">—</div></div></div></div>
      <div class="col-4"><div class="card"><div class="card-body py-3 text-center">
        <div class="text-muted small">Outstanding</div><div class="h4 mb-0 text-warning" id="lyOut">—</div></div></div></div>
    </div>
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-people"></i> Members</h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>#</th><th>Customer</th><th>Mobile</th><th class="text-end">Earned</th><th class="text-end">Redeemed</th><th class="text-end">Balance</th></tr></thead>
          <tbody id="lyBody"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody>
        </table>
      </div>
    </div></div>
  </div>
</div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
const esc = s => String(s==null?'':s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

function saveLoyalty(ev){
  ev.preventDefault();
  const fd = new FormData(ev.target);
  AK.post(B+'/api/loyalty.php?action=save', fd).then(r => AK.handle(r, loadMembers));
}

function loadMembers(){
  AK.get(B+'/api/loyalty.php?action=members').then(res=>{
    if(!res || res.status!=='success') return;
    const t = res.data.totals||{};
    document.getElementById('lyMembers').textContent = t.members||0;
    document.getElementById('lyEarned').textContent = t.total_earned||0;
    document.getElementById('lyOut').textContent = t.outstanding||0;
    const rows = res.data.members||[];
    document.getElementById('lyBody').innerHTML = rows.length ? rows.map((m,i)=>
      `<tr><td>\${i+1}</td><td class="fw-semibold">\${esc(m.customer_name||'Guest')}</td>`+
      `<td>\${esc(m.customer_mobile)}</td><td class="text-end">\${m.earned}</td>`+
      `<td class="text-end">\${m.redeemed}</td><td class="text-end"><span class="badge bg-success">\${m.balance}</span></td></tr>`).join('')
      : '<tr><td colspan="6" class="text-center text-muted py-4">No members yet — customers earn points when they order with their mobile number.</td></tr>';
  });
}
loadMembers();
</script>
HTML;
endif;
require __DIR__ . '/_footer.php';
?>
