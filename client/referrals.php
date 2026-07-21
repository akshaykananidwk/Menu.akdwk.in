<?php
/**
 * Client — Refer & Earn.
 * Share a referral link; when an invited restaurant activates a paid plan, this
 * restaurant earns bonus days (rewarded via creditReferralOnActivation()).
 */
$pageTitle = 'Refer & Earn';
$activeNav = 'referrals';
require __DIR__ . '/_header.php';

$tid      = (int)currentTenantId();
$code     = tenantReferralCode($tid);
$link     = referralLink($tid);
$reward   = referralRewardDays();

try {
    $rows = db_all('SELECT r.*, t.restaurant_name, t.city, t.created_at AS joined
                    FROM ' . tbl('referrals') . ' r
                    LEFT JOIN ' . tbl('tenants') . ' t ON t.id = r.referred_id
                    WHERE r.referrer_id = :me ORDER BY r.id DESC', [':me' => $tid]);
} catch (Throwable $e) { $rows = []; }

$total    = count($rows);
$rewarded = array_filter($rows, fn($r) => $r['status'] === 'rewarded');
$daysEarned = array_sum(array_map(fn($r) => (int)$r['reward_days'], $rewarded));

$shareMsg = "Hi! I use " . getSetting('site_name', 'AK Menu System') . " for my restaurant's digital QR menu & ordering — it's great. Sign up free with my link and get a free trial: " . $link;
?>
<style>
.ref-hero{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border-radius:18px}
.ref-link{font-family:monospace;font-size:.95rem;word-break:break-all}
.stat{border:1px solid #eef0f5;border-radius:14px;padding:1rem;text-align:center}
.stat .n{font-size:1.7rem;font-weight:800;color:var(--primary)}
.step-r{display:flex;gap:.7rem;align-items:flex-start;margin-bottom:.7rem}
.step-r .b{flex:0 0 auto;width:30px;height:30px;border-radius:9px;background:var(--primary);color:#fff;display:grid;place-items:center;font-weight:700}
</style>

<div class="ref-hero p-4 mb-4">
  <div class="row align-items-center g-3">
    <div class="col-md-7">
      <h4 class="fw-bold mb-1"><i class="bi bi-gift"></i> Invite restaurants, earn free days</h4>
      <p class="mb-0 opacity-90">For every restaurant that joins with your link <strong>and buys any plan</strong>, you get <strong><?= $reward ?> extra days</strong> added to your subscription. No limit — invite as many as you like!</p>
    </div>
    <div class="col-md-5 text-md-end">
      <div class="badge bg-light text-dark fs-6 px-3 py-2">Your code: <strong><?= e($code ?: '—') ?></strong></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= $total ?></div><div class="text-muted small">Invited</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= count($rewarded) ?></div><div class="text-muted small">Joined &amp; paid</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= $daysEarned ?></div><div class="text-muted small">Days earned</div></div></div>
  <div class="col-6 col-md-3"><div class="stat"><div class="n"><?= $reward ?></div><div class="text-muted small">Days per referral</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-2">Your referral link</h6>
      <div class="input-group mb-2">
        <input type="text" id="refLink" class="form-control ref-link" value="<?= e($link) ?>" readonly>
        <button class="btn btn-primary" id="copyBtn"><i class="bi bi-clipboard"></i> Copy</button>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-success btn-sm" target="_blank" href="https://wa.me/?text=<?= rawurlencode($shareMsg) ?>"><i class="bi bi-whatsapp"></i> Share on WhatsApp</a>
        <a class="btn btn-outline-secondary btn-sm" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($link) ?>"><i class="bi bi-facebook"></i> Facebook</a>
        <button class="btn btn-outline-secondary btn-sm" id="copyMsg"><i class="bi bi-chat-text"></i> Copy message</button>
      </div>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3">How it works</h6>
      <div class="step-r"><div class="b">1</div><div>Share your link with other restaurant owners.</div></div>
      <div class="step-r"><div class="b">2</div><div>They sign up and try it free.</div></div>
      <div class="step-r"><div class="b">3</div><div>When they buy any plan, you get <strong><?= $reward ?> days</strong> added automatically.</div></div>
    </div></div>
  </div>
</div>

<?php if ($rows): ?>
<h6 class="fw-semibold mt-4 mb-2">Your referrals</h6>
<div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Restaurant</th><th>Joined</th><th>Status</th><th class="text-end">Reward</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="fw-semibold"><?= e($r['restaurant_name'] ?? 'Restaurant') ?><?= $r['city'] ? ' <span class="text-muted small">· ' . e($r['city']) . '</span>' : '' ?></td>
        <td class="small text-muted"><?= $r['joined'] ? e(date('d M Y', strtotime($r['joined']))) : '—' ?></td>
        <td><?= $r['status'] === 'rewarded'
            ? '<span class="badge bg-success-subtle text-success">Paid · rewarded</span>'
            : '<span class="badge bg-warning-subtle text-warning">Joined · trial</span>' ?></td>
        <td class="text-end"><?= $r['status'] === 'rewarded' ? '+' . (int)$r['reward_days'] . ' days' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>

<?php
$msgJs = json_encode($shareMsg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$pageScript = <<<HTML
<script>
const SHAREMSG = {$msgJs};
function copyText(t, btn){
  navigator.clipboard.writeText(t).then(()=>{ AK.toast('success','Copied!'); }, ()=>{ AK.toast('error','Copy failed'); });
}
document.getElementById('copyBtn').addEventListener('click', ()=>copyText(document.getElementById('refLink').value));
document.getElementById('copyMsg').addEventListener('click', ()=>copyText(SHAREMSG));
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
