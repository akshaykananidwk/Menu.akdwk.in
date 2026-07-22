<?php
/**
 * MENU DESIGN TEMPLATE: "Fine Dine"
 * Design theme: Premium luxury — deep charcoal/black background, gold/champagne accents,
 * generous whitespace, centered elegant typography, uppercase category titles with
 * ornamental dividers. Understated and refined.
 * Receives (from /r/index.php): $menu, $tenant, $categories, $orderingMode,
 * $tableToken, $tableNo, $currency, $lang, $showBranding, $folder.
 * Templates ONLY read the provided data — no DB queries here.
 */
if (!isset($tenant)) { exit('Template must be rendered through /r/'); }
$primary   = $tenant['primary_color'] ?: '#c9a45c';
$secondary = $tenant['secondary_color'] ?: '#0d0d0f';
$accent    = $tenant['accent_color'] ?: '#e8cd8f';
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
<html lang="<?= e($lang) ?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="theme-color" content="<?= e($secondary) ?>">
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
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&family=Noto+Sans+Gujarati:wght@400;600&display=swap" rel="stylesheet">
<link href="<?= e(assetUrl('assets/css/app.css')) ?>" rel="stylesheet">
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
body{font-family:<?= $lang==='gu'?"'Noto Sans Gujarati',":'' ?>'Poppins',system-ui,sans-serif;background:#0d0d0f;color:#e8e4da;padding-bottom:90px;}
.display{font-family:'Cormorant Garamond',Georgia,serif;}
.hero{text-align:center;padding:30px 16px 20px;background:radial-gradient(circle at 50% 0%,rgba(201,164,92,.16),transparent 60%);border-bottom:1px solid rgba(201,164,92,.25);}
.hero .logo{width:76px;height:76px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);background:#000;}
.hero h1{font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:600;margin:12px 0 2px;color:#fff;letter-spacing:2px;}
.hero .sub{font-size:.78rem;color:#a99a76;letter-spacing:1px;text-transform:uppercase;}
.status-badge{font-size:.68rem;padding:3px 14px;border-radius:0;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;border:1px solid;}
.status-open{color:var(--accent);border-color:var(--primary);}.status-closed{color:#c98080;border-color:#7a3a3a;}
.langbtn{border:1px solid var(--primary);background:transparent;color:var(--accent);}
.act-btns a{border:1px solid rgba(201,164,92,.4);color:var(--accent);border-radius:0;padding:9px 0;font-size:.72rem;text-decoration:none;display:block;text-align:center;letter-spacing:1px;text-transform:uppercase;background:rgba(255,255,255,.02);}
.catnav{position:sticky;top:0;z-index:20;background:#0d0d0f;border-bottom:1px solid rgba(201,164,92,.2);overflow-x:auto;white-space:nowrap;padding:12px 8px;text-align:center;}
.catnav a{display:inline-block;padding:5px 16px;margin:0 3px;color:#b8b2a3;text-decoration:none;font-size:.78rem;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;}
.catnav a.active{color:var(--accent);border-bottom:1px solid var(--primary);}
.cat-title{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:1.7rem;text-align:center;margin:34px 4px 2px;color:#fff;text-transform:uppercase;letter-spacing:4px;}
.ornament{text-align:center;color:var(--primary);font-size:.9rem;margin-bottom:18px;letter-spacing:6px;}
.item{padding:16px 4px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;gap:16px;align-items:flex-start;}
.item .info{flex:1;min-width:0;text-align:center;}
.item.has-img .info{text-align:left;}
.item .iname{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:1.24rem;color:#f3efe4;letter-spacing:.5px;}
.item .idesc{font-size:.8rem;color:#948b78;margin:4px 0;font-style:italic;}
.item .iprice{font-family:'Cormorant Garamond',serif;font-weight:600;color:var(--accent);font-size:1.2rem;margin-top:4px;}
.item img.thumb{width:84px;height:84px;border-radius:2px;object-fit:cover;flex-shrink:0;border:1px solid rgba(201,164,92,.3);}
.badge-soft{font-size:.58rem;padding:2px 8px;border-radius:0;font-weight:600;letter-spacing:1px;text-transform:uppercase;border:1px solid;}
.b-best{background:transparent;color:var(--accent);border-color:var(--primary);}.b-new{background:transparent;color:#9fd0a8;border-color:#3f6b46;}
.addbtn{background:transparent;color:var(--accent);border:1px solid var(--primary);border-radius:0;padding:6px 18px;font-size:.72rem;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;}
.cartbar{position:fixed;bottom:0;left:0;right:0;background:#000;border-top:1px solid var(--primary);color:var(--accent);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;z-index:40;cursor:pointer;transform:translateY(120%);transition:.25s;letter-spacing:1px;text-transform:uppercase;font-size:.82rem;}
.cartbar.show{transform:none;}
.filterchip{font-size:.72rem;padding:5px 14px;border-radius:0;border:1px solid rgba(201,164,92,.4);background:transparent;cursor:pointer;color:#c7bfaa;letter-spacing:1px;text-transform:uppercase;}
.filterchip.on{background:var(--primary);color:#0d0d0f;border-color:var(--primary);}
.strike{text-decoration:line-through;color:#6b6455;font-weight:400;font-size:.85rem;}
#search{border:1px solid rgba(201,164,92,.35);background:rgba(255,255,255,.03);color:#e8e4da;border-radius:0;}
#search::placeholder{color:#7a725f;}
.veg-marker{border-color:var(--accent)!important;}.veg-marker::after{background:var(--accent)!important;}
</style>
</head>
<body>

<!-- ===== HERO / HEADER ===== -->
<header class="hero">
  <div class="d-flex justify-content-end mb-2">
    <div class="dropdown">
      <button class="btn btn-sm langbtn dropdown-toggle" data-bs-toggle="dropdown"><?= $lang==='gu'?'ગુ':'EN' ?></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="?lang=en<?= $tableToken?'&table='.e($tableToken):'' ?>">English</a></li>
        <li><a class="dropdown-item" href="?lang=gu<?= $tableToken?'&table='.e($tableToken):'' ?>">ગુજરાતી</a></li>
      </ul>
    </div>
  </div>
  <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" class="logo" alt="logo"><?php endif; ?>
  <h1><?= e($tenant['restaurant_name']) ?></h1>
  <div class="ornament">✦ ✦ ✦</div>
  <div class="sub"><?= e($tenant['city'] ?: $tenant['address']) ?></div>
  <div class="mt-3">
    <span class="status-badge <?= $isOpen?'status-open':'status-closed' ?>">
      <?= $isOpen ? __('open_now') : __('closed_now') ?>
    </span>
    <?php if ($tableNo): ?><span class="ms-2 small text-uppercase" style="letter-spacing:1px;color:#a99a76;">Table <?= e($tableNo) ?></span><?php endif; ?>
  </div>
  <div class="row g-2 mt-3 act-btns px-2">
    <?php if ($tenant['mobile']): ?><div class="col"><a href="tel:<?= e($tenant['mobile']) ?>"><i class="bi bi-telephone"></i><br><?= __('call') ?></a></div><?php endif; ?>
    <?php if ($tenant['whatsapp_no']): ?><div class="col"><a href="<?= e(waMeUrl($tenant['whatsapp_no'])) ?>"><i class="bi bi-whatsapp"></i><br><?= __('whatsapp') ?></a></div><?php endif; ?>
    <?php if ($tenant['maps_url']): ?><div class="col"><a href="<?= e($tenant['maps_url']) ?>" target="_blank"><i class="bi bi-geo"></i><br><?= __('directions') ?></a></div><?php endif; ?>
    <?php if ($tenant['google_review_url']): ?><div class="col"><a href="<?= e($tenant['google_review_url']) ?>" target="_blank"><i class="bi bi-star"></i><br>Review</a></div><?php endif; ?>
  </div>
</header>

<!-- ===== SEARCH + FILTERS ===== -->
<div class="px-3 py-3">
  <input id="search" class="form-control form-control-sm mb-2" placeholder="<?= __('search') ?>...">
  <div class="d-flex gap-2 flex-wrap justify-content-center">
    <span class="filterchip" data-filter="veg"><?= __('veg') ?></span>
    <span class="filterchip" data-filter="nonveg"><?= __('non_veg') ?></span>
    <span class="filterchip" data-filter="best"><?= __('bestseller') ?></span>
  </div>
</div>

<!-- ===== CATEGORY NAV ===== -->
<nav class="catnav" id="catnav">
  <?php foreach ($categories as $i => $c): ?>
    <a href="#cat<?= (int)$c['id'] ?>" class="<?= $i===0?'active':'' ?>"><?= e($L($c,'name')) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ===== MENU ===== -->
<main class="px-3" style="max-width:660px;margin:0 auto;">
  <?php if (empty($categories)): ?>
    <div class="empty-state text-center py-5"><i class="bi bi-journal-x"></i><p>Menu coming soon.</p></div>
  <?php endif; ?>
  <?php foreach ($categories as $c): ?>
    <section id="cat<?= (int)$c['id'] ?>" class="cat-section">
      <div class="cat-title"><?= e($L($c,'name')) ?></div>
      <div class="ornament">— ◆ —</div>
      <?php foreach ($c['items'] as $it):
        $price = $it['discount_price'] > 0 ? $it['discount_price'] : $it['price'];
        $hasVar = !empty($it['variants']);
        $hasImg = $tenant['show_images'] && $it['image']; ?>
        <div class="item <?= $hasImg?'has-img':'' ?>" data-name="<?= e(strtolower($L($it,'name'))) ?>"
             data-veg="<?= (int)$it['is_veg'] ?>" data-best="<?= (int)$it['is_bestseller'] ?>">
          <?php if ($hasImg): ?>
            <img src="<?= e(mediaUrl($it['image'])) ?>" class="thumb" loading="lazy" alt="">
          <?php endif; ?>
          <div class="info">
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap <?= $hasImg?'':'justify-content-center' ?>">
              <?php if ($tenant['show_veg_marker']): ?>
                <span class="veg-marker <?= $it['is_veg']?'':'nonveg' ?>"></span>
              <?php endif; ?>
              <span class="iname"><?= e($L($it,'name')) ?></span>
              <?php if ($it['is_bestseller']): ?><span class="badge-soft b-best"><?= __('bestseller') ?></span><?php endif; ?>
              <?php if ($it['is_new']): ?><span class="badge-soft b-new"><?= __('new') ?></span><?php endif; ?>
            </div>
            <?php if ($tenant['show_descriptions'] && $L($it,'description')): ?>
              <div class="idesc"><?= e($L($it,'description')) ?></div>
            <?php endif; ?>
            <?php if ($tenant['show_prices']): ?>
              <div class="iprice">
                <?php if ($it['discount_price'] > 0): ?><span class="strike"><?= e($currency).number_format($it['price'],0) ?></span> <?php endif; ?>
                <?= e($currency) . number_format($price,0) ?><?= $hasVar?' <span class="small" style="text-transform:none;font-style:italic;">onwards</span>':'' ?>
              </div>
            <?php endif; ?>
            <?php if ($canOrder && $it['is_available']): ?>
              <button class="addbtn mt-2" onclick='openItem(<?= json_encode([
                "id"=>(int)$it["id"],"name"=>$L($it,"name"),"price"=>(float)$price,
                "veg"=>(int)$it["is_veg"],"variants"=>array_map(fn($v)=>["label"=>$v["label"],"price"=>(float)$v["price"]],$it["variants"]),
                "addons"=>array_map(fn($a)=>["id"=>$a["id"],"name"=>$a["name"],"price"=>(float)$a["price"]],$it["addons"]),
              ], JSON_UNESCAPED_UNICODE) ?>)'><?= __('add') ?></button>
            <?php elseif ($canOrder && !$it['is_available']): ?>
              <span class="badge bg-secondary mt-2 d-inline-block">Sold out</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</main>

<?php if ($showBranding): ?>
  <footer class="text-center small py-4" style="color:#6b6455;letter-spacing:1px;text-transform:uppercase;"><?= e(POWERED_BY) ?></footer>
<?php endif; ?>

<?php if ($canOrder): ?>
<!-- ===== FLOATING CART ===== -->
<div class="cartbar" id="cartbar" onclick="openCart()">
  <span><i class="bi bi-bag"></i> <span id="cartCount">0</span> items</span>
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
