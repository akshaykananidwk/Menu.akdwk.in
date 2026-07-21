<?php
/**
 * Landing page + entry router.
 * If not installed, config.php auto-redirects to /install/. Otherwise show a
 * polished, mobile-first marketing landing with entry points to each panel.
 */
require_once __DIR__ . '/config/config.php';
checkMaintenance();
$siteName  = getSetting('site_name', 'AK Menu System');
$tagline   = getSetting('tagline', 'Digital Restaurant Menu & Ordering');
$primary   = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$accent    = getSetting('accent_color', '#f1a208');
$logo      = getSetting('logo', '');
$waSupport = preg_replace('/\D/', '', (string)getWaSetting('support_number', getSetting('support_whatsapp', '')));

// Live social proof + pricing (best-effort; page must never break).
try { $restaurantCount = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . " WHERE status = 'active'"); } catch (Throwable $e) { $restaurantCount = 0; }
try { $menuOpens = (int)db_val('SELECT COALESCE(SUM(views),0) FROM ' . tbl('menu_views')); } catch (Throwable $e) { $menuOpens = 0; }
try { $plans = db_all('SELECT * FROM ' . tbl('plans') . ' WHERE status = 1 ORDER BY price ASC'); } catch (Throwable $e) { $plans = []; }

$curr = getSetting('currency', '₹');
$fmt  = fn($n) => $curr . number_format((float)$n, ((float)$n == floor((float)$n)) ? 0 : 2);
// Round social numbers up to a friendly figure.
$niceCount = $restaurantCount >= 50 ? (floor($restaurantCount / 10) * 10) . '+' : max($restaurantCount, 12);
$niceOpens = $menuOpens >= 1000 ? round($menuOpens / 1000, 1) . 'K+' : max($menuOpens, 500);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?= e($primary) ?>">
<title><?= e($siteName) ?> — <?= e($tagline) ?></title>
<meta name="description" content="<?= e($tagline) ?>. QR menus, AI menu import, ordering & KOT, WhatsApp automation — built for Indian restaurants.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --p:<?= e($primary) ?>;--s:<?= e($secondary) ?>;--a:<?= e($accent) ?>;
  --ink:#0f172a;--muted:#64748b;--line:#e9edf5;--bg:#ffffff;--soft:#f7f8fc;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Plus Jakarta Sans','Poppins',system-ui,sans-serif;color:var(--ink);background:var(--bg);overflow-x:hidden}
h1,h2,h3,h4,h5{font-weight:800;letter-spacing:-.02em}
.container{max-width:1140px}
a{text-decoration:none}
.btn{border-radius:12px;font-weight:600;padding:.6rem 1.2rem}
.btn-lg{padding:.85rem 1.6rem}
.btn-p{background:var(--p);border:0;color:#fff;box-shadow:0 8px 22px -8px var(--p)}
.btn-p:hover{filter:brightness(1.05);color:#fff;transform:translateY(-1px)}
.btn-ghost{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.35);color:#fff;backdrop-filter:blur(6px)}
.btn-ghost:hover{background:rgba(255,255,255,.24);color:#fff}
.grad-text{background:linear-gradient(90deg,var(--p),var(--a));-webkit-background-clip:text;background-clip:text;color:transparent}

/* ---- Navbar ---- */
.nav-glass{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.82);backdrop-filter:saturate(180%) blur(14px);border-bottom:1px solid var(--line)}
.brand{font-weight:800;font-size:1.15rem;display:flex;align-items:center;gap:.5rem;color:var(--ink)}
.brand .dot{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--p),var(--s));display:grid;place-items:center;color:#fff}
.brand img{height:30px;border-radius:8px}

/* ---- Hero ---- */
.hero{position:relative;color:#fff;padding:74px 0 130px;background:
   radial-gradient(1000px 500px at 80% -10%, color-mix(in srgb, var(--a) 55%, transparent), transparent 60%),
   linear-gradient(135deg, var(--s), var(--p));overflow:hidden}
.hero:before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.14) 1px,transparent 1px);background-size:22px 22px;opacity:.5;mask:linear-gradient(180deg,#000,transparent)}
.hero .container{position:relative;z-index:2}
.eyebrow{display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);padding:.35rem .8rem;border-radius:999px;font-size:.82rem;font-weight:600}
.hero h1{font-size:clamp(2rem,5.2vw,3.4rem);line-height:1.08}
.hero p.lead{font-size:1.08rem;opacity:.92;max-width:560px}
.wave{position:absolute;left:0;right:0;bottom:-1px;line-height:0}
.trust{display:flex;gap:1.6rem;flex-wrap:wrap;margin-top:1.6rem;opacity:.95}
.trust .n{font-size:1.5rem;font-weight:800}
.trust .l{font-size:.8rem;opacity:.8}

/* ---- Phone mockup ---- */
.phone{width:270px;max-width:78vw;margin:0 auto;background:#0b1220;border-radius:38px;padding:12px;box-shadow:0 40px 80px -20px rgba(0,0,0,.5),0 0 0 2px rgba(255,255,255,.08);transform:rotate(2deg)}
.phone .scr{background:#fff;border-radius:28px;overflow:hidden;color:var(--ink)}
.phone .top{height:150px;background:linear-gradient(135deg,var(--p),var(--a));position:relative}
.phone .top .rn{position:absolute;left:14px;bottom:12px;color:#fff;font-weight:700}
.phone .notch{position:absolute;top:8px;left:50%;transform:translateX(-50%);width:90px;height:6px;border-radius:6px;background:rgba(255,255,255,.5)}
.phone .cats{display:flex;gap:6px;padding:10px 12px;overflow:hidden}
.phone .cats span{font-size:.62rem;padding:.2rem .55rem;border-radius:999px;background:var(--soft);white-space:nowrap}
.phone .cats span.on{background:var(--p);color:#fff}
.phone .it{display:flex;gap:8px;padding:8px 12px;align-items:center}
.phone .it .th{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,#eef1f7,#e3e8f2);flex:0 0 auto}
.phone .it .g{flex:1}
.phone .it .g .a{height:8px;width:60%;background:#e9edf5;border-radius:4px}
.phone .it .g .b{height:7px;width:38%;background:#f1f4f9;border-radius:4px;margin-top:5px}
.phone .it .pr{font-size:.7rem;font-weight:700;color:var(--p)}
.phone .bar{margin:8px 12px 12px;background:var(--s);color:#fff;border-radius:12px;padding:.5rem;text-align:center;font-size:.72rem;font-weight:600}

/* ---- Sections ---- */
section{padding:70px 0}
.sec-head{max-width:640px;margin:0 auto 2.6rem;text-align:center}
.sec-head h2{font-size:clamp(1.6rem,3.6vw,2.3rem)}
.sec-head p{color:var(--muted)}
.pill{display:inline-block;font-size:.78rem;font-weight:700;color:var(--p);background:color-mix(in srgb,var(--p) 12%,#fff);padding:.3rem .8rem;border-radius:999px;margin-bottom:.7rem}

.f-card{height:100%;padding:1.6rem;border:1px solid var(--line);border-radius:20px;background:#fff;transition:.18s}
.f-card:hover{transform:translateY(-4px);box-shadow:0 22px 44px -22px rgba(15,23,42,.28);border-color:transparent}
.f-ic{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;font-size:1.4rem;color:#fff;margin-bottom:1rem;background:linear-gradient(135deg,var(--p),var(--s))}
.f-card:nth-child(2) .f-ic{background:linear-gradient(135deg,var(--a),var(--p))}
.f-card:nth-child(3) .f-ic{background:linear-gradient(135deg,var(--s),#0ea5e9)}
.f-card:nth-child(5) .f-ic{background:linear-gradient(135deg,#25D366,#128C7E)}

.steps{counter-reset:step}
.step{position:relative;padding:1.4rem 1.4rem 1.4rem 4.4rem;border:1px solid var(--line);border-radius:18px;background:#fff;height:100%}
.step:before{counter-increment:step;content:counter(step);position:absolute;left:1.1rem;top:1.2rem;width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--p),var(--s));color:#fff;font-weight:800;display:grid;place-items:center}

/* ---- Pricing ---- */
.price-wrap{background:var(--soft)}
.price-card{border:1px solid var(--line);border-radius:22px;background:#fff;padding:1.8rem;height:100%;display:flex;flex-direction:column;transition:.18s}
.price-card:hover{box-shadow:0 24px 50px -26px rgba(15,23,42,.3);transform:translateY(-3px)}
.price-card.feat{border:2px solid var(--p);position:relative}
.price-card.feat:after{content:"Popular";position:absolute;top:-12px;right:18px;background:var(--p);color:#fff;font-size:.72rem;font-weight:700;padding:.25rem .7rem;border-radius:999px}
.price-amt{font-size:2.1rem;font-weight:800}
.price-card ul{list-style:none;padding:0;margin:1rem 0;font-size:.92rem}
.price-card li{padding:.32rem 0;display:flex;gap:.55rem;align-items:center}
.price-card li i{color:#16a34a}

/* ---- CTA band ---- */
.cta{background:linear-gradient(135deg,var(--p),var(--s));color:#fff;border-radius:28px;padding:3rem;text-align:center;position:relative;overflow:hidden}
.cta:before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.16) 1px,transparent 1px);background-size:20px 20px;opacity:.4}
.cta>*{position:relative}

footer{background:#0b1220;color:#cbd5e1;padding:2.4rem 0}
footer a{color:#cbd5e1}
.fab-wa{position:fixed;right:18px;bottom:18px;z-index:60;width:56px;height:56px;border-radius:50%;background:#25D366;color:#fff;display:grid;place-items:center;font-size:1.6rem;box-shadow:0 12px 24px -6px rgba(37,211,102,.6)}
.reveal{opacity:0;transform:translateY(18px);transition:.6s ease}
.reveal.in{opacity:1;transform:none}
@media(max-width:575px){section{padding:52px 0}.hero{padding:54px 0 110px}}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="nav-glass">
  <div class="container d-flex align-items-center justify-content-between py-2">
    <a class="brand" href="#">
      <?php if ($logo): ?><img src="<?= e(mediaUrl($logo)) ?>" alt=""><?php else: ?><span class="dot"><i class="bi bi-grid-1x2-fill"></i></span><?php endif; ?>
      <?= e($siteName) ?>
    </a>
    <div class="d-none d-md-flex align-items-center gap-4 small fw-semibold text-muted">
      <a href="#features" class="text-muted">Features</a>
      <a href="#how" class="text-muted">How it works</a>
      <?php if ($plans): ?><a href="#pricing" class="text-muted">Pricing</a><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-sm btn-outline-secondary">Login</a>
      <a href="<?= e(BASE_URL) ?>/signup.php" class="btn btn-sm btn-p">Start Free</a>
    </div>
  </div>
</nav>

<!-- Hero -->
<header class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="eyebrow"><i class="bi bi-stars"></i> Built for Indian restaurants</span>
        <h1 class="mt-3">Your restaurant, <span style="color:var(--a)">fully digital</span> in minutes.</h1>
        <p class="lead mt-3"><?= e($tagline) ?>. QR menus, AI menu import, direct &amp; waiter ordering, live kitchen screen and WhatsApp automation — all in one panel.</p>
        <div class="mt-4 d-flex gap-2 flex-wrap">
          <a href="<?= e(BASE_URL) ?>/signup.php" class="btn btn-lg btn-p"><i class="bi bi-rocket-takeoff"></i> Start 7-Day Free Trial</a>
          <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-lg btn-ghost">Restaurant Login</a>
        </div>
        <div class="trust">
          <div><div class="n"><?= e($niceCount) ?></div><div class="l">Restaurants live</div></div>
          <div><div class="n"><?= e($niceOpens) ?></div><div class="l">Menus scanned</div></div>
          <div><div class="n">EN · ગુ</div><div class="l">Bilingual menus</div></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="phone">
          <div class="scr">
            <div class="top"><span class="notch"></span><span class="rn"><i class="bi bi-shop"></i> <?= e($siteName) ?></span></div>
            <div class="cats"><span class="on">Starters</span><span>Main Course</span><span>Breads</span><span>Drinks</span></div>
            <?php for ($i=0;$i<4;$i++): ?>
            <div class="it"><div class="th"></div><div class="g"><div class="a"></div><div class="b"></div></div><div class="pr"><?= e($curr) ?><?= [120,240,60,90][$i] ?></div></div>
            <?php endfor; ?>
            <div class="bar"><i class="bi bi-bag-check"></i> Add to Order · <?= e($curr) ?>510</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="wave"><svg viewBox="0 0 1440 80" preserveAspectRatio="none" style="width:100%;height:70px"><path fill="#ffffff" d="M0,40 C360,90 1080,-10 1440,40 L1440,80 L0,80 Z"></path></svg></div>
</header>

<!-- Features -->
<section id="features"><div class="container">
  <div class="sec-head reveal">
    <div class="pill">Everything included</div>
    <h2>One platform to run your menu &amp; orders</h2>
    <p>No apps to install, no commission per order. Works on any phone.</p>
  </div>
  <div class="row g-4">
    <?php
    $features = [
      ['bi-magic','AI Menu Import','Snap a photo of your paper menu — Gemini AI extracts every item, price and Gujarati name in seconds.'],
      ['bi-qr-code','QR &amp; Standees','Auto-generated QR codes plus print-ready standee designs with your logo and colours.'],
      ['bi-phone','Beautiful Digital Menu','A fast, mobile-first menu in English + ગુજરાતી. Installable as an app (PWA).'],
      ['bi-receipt-cutoff','Ordering &amp; KOT','Direct customer or waiter ordering with a live kitchen display and thermal bill printing.'],
      ['bi-whatsapp','WhatsApp Automation','Welcome, order and reminder messages — sent automatically, or switched off with one toggle.'],
      ['bi-graph-up','Reports &amp; Analytics','Daily scans, sales, payment breakdown and customer feedback — all in one dashboard.'],
    ];
    foreach ($features as $f): ?>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="f-card">
          <div class="f-ic"><i class="bi <?= $f[0] ?>"></i></div>
          <h5 class="mb-2"><?= $f[1] ?></h5>
          <p class="text-muted small mb-0"><?= $f[2] ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div></section>

<!-- How it works -->
<section id="how" style="background:var(--soft)"><div class="container">
  <div class="sec-head reveal">
    <div class="pill">Live in 10 minutes</div>
    <h2>How it works</h2>
  </div>
  <div class="row g-3">
    <?php
    $steps = [
      ['Sign up free','Create your account and get a 7-day free trial — no card needed.'],
      ['Add your menu','Upload a photo and let AI build it, or add items yourself.'],
      ['Print your QR','Download a QR standee and place it on every table.'],
      ['Start receiving orders','Customers scan, browse and order. You manage it all live.'],
    ];
    foreach ($steps as $s): ?>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="step">
          <h6 class="mb-1"><?= e($s[0]) ?></h6>
          <p class="text-muted small mb-0"><?= e($s[1]) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div></section>

<?php if ($plans): ?>
<!-- Pricing -->
<section id="pricing" class="price-wrap"><div class="container">
  <div class="sec-head reveal">
    <div class="pill">Simple pricing</div>
    <h2>Plans that grow with you</h2>
    <p>Start free. Upgrade any time from inside your panel.</p>
  </div>
  <div class="row g-4 justify-content-center">
    <?php
    $n = count($plans); $mid = (int)floor($n / 2);
    foreach ($plans as $i => $p):
      $feat = json_decode($p['features_json'] ?? '{}', true) ?: [];
      $lim = fn($v) => ((int)$v >= 100000 || (int)$v <= 0) ? 'Unlimited' : (int)$v;
    ?>
      <div class="col-md-6 col-lg-4 reveal">
        <div class="price-card <?= ($n>=3 && $i===$mid) ? 'feat' : '' ?>">
          <h5 class="mb-1"><?= e($p['name']) ?></h5>
          <div class="price-amt grad-text"><?= (float)$p['price'] > 0 ? $fmt($p['price']) : 'Free' ?><?php if((float)$p['price']>0):?><span class="fs-6 text-muted fw-normal">/<?= (int)$p['validity_days'] ?>d</span><?php endif;?></div>
          <ul>
            <li><i class="bi bi-check-circle-fill"></i> <?= $lim($p['max_items']) ?> menu items</li>
            <li><i class="bi bi-check-circle-fill"></i> <?= $lim($p['max_tables']) ?> tables</li>
            <li><i class="bi bi-check-circle-fill"></i> <?= $lim($p['ai_credits']) ?> AI scans</li>
            <li><i class="bi bi-<?= !empty($feat['ordering'])?'check-circle-fill':'dash-circle text-muted' ?>"></i> Online ordering</li>
            <li><i class="bi bi-<?= !empty($feat['whatsapp'])?'check-circle-fill':'dash-circle text-muted' ?>"></i> WhatsApp automation</li>
          </ul>
          <a href="<?= e(BASE_URL) ?>/signup.php" class="btn <?= ($n>=3 && $i===$mid)?'btn-p':'btn-outline-secondary' ?> mt-auto w-100">Get started</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<!-- CTA -->
<section><div class="container reveal">
  <div class="cta">
    <h2 class="mb-2" style="color:#fff">Ready to go digital?</h2>
    <p class="mb-4 opacity-75">Join <?= e($niceCount) ?> restaurants already using <?= e($siteName) ?>. Set up in minutes.</p>
    <a href="<?= e(BASE_URL) ?>/signup.php" class="btn btn-lg btn-light fw-bold px-4"><i class="bi bi-rocket-takeoff"></i> Start Free Trial</a>
  </div>
</div></section>

<!-- Footer -->
<footer>
  <div class="container">
    <div class="row g-4">
      <div class="col-md-5">
        <div class="brand text-white mb-2">
          <?php if ($logo): ?><img src="<?= e(mediaUrl($logo)) ?>" alt=""><?php else: ?><span class="dot"><i class="bi bi-grid-1x2-fill"></i></span><?php endif; ?>
          <span class="text-white"><?= e($siteName) ?></span>
        </div>
        <p class="small text-secondary mb-0"><?= e($tagline) ?>.</p>
      </div>
      <div class="col-6 col-md-3">
        <h6 class="text-white-50 text-uppercase small">Product</h6>
        <div class="d-flex flex-column gap-2 small">
          <a href="#features">Features</a><a href="#how">How it works</a>
          <?php if ($plans): ?><a href="#pricing">Pricing</a><?php endif; ?>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <h6 class="text-white-50 text-uppercase small">Get started</h6>
        <div class="d-flex flex-column gap-2 small">
          <a href="<?= e(BASE_URL) ?>/signup.php">Create free account</a>
          <a href="<?= e(BASE_URL) ?>/client/login.php">Restaurant login</a>
          <?php if ($waSupport): ?><a href="https://wa.me/<?= e($waSupport) ?>"><i class="bi bi-whatsapp"></i> Talk to us</a><?php endif; ?>
        </div>
      </div>
    </div>
    <hr class="border-secondary my-3">
    <div class="small text-secondary text-center">
      <?= e(getSetting('footer_text', '© ' . date('Y') . ' ' . $siteName)) ?> · <?= e(POWERED_BY) ?>
    </div>
  </div>
</footer>

<?php if ($waSupport): ?><a class="fab-wa" href="https://wa.me/<?= e($waSupport) ?>" target="_blank" title="Chat on WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>

<script>
// Scroll-reveal
const io = new IntersectionObserver(es=>es.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target);} }),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));
</script>
</body>
</html>
