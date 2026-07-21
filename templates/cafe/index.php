<?php
/**
 * MENU DESIGN TEMPLATE: "Cafe"
 * Design theme: Cozy coffee-house — warm brown/latte palette, rounded soft cards,
 * big friendly imagery, chalkboard-style category headers, playful & inviting.
 * Receives (from /r/index.php): $menu, $tenant, $categories, $orderingMode,
 * $tableToken, $tableNo, $currency, $lang, $showBranding, $folder.
 * Templates ONLY read the provided data — no DB queries here.
 */
if (!isset($tenant)) { exit('Template must be rendered through /r/'); }
$primary   = $tenant['primary_color'] ?: '#8d5b3f';
$secondary = $tenant['secondary_color'] ?: '#5a3825';
$accent    = $tenant['accent_color'] ?: '#d99a5b';
$isOpen    = isRestaurantOpen($tenant);
$canOrder  = $orderingMode === 'direct';
$menuUrl   = publicMenuUrl($tenant['slug']);
$logoUrl   = $tenant['logo'] ? BASE_URL . '/' . $tenant['logo'] : '';

/** Localised item name with EN fallback. */
$L = function(array $row, string $field) use ($lang) {
    $gu = $field . '_gu';
    return ($lang === 'gu' && !empty($row[$gu])) ? $row[$gu] : ($row[$field] ?? '');
};
?>
<!doctype html>
<html lang="<?= e($lang) ?>" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="theme-color" content="<?= e($primary) ?>">
<title><?= e($tenant['restaurant_name']) ?> — Menu</title>
<meta name="description" content="<?= e($tenant['restaurant_name']) ?> digital menu. <?= e($tenant['address']) ?>">
<meta property="og:type" content="restaurant.menu">
<meta property="og:title" content="<?= e($tenant['restaurant_name']) ?> — Menu">
<meta property="og:url" content="<?= e($menuUrl) ?>">
<?php if ($logoUrl): ?><meta property="og:image" content="<?= e($logoUrl) ?>"><?php endif; ?>
<link rel="manifest" href="<?= e(BASE_URL) ?>/r/manifest.php?slug=<?= e($tenant['slug']) ?>">
<link rel="icon" href="<?= e($logoUrl ?: BASE_URL.'/assets/img/favicon.png') ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Poppins:wght@300;400;500;600;700&family=Noto+Sans+Gujarati:wght@400;600&display=swap" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/assets/css/app.css" rel="stylesheet">
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
:root{--primary:<?= e($primary) ?>;--secondary:<?= e($secondary) ?>;--accent:<?= e($accent) ?>;}
body{font-family:<?= $lang==='gu'?"'Noto Sans Gujarati',":'' ?>'Poppins',system-ui,sans-serif;background:#f3e7d8;color:#4a3526;padding-bottom:90px;}
.script{font-family:'Pacifico',cursive;}
.hero{background:linear-gradient(160deg,var(--primary),var(--secondary));color:#fff5ec;padding:22px 16px 26px;border-radius:0 0 28px 28px;}
.hero .logo{width:66px;height:66px;border-radius:22px;object-fit:cover;border:3px solid rgba(255,255,255,.5);background:#fff;}
.hero h1{font-family:'Pacifico',cursive;font-size:1.6rem;margin:8px 0 4px;}
.status-badge{font-size:.72rem;padding:3px 12px;border-radius:20px;font-weight:600;}
.act-btns a{background:rgba(255,255,255,.2);color:#fff5ec;border-radius:16px;padding:9px 0;font-size:.78rem;text-decoration:none;display:block;text-align:center;}
.catnav{position:sticky;top:0;z-index:20;background:#f3e7d8;overflow-x:auto;white-space:nowrap;padding:10px 8px;}
.catnav a{display:inline-block;padding:7px 16px;margin-right:8px;border-radius:22px;background:#e3cfb8;color:var(--secondary);text-decoration:none;font-size:.86rem;font-weight:600;}
.catnav a.active{background:var(--primary);color:#fff;}
.chalk{background:#3a2a20;color:#fff;border-radius:16px;padding:14px 18px;margin:22px 4px 14px;box-shadow:inset 0 0 0 2px rgba(255,255,255,.15);}
.chalk .cat-title{font-family:'Pacifico',cursive;font-size:1.45rem;margin:0;color:#ffe9cf;}
.chalk .csub{font-size:.72rem;opacity:.7;letter-spacing:2px;text-transform:uppercase;}
.item{background:#fffaf3;border-radius:22px;padding:12px;margin-bottom:14px;display:flex;gap:14px;box-shadow:0 4px 14px rgba(90,56,37,.12);}
.item .info{flex:1;min-width:0;}
.item .iname{font-weight:600;font-size:1.02rem;color:var(--secondary);}
.item .idesc{font-size:.82rem;color:#8a7256;margin:3px 0;}
.item .iprice{font-weight:700;color:var(--primary);font-size:1.02rem;}
.item img.thumb{width:104px;height:104px;border-radius:18px;object-fit:cover;flex-shrink:0;}
.badge-soft{font-size:.62rem;padding:3px 9px;border-radius:12px;font-weight:600;}
.b-best{background:#ffe6b0;color:#8a5a00;}.b-new{background:#cfe9d4;color:#1f6b34;}
.addbtn{background:var(--accent);color:#4a3526;border:0;border-radius:16px;padding:7px 16px;font-size:.82rem;font-weight:700;}
.cartbar{position:fixed;bottom:0;left:0;right:0;background:var(--primary);color:#fff;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;z-index:40;cursor:pointer;transform:translateY(120%);transition:.25s;border-radius:22px 22px 0 0;}
.cartbar.show{transform:none;}
.filterchip{font-size:.76rem;padding:5px 14px;border-radius:20px;border:1px solid #d3b896;background:#fffaf3;cursor:pointer;color:var(--secondary);}
.filterchip.on{background:var(--primary);color:#fff;border-color:var(--primary);}
.strike{text-decoration:line-through;color:#b39a7d;font-weight:400;font-size:.82rem;}
#search{border:1px solid #d3b896;background:#fffaf3;border-radius:18px;}
</style>
</head>
<body>

<!-- ===== HERO / HEADER ===== -->
<header class="hero">
  <div class="d-flex justify-content-between align-items-start">
    <div class="d-flex gap-3 align-items-center">
      <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" class="logo" alt="logo"><?php endif; ?>
      <div>
        <h1><?= e($tenant['restaurant_name']) ?></h1>
        <div class="small opacity-75"><i class="bi bi-geo-alt"></i> <?= e($tenant['city'] ?: $tenant['address']) ?></div>
        <span class="status-badge <?= $isOpen?'bg-success':'bg-danger' ?> mt-1 d-inline-block">
          <?= $isOpen ? '☕ '.__('open_now') : '● '.__('closed_now') ?>
        </span>
      </div>
    </div>
    <div class="dropdown">
      <button class="btn btn-sm btn-light rounded-pill dropdown-toggle" data-bs-toggle="dropdown"><?= $lang==='gu'?'ગુ':'EN' ?></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="?lang=en<?= $tableToken?'&table='.e($tableToken):'' ?>">English</a></li>
        <li><a class="dropdown-item" href="?lang=gu<?= $tableToken?'&table='.e($tableToken):'' ?>">ગુજરાતી</a></li>
      </ul>
    </div>
  </div>
  <?php if ($tableNo): ?><div class="mt-2 small"><i class="bi bi-grid-3x3-gap"></i> Table <?= e($tableNo) ?></div><?php endif; ?>
  <div class="row g-2 mt-3 act-btns">
    <?php if ($tenant['mobile']): ?><div class="col"><a href="tel:<?= e($tenant['mobile']) ?>"><i class="bi bi-telephone"></i><br><?= __('call') ?></a></div><?php endif; ?>
    <?php if ($tenant['whatsapp_no']): ?><div class="col"><a href="<?= e(waMeUrl($tenant['whatsapp_no'])) ?>"><i class="bi bi-whatsapp"></i><br><?= __('whatsapp') ?></a></div><?php endif; ?>
    <?php if ($tenant['maps_url']): ?><div class="col"><a href="<?= e($tenant['maps_url']) ?>" target="_blank"><i class="bi bi-geo"></i><br><?= __('directions') ?></a></div><?php endif; ?>
    <?php if ($tenant['google_review_url']): ?><div class="col"><a href="<?= e($tenant['google_review_url']) ?>" target="_blank"><i class="bi bi-star"></i><br>Review</a></div><?php endif; ?>
  </div>
</header>

<!-- ===== SEARCH + FILTERS ===== -->
<div class="px-3 py-3">
  <input id="search" class="form-control form-control-sm mb-2" placeholder="<?= __('search') ?>...">
  <div class="d-flex gap-2 flex-wrap">
    <span class="filterchip" data-filter="veg"><i class="bi bi-circle-fill text-success"></i> <?= __('veg') ?></span>
    <span class="filterchip" data-filter="nonveg"><i class="bi bi-circle-fill text-danger"></i> <?= __('non_veg') ?></span>
    <span class="filterchip" data-filter="best"><i class="bi bi-star-fill text-warning"></i> <?= __('bestseller') ?></span>
  </div>
</div>

<!-- ===== CATEGORY NAV ===== -->
<nav class="catnav" id="catnav">
  <?php foreach ($categories as $i => $c): ?>
    <a href="#cat<?= (int)$c['id'] ?>" class="<?= $i===0?'active':'' ?>"><?= e($L($c,'name')) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ===== MENU ===== -->
<main class="px-3">
  <?php if (empty($categories)): ?>
    <div class="empty-state text-center py-5"><i class="bi bi-journal-x"></i><p>Menu coming soon.</p></div>
  <?php endif; ?>
  <?php foreach ($categories as $c): ?>
    <section id="cat<?= (int)$c['id'] ?>" class="cat-section">
      <div class="chalk">
        <div class="csub">— Freshly served —</div>
        <div class="cat-title"><?= e($L($c,'name')) ?></div>
      </div>
      <?php foreach ($c['items'] as $it):
        $price = $it['discount_price'] > 0 ? $it['discount_price'] : $it['price'];
        $hasVar = !empty($it['variants']); ?>
        <div class="item" data-name="<?= e(strtolower($L($it,'name'))) ?>"
             data-veg="<?= (int)$it['is_veg'] ?>" data-best="<?= (int)$it['is_bestseller'] ?>">
          <?php if ($tenant['show_images'] && $it['image']): ?>
            <img src="<?= e(mediaUrl($it['image'])) ?>" class="thumb" loading="lazy" alt="">
          <?php endif; ?>
          <div class="info">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
              <?php if ($tenant['show_veg_marker']): ?>
                <span class="veg-marker <?= $it['is_veg']?'':'nonveg' ?>"></span>
              <?php endif; ?>
              <span class="iname"><?= e($L($it,'name')) ?></span>
              <?php if ($it['is_bestseller']): ?><span class="badge-soft b-best">★ <?= __('bestseller') ?></span><?php endif; ?>
              <?php if ($it['is_new']): ?><span class="badge-soft b-new"><?= __('new') ?></span><?php endif; ?>
            </div>
            <?php if ($tenant['show_descriptions'] && $L($it,'description')): ?>
              <div class="idesc"><?= e($L($it,'description')) ?></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center mt-1">
              <?php if ($tenant['show_prices']): ?>
                <div class="iprice">
                  <?php if ($it['discount_price'] > 0): ?><span class="strike"><?= e($currency).number_format($it['price'],0) ?></span> <?php endif; ?>
                  <?= e($currency) . number_format($price,0) ?><?= $hasVar?' <span class="text-muted small">onwards</span>':'' ?>
                </div>
              <?php else: ?><span></span><?php endif; ?>
              <?php if ($canOrder && $it['is_available']): ?>
                <button class="addbtn" onclick='openItem(<?= json_encode([
                  "id"=>(int)$it["id"],"name"=>$L($it,"name"),"price"=>(float)$price,
                  "veg"=>(int)$it["is_veg"],"variants"=>array_map(fn($v)=>["label"=>$v["label"],"price"=>(float)$v["price"]],$it["variants"]),
                  "addons"=>array_map(fn($a)=>["id"=>$a["id"],"name"=>$a["name"],"price"=>(float)$a["price"]],$it["addons"]),
                ], JSON_UNESCAPED_UNICODE) ?>)'><?= __('add') ?> +</button>
              <?php elseif ($canOrder && !$it['is_available']): ?>
                <span class="badge bg-secondary">Sold out</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</main>

<?php if ($showBranding): ?>
  <footer class="text-center small py-4" style="color:#8a7256;"><?= e(POWERED_BY) ?></footer>
<?php endif; ?>

<?php if ($canOrder): ?>
<!-- ===== FLOATING CART ===== -->
<div class="cartbar" id="cartbar" onclick="openCart()">
  <span><i class="bi bi-cart"></i> <span id="cartCount">0</span> items</span>
  <span><span id="cartTotal"><?= e($currency) ?>0</span> · <?= __('place_order') ?> <i class="bi bi-arrow-right"></i></span>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---- Category scroll spy + search + filters ----
const cats=[...document.querySelectorAll('.cat-section')];
const navlinks=[...document.querySelectorAll('#catnav a')];
navlinks.forEach(a=>a.addEventListener('click',e=>{e.preventDefault();
  document.querySelector(a.getAttribute('href')).scrollIntoView({behavior:'smooth',block:'start'});}));
window.addEventListener('scroll',()=>{let cur=cats[0]?.id;
  for(const s of cats){if(s.getBoundingClientRect().top<120)cur=s.id;}
  navlinks.forEach(a=>a.classList.toggle('active',a.getAttribute('href')==='#'+cur));});
document.getElementById('search').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('.item').forEach(it=>{it.style.display=it.dataset.name.includes(q)?'':'none';});});
let filters={veg:false,nonveg:false,best:false};
document.querySelectorAll('.filterchip').forEach(c=>c.addEventListener('click',()=>{
  c.classList.toggle('on');filters[c.dataset.filter]=c.classList.contains('on');applyFilters();}));
function applyFilters(){document.querySelectorAll('.item').forEach(it=>{
  let ok=true;
  if(filters.veg&&it.dataset.veg!=='1')ok=false;
  if(filters.nonveg&&it.dataset.veg!=='0')ok=false;
  if(filters.best&&it.dataset.best!=='1')ok=false;
  it.style.display=ok?'':'none';});}

<?php if ($canOrder): ?>
// ---- Cart (in-memory) ----
const CURRENCY='<?= e($currency) ?>';
const TABLE_TOKEN='<?= e($tableToken ?? '') ?>';
const SLUG='<?= e($tenant['slug']) ?>';
let cart=[];
function money(n){return CURRENCY+Number(n).toFixed(0);}
function openItem(it){
  let vhtml='';
  if(it.variants&&it.variants.length){vhtml='<div class="mb-2"><label class="small fw-600">Choose</label>'+
    it.variants.map((v,i)=>`<div class="form-check"><input class="form-check-input" type="radio" name="variant" value="${i}" ${i===0?'checked':''}><label class="form-check-label">${v.label} — ${money(v.price)}</label></div>`).join('')+'</div>';}
  let ahtml='';
  if(it.addons&&it.addons.length){ahtml='<div class="mb-2"><label class="small fw-600">Add-ons</label>'+
    it.addons.map((a,i)=>`<div class="form-check"><input class="form-check-input addon-chk" type="checkbox" value="${i}"><label class="form-check-label">${a.name} +${money(a.price)}</label></div>`).join('')+'</div>';}
  const html=`<div><h6>${it.name}</h6>${vhtml}${ahtml}
    <div class="mb-2"><label class="small">Notes</label><input id="itemNotes" class="form-control form-control-sm" placeholder="e.g. less spicy"></div>
    <div class="d-flex align-items-center gap-2"><label>Qty</label>
      <input id="itemQty" type="number" value="1" min="1" class="form-control form-control-sm" style="width:70px"></div></div>`;
  showModal(it.name,html,()=>{
    let variant=null,price=it.price;
    const vr=document.querySelector('input[name=variant]:checked');
    if(vr){variant=it.variants[vr.value];price=variant.price;}
    let addons=[];document.querySelectorAll('.addon-chk:checked').forEach(c=>{const a=it.addons[c.value];addons.push(a);price+=a.price;});
    const qty=Math.max(1,parseInt(document.getElementById('itemQty').value)||1);
    cart.push({id:it.id,name:it.name,variant:variant?variant.label:null,addons:addons,qty,price,notes:document.getElementById('itemNotes').value});
    renderCart();bsModal.hide();
  });
}
function renderCart(){
  const count=cart.reduce((s,c)=>s+c.qty,0);
  const total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  document.getElementById('cartCount').textContent=count;
  document.getElementById('cartTotal').textContent=money(total);
  document.getElementById('cartbar').classList.toggle('show',count>0);
}
function openCart(){
  if(!cart.length)return;
  const rows=cart.map((c,i)=>`<div class="d-flex justify-content-between border-bottom py-2">
    <div><b>${c.name}</b> ${c.variant?'('+c.variant+')':''}<br><small>${c.qty} × ${money(c.price)}</small></div>
    <div>${money(c.qty*c.price)} <button class="btn btn-sm text-danger" onclick="cart.splice(${i},1);renderCart();bsModal.hide();openCart();">&times;</button></div></div>`).join('');
  const total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  const form=`${rows}<div class="text-end fw-700 my-2">Total: ${money(total)}</div>
    <input id="custName" class="form-control form-control-sm mb-2" placeholder="<?= __('your_name') ?>">
    <input id="custMobile" class="form-control form-control-sm mb-2" placeholder="Mobile" inputmode="numeric">
    <select id="orderType" class="form-select form-select-sm mb-2">
      <option value="dinein">Dine-in</option><option value="takeaway">Takeaway</option><option value="delivery">Delivery</option></select>`;
  showModal('<?= __('cart') ?>',form,placeOrder,'<?= __('place_order') ?>');
}
function placeOrder(){
  const name=document.getElementById('custName').value.trim();
  if(!name){alert('Enter your name');return;}
  const payload={slug:SLUG,table_token:TABLE_TOKEN,customer_name:name,
    customer_mobile:document.getElementById('custMobile').value,order_type:document.getElementById('orderType').value,
    items:cart};
  fetch('<?= e(BASE_URL) ?>/api/order.php?action=place',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success'){bsModal.hide();cart=[];renderCart();
        showFeedback(d.data.order_id);}
      else alert(d.message||'Order failed');});
}
function showFeedback(orderId){
  const html=`<div class="text-center"><h5><?= __('order_placed') ?></h5>
    <p class="text-muted"><?= __('rate_experience') ?></p>
    <div id="stars" style="font-size:2rem">${[1,2,3,4,5].map(s=>`<i class="bi bi-star star" data-s="${s}" onclick="rate(${s},${orderId})"></i>`).join('')}</div></div>`;
  showModal('Thank you!',html,null);
}
window.rate=function(stars,orderId){
  document.querySelectorAll('.star').forEach((el,i)=>el.className='bi '+(i<stars?'bi-star-fill text-warning':'bi-star')+' star');
  const rev='<?= e($tenant['google_review_url']) ?>';
  fetch('<?= e(BASE_URL) ?>/api/order.php?action=feedback',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({order_id:orderId,slug:SLUG,rating:stars})}).then(()=>{});
  if(stars>=4&&rev){setTimeout(()=>location.href=rev,700);}  // smart routing
  else{setTimeout(()=>{bsModal.hide();alert('Thank you for your feedback!');},700);}
}
<?php endif; ?>

// ---- Simple modal helper ----
let bsModal;
function showModal(title,body,onOk,okText){
  let el=document.getElementById('akModal');
  if(!el){el=document.createElement('div');el.id='akModal';el.className='modal fade';
    el.innerHTML=`<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header py-2"><h6 class="modal-title"></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"></div>
      <div class="modal-footer py-2"><button class="btn btn-primary btn-sm" id="modalOk"></button></div></div></div>`;
    document.body.appendChild(el);bsModal=new bootstrap.Modal(el);}
  el.querySelector('.modal-title').textContent=title;
  el.querySelector('.modal-body').innerHTML=body;
  const ok=el.querySelector('#modalOk');
  ok.textContent=okText||'<?= __('add_to_cart') ?>';
  ok.style.display=onOk?'':'none';
  ok.onclick=onOk||null;
  bsModal.show();
}

// ---- PWA service worker ----
if('serviceWorker' in navigator){navigator.serviceWorker.register('<?= e(BASE_URL) ?>/r/sw.js').catch(()=>{});}
</script>
</body>
</html>
