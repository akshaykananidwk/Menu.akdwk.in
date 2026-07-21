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
$waSupport = formatWaNumber((string)getWaSetting('support_number', getSetting('support_whatsapp', ''))) ?? '';

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
<?php
$seoDesc = getSetting('seo_description', $tagline . '. Create a QR code menu with AI menu import, direct & waiter ordering, live kitchen (KOT) screen and WhatsApp automation. Built for Indian restaurants, cafés and food trucks. Free 7-day trial.');
$seoKeywords = getSetting('seo_keywords', 'digital menu, QR code menu, restaurant menu system, online menu, QR menu India, digital menu card, restaurant ordering system, e-menu, contactless menu, cafe menu QR, hotel menu software, menu maker, ડિજિટલ મેનુ, QR મેનુ');
$ogImage = $logo ? mediaUrl($logo) : (BASE_URL . '/assets/img/food/paneer-tikka.jpg');
?>
<meta name="description" content="<?= e($seoDesc) ?>">
<meta name="keywords" content="<?= e($seoKeywords) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= e(BASE_URL) ?>/">
<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($siteName) ?> — <?= e($tagline) ?>">
<meta property="og:description" content="<?= e($seoDesc) ?>">
<meta property="og:url" content="<?= e(BASE_URL) ?>/">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="en_IN">
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($siteName) ?> — <?= e($tagline) ?>">
<meta name="twitter:description" content="<?= e($seoDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<!-- Structured data -->
<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org',
  '@type'    => 'SoftwareApplication',
  'name'     => $siteName,
  'applicationCategory' => 'BusinessApplication',
  'operatingSystem'     => 'Web',
  'description' => $seoDesc,
  'url'         => BASE_URL . '/',
  'image'       => $ogImage,
  'offers'      => array_map(fn($p) => [
      '@type' => 'Offer',
      'price' => (string)(float)$p['price'],
      'priceCurrency' => 'INR',
      'name'  => $p['name'],
  ], $plans ?: [['price'=>0,'name'=>'Free Trial']]),
  'aggregateRating' => ['@type'=>'AggregateRating','ratingValue'=>'4.8','reviewCount'=>(string)max(12,$restaurantCount)],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
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

/* ---- Animated hero scene: person presenting a live phone ---- */
.scene{position:relative;width:100%;max-width:460px;margin:0 auto;height:440px;perspective:1200px;animation:sceneIn 1s cubic-bezier(.2,.8,.2,1) both}
.scene .blob{position:absolute;inset:6% 4% 4% 6%;border-radius:46% 54% 52% 48%/48% 46% 54% 52%;background:radial-gradient(120% 120% at 30% 20%, rgba(255,255,255,.28), transparent 60%),linear-gradient(135deg,color-mix(in srgb,var(--a) 85%,#fff),color-mix(in srgb,var(--p) 75%,#000));filter:blur(2px);opacity:.55;animation:blobm 9s ease-in-out infinite}
.person{position:absolute;left:-6%;bottom:0;width:210px;height:auto;z-index:2;filter:drop-shadow(0 24px 30px rgba(0,0,0,.25));animation:sway 6s ease-in-out infinite}
.float-food{position:absolute;font-size:1.7rem;z-index:1;filter:drop-shadow(0 6px 10px rgba(0,0,0,.25))}
.float-food.f1{top:6%;right:16%;animation:floaty 5s ease-in-out infinite}
.float-food.f2{top:24%;right:2%;animation:floaty 6.5s ease-in-out infinite .6s}
.float-food.f3{bottom:20%;left:2%;animation:floaty 5.8s ease-in-out infinite .3s}
.float-food.f4{top:44%;right:20%;font-size:1.3rem;animation:floaty 7s ease-in-out infinite .9s}

.phone{position:absolute;right:2%;top:50%;width:236px;transform:translateY(-50%) rotate(-4deg);transform-style:preserve-3d;z-index:3;background:#0b1220;border-radius:34px;padding:11px;box-shadow:0 44px 80px -22px rgba(0,0,0,.55),0 0 0 2px rgba(255,255,255,.08);animation:phoneFloat 5.5s ease-in-out infinite}
.phone .scr{background:#fff;border-radius:26px;overflow:hidden;color:var(--ink);position:relative}
.phone .top{height:120px;background:linear-gradient(135deg,var(--p),var(--a));position:relative}
.phone .top .rn{position:absolute;left:14px;bottom:12px;color:#fff;font-weight:700;font-size:.85rem}
.phone .notch{position:absolute;top:8px;left:50%;transform:translateX(-50%);width:82px;height:6px;border-radius:6px;background:rgba(255,255,255,.5)}
.phone .cats{display:flex;gap:6px;padding:10px 12px;overflow:hidden}
.phone .cats span{font-size:.6rem;padding:.2rem .55rem;border-radius:999px;background:var(--soft);white-space:nowrap;transition:.3s}
.phone .cats span.on{background:var(--p);color:#fff}
.phone .cats span.c2{animation:catcycle 8s infinite}
.phone .cats span.c1{animation:catcycle2 8s infinite}
.phone .it{display:flex;gap:8px;padding:7px 12px;align-items:center;opacity:0;animation:itemIn .6s ease forwards}
.phone .it:nth-child(1){animation-delay:.3s}
.phone .it:nth-child(2){animation-delay:.55s}
.phone .it:nth-child(3){animation-delay:.8s}
.phone .it:nth-child(4){animation-delay:1.05s}
.phone .it .th{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#ffe3c2,#ffd0a1);flex:0 0 auto;display:grid;place-items:center;font-size:1rem}
.phone .it .g{flex:1}
.phone .it .g .a{height:8px;width:62%;background:#e9edf5;border-radius:4px}
.phone .it .g .b{height:7px;width:40%;background:#f1f4f9;border-radius:4px;margin-top:5px}
.phone .it .pr{font-size:.68rem;font-weight:700;color:var(--p)}
.phone .bar{position:relative;margin:8px 12px 12px;background:var(--s);color:#fff;border-radius:12px;padding:.5rem;text-align:center;font-size:.72rem;font-weight:600;overflow:hidden}
.phone .bar .ripple{position:absolute;top:50%;left:50%;width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.6);transform:translate(-50%,-50%) scale(0);animation:tap 6s ease-out infinite}
.phone .tap-dot{position:absolute;z-index:5;right:26px;bottom:24px;width:26px;height:26px;border-radius:50%;border:2px solid rgba(255,255,255,.9);background:rgba(255,255,255,.25);animation:tapDot 6s ease-in-out infinite;pointer-events:none}
.phone .placed{position:absolute;inset:0;background:linear-gradient(135deg,rgba(16,185,129,.96),rgba(5,150,105,.96));color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;opacity:0;animation:placed 6s ease-in-out infinite}
.phone .placed .chk{width:52px;height:52px;border-radius:50%;background:#fff;color:#059669;display:grid;place-items:center;font-size:1.6rem}
.cart-badge{position:absolute;top:-8px;right:-8px;min-width:22px;height:22px;padding:0 5px;border-radius:999px;background:var(--a);color:#111;font-size:.72rem;font-weight:800;display:grid;place-items:center;box-shadow:0 4px 10px rgba(0,0,0,.25);animation:cartpop 6s ease-in-out infinite}

@keyframes sceneIn{from{opacity:0;transform:translateY(24px) scale(.96)}to{opacity:1;transform:none}}
@keyframes phoneFloat{0%,100%{transform:translateY(-50%) rotate(-4deg)}50%{transform:translateY(-58%) rotate(-1deg)}}
@keyframes sway{0%,100%{transform:rotate(-1.5deg)}50%{transform:rotate(1.5deg)}}
@keyframes blobm{0%,100%{border-radius:46% 54% 52% 48%/48% 46% 54% 52%;transform:rotate(0)}50%{border-radius:54% 46% 48% 52%/52% 54% 46% 48%;transform:rotate(8deg)}}
@keyframes floaty{0%,100%{transform:translateY(0) rotate(-6deg)}50%{transform:translateY(-16px) rotate(6deg)}}
@keyframes itemIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@keyframes tap{0%,55%{transform:translate(-50%,-50%) scale(0);opacity:.7}70%{transform:translate(-50%,-50%) scale(9);opacity:0}100%{opacity:0}}
@keyframes tapDot{0%,52%{transform:translateY(0) scale(1);opacity:0}56%{opacity:1}60%{transform:translateY(6px) scale(.8);opacity:1}66%,100%{transform:translateY(0) scale(1);opacity:0}}
@keyframes placed{0%,68%{opacity:0;transform:scale(1.04)}76%,92%{opacity:1;transform:scale(1)}100%{opacity:0;transform:scale(1.04)}}
@keyframes cartpop{0%,30%{transform:scale(0);opacity:0}40%{transform:scale(1.25);opacity:1}50%,66%{transform:scale(1);opacity:1}74%,100%{transform:scale(0);opacity:0}}
@keyframes catcycle{0%,40%{background:var(--p);color:#fff}50%,100%{background:var(--soft);color:inherit}}
@keyframes catcycle2{0%,40%{background:var(--soft);color:inherit}50%,100%{background:var(--p);color:#fff}}
@media(prefers-reduced-motion:reduce){.scene,.person,.phone,.float-food,.blob,.phone .it,.phone .placed,.tap-dot,.cart-badge,.phone .bar .ripple{animation:none!important}.phone .it{opacity:1}}

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
        <div class="scene">
          <div class="blob"></div>
          <span class="float-food f1">🍔</span>
          <span class="float-food f2">🍕</span>
          <span class="float-food f3">🥤</span>
          <span class="float-food f4">🍩</span>

          <!-- Illustrated person presenting the phone -->
          <svg class="person" viewBox="0 0 240 330" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
              <linearGradient id="shirt" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="var(--p)"/><stop offset="1" stop-color="var(--s)"/>
              </linearGradient>
            </defs>
            <!-- shoulders / torso -->
            <path d="M18 330 Q18 208 120 208 Q222 208 222 330 Z" fill="url(#shirt)"/>
            <!-- collar -->
            <path d="M92 214 Q120 240 148 214 L148 208 L92 208 Z" fill="rgba(255,255,255,.18)"/>
            <!-- neck -->
            <rect x="102" y="150" width="36" height="56" rx="18" fill="#e7ac82"/>
            <!-- head -->
            <circle cx="120" cy="118" r="50" fill="#f4c19a"/>
            <!-- ears -->
            <circle cx="72" cy="120" r="9" fill="#f4c19a"/><circle cx="168" cy="120" r="9" fill="#f4c19a"/>
            <!-- hair -->
            <path d="M68 122 Q64 58 120 56 Q176 58 172 122 Q172 96 156 92 Q150 74 120 74 Q90 74 84 92 Q68 98 68 122 Z" fill="#2b2b3a"/>
            <!-- eyes + brows + smile -->
            <circle cx="103" cy="116" r="4.6" fill="#2b2b3a"/><circle cx="137" cy="116" r="4.6" fill="#2b2b3a"/>
            <path d="M95 106 Q103 101 111 106" stroke="#2b2b3a" stroke-width="3" fill="none" stroke-linecap="round"/>
            <path d="M129 106 Q137 101 145 106" stroke="#2b2b3a" stroke-width="3" fill="none" stroke-linecap="round"/>
            <path d="M106 134 Q120 148 134 134" stroke="#c47a50" stroke-width="4" fill="none" stroke-linecap="round"/>
            <!-- extended arm + open hand toward the phone (right) -->
            <path d="M196 250 Q236 226 236 176 Q236 158 214 158 Q206 210 168 226 Z" fill="url(#shirt)"/>
            <path d="M224 150 q22 -4 24 14 q2 16 -14 22 q-18 6 -26 -8 q-6 -14 6 -22 Z" fill="#f4c19a"/>
          </svg>

          <!-- Live phone -->
          <div class="phone">
            <span class="cart-badge">3</span>
            <div class="scr">
              <div class="tap-dot"></div>
              <div class="top"><span class="notch"></span><span class="rn"><i class="bi bi-shop"></i> <?= e($siteName) ?></span></div>
              <div class="cats"><span class="c1 on">Starters</span><span class="c2">Main Course</span><span>Breads</span><span>Drinks</span></div>
              <?php $emoji=['🍲','🍛','🍗','🥗']; for ($i=0;$i<4;$i++): ?>
              <div class="it"><div class="th"><?= $emoji[$i] ?></div><div class="g"><div class="a"></div><div class="b"></div></div><div class="pr"><?= e($curr) ?><?= [120,240,60,90][$i] ?></div></div>
              <?php endfor; ?>
              <div class="bar"><span class="ripple"></span><i class="bi bi-bag-check"></i> Add to Order · <?= e($curr) ?>510</div>
              <div class="placed"><div class="chk"><i class="bi bi-check-lg"></i></div><div style="font-weight:800">Order placed!</div><div style="font-size:.7rem;opacity:.9">Sent to the kitchen 🔔</div></div>
            </div>
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
