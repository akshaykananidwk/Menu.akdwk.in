<?php
/**
 * Admin › Marketing & Growth.
 *   - Ad-budget tracker: revenue from paid invoices and the % you want to reinvest.
 *   - Ready-to-run Ads Kit: Google keywords + ad copy, FB/IG captions (EN + GU),
 *     WhatsApp broadcast templates and hashtags — each with a Copy button.
 * Note: this prepares the campaigns; you still paste them into your own ad
 * accounts (Google Ads / Meta) and set the budget there.
 */
$pageTitle = 'Marketing & Growth';
$activeNav = 'marketing';
require __DIR__ . '/_header.php';

// ---- Save the small settings (budget % + referral reward) -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    setSetting('ad_budget_percent', max(0, min(100, (int)($_POST['ad_budget_percent'] ?? 50))));
    setSetting('referral_reward_days', max(0, (int)($_POST['referral_reward_days'] ?? 30)));
    logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Updated marketing settings');
    redirect(BASE_URL . '/admin/marketing.php?saved=1');
}

$curr    = getSetting('currency', '₹');
$percent = (int)getSetting('ad_budget_percent', 50);
$site    = getSetting('site_name', 'AK Menu System');
$base    = rtrim(BASE_URL, '/');

// Revenue from paid invoices (best-effort).
try {
    $revAll   = (float)db_val('SELECT COALESCE(SUM(total),0) FROM ' . tbl('invoices') . " WHERE status='paid'");
    $revMonth = (float)db_val('SELECT COALESCE(SUM(total),0) FROM ' . tbl('invoices') . " WHERE status='paid' AND paid_on >= :d", [':d' => date('Y-m-01')]);
} catch (Throwable $e) { $revAll = 0; $revMonth = 0; }
$money = fn($n) => $curr . number_format((float)$n, 0);
?>
<style>
.mk-card{border:1px solid #eef0f5;border-radius:16px}
.budget{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border-radius:16px}
.kit pre{background:#0f172a;color:#e2e8f0;border-radius:10px;padding:14px;white-space:pre-wrap;font-size:.86rem;margin:0}
.kit .blk{position:relative;margin-bottom:1rem}
.kit .cp{position:absolute;top:8px;right:8px}
.chip{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:.15rem .6rem;font-size:.8rem;margin:.15rem}
</style>

<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2 small">Saved.</div><?php endif; ?>

<!-- Budget tracker -->
<div class="budget p-4 mb-4">
  <div class="row g-3 align-items-center">
    <div class="col-md-8">
      <div class="text-uppercase small opacity-75" style="letter-spacing:.5px">Reinvest in advertising</div>
      <div class="d-flex flex-wrap gap-4 mt-2">
        <div><div class="h3 fw-bold mb-0"><?= $money($revAll) ?></div><div class="small opacity-75">Total revenue (paid)</div></div>
        <div><div class="h3 fw-bold mb-0"><?= $money($revAll * $percent / 100) ?></div><div class="small opacity-75"><?= $percent ?>% ad budget (all-time)</div></div>
        <div><div class="h3 fw-bold mb-0"><?= $money($revMonth * $percent / 100) ?></div><div class="small opacity-75">This month's ad budget</div></div>
      </div>
      <p class="small opacity-75 mt-2 mb-0"><i class="bi bi-info-circle"></i> Spend this amount on Google/Meta ads. Money is added only when a plan is actually paid, so your budget grows with real sales.</p>
    </div>
    <div class="col-md-4">
      <form method="post" class="bg-white text-dark rounded p-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label class="form-label small mb-1">Reinvest % of revenue</label>
        <div class="input-group input-group-sm mb-2">
          <input type="number" name="ad_budget_percent" min="0" max="100" value="<?= $percent ?>" class="form-control">
          <span class="input-group-text">%</span>
        </div>
        <label class="form-label small mb-1">Referral reward (days)</label>
        <input type="number" name="referral_reward_days" min="0" value="<?= (int)getSetting('referral_reward_days', 30) ?>" class="form-control form-control-sm mb-2">
        <button class="btn btn-primary btn-sm w-100">Save</button>
      </form>
    </div>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-megaphone"></i> These are ready-to-use campaigns. Paste them into your <strong>Google Ads</strong> and <strong>Meta (Facebook/Instagram) Ads</strong> accounts, point them at <strong><?= e($base) ?></strong>, and set the budget above. Keywords are chosen so your site shows when people search for a digital/QR menu.
</div>

<div class="kit row g-3">
  <!-- Google keywords -->
  <div class="col-lg-6">
    <div class="card mk-card h-100"><div class="card-body">
      <h6 class="fw-semibold"><i class="bi bi-google"></i> Google Ads — Keywords</h6>
      <p class="text-muted small">Add as “Phrase match”. These target owners looking for exactly what you sell.</p>
      <div class="blk">
        <button class="btn btn-sm btn-outline-secondary cp" data-copy="#kwGoogle">Copy</button>
        <pre id="kwGoogle">digital menu for restaurant
QR code menu
QR menu maker
restaurant menu QR code
online menu for restaurant
digital menu card
contactless menu
scan menu restaurant
restaurant ordering system
e-menu for cafe
menu QR code generator
hotel digital menu
food menu app for restaurant
digital menu India
restaurant menu software</pre>
      </div>
    </div></div>
  </div>

  <!-- Google ad copy -->
  <div class="col-lg-6">
    <div class="card mk-card h-100"><div class="card-body">
      <h6 class="fw-semibold"><i class="bi bi-card-text"></i> Google Ads — Ad copy</h6>
      <p class="text-muted small">Headlines (max 30 chars) + descriptions (max 90 chars).</p>
      <div class="blk">
        <button class="btn btn-sm btn-outline-secondary cp" data-copy="#adGoogle">Copy</button>
        <pre id="adGoogle">Headlines:
• Digital QR Menu in Minutes
• Free 7-Day Menu Trial
• QR Menu + Online Ordering
• AI Builds Your Menu
• No App. Just Scan & Order

Descriptions:
• Turn your paper menu into a smart QR menu. AI import, ordering & KOT. Try free.
• QR menu, WhatsApp orders, live kitchen screen. Built for Indian restaurants.

Final URL: <?= e($base) ?>/</pre>
      </div>
    </div></div>
  </div>

  <!-- Facebook / Instagram -->
  <div class="col-lg-6">
    <div class="card mk-card h-100"><div class="card-body">
      <h6 class="fw-semibold"><i class="bi bi-facebook"></i> Facebook / Instagram — Ad</h6>
      <div class="blk">
        <button class="btn btn-sm btn-outline-secondary cp" data-copy="#adMeta">Copy</button>
        <pre id="adMeta">Primary text (English):
📱 Turn your restaurant menu digital in minutes!
Create a QR code menu customers scan to browse & order — no app needed.
✅ AI menu import  ✅ Online ordering  ✅ WhatsApp alerts  ✅ Live kitchen screen
🎁 Free 7-day trial. Start now 👇
<?= e($base) ?>/signup.php

Headline: Your Restaurant, Fully Digital
Button: Sign Up

પ્રાથમિક ટેક્સ્ટ (ગુજરાતી):
📱 તમારી રેસ્ટોરન્ટનું મેનુ ડિજિટલ કરો — QR કોડ સ્કેન કરીને ગ્રાહક જાતે ઓર્ડર કરે!
✅ AI મેનુ ✅ ઓનલાઈન ઓર્ડર ✅ WhatsApp ✅ કિચન સ્ક્રીન
🎁 7 દિવસ ફ્રી ટ્રાયલ. અત્યારે જ શરૂ કરો 👇
<?= e($base) ?>/signup.php</pre>
      </div>
    </div></div>
  </div>

  <!-- WhatsApp + hashtags -->
  <div class="col-lg-6">
    <div class="card mk-card h-100"><div class="card-body">
      <h6 class="fw-semibold"><i class="bi bi-whatsapp"></i> WhatsApp broadcast</h6>
      <div class="blk">
        <button class="btn btn-sm btn-outline-secondary cp" data-copy="#waMsg">Copy</button>
        <pre id="waMsg">Namaste 🙏 Is your restaurant menu still on paper?

With <?= e($site) ?> you get a QR code menu customers scan to browse & order — plus WhatsApp order alerts and a live kitchen screen.

🎁 Try it FREE for 7 days: <?= e($base) ?>/signup.php

Reply "MENU" and we'll set it up for you.</pre>
      </div>
      <h6 class="fw-semibold mt-2"><i class="bi bi-hash"></i> Hashtags</h6>
      <div>
        <?php foreach (['#DigitalMenu','#QRMenu','#RestaurantTech','#QRCodeMenu','#Foodtech','#RestaurantOwner','#CafeLife','#DigitalIndia','#OnlineOrdering','#MenuCard'] as $h): ?>
          <span class="chip"><?= e($h) ?></span>
        <?php endforeach; ?>
      </div>
    </div></div>
  </div>
</div>

<?php
$pageScript = <<<HTML
<script>
document.querySelectorAll('.cp').forEach(function(b){
  b.addEventListener('click', function(){
    var el = document.querySelector(b.dataset.copy);
    if(!el) return;
    navigator.clipboard.writeText(el.innerText).then(function(){
      var t = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> Copied'; b.classList.add('btn-success'); b.classList.remove('btn-outline-secondary');
      setTimeout(function(){ b.innerHTML = t; b.classList.remove('btn-success'); b.classList.add('btn-outline-secondary'); }, 1500);
    });
  });
});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
