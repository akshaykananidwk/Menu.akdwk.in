<?php
/**
 * Digital TV Menu Board — a full-screen, auto-scrolling menu for a screen/TV
 * inside the restaurant. Read-only, no ordering. /r/tv.php?slug={slug}
 * Refreshes itself periodically so edits appear without touching the TV.
 */
require_once dirname(__DIR__) . '/config/config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$tenant = $slug !== '' ? db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]) : null;
if (!$tenant) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;text-align:center;padding:60px"><h1>Menu not found</h1></div>';
    exit;
}

$lang     = in_array(($_GET['lang'] ?? ''), ['en', 'gu'], true) ? $_GET['lang'] : ($tenant['language'] ?: 'en');
$menu     = getTenantMenu((int)$tenant['id'], true);
$cats     = $menu['categories'];
$currency = $tenant['currency'] ?: getSetting('currency', '₹');
$primary  = $tenant['primary_color'] ?: '#e63946';
$secondary= $tenant['secondary_color'] ?: '#1d3557';
$L = fn($row, $f) => ($lang === 'gu' && !empty($row[$f . '_gu'])) ? $row[$f . '_gu'] : ($row[$f] ?? '');
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($tenant['restaurant_name']) ?> — Menu</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{--p:<?= e($primary) ?>;--s:<?= e($secondary) ?>;}
  *{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%;overflow:hidden;background:#0e1116;color:#f4f6fb;font-family:'Poppins',system-ui,sans-serif}
  .tvhead{display:flex;align-items:center;gap:18px;padding:22px 40px;background:linear-gradient(120deg,var(--s),var(--p));position:sticky;top:0;z-index:5}
  .tvhead img{height:64px;border-radius:12px;background:#fff;padding:4px}
  .tvhead h1{font-size:2.2rem;font-weight:800;letter-spacing:.5px}
  .tvhead .sub{opacity:.9;font-weight:500}
  .tvscroll{height:calc(100vh - 112px);overflow:hidden;position:relative}
  .tvcols{columns:3;column-gap:34px;padding:28px 40px}
  @media (max-width:1200px){.tvcols{columns:2}}
  @media (max-width:800px){.tvcols{columns:1}}
  .cat{break-inside:avoid;margin-bottom:26px}
  .cat h2{font-size:1.5rem;font-weight:700;color:var(--p);border-bottom:3px solid rgba(255,255,255,.12);padding-bottom:8px;margin-bottom:12px;display:flex;justify-content:space-between}
  .row{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:8px 0;border-bottom:1px dashed rgba(255,255,255,.07)}
  .nm{font-size:1.18rem;font-weight:600}
  .nm .veg{display:inline-block;width:12px;height:12px;border:2px solid #22c55e;border-radius:3px;position:relative;margin-right:8px;vertical-align:middle}
  .nm .veg.nv{border-color:#ef4444}
  .nm .veg::after{content:'';position:absolute;inset:2px;border-radius:50%;background:#22c55e}
  .nm .veg.nv::after{background:#ef4444}
  .nm small{display:block;font-weight:400;opacity:.6;font-size:.82rem}
  .pr{font-size:1.18rem;font-weight:700;color:#ffd166;white-space:nowrap}
  .pr .st{opacity:.5;text-decoration:line-through;font-size:.8rem;margin-right:6px;color:#f4f6fb}
  .tvfoot{position:fixed;bottom:0;left:0;right:0;height:0}
  .badge{font-size:.7rem;background:var(--p);color:#fff;border-radius:20px;padding:2px 9px;margin-left:6px;vertical-align:middle}
</style>
</head>
<body>
<div class="tvhead">
  <?php if ($tenant['logo']): ?><img src="<?= e(BASE_URL . '/' . $tenant['logo']) ?>" alt=""><?php endif; ?>
  <div>
    <h1><?= e($tenant['restaurant_name']) ?></h1>
    <?php if ($tenant['city'] || $tenant['mobile']): ?>
      <div class="sub"><?= e(trim(($tenant['city'] ?? '') . ($tenant['mobile'] ? ' · ' . $tenant['mobile'] : ''), ' ·')) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="tvscroll" id="scroll">
  <div class="tvcols" id="cols">
    <?php foreach ($cats as $c): if (empty($c['items'])) continue; ?>
      <div class="cat">
        <h2><span><?= e($L($c, 'name')) ?></span></h2>
        <?php foreach ($c['items'] as $it):
          if (empty($it['is_available'])) continue;
          $price = ($it['discount_price'] > 0 ? $it['discount_price'] : $it['price']); ?>
          <div class="row">
            <div class="nm">
              <?php if ($tenant['show_veg_marker']): ?><span class="veg <?= $it['is_veg'] ? '' : 'nv' ?>"></span><?php endif; ?>
              <?= e($L($it, 'name')) ?>
              <?php if (!empty($it['is_bestseller'])): ?><span class="badge">★</span><?php endif; ?>
              <?php if ($tenant['show_descriptions'] && $L($it, 'description')): ?><small><?= e($L($it, 'description')) ?></small><?php endif; ?>
            </div>
            <?php if ($tenant['show_prices']): ?>
              <div class="pr"><?php if ($it['discount_price'] > 0): ?><span class="st"><?= e($currency) . number_format($it['price'], 0) ?></span><?php endif; ?><?= e($currency) . number_format($price, 0) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
// Gentle auto-scroll: drift down, pause at the bottom, jump back to top. Loops.
(function(){
  const sc = document.getElementById('scroll');
  let dir = 1, paused = 0;
  function step(){
    if(paused > 0){ paused--; return; }
    const max = sc.scrollHeight - sc.clientHeight;
    if(max <= 4) return;               // fits on screen, nothing to scroll
    sc.scrollTop += dir * 0.6;
    if(sc.scrollTop >= max){ paused = 140; dir = -1; }       // ~2.3s pause at bottom
    else if(sc.scrollTop <= 0){ paused = 140; dir = 1; }     // pause at top
  }
  setInterval(step, 16);
  // Reload periodically so menu edits appear on the TV automatically.
  setTimeout(()=>location.reload(), 5*60*1000);
})();
</script>
</body>
</html>
