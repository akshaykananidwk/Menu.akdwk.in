<?php
/**
 * MENU DESIGN TEMPLATE: "Fast Food"
 * Design theme: Bold & energetic — bright high-contrast colours (red/yellow), heavy
 * rounded type, big bold prices, a grid of image tiles, prominent "ADD" buttons.
 * Receives (from /r/index.php): $menu, $tenant, $categories, $orderingMode,
 * $tableToken, $tableNo, $currency, $lang, $showBranding, $folder.
 * Templates ONLY read the provided data — no DB queries here.
 */
if (!isset($tenant)) { exit('Template must be rendered through /r/'); }
$primary   = $tenant['primary_color'] ?: '#e01e26';
$secondary = $tenant['secondary_color'] ?: '#1a1a1a';
$accent    = $tenant['accent_color'] ?: '#ffcc00';
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
<link href="https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Poppins:wght@400;500;600;700;800&family=Noto+Sans+Gujarati:wght@400;600&display=swap" rel="stylesheet">
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
body{font-family:<?= $lang==='gu'?"'Noto Sans Gujarati',":'' ?>'Poppins',system-ui,sans-serif;background:#fff8e1;color:#1a1a1a;padding-bottom:94px;}
.pop{font-family:'Luckiest Guy',cursive;letter-spacing:1px;}
.hero{background:var(--primary);color:#fff;padding:18px 16px 22px;}
.hero .logo{width:60px;height:60px;border-radius:14px;object-fit:cover;border:3px solid var(--accent);background:#fff;}
.hero h1{font-family:'Luckiest Guy',cursive;font-size:1.7rem;margin:6px 0 2px;text-shadow:2px 2px 0 rgba(0,0,0,.25);letter-spacing:1px;}
.status-badge{font-size:.74rem;padding:3px 12px;border-radius:20px;font-weight:700;}
.act-btns a{background:var(--accent);color:#1a1a1a;border-radius:12px;padding:9px 0;font-size:.78rem;font-weight:700;text-decoration:none;display:block;text-align:center;}
.catnav{position:sticky;top:0;z-index:20;background:var(--secondary);overflow-x:auto;white-space:nowrap;padding:10px 8px;}
.catnav a{display:inline-block;padding:7px 16px;margin-right:8px;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;text-decoration:none;font-size:.86rem;font-weight:700;}
.catnav a.active{background:var(--accent);color:#1a1a1a;}
.cat-title{font-family:'Luckiest Guy',cursive;font-size:1.5rem;margin:22px 4px 12px;color:var(--primary);text-transform:uppercase;letter-spacing:1px;}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;}
.item{background:#fff;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 4px 0 rgba(0,0,0,.12);border:2px solid #1a1a1a;}
.item .imgwrap{position:relative;background:#ffe9a8;}
.item img.thumb{width:100%;height:120px;object-fit:cover;display:block;}
.item .noimg{height:120px;display:flex;align-items:center;justify-content:center;font-size:2.4rem;color:var(--primary);background:#ffe9a8;}
.item .info{padding:9px 10px 10px;display:flex;flex-direction:column;flex:1;}
.item .iname{font-weight:700;font-size:.92rem;line-height:1.15;}
.item .idesc{font-size:.72rem;color:#777;margin:2px 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.item .iprice{font-family:'Luckiest Guy',cursive;color:var(--primary);font-size:1.3rem;line-height:1;}
.badge-soft{position:absolute;top:6px;font-size:.6rem;padding:3px 8px;border-radius:8px;font-weight:800;text-transform:uppercase;}
.b-best{left:6px;background:var(--accent);color:#1a1a1a;}.b-new{right:6px;background:#22a45d;color:#fff;}
.veg-tag{position:absolute;bottom:6px;left:6px;background:#fff;border-radius:6px;padding:2px;}
.addbtn{background:var(--primary);color:#fff;border:0;border-radius:10px;padding:8px;font-size:.9rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;width:100%;margin-top:auto;}
.soldout{background:#999;color:#fff;border-radius:10px;padding:8px;font-weight:700;text-align:center;margin-top:auto;font-size:.8rem;}
.cartbar{position:fixed;bottom:0;left:0;right:0;background:var(--primary);color:#fff;padding:15px 20px;display:flex;justify-content:space-between;align-items:center;z-index:40;cursor:pointer;transform:translateY(120%);transition:.25s;font-weight:800;font-size:1rem;}
.cartbar.show{transform:none;}
.filterchip{font-size:.78rem;padding:5px 14px;border-radius:12px;border:2px solid #1a1a1a;background:#fff;cursor:pointer;font-weight:700;}
.filterchip.on{background:var(--primary);color:#fff;border-color:var(--primary);}
.strike{text-decoration:line-through;color:#aaa;font-weight:400;font-size:.8rem;font-family:'Poppins',sans-serif;}
#search{border:2px solid #1a1a1a;border-radius:12px;font-weight:600;}
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
        <div class="small"><i class="bi bi-geo-alt"></i> <?= e($tenant['city'] ?: $tenant['address']) ?></div>
        <span class="status-badge <?= $isOpen?'bg-success':'bg-dark' ?> mt-1 d-inline-block">
          <?= $isOpen ? '● '.__('open_now') : '● '.__('closed_now') ?>
        </span>
      </div>
    </div>
    <div class="dropdown">
      <button class="btn btn-sm btn-warning fw-bold dropdown-toggle" data-bs-toggle="dropdown"><?= $lang==='gu'?'ગુ':'EN' ?></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="?lang=en<?= $tableToken?'&table='.e($tableToken):'' ?>">English</a></li>
        <li><a class="dropdown-item" href="?lang=gu<?= $tableToken?'&table='.e($tableToken):'' ?>">ગુજરાતી</a></li>
      </ul>
    </div>
  </div>
  <?php if ($tableNo): ?><div class="mt-2 small fw-bold"><i class="bi bi-grid-3x3-gap"></i> Table <?= e($tableNo) ?></div><?php endif; ?>
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
      <div class="cat-title"><?= e($L($c,'name')) ?></div>
      <div class="grid">
      <?php foreach ($c['items'] as $it):
        $price = $it['discount_price'] > 0 ? $it['discount_price'] : $it['price'];
        $hasVar = !empty($it['variants']); ?>
        <div class="item" data-name="<?= e(strtolower($L($it,'name'))) ?>"
             data-veg="<?= (int)$it['is_veg'] ?>" data-best="<?= (int)$it['is_bestseller'] ?>">
          <div class="imgwrap">
            <?php if ($tenant['show_images'] && $it['image']): ?>
              <img src="<?= e(mediaUrl($it['image'])) ?>" class="thumb" loading="lazy" alt="">
            <?php else: ?>
              <div class="noimg"><i class="bi bi-cup-hot"></i></div>
            <?php endif; ?>
            <?php if ($it['is_bestseller']): ?><span class="badge-soft b-best">★ Hot</span><?php endif; ?>
            <?php if ($it['is_new']): ?><span class="badge-soft b-new"><?= __('new') ?></span><?php endif; ?>
            <?php if ($tenant['show_veg_marker']): ?>
              <span class="veg-tag"><span class="veg-marker <?= $it['is_veg']?'':'nonveg' ?>"></span></span>
            <?php endif; ?>
          </div>
          <div class="info">
            <div class="iname"><?= e($L($it,'name')) ?></div>
            <?php if ($tenant['show_descriptions'] && $L($it,'description')): ?>
              <div class="idesc"><?= e($L($it,'description')) ?></div>
            <?php endif; ?>
            <?php if ($tenant['show_prices']): ?>
              <div class="iprice mb-2">
                <?php if ($it['discount_price'] > 0): ?><span class="strike"><?= e($currency).number_format($it['price'],0) ?></span> <?php endif; ?>
                <?= e($currency) . number_format($price,0) ?><?= $hasVar?' <span class="text-muted small" style="font-family:Poppins">onwards</span>':'' ?>
              </div>
            <?php endif; ?>
            <?php if ($canOrder && $it['is_available']): ?>
              <button class="addbtn" onclick='openItem(<?= json_encode([
                "id"=>(int)$it["id"],"name"=>$L($it,"name"),"price"=>(float)$price,
                "veg"=>(int)$it["is_veg"],"variants"=>array_map(fn($v)=>["label"=>$v["label"],"price"=>(float)$v["price"]],$it["variants"]),
                "addons"=>array_map(fn($a)=>["id"=>$a["id"],"name"=>$a["name"],"price"=>(float)$a["price"]],$it["addons"]),
              ], JSON_UNESCAPED_UNICODE) ?>)'><?= __('add') ?> +</button>
            <?php elseif ($canOrder && !$it['is_available']): ?>
              <div class="soldout">Sold out</div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</main>

<?php if ($showBranding): ?>
  <footer class="text-center small py-4 text-muted"><?= e(POWERED_BY) ?></footer>
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
