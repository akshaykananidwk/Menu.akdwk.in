<?php
/**
 * Client — Plans & Billing.
 * Shows the current subscription, every available plan, and a payment flow
 * (online Razorpay when configured, else offline UPI/GPay + screenshot).
 * Purchases go through api/plan.php.
 */
$pageTitle = 'Plans & Billing';
$activeNav = 'plans';
require __DIR__ . '/_header.php';

$tid     = (int)currentTenantId();
$curr    = $tenant['currency'] ?: '₹';
$today   = date('Y-m-d');
$plan    = tenantPlan($tid);
$plans   = db_all('SELECT * FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY price ASC');

$taxRate = INVOICE_TAX_RATE;
$online  = razorpayEnabled();
$upi     = platformUpi();

// Any pending request blocks re-purchase until admin acts.
try {
    $pending = db_one('SELECT pr.*, p.name AS plan_name FROM ' . tbl('plan_requests') . ' pr
                       LEFT JOIN ' . tbl('plans') . ' p ON p.id = pr.plan_id
                       WHERE pr.tenant_id = :t AND pr.status = "pending" ORDER BY pr.id DESC LIMIT 1', [':t' => $tid]);
} catch (Throwable $e) {
    $pending = null; // table not migrated yet — treat as no pending request
}

// Billing history (best-effort — never break the page).
try {
    $invoices = db_all('SELECT i.*, p.name AS plan_name FROM ' . tbl('invoices') . ' i
                        LEFT JOIN ' . tbl('plans') . ' p ON p.id = i.plan_id
                        WHERE i.tenant_id = :t ORDER BY i.id DESC LIMIT 24', [':t' => $tid]);
} catch (Throwable $e) { $invoices = []; }

// Expiry maths for the status banner.
$expiry   = $tenant['expiry_date'] ?: null;
$daysLeft = $expiry ? (int)floor((strtotime($expiry) - strtotime($today)) / 86400) : null;
$expired  = $tenant['status'] === 'expired' || ($expiry && $expiry < $today);

/** Money formatter. */
$money = function (float $n) use ($curr) {
    return $curr . number_format($n, ($n == floor($n)) ? 0 : 2);
};
/** Gross total incl. tax. */
$gross = function (float $price) use ($taxRate) { return round($price + $price * $taxRate / 100, 2); };

// Launch offer (e.g. 50% off within 24h of signup) + a menu-size based suggestion.
$offer      = launchOffer($tid);
$suggestId  = suggestPlanForTenant($tid);
try { $myItems = (int)db_val('SELECT COUNT(*) FROM ' . tbl('items') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tid]); }
catch (Throwable $e) { $myItems = 0; }
/** Payable after launch discount. */
$payable = function (float $g) use ($offer) {
    return $offer['active'] ? round($g * (100 - $offer['percent']) / 100, 2) : $g;
};

/** Human feature/limit lines for a plan card. */
function planLines(array $p): array {
    $feat  = json_decode($p['features_json'] ?? '{}', true) ?: [];
    $lim   = fn($v) => ((int)$v >= 100000 || (int)$v <= 0) ? '∞' : (int)$v;
    $lines = [
        $lim($p['max_items']) . ' menu items',
        $lim($p['max_categories']) . ' categories',
        $lim($p['max_tables']) . ' tables',
        $lim($p['max_waiters']) . ' staff logins',
        $lim($p['ai_credits']) . ' AI menu scans',
    ];
    $flags = [
        'ordering'        => 'Online ordering',
        'whatsapp'        => 'WhatsApp notifications',
        'remove_branding' => 'Remove branding',
        'analytics'       => 'Advanced reports',
        'coupons'         => 'Discounts & coupons',
        'custom_domain'   => 'Custom domain',
    ];
    foreach ($flags as $k => $label) {
        if (!empty($feat[$k])) $lines[] = $label;
    }
    return $lines;
}

// Pre-generate a scan-to-pay UPI QR per plan when a VPA is configured.
$qrByPlan = [];
if ($upi['id'] !== '') {
    foreach ($plans as $pp) {
        $g = $gross((float)$pp['price']);
        if ($g <= 0) continue;
        $link = upiPayLink($g, 'Plan ' . $pp['name']);
        if ($link === '') continue;
        $path = generateQr($link, 'upi_' . $tid . '_' . (int)$pp['id']);
        if ($path) $qrByPlan[(int)$pp['id']] = mediaUrl($path);
    }
}
?>
<style>
.plan-status{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border-radius:16px}
.plan-card{border:1px solid #e6e8ef;border-radius:16px;transition:.15s;height:100%}
.plan-card:hover{box-shadow:0 12px 32px rgba(0,0,0,.08);transform:translateY(-2px)}
.plan-card.current{border:2px solid var(--primary);box-shadow:0 8px 28px rgba(0,0,0,.07)}
.plan-card.reco{border:2px solid var(--primary);box-shadow:0 12px 30px rgba(0,0,0,.10);position:relative}
.offer-band{background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;border-radius:14px;padding:1rem 1.2rem}
#offerCountdown{font-variant-numeric:tabular-nums}
.plan-price{font-size:1.9rem;font-weight:700}
.plan-feat{list-style:none;padding:0;margin:0;font-size:.9rem}
.plan-feat li{padding:.28rem 0;border-bottom:1px dashed #eef0f5;display:flex;gap:.5rem;align-items:center}
.plan-feat li:last-child{border-bottom:0}
.plan-feat i{color:#1aa260}
.pay-tab .nav-link{border-radius:10px}
</style>

<!-- Current subscription -->
<div class="plan-status p-4 mb-4">
  <div class="row align-items-center g-3">
    <div class="col-md-8">
      <div class="small text-white-50 text-uppercase" style="letter-spacing:.5px">Your current plan</div>
      <h3 class="fw-bold mb-1"><?= e($plan['name'] ?? 'No plan') ?></h3>
      <div class="opacity-75">
        <?php if ($expired): ?>
          <i class="bi bi-exclamation-triangle-fill"></i> Expired<?= $expiry ? ' on ' . e(date('d M Y', strtotime($expiry))) : '' ?> — renew below to go live again.
        <?php elseif ($daysLeft !== null): ?>
          <i class="bi bi-calendar-check"></i> Valid till <strong><?= e(date('d M Y', strtotime($expiry))) ?></strong>
          · <?= $daysLeft ?> day<?= $daysLeft == 1 ? '' : 's' ?> left
        <?php else: ?>
          <i class="bi bi-infinity"></i> No expiry set
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-4 text-md-end">
      <span class="badge bg-light text-dark fs-6 px-3 py-2">
        <?= $plan ? $money((float)$plan['price']) . ' / ' . (int)$plan['validity_days'] . ' days' : '—' ?>
      </span>
    </div>
  </div>
</div>

<?php if ($pending): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
  <i class="bi bi-hourglass-split fs-5"></i>
  <div>
    Your request for <strong><?= e($pending['plan_name']) ?></strong>
    (<?= $money((float)$pending['amount']) ?>, <?= e(ucfirst($pending['method'])) ?>) is <strong>pending verification</strong>.
    We'll activate it as soon as the payment is confirmed.
  </div>
</div>
<?php endif; ?>

<?php if ($offer['active']): ?>
<div class="offer-band mb-3" data-ends="<?= (int)(time() + $offer['seconds_left']) ?>">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="fs-3">🎉</div>
    <div class="flex-grow-1">
      <div class="fw-bold">Welcome offer — pay only <?= 100 - $offer['percent'] ?>%!</div>
      <div class="small">Upgrade now and get <strong><?= $offer['percent'] ?>% OFF</strong> any plan. Offer ends in <span id="offerCountdown" class="fw-bold">—</span>.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<h5 class="fw-semibold mb-3">Choose a plan</h5>
<?php if ($suggestId && $myItems > 0): ?>
  <?php $sg = null; foreach ($plans as $pp) { if ((int)$pp['id'] === $suggestId) { $sg = $pp; break; } } ?>
  <?php if ($sg && (!$plan || (int)$plan['id'] !== $suggestId)): ?>
  <div class="alert alert-primary d-flex align-items-center gap-2 py-2">
    <i class="bi bi-magic fs-5"></i>
    <div class="small">You have <strong><?= $myItems ?> menu items</strong>. Based on that, the <strong><?= e($sg['name']) ?></strong> plan is the best fit for you — it's marked <span class="badge bg-primary">Recommended</span> below.</div>
  </div>
  <?php endif; ?>
<?php endif; ?>
<div class="row g-3">
  <?php foreach ($plans as $p):
      $isCurrent = $plan && (int)$plan['id'] === (int)$p['id'] && !$expired;
      $isReco    = $suggestId === (int)$p['id'] && !$isCurrent;
      $g   = $gross((float)$p['price']);
      $pay = $payable($g);                        // after launch discount
      $hasOffer = $offer['active'] && (float)$p['price'] > 0;
  ?>
  <div class="col-md-6 col-xl-4">
    <div class="card plan-card <?= $isCurrent ? 'current' : '' ?> <?= $isReco ? 'reco' : '' ?>">
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start">
          <h5 class="fw-bold mb-0"><?= e($p['name']) ?></h5>
          <?php if ($isCurrent): ?><span class="badge bg-primary">Current</span>
          <?php elseif ($isReco): ?><span class="badge bg-primary">Recommended</span><?php endif; ?>
        </div>
        <div class="my-2">
          <?php if ($hasOffer): ?>
            <span class="plan-price" style="color:var(--primary)"><?= $money($payable((float)$p['price'])) ?></span>
            <span class="text-muted text-decoration-line-through small ms-1"><?= $money((float)$p['price']) ?></span>
            <span class="badge bg-danger ms-1"><?= $offer['percent'] ?>% OFF</span>
          <?php else: ?>
            <span class="plan-price" style="color:var(--primary)"><?= (float)$p['price'] > 0 ? $money((float)$p['price']) : 'Free' ?></span>
          <?php endif; ?>
          <?php if ((float)$p['price'] > 0): ?>
            <span class="text-muted small">/ <?= (int)$p['validity_days'] ?> days</span>
          <?php endif; ?>
        </div>
        <?php if ($taxRate > 0 && (float)$p['price'] > 0): ?>
          <div class="text-muted small mb-2">incl. <?= rtrim(rtrim(number_format($taxRate, 2), '0'), '.') ?>% GST · pay <strong><?= $money($pay) ?></strong><?= $hasOffer ? ' <span class="text-success">(offer applied)</span>' : '' ?></div>
        <?php endif; ?>
        <ul class="plan-feat mb-3">
          <?php foreach (planLines($p) as $line): ?>
            <li><i class="bi bi-check-circle-fill"></i> <?= e($line) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="mt-auto">
          <?php if ($isCurrent): ?>
            <button class="btn btn-outline-primary w-100 buyBtn"
                    data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>"
                    data-price="<?= $pay ?>" data-days="<?= (int)$p['validity_days'] ?>"
                    <?= $pending ? 'disabled' : '' ?>>
              <i class="bi bi-arrow-repeat"></i> Renew / Extend
            </button>
          <?php else: ?>
            <button class="btn btn-primary w-100 buyBtn"
                    data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>"
                    data-price="<?= $pay ?>" data-days="<?= (int)$p['validity_days'] ?>"
                    <?= $pending ? 'disabled' : '' ?>>
              <i class="bi bi-bag-check"></i> <?= (float)$p['price'] > 0 ? 'Choose plan' : 'Request' ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($invoices): ?>
<h5 class="fw-semibold mt-5 mb-3"><i class="bi bi-receipt"></i> Billing history</h5>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Invoice</th><th>Plan</th><th>Date</th><th>Amount</th><th>Status</th><th class="text-end">Invoice</th></tr>
      </thead>
      <tbody>
      <?php foreach ($invoices as $iv): ?>
        <tr>
          <td class="fw-semibold"><?= e($iv['invoice_no']) ?></td>
          <td><?= e($iv['plan_name'] ?? '—') ?></td>
          <td class="small text-muted"><?= e(date('d M Y', strtotime($iv['created_at']))) ?></td>
          <td class="fw-semibold"><?= $money((float)$iv['total']) ?></td>
          <td>
            <?= $iv['status'] === 'paid'
              ? '<span class="badge bg-success-subtle text-success">Paid</span>'
              : '<span class="badge bg-warning-subtle text-warning">Unpaid</span>' ?>
          </td>
          <td class="text-end">
            <a href="<?= e(BASE_URL) ?>/invoice.php?id=<?= (int)$iv['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-download"></i> Download
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-wallet2"></i> Pay for <span id="pmPlan"></span></h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center bg-light rounded p-3 mb-3">
          <div>
            <div class="fw-semibold" id="pmPlan2"></div>
            <div class="text-muted small" id="pmDays"></div>
          </div>
          <div class="fs-4 fw-bold text-primary" id="pmAmount"></div>
        </div>

        <ul class="nav nav-pills pay-tab gap-2 mb-3" role="tablist">
          <?php if ($online): ?>
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#payOnline" type="button"><i class="bi bi-credit-card"></i> Pay Online</button></li>
          <?php endif; ?>
          <li class="nav-item"><button class="nav-link <?= $online ? '' : 'active' ?>" data-bs-toggle="pill" data-bs-target="#payOffline" type="button"><i class="bi bi-phone"></i> UPI / GPay</button></li>
        </ul>

        <div class="tab-content">
          <?php if ($online): ?>
          <div class="tab-pane fade show active" id="payOnline">
            <p class="text-muted small">Pay securely by card, UPI or netbanking. Your plan activates instantly.</p>
            <button class="btn btn-success w-100" id="rzpBtn"><i class="bi bi-lock-fill"></i> Pay <span class="rzpAmt"></span> now</button>
          </div>
          <?php endif; ?>

          <div class="tab-pane fade <?= $online ? '' : 'show active' ?>" id="payOffline">
            <ol class="small text-muted ps-3 mb-3">
              <li>Send <strong class="offAmt text-dark"></strong> by GPay / PhonePe / Paytm to
                  <strong class="text-dark"><?= e($upi['number']) ?></strong><?= $upi['id'] !== '' ? ' (' . e($upi['id']) . ')' : '' ?>.</li>
              <li>Send the payment screenshot on WhatsApp to
                  <strong class="text-dark"><?= e($upi['whatsapp'] ?: $upi['number']) ?></strong>.</li>
              <li>Tap <em>“I've Paid”</em> below — we verify &amp; activate within a few hours.</li>
            </ol>

            <div class="qrWrap text-center mb-3" style="display:none">
              <img id="upiQr" src="" alt="UPI QR" style="max-width:190px;border:1px solid #eee;border-radius:12px;padding:6px">
              <div class="text-muted small mt-1">Scan to pay the exact amount</div>
            </div>

            <?php if ($upi['whatsapp'] ?: $upi['number']): ?>
            <a id="waLink" target="_blank" class="btn btn-outline-success w-100 mb-2"><i class="bi bi-whatsapp"></i> Open WhatsApp to send screenshot</a>
            <?php endif; ?>

            <form id="offForm" enctype="multipart/form-data">
              <input type="hidden" name="plan_id" id="offPlanId">
              <div class="mb-2">
                <label class="form-label small">Transaction / UTR ref (optional)</label>
                <input name="txn_ref" class="form-control form-control-sm" placeholder="e.g. 4213xxxxxx">
              </div>
              <div class="mb-3">
                <label class="form-label small">Payment screenshot (optional)</label>
                <input type="file" name="screenshot" accept="image/*" class="form-control form-control-sm">
              </div>
              <button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2-circle"></i> I've Paid — Notify Admin</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$base = BASE_URL;
$onlineJs = $online ? '1' : '0';
$waBase   = formatWaNumber((string)($upi['whatsapp'] ?: $upi['number'])) ?? '';
$jflags   = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$rname    = json_encode($tenant['restaurant_name'], $jflags);
$qrJson   = json_encode($qrByPlan, $jflags) ?: '{}';
$rzpSrc   = $online ? '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>' : '';
$pageScript = <<<HTML
$rzpSrc
<script>
const B = '$base', ONLINE = $onlineJs === 1, WA = '$waBase', RNAME = $rname, QR = $qrJson;
let cur = {};
const payModal = new bootstrap.Modal(document.getElementById('payModal'));

// Launch-offer countdown (auto-hides when it expires).
(function(){
  const band = document.querySelector('.offer-band'); if(!band) return;
  const ends = parseInt(band.dataset.ends,10)*1000;
  const el = document.getElementById('offerCountdown');
  function tick(){
    let s = Math.floor((ends - Date.now())/1000);
    if(s<=0){ band.style.display='none'; return; }
    const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), sec=s%60;
    if(el) el.textContent = (h<10?'0':'')+h+':'+(m<10?'0':'')+m+':'+(sec<10?'0':'')+sec;
    setTimeout(tick,1000);
  }
  tick();
})();

function money(n){ return '{$curr}' + (Number(n)%1===0 ? Number(n).toFixed(0) : Number(n).toFixed(2)); }

document.querySelectorAll('.buyBtn').forEach(function(b){
  b.addEventListener('click', function(){
    cur = { id:b.dataset.id, name:b.dataset.name, price:parseFloat(b.dataset.price), days:b.dataset.days };
    document.getElementById('pmPlan').textContent = cur.name;
    document.getElementById('pmPlan2').textContent = cur.name + ' plan';
    document.getElementById('pmDays').textContent = cur.days + ' days validity';
    document.getElementById('pmAmount').textContent = money(cur.price);
    document.getElementById('offPlanId').value = cur.id;
    document.querySelectorAll('.offAmt,.rzpAmt').forEach(function(el){ el.textContent = money(cur.price); });

    // UPI QR (if available for this plan)
    const qw = document.querySelector('.qrWrap');
    if (QR[cur.id]) { document.getElementById('upiQr').src = QR[cur.id]; qw.style.display = ''; }
    else { qw.style.display = 'none'; }

    // WhatsApp deep-link
    const wl = document.getElementById('waLink');
    if (wl && WA) {
      const txt = encodeURIComponent('Hi, I paid ' + money(cur.price) + ' for the ' + cur.name + ' plan (' + RNAME + '). Screenshot attached.');
      wl.href = 'https://wa.me/' + WA + '?text=' + txt;
    }
    payModal.show();
  });
});

// ---- Offline "I've Paid" ----
document.getElementById('offForm').addEventListener('submit', function(e){
  e.preventDefault();
  const btn = e.submitter; btn.disabled = true;
  const fd = new FormData(e.target);
  AK.post(B + '/api/plan.php?action=request_offline', fd).then(function(res){
    AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 1200); });
    if (!res || res.status !== 'success') btn.disabled = false;
  }).catch(function(){ btn.disabled = false; });
});

// ---- Online (Razorpay) ----
const rzpBtn = document.getElementById('rzpBtn');
if (rzpBtn) {
  rzpBtn.addEventListener('click', function(){
    rzpBtn.disabled = true;
    AK.post(B + '/api/plan.php?action=razorpay_order', { plan_id: cur.id }).then(function(res){
      if (!res || res.status !== 'success') { AK.toast('error', (res && res.message) || 'Could not start payment'); rzpBtn.disabled = false; return; }
      const d = res.data;
      const rzp = new Razorpay({
        key: d.key_id, order_id: d.order_id, amount: d.amount, currency: d.currency,
        name: d.name, description: d.plan_name + ' plan', prefill: d.prefill,
        theme: { color: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#e63946' },
        handler: function(r){
          AK.post(B + '/api/plan.php?action=razorpay_verify', {
            plan_id: cur.id,
            razorpay_order_id: r.razorpay_order_id,
            razorpay_payment_id: r.razorpay_payment_id,
            razorpay_signature: r.razorpay_signature
          }).then(function(v){ AK.handle(v, function(){ setTimeout(function(){ location.reload(); }, 1200); }); });
        },
        modal: { ondismiss: function(){ rzpBtn.disabled = false; } }
      });
      rzp.on('payment.failed', function(){ AK.toast('error', 'Payment failed. Please try again.'); rzpBtn.disabled = false; });
      rzp.open();
    }).catch(function(){ rzpBtn.disabled = false; });
  });
}
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
