<?php
/**
 * MENU DESIGN TEMPLATE: "Modern" (premium redesign)
 * Receives (from /r/index.php): $menu, $tenant, $categories, $orderingMode,
 * $tableToken, $tableNo, $currency, $lang, $showBranding, $folder.
 * Templates ONLY read the provided data — no DB queries here.
 * Design goal: top-tier, app-quality digital menu (Zomato/Swiggy calibre) —
 * polished hero, sticky pill category nav with scroll-spy, gorgeous item cards
 * with floating ADD buttons, bottom-sheet item detail, refined floating cart.
 */
if (!isset($tenant)) { exit('Template must be rendered through /r/'); }
$primary   = $tenant['primary_color'] ?: '#e63946';
$secondary = $tenant['secondary_color'] ?: '#1d3557';
$accent    = $tenant['accent_color'] ?: '#f1a208';
$isOpen    = isRestaurantOpen($tenant);
$canOrder  = $orderingMode === 'direct';
$menuUrl   = publicMenuUrl($tenant['slug']);
$logoUrl   = $tenant['logo'] ? mediaUrl($tenant['logo']) : '';

/** Localised item name with EN fallback. */
$L = function(array $row, string $field) use ($lang) {
    $gu = $field . '_gu';
    return ($lang === 'gu' && !empty($row[$gu])) ? $row[$gu] : ($row[$field] ?? '');
};
// Count total items (for a subtle hero stat)
$itemCount = 0; foreach ($categories as $c) { $itemCount += count($c['items']); }
?>
<!doctype html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= e($primary) ?>">
<title><?= e($tenant['restaurant_name']) ?> — Menu</title>
<meta name="description" content="<?= e($tenant['restaurant_name']) ?> digital menu. <?= e($tenant['address']) ?>">
<!-- Open Graph -->
<meta property="og:type" content="restaurant.menu">
<meta property="og:title" content="<?= e($tenant['restaurant_name']) ?> — Menu">
<meta property="og:url" content="<?= e($menuUrl) ?>">
<?php if ($logoUrl): ?><meta property="og:image" content="<?= e($logoUrl) ?>"><?php endif; ?>
<link rel="manifest" href="<?= e(BASE_URL) ?>/r/manifest.php?slug=<?= e($tenant['slug']) ?>">
<link rel="icon" href="<?= e($logoUrl ?: BASE_URL.'/assets/img/favicon.png') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Noto+Sans+Gujarati:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/assets/css/app.css" rel="stylesheet">
<!-- JSON-LD Restaurant schema -->
<script type="application/ld+json">
<?= json_encode([
  '@context' => 'https://schema.org', '@type' => 'Restaurant',
  'name' => $tenant['restaurant_name'], 'url' => $menuUrl,
  'image' => $logoUrl, 'address' => $tenant['address'],
  'telephone' => $tenant['mobile'],
  'servesCuisine' => 'Indian',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<style>
:root{
  --primary:<?= e($primary) ?>;--secondary:<?= e($secondary) ?>;--accent:<?= e($accent) ?>;
  --ink:#141824;--muted:#7b8398;--line:#eef0f5;--bg:#f6f7fb;--card:#ffffff;
  --shadow:0 6px 22px rgba(20,24,36,.07);--shadow-sm:0 2px 10px rgba(20,24,36,.05);
  --radius:20px;
}
*{-webkit-tap-highlight-color:transparent;}
body{font-family:<?= $lang==='gu'?"'Noto Sans Gujarati',":'' ?>'Poppins',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);padding-bottom:96px;overflow-x:hidden;}
.wrap{max-width:480px;margin:0 auto;position:relative;}
img{max-width:100%;}

/* ===== HERO ===== */
.hero{position:relative;padding:calc(env(safe-area-inset-top) + 20px) 18px 62px;color:#fff;overflow:hidden;
  background:linear-gradient(150deg,var(--primary) 0%,color-mix(in srgb,var(--secondary) 78%,var(--primary)) 100%);}
.hero::before{content:"";position:absolute;inset:0;
  background:radial-gradient(120% 90% at 85% -10%,rgba(255,255,255,.22),transparent 55%),
             radial-gradient(90% 70% at -10% 110%,rgba(0,0,0,.20),transparent 60%);pointer-events:none;}
.hero-top{position:relative;display:flex;justify-content:space-between;align-items:flex-start;z-index:2;}
.brand{display:flex;gap:14px;align-items:center;}
.brand .logo{width:66px;height:66px;border-radius:18px;object-fit:cover;background:#fff;
  box-shadow:0 6px 18px rgba(0,0,0,.22);border:2px solid rgba(255,255,255,.55);}
.brand h1{font-size:1.42rem;font-weight:700;margin:0 0 3px;letter-spacing:-.2px;line-height:1.15;}
.brand .loc{font-size:.8rem;opacity:.9;display:flex;align-items:center;gap:5px;}
.pill{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:600;padding:4px 11px;border-radius:30px;backdrop-filter:blur(6px);}
.pill.open{background:rgba(46,196,120,.22);color:#c8ffe0;box-shadow:inset 0 0 0 1px rgba(120,255,180,.35);}
.pill.closed{background:rgba(255,90,90,.22);color:#ffd6d6;box-shadow:inset 0 0 0 1px rgba(255,150,150,.35);}
.pill .dot{width:7px;height:7px;border-radius:50%;background:currentColor;box-shadow:0 0 0 3px rgba(255,255,255,.15);}
.hero-meta{position:relative;z-index:2;display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;}
.chip-i{font-size:.72rem;font-weight:500;background:rgba(255,255,255,.16);padding:5px 11px;border-radius:30px;display:inline-flex;align-items:center;gap:6px;}
.langbtn{background:rgba(255,255,255,.18)!important;color:#fff!important;border:0!important;border-radius:30px!important;font-weight:600;font-size:.78rem;padding:6px 12px;backdrop-filter:blur(6px);}
.act-row{position:relative;z-index:2;display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:9px;margin-top:16px;}
.act{background:rgba(255,255,255,.15);color:#fff;border-radius:14px;padding:9px 4px;font-size:.72rem;font-weight:500;
  text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:3px;backdrop-filter:blur(6px);
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.14);transition:transform .12s,background .2s;}
.act i{font-size:1.05rem;}
.act:active{transform:scale(.94);background:rgba(255,255,255,.26);}

/* ===== SEARCH (overlaps hero) ===== */
.searchwrap{position:relative;z-index:3;margin:-46px 16px 0;}
.searchbox{display:flex;align-items:center;gap:10px;background:var(--card);border-radius:16px;padding:12px 16px;box-shadow:var(--shadow);}
.searchbox i{color:var(--primary);font-size:1.1rem;}
.searchbox input{border:0;outline:0;width:100%;font-size:.92rem;background:transparent;color:var(--ink);font-family:inherit;}
.filters{display:flex;gap:8px;overflow-x:auto;padding:14px 16px 4px;scrollbar-width:none;}
.filters::-webkit-scrollbar{display:none;}
.filterchip{flex-shrink:0;font-size:.78rem;font-weight:500;padding:7px 14px;border-radius:30px;border:1px solid var(--line);
  background:var(--card);color:var(--ink);cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.15s;box-shadow:var(--shadow-sm);}
.filterchip:active{transform:scale(.95);}
.filterchip.on{background:var(--ink);color:#fff;border-color:var(--ink);}
.filterchip .g{color:#22a45d;}.filterchip .r{color:#e23744;}.filterchip .y{color:var(--accent);}
.filterchip.on .g,.filterchip.on .r,.filterchip.on .y{color:#fff;}

/* ===== CATEGORY NAV ===== */
.catnav{position:sticky;top:0;z-index:30;background:rgba(246,247,251,.92);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--line);overflow-x:auto;white-space:nowrap;padding:11px 12px;scrollbar-width:none;}
.catnav::-webkit-scrollbar{display:none;}
.catnav a{display:inline-block;padding:8px 16px;margin-right:8px;border-radius:30px;background:var(--card);color:var(--muted);
  text-decoration:none;font-size:.84rem;font-weight:600;box-shadow:var(--shadow-sm);transition:.2s;}
.catnav a.active{background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--secondary) 60%,var(--primary)));color:#fff;box-shadow:0 4px 14px color-mix(in srgb,var(--primary) 45%,transparent);}

/* ===== SECTION + CARDS ===== */
main{padding:6px 16px 10px;}
.cat-head{display:flex;align-items:baseline;gap:9px;margin:24px 2px 12px;}
.cat-head h2{font-size:1.22rem;font-weight:700;margin:0;letter-spacing:-.3px;}
.cat-head .cnt{font-size:.72rem;font-weight:600;color:var(--muted);background:var(--card);padding:2px 9px;border-radius:20px;box-shadow:var(--shadow-sm);}
.cat-head .rule{flex:1;height:1px;background:var(--line);}

.item{background:var(--card);border-radius:var(--radius);padding:14px;margin-bottom:14px;display:flex;gap:14px;
  box-shadow:var(--shadow-sm);opacity:0;transform:translateY(14px);transition:opacity .45s ease,transform .45s ease,box-shadow .2s;}
.item.in{opacity:1;transform:none;}
.item:active{box-shadow:var(--shadow);}
.item .info{flex:1;min-width:0;display:flex;flex-direction:column;}
.item .toprow{display:flex;align-items:center;gap:7px;margin-bottom:5px;flex-wrap:wrap;}
.item .iname{font-weight:600;font-size:1rem;line-height:1.25;letter-spacing:-.2px;}
.item .idesc{font-size:.82rem;color:var(--muted);line-height:1.4;margin:1px 0 8px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.priceline{display:flex;align-items:baseline;gap:8px;margin-top:auto;flex-wrap:wrap;}
.iprice{font-weight:700;font-size:1.02rem;color:var(--ink);}
.iprice .cur{font-size:.86em;}
.strike{text-decoration:line-through;color:#b6bccb;font-weight:500;font-size:.85rem;}
.onwards{font-size:.7rem;color:var(--muted);font-weight:500;}
.off{font-size:.68rem;font-weight:700;color:#22a45d;background:#e7f7ee;padding:2px 7px;border-radius:6px;}

.badge-soft{font-size:.62rem;padding:3px 8px;border-radius:20px;font-weight:600;display:inline-flex;align-items:center;gap:4px;}
.b-best{background:linear-gradient(135deg,#fff4d6,#ffe6a8);color:#8a5b00;}
.b-new{background:#e3f0ff;color:#0a5bd3;}

/* media column with floating ADD */
.media{position:relative;width:112px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;}
.media img.thumb{width:112px;height:104px;border-radius:16px;object-fit:cover;box-shadow:var(--shadow-sm);}
.noimg{width:112px;height:104px;border-radius:16px;background:linear-gradient(135deg,#f3f4f9,#e9ebf3);
  display:flex;align-items:center;justify-content:center;color:#c2c7d6;font-size:1.7rem;}
.addbtn{position:absolute;bottom:-13px;left:50%;transform:translateX(-50%);background:var(--card);color:var(--primary);
  border:1px solid var(--line);border-radius:12px;padding:7px 20px;font-size:.82rem;font-weight:700;letter-spacing:.4px;
  box-shadow:0 6px 16px rgba(20,24,36,.14);text-transform:uppercase;transition:.12s;white-space:nowrap;}
.addbtn:active{transform:translateX(-50%) scale(.92);}
.addbtn .pl{color:var(--accent);margin-left:2px;font-weight:800;}
.media.noimgcol{width:auto;justify-content:flex-end;}
.addbtn.inline{position:static;transform:none;align-self:flex-start;}
.addbtn.inline:active{transform:scale(.94);}
.soldout{font-size:.72rem;font-weight:600;color:#9aa0b2;background:#f0f1f5;border-radius:10px;padding:6px 12px;margin-top:8px;}

.empty-state{text-align:center;color:var(--muted);padding:70px 20px;}
.empty-state i{font-size:2.6rem;opacity:.4;}

/* veg marker refinement */
.veg-marker{width:15px;height:15px;flex-shrink:0;}

/* ===== FLOATING CART ===== */
.cartbar{position:fixed;left:0;right:0;bottom:calc(env(safe-area-inset-bottom) + 14px);width:calc(100% - 32px);max-width:448px;
  margin:0 auto;background:linear-gradient(135deg,var(--primary),color-mix(in srgb,var(--secondary) 55%,var(--primary)));
  color:#fff;padding:13px 18px;display:flex;justify-content:space-between;align-items:center;z-index:50;cursor:pointer;
  border-radius:18px;box-shadow:0 12px 30px color-mix(in srgb,var(--primary) 40%,transparent);
  transform:translateY(180%);transition:transform .32s cubic-bezier(.2,.9,.3,1.2);}
.cartbar.show{transform:none;}
.cartbar .cl{display:flex;align-items:center;gap:10px;font-weight:600;font-size:.9rem;}
.cartbar .cc{background:rgba(255,255,255,.24);width:26px;height:26px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;}
.cartbar .cr{display:flex;align-items:center;gap:8px;font-weight:700;font-size:.92rem;}

/* ===== BOTTOM SHEET MODAL ===== */
.aksheet .modal-dialog{position:fixed;left:0;right:0;bottom:0;margin:0;max-width:480px;width:100%;}
@media(min-width:480px){.aksheet .modal-dialog{left:50%;transform:translateX(-50%);}}
.aksheet .modal-content{border:0;border-radius:24px 24px 0 0;box-shadow:0 -10px 40px rgba(0,0,0,.22);overflow:hidden;}
.aksheet .modal-content::before{content:"";position:absolute;top:9px;left:50%;transform:translateX(-50%);width:42px;height:5px;border-radius:10px;background:#dfe2ec;z-index:5;}
.aksheet .modal-header{border:0;padding:26px 20px 6px;}
.aksheet .modal-title{font-weight:700;font-size:1.12rem;}
.aksheet .modal-body{padding:6px 20px 12px;}
.aksheet .modal-footer{border:0;padding:12px 20px calc(env(safe-area-inset-bottom) + 16px);}
.aksheet .modal-footer .btn{width:100%;border-radius:14px;padding:12px;font-weight:700;font-size:.98rem;background:var(--primary);border:0;}
.sheet-sec{margin-bottom:14px;}
.sheet-sec .lbl{font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:8px;display:block;}
.opt{display:flex;align-items:center;gap:10px;padding:11px 13px;border:1px solid var(--line);border-radius:13px;margin-bottom:8px;cursor:pointer;transition:.15s;}
.opt:has(input:checked){border-color:var(--primary);background:color-mix(in srgb,var(--primary) 6%,#fff);}
.opt .form-check-input{margin:0;flex-shrink:0;}
.opt .oname{flex:1;font-size:.9rem;font-weight:500;}
.opt .oprice{font-weight:600;font-size:.86rem;color:var(--primary);}
.qtyrow{display:flex;align-items:center;justify-content:space-between;background:#f6f7fb;border-radius:13px;padding:8px 12px;}
.qtystep{display:flex;align-items:center;gap:0;background:#fff;border-radius:11px;box-shadow:var(--shadow-sm);}
.qtystep button{width:36px;height:36px;border:0;background:transparent;font-size:1.2rem;font-weight:700;color:var(--primary);}
.qtystep span{min-width:34px;text-align:center;font-weight:700;}
.fld{width:100%;border:1px solid var(--line);border-radius:13px;padding:12px 14px;font-size:.92rem;outline:0;font-family:inherit;margin-bottom:10px;background:#fbfbfd;}
.fld:focus{border-color:var(--primary);}
.cart-line{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:12px 0;border-bottom:1px solid var(--line);}
.cart-line .cn{font-weight:600;font-size:.92rem;}
.cart-line .csub{font-size:.78rem;color:var(--muted);}
.cart-line .cx{border:0;background:#f6f7fb;color:#e23744;width:28px;height:28px;border-radius:9px;font-size:1rem;line-height:1;}
.totrow{display:flex;justify-content:space-between;font-weight:700;font-size:1.05rem;padding:14px 0 4px;}
.stars-wrap{font-size:2.3rem;letter-spacing:6px;color:var(--accent);}
.stars-wrap .star{cursor:pointer;transition:transform .12s;}
.stars-wrap .star:active{transform:scale(1.2);}

footer.brand-foot{text-align:center;color:var(--muted);font-size:.76rem;padding:26px 0 30px;}
footer.brand-foot b{color:var(--ink);font-weight:600;}
</style>
</head>
<body>
<div class="wrap">

<!-- ===== HERO / HEADER ===== -->
<header class="hero">
  <div class="hero-top">
    <div class="brand">
      <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" class="logo" alt="logo"><?php endif; ?>
      <div>
        <h1><?= e($tenant['restaurant_name']) ?></h1>
        <?php if ($tenant['city'] || $tenant['address']): ?>
          <div class="loc"><i class="bi bi-geo-alt-fill"></i> <?= e($tenant['city'] ?: $tenant['address']) ?></div>
        <?php endif; ?>
        <div class="mt-2">
          <span class="pill <?= $isOpen?'open':'closed' ?>"><span class="dot"></span><?= $isOpen ? __('open_now') : __('closed_now') ?></span>
        </div>
      </div>
    </div>
    <div class="dropdown">
      <button class="btn langbtn dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-translate"></i> <?= $lang==='gu'?'ગુ':'EN' ?></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="?lang=en<?= $tableToken?'&table='.e($tableToken):'' ?>">English</a></li>
        <li><a class="dropdown-item" href="?lang=gu<?= $tableToken?'&table='.e($tableToken):'' ?>">ગુજરાતી</a></li>
      </ul>
    </div>
  </div>
  <div class="hero-meta">
    <?php if ($tableNo): ?><span class="chip-i"><i class="bi bi-grid-3x3-gap-fill"></i> Table <?= e($tableNo) ?></span><?php endif; ?>
    <?php if ($itemCount): ?><span class="chip-i"><i class="bi bi-egg-fried"></i> <?= (int)$itemCount ?> <?= __('items') ?></span><?php endif; ?>
  </div>
  <?php if ($tenant['mobile'] || $tenant['whatsapp_no'] || $tenant['maps_url'] || $tenant['google_review_url']): ?>
  <div class="act-row">
    <?php if ($tenant['mobile']): ?><a class="act" href="tel:<?= e($tenant['mobile']) ?>"><i class="bi bi-telephone-fill"></i><?= __('call') ?></a><?php endif; ?>
    <?php if ($tenant['whatsapp_no']): ?><a class="act" href="https://wa.me/91<?= e($tenant['whatsapp_no']) ?>"><i class="bi bi-whatsapp"></i><?= __('whatsapp') ?></a><?php endif; ?>
    <?php if ($tenant['maps_url']): ?><a class="act" href="<?= e($tenant['maps_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i><?= __('directions') ?></a><?php endif; ?>
    <?php if ($tenant['google_review_url']): ?><a class="act" href="<?= e($tenant['google_review_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-star-fill"></i>Review</a><?php endif; ?>
  </div>
  <?php endif; ?>
</header>

<!-- ===== SEARCH + FILTERS ===== -->
<div class="searchwrap">
  <div class="searchbox">
    <i class="bi bi-search"></i>
    <input id="search" placeholder="<?= __('search') ?>...">
  </div>
</div>
<div class="filters">
  <span class="filterchip" data-filter="veg"><i class="bi bi-circle-fill g"></i> <?= __('veg') ?></span>
  <span class="filterchip" data-filter="nonveg"><i class="bi bi-circle-fill r"></i> <?= __('non_veg') ?></span>
  <span class="filterchip" data-filter="best"><i class="bi bi-star-fill y"></i> <?= __('bestseller') ?></span>
</div>

<!-- ===== CATEGORY NAV ===== -->
<nav class="catnav" id="catnav">
  <?php foreach ($categories as $i => $c): ?>
    <a href="#cat<?= (int)$c['id'] ?>" class="<?= $i===0?'active':'' ?>"><?= e($L($c,'name')) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ===== MENU ===== -->
<main>
  <?php if (empty($categories)): ?>
    <div class="empty-state"><i class="bi bi-journal-x"></i><p class="mt-2">Menu coming soon.</p></div>
  <?php endif; ?>
  <?php foreach ($categories as $c): ?>
    <section id="cat<?= (int)$c['id'] ?>" class="cat-section">
      <div class="cat-head">
        <h2><?= e($L($c,'name')) ?></h2>
        <span class="cnt"><?= count($c['items']) ?></span>
        <span class="rule"></span>
      </div>
      <?php foreach ($c['items'] as $it):
        $price = $it['discount_price'] > 0 ? $it['discount_price'] : $it['price'];
        $hasVar = !empty($it['variants']);
        $hasImg = $tenant['show_images'] && $it['image'];
        $off = ($it['discount_price'] > 0 && $it['price'] > 0) ? (int)round(100 - ($it['discount_price']/$it['price']*100)) : 0; ?>
        <div class="item" data-name="<?= e(strtolower($L($it,'name'))) ?>"
             data-veg="<?= (int)$it['is_veg'] ?>" data-best="<?= (int)$it['is_bestseller'] ?>">
          <div class="info">
            <div class="toprow">
              <?php if ($tenant['show_veg_marker']): ?>
                <span class="veg-marker <?= $it['is_veg']?'':'nonveg' ?>"></span>
              <?php endif; ?>
              <?php if ($it['is_bestseller']): ?><span class="badge-soft b-best"><i class="bi bi-star-fill"></i> <?= __('bestseller') ?></span><?php endif; ?>
              <?php if ($it['is_new']): ?><span class="badge-soft b-new"><?= __('new') ?></span><?php endif; ?>
            </div>
            <div class="iname"><?= e($L($it,'name')) ?></div>
            <?php if ($tenant['show_descriptions'] && $L($it,'description')): ?>
              <div class="idesc"><?= e($L($it,'description')) ?></div>
            <?php endif; ?>
            <?php if ($tenant['show_prices']): ?>
              <div class="priceline">
                <span class="iprice"><span class="cur"><?= e($currency) ?></span><?= number_format($price,0) ?></span>
                <?php if ($it['discount_price'] > 0): ?><span class="strike"><?= e($currency).number_format($it['price'],0) ?></span><?php endif; ?>
                <?php if ($off > 0): ?><span class="off"><?= $off ?>% OFF</span><?php endif; ?>
                <?php if ($hasVar): ?><span class="onwards">onwards</span><?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if ($canOrder && !$hasImg): ?>
              <?php if ($it['is_available']): ?>
                <button class="addbtn inline mt-2" onclick='openItem(<?= json_encode([
                  "id"=>(int)$it["id"],"name"=>$L($it,"name"),"price"=>(float)$price,
                  "veg"=>(int)$it["is_veg"],"variants"=>array_map(fn($v)=>["label"=>$v["label"],"price"=>(float)$v["price"]],$it["variants"]),
                  "addons"=>array_map(fn($a)=>["id"=>$a["id"],"name"=>$a["name"],"price"=>(float)$a["price"]],$it["addons"]),
                ], JSON_UNESCAPED_UNICODE) ?>)'><?= __('add') ?> <span class="pl">+</span></button>
              <?php else: ?><span class="soldout">Sold out</span><?php endif; ?>
            <?php endif; ?>
          </div>
          <?php if ($hasImg || ($canOrder && $it['is_available'])): ?>
          <div class="media <?= $hasImg?'':'noimgcol' ?>">
            <?php if ($hasImg): ?>
              <img src="<?= e(mediaUrl($it['image'])) ?>" class="thumb" loading="lazy" alt="<?= e($L($it,'name')) ?>">
            <?php endif; ?>
            <?php if ($canOrder && $hasImg): ?>
              <?php if ($it['is_available']): ?>
                <button class="addbtn" onclick='openItem(<?= json_encode([
                  "id"=>(int)$it["id"],"name"=>$L($it,"name"),"price"=>(float)$price,
                  "veg"=>(int)$it["is_veg"],"variants"=>array_map(fn($v)=>["label"=>$v["label"],"price"=>(float)$v["price"]],$it["variants"]),
                  "addons"=>array_map(fn($a)=>["id"=>$a["id"],"name"=>$a["name"],"price"=>(float)$a["price"]],$it["addons"]),
                ], JSON_UNESCAPED_UNICODE) ?>)'><?= __('add') ?> <span class="pl">+</span></button>
              <?php else: ?><span class="soldout">Sold out</span><?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</main>

<?php if ($showBranding): ?>
  <footer class="brand-foot"><?= e(POWERED_BY) ?></footer>
<?php endif; ?>

<?php if ($canOrder): ?>
<!-- ===== FLOATING CART ===== -->
<div class="cartbar" id="cartbar" onclick="openCart()">
  <span class="cl"><span class="cc" id="cartCount">0</span> <?= __('items') ?></span>
  <span class="cr"><span id="cartTotal"><?= e($currency) ?>0</span> · <?= __('place_order') ?> <i class="bi bi-arrow-right-circle-fill"></i></span>
</div>
<?php endif; ?>

</div><!-- /wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---- Fade-in on scroll ----
const io=new IntersectionObserver((es)=>{es.forEach(x=>{if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target);}});},{threshold:.08});
document.querySelectorAll('.item').forEach(el=>io.observe(el));

// ---- Category scroll spy + smooth scroll ----
const cats=[...document.querySelectorAll('.cat-section')];
const navlinks=[...document.querySelectorAll('#catnav a')];
navlinks.forEach(a=>a.addEventListener('click',e=>{e.preventDefault();
  const t=document.querySelector(a.getAttribute('href'));
  const y=t.getBoundingClientRect().top+window.scrollY-70;
  window.scrollTo({top:y,behavior:'smooth'});}));
window.addEventListener('scroll',()=>{let cur=cats[0]?.id;
  for(const s of cats){if(s.getBoundingClientRect().top<130)cur=s.id;}
  navlinks.forEach(a=>{const on=a.getAttribute('href')==='#'+cur;a.classList.toggle('active',on);
    if(on){a.scrollIntoView({behavior:'smooth',inline:'center',block:'nearest'});}});},{passive:true});

// ---- Live search ----
document.getElementById('search').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('.item').forEach(it=>{it.style.display=it.dataset.name.includes(q)?'':'none';});
  syncSections();});
// ---- Filter chips ----
let filters={veg:false,nonveg:false,best:false};
document.querySelectorAll('.filterchip').forEach(c=>c.addEventListener('click',()=>{
  c.classList.toggle('on');filters[c.dataset.filter]=c.classList.contains('on');applyFilters();}));
function applyFilters(){document.querySelectorAll('.item').forEach(it=>{
  let ok=true;
  if(filters.veg&&it.dataset.veg!=='1')ok=false;
  if(filters.nonveg&&it.dataset.veg!=='0')ok=false;
  if(filters.best&&it.dataset.best!=='1')ok=false;
  it.style.display=ok?'':'none';});syncSections();}
// hide a category header if all its items are filtered out
function syncSections(){document.querySelectorAll('.cat-section').forEach(s=>{
  const any=[...s.querySelectorAll('.item')].some(i=>i.style.display!=='none');
  s.style.display=any?'':'none';});}

<?php if ($canOrder): ?>
// ---- Cart (in-memory) ----
const CURRENCY='<?= e($currency) ?>';
const TABLE_TOKEN='<?= e($tableToken ?? '') ?>';
const SLUG='<?= e($tenant['slug']) ?>';
let cart=[];
function money(n){return CURRENCY+Number(n).toFixed(0);}
function openItem(it){
  let vhtml='';
  if(it.variants&&it.variants.length){vhtml='<div class="sheet-sec"><span class="lbl">Choose one</span>'+
    it.variants.map((v,i)=>`<label class="opt"><input class="form-check-input" type="radio" name="variant" value="${i}" ${i===0?'checked':''}><span class="oname">${v.label}</span><span class="oprice">${money(v.price)}</span></label>`).join('')+'</div>';}
  let ahtml='';
  if(it.addons&&it.addons.length){ahtml='<div class="sheet-sec"><span class="lbl">Add-ons</span>'+
    it.addons.map((a,i)=>`<label class="opt"><input class="form-check-input addon-chk" type="checkbox" value="${i}"><span class="oname">${a.name}</span><span class="oprice">+${money(a.price)}</span></label>`).join('')+'</div>';}
  const html=`${vhtml}${ahtml}
    <div class="sheet-sec"><span class="lbl">Special instructions</span>
      <input id="itemNotes" class="fld" placeholder="e.g. less spicy, no onion"></div>
    <div class="sheet-sec"><span class="lbl">Quantity</span>
      <div class="qtyrow"><span style="font-weight:600">How many?</span>
        <div class="qtystep"><button type="button" onclick="qStep(-1)">−</button><span id="itemQty">1</span><button type="button" onclick="qStep(1)">+</button></div>
      </div></div>`;
  showModal(it.name,html,()=>{
    let variant=null,price=it.price;
    const vr=document.querySelector('input[name=variant]:checked');
    if(vr){variant=it.variants[vr.value];price=variant.price;}
    let addons=[];document.querySelectorAll('.addon-chk:checked').forEach(c=>{const a=it.addons[c.value];addons.push(a);price+=a.price;});
    const qty=Math.max(1,parseInt(document.getElementById('itemQty').textContent)||1);
    cart.push({id:it.id,name:it.name,variant:variant?variant.label:null,addons:addons,qty,price,notes:document.getElementById('itemNotes').value});
    renderCart();bsModal.hide();
  },'<?= __('add_to_cart') ?>');
}
window.qStep=function(d){const el=document.getElementById('itemQty');el.textContent=Math.max(1,(parseInt(el.textContent)||1)+d);};
function renderCart(){
  const count=cart.reduce((s,c)=>s+c.qty,0);
  const total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  document.getElementById('cartCount').textContent=count;
  document.getElementById('cartTotal').textContent=money(total);
  document.getElementById('cartbar').classList.toggle('show',count>0);
}
function openCart(){
  if(!cart.length)return;
  const rows=cart.map((c,i)=>`<div class="cart-line">
    <div><div class="cn">${c.name}</div><div class="csub">${c.variant?c.variant+' · ':''}${c.qty} × ${money(c.price)}</div></div>
    <div class="d-flex align-items-center gap-2"><b>${money(c.qty*c.price)}</b><button class="cx" onclick="cart.splice(${i},1);renderCart();bsModal.hide();openCart();">×</button></div></div>`).join('');
  const total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  const form=`${rows}<div class="totrow"><span>Total</span><span>${money(total)}</span></div>
    <div class="mt-3">
    <input id="custName" class="fld" placeholder="<?= __('your_name') ?>">
    <input id="custMobile" class="fld" placeholder="Mobile number" inputmode="numeric">
    <select id="orderType" class="fld">
      <option value="dinein">Dine-in</option><option value="takeaway">Takeaway</option><option value="delivery">Delivery</option></select></div>`;
  showModal('<?= __('cart') ?>',form,placeOrder,'<?= __('place_order') ?>');
}
function placeOrder(){
  const name=document.getElementById('custName').value.trim();
  if(!name){document.getElementById('custName').focus();document.getElementById('custName').style.borderColor='#e23744';return;}
  const payload={slug:SLUG,table_token:TABLE_TOKEN,customer_name:name,
    customer_mobile:document.getElementById('custMobile').value,order_type:document.getElementById('orderType').value,
    items:cart};
  const okb=document.getElementById('modalOk');okb.disabled=true;okb.textContent='Placing…';
  fetch('<?= e(BASE_URL) ?>/api/order.php?action=place',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success'){bsModal.hide();cart=[];renderCart();
        showFeedback(d.data.order_id);}
      else{alert(d.message||'Order failed');okb.disabled=false;okb.textContent='<?= __('place_order') ?>';}})
    .catch(()=>{alert('Network error');okb.disabled=false;okb.textContent='<?= __('place_order') ?>';});
}
function showFeedback(orderId){
  const html=`<div class="text-center py-2">
    <div style="font-size:3rem;line-height:1">🎉</div>
    <h5 class="fw-bold mt-2"><?= __('order_placed') ?></h5>
    <p class="text-muted mb-3"><?= __('rate_experience') ?></p>
    <div class="stars-wrap" id="stars">${[1,2,3,4,5].map(s=>`<i class="bi bi-star star" data-s="${s}" onclick="rate(${s},${orderId})"></i>`).join('')}</div></div>`;
  showModal('Thank you!',html,null);
}
window.rate=function(stars,orderId){
  document.querySelectorAll('.star').forEach((el,i)=>el.className='bi '+(i<stars?'bi-star-fill':'bi-star')+' star');
  const rev='<?= e($tenant['google_review_url']) ?>';
  fetch('<?= e(BASE_URL) ?>/api/order.php?action=feedback',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({order_id:orderId,slug:SLUG,rating:stars})}).then(()=>{});
  if(stars>=4&&rev){setTimeout(()=>location.href=rev,700);}  // smart routing: happy → Google review
  else{setTimeout(()=>{bsModal.hide();alert('Thank you for your feedback!');},700);}
}
<?php endif; ?>

// ---- Bottom-sheet modal helper ----
let bsModal;
function showModal(title,body,onOk,okText){
  let el=document.getElementById('akModal');
  if(!el){el=document.createElement('div');el.id='akModal';el.className='modal fade aksheet';
    el.innerHTML=`<div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header"><h6 class="modal-title"></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"></div>
      <div class="modal-footer"><button class="btn btn-primary" id="modalOk"></button></div></div></div>`;
    document.body.appendChild(el);bsModal=new bootstrap.Modal(el);}
  el.querySelector('.modal-title').textContent=title;
  el.querySelector('.modal-body').innerHTML=body;
  const ok=el.querySelector('#modalOk');
  ok.disabled=false;
  ok.textContent=okText||'<?= __('add_to_cart') ?>';
  ok.parentElement.style.display=onOk?'':'none';
  ok.onclick=onOk||null;
  bsModal.show();
}

// ---- PWA service worker ----
if('serviceWorker' in navigator){navigator.serviceWorker.register('<?= e(BASE_URL) ?>/r/sw.js').catch(()=>{});}
</script>
</body>
</html>
