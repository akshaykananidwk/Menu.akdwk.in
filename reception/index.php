<?php
/**
 * Reception panel — the front-desk / captain screen (part of the master app).
 * Two tabs:
 *   Order  — take an order for any table (same flow as a waiter).
 *   Floor  — live orders (advance status), table-service calls and today's
 *            reservations. No payment collection (that stays with the owner).
 */
require_once dirname(__DIR__) . '/config/config.php';
requireStaff('reception');

$tid    = (int)($_SESSION['staff_tenant_id'] ?? 0);
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $tid]);
$menu   = getTenantMenu($tid);
$tables = db_all('SELECT id, table_no, qr_token, status FROM ' . tbl('tables') . ' WHERE tenant_id = :t ORDER BY table_no', [':t' => $tid]);
$currency = $tenant['currency'] ?: '₹';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0f1729">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<link rel="manifest" href="<?= e(BASE_URL) ?>/app/manifest.php">
<link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>/assets/img/icon-192.png">
<title>Reception · <?= e($tenant['restaurant_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#eef1f6;padding-bottom:76px;font-family:system-ui}
.topbar{background:#0f1729;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:30}
.cat-h{font-weight:700;margin:14px 4px 6px;color:#0f1729}
.mitem{background:#fff;border-radius:12px;padding:10px 12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.mitem .add{min-width:52px;min-height:44px;font-size:1.3rem;border-radius:10px}
.tabbar{display:flex;overflow-x:auto;gap:8px;padding:10px;background:#fff;position:sticky;top:52px;z-index:20;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.tab-chip.active{background:#0f1729 !important;color:#fff !important}
.cartbar{position:fixed;bottom:60px;left:0;right:0;background:#2563eb;color:#fff;padding:13px 18px;display:flex;justify-content:space-between;align-items:center;z-index:35;cursor:pointer}
.veg{width:12px;height:12px;border:2px solid #0a0;border-radius:2px;display:inline-block}.veg.nv{border-color:#c00}
.qtybtn{width:40px;height:40px;font-size:1.2rem}
.bottomnav{position:fixed;bottom:0;left:0;right:0;height:60px;background:#fff;display:flex;z-index:40;box-shadow:0 -2px 10px rgba(0,0,0,.08)}
.bottomnav button{flex:1;border:0;background:transparent;color:#8a90a2;font-size:.72rem;font-weight:600;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px}
.bottomnav button i{font-size:1.3rem}
.bottomnav button.on{color:#2563eb}
.bottomnav .dot{position:absolute;top:8px;margin-left:22px;background:#e23744;color:#fff;border-radius:20px;font-size:.6rem;padding:0 5px;font-weight:700}
.view{display:none}.view.on{display:block}
.ord-card{background:#fff;border-radius:12px;padding:12px;margin-bottom:10px;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.svc{background:#fff6e5;border:1px solid #ffe0a3;border-radius:12px;padding:10px 12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center}
.svc.bill{background:#fdecec;border-color:#f6b8b8}
</style>
</head><body>

<div class="topbar">
  <div><i class="bi bi-clipboard2-check"></i> <strong id="topTitle">Reception</strong>
    <div class="small opacity-75"><?= e($tenant['restaurant_name']) ?> · <?= e($_SESSION['staff_name'] ?? '') ?></div></div>
  <a href="<?= e(BASE_URL) ?>/reception/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i></a>
</div>

<!-- ============ ORDER VIEW ============ -->
<div class="view on" id="vOrder">
  <div class="p-3 bg-white">
    <label class="form-label small fw-bold mb-1">Table</label>
    <select id="tableSel" class="form-select form-select-lg">
      <option value="">— Choose table (or takeaway) —</option>
      <?php foreach ($tables as $t): ?>
        <option value="<?= e($t['qr_token']) ?>" data-no="<?= e($t['table_no']) ?>">Table <?= e($t['table_no']) ?> (<?= e(ucfirst($t['status'])) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="tabbar" id="catbar">
    <?php foreach ($menu['categories'] as $i => $c): ?>
      <button class="btn btn-sm btn-light tab-chip <?= $i === 0 ? 'active' : '' ?>" data-cat="cat<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
    <?php endforeach; ?>
  </div>
  <main class="px-3 pt-2">
    <?php if (empty($menu['categories'])): ?><div class="alert alert-info mt-3">No menu items yet.</div><?php endif; ?>
    <?php foreach ($menu['categories'] as $c): ?>
      <section id="cat<?= (int)$c['id'] ?>">
        <div class="cat-h"><?= e($c['name']) ?></div>
        <?php foreach ($c['items'] as $it):
          if (!$it['is_available']) continue;
          $price = ($it['discount_price'] !== null && (float)$it['discount_price'] > 0) ? (float)$it['discount_price'] : (float)$it['price'];
          $itemJson = json_encode([
            'id' => (int)$it['id'], 'name' => $it['name'], 'price' => $price,
            'variants' => array_map(fn($v) => ['label' => $v['label'], 'price' => (float)$v['price']], $it['variants']),
            'addons'   => array_map(fn($a) => ['name' => $a['name'], 'price' => (float)$a['price']], $it['addons']),
          ], JSON_UNESCAPED_UNICODE); ?>
          <div class="mitem">
            <div><span class="veg <?= $it['is_veg'] ? '' : 'nv' ?>"></span>
              <span class="fw-semibold"><?= e($it['name']) ?></span>
              <div class="text-muted small"><?= e($currency) . number_format($price, 0) ?><?= !empty($it['variants']) ? ' onwards' : '' ?></div></div>
            <button class="btn btn-primary add" onclick='addItem(<?= e($itemJson) ?>)'>+</button>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </main>
  <div class="cartbar" id="cartbar" style="display:none" onclick="openCart()">
    <span><i class="bi bi-cart"></i> <span id="cartCount">0</span> items</span>
    <span><span id="cartTotal"><?= e($currency) ?>0</span> · Review <i class="bi bi-arrow-right"></i></span>
  </div>
</div>

<!-- ============ FLOOR VIEW ============ -->
<div class="view" id="vFloor">
  <div class="p-3">
    <div id="svcWrap" class="mb-2"></div>
    <h6 class="cat-h">Live Orders</h6>
    <div id="ordWrap"><div class="text-muted small">Loading…</div></div>
    <h6 class="cat-h mt-3">Today's Reservations</h6>
    <div id="resvWrap"><div class="text-muted small">Loading…</div></div>
  </div>
</div>

<!-- bottom nav -->
<div class="bottomnav">
  <button id="navOrder" class="on" onclick="showView('order')"><i class="bi bi-plus-square"></i>Take Order</button>
  <button id="navFloor" onclick="showView('floor')"><i class="bi bi-columns-gap"></i>Floor <span id="floorDot" class="dot" style="display:none">0</span></button>
</div>

<!-- Modal -->
<div class="modal fade" id="rModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header py-2"><h6 class="modal-title" id="rmTitle"></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="rmBody"></div>
    <div class="modal-footer py-2"><button class="btn btn-primary" id="rmOk"></button></div>
  </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$base = BASE_URL; $csrf = csrfToken(); $staffJs = (int)$staffId;
$curJs = json_encode($currency); $slugJs = json_encode($tenant['slug']);
echo <<<HTML
<script>
const B='$base', CSRF='$csrf', STAFF_ID=$staffJs, CUR=$curJs, SLUG=$slugJs;
const money=n=>CUR+Number(n||0).toFixed(0);
const esc=s=>(s==null?'':String(s)).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let cart=[];
const modal=new bootstrap.Modal(document.getElementById('rModal'));

// ---- Tab switch ----
function showView(v){
  document.getElementById('vOrder').classList.toggle('on', v==='order');
  document.getElementById('vFloor').classList.toggle('on', v==='floor');
  document.getElementById('navOrder').classList.toggle('on', v==='order');
  document.getElementById('navFloor').classList.toggle('on', v==='floor');
  document.getElementById('topTitle').textContent = v==='order'?'Take Order':'Floor';
  document.getElementById('cartbar').style.display = (v==='order' && cart.length)?'flex':'none';
  if(v==='floor') pollFloor();
}

// ---- Category quick-nav ----
document.querySelectorAll('#catbar .tab-chip').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('#catbar .tab-chip').forEach(x=>x.classList.remove('active'));
  b.classList.add('active'); document.getElementById(b.dataset.cat)?.scrollIntoView({behavior:'smooth',block:'start'});
}));

// ---- Order taking ----
function addItem(it){
  document.getElementById('rmTitle').textContent=it.name;
  let vh=''; if(it.variants&&it.variants.length){ vh='<div class="mb-2"><label class="small fw-bold">Variant</label>'+
    it.variants.map((v,i)=>`<div class="form-check"><input class="form-check-input" type="radio" name="rv" value="\${i}" \${i===0?'checked':''}><label class="form-check-label">\${esc(v.label)} — \${money(v.price)}</label></div>`).join('')+'</div>'; }
  let ah=''; if(it.addons&&it.addons.length){ ah='<div class="mb-2"><label class="small fw-bold">Add-ons</label>'+
    it.addons.map((a,i)=>`<div class="form-check"><input class="form-check-input ra" type="checkbox" value="\${i}"><label class="form-check-label">\${esc(a.name)} +\${money(a.price)}</label></div>`).join('')+'</div>'; }
  document.getElementById('rmBody').innerHTML=vh+ah+
    `<div class="mb-2"><label class="small fw-bold">Notes</label><input id="rNotes" class="form-control" placeholder="e.g. less spicy"></div>
     <div class="d-flex align-items-center gap-2"><label class="fw-bold">Qty</label>
       <button type="button" class="btn btn-outline-secondary qtybtn" onclick="stepQty(-1)">−</button>
       <input id="rQty" type="number" value="1" min="1" class="form-control text-center" style="width:70px">
       <button type="button" class="btn btn-outline-secondary qtybtn" onclick="stepQty(1)">+</button></div>`;
  const ok=document.getElementById('rmOk'); ok.textContent='Add to Cart'; ok.style.display='';
  ok.onclick=()=>{
    let price=it.price, variant=null;
    const vr=document.querySelector('input[name=rv]:checked'); if(vr){ variant=it.variants[vr.value]; price=variant.price; }
    let addons=[]; document.querySelectorAll('.ra:checked').forEach(c=>{const a=it.addons[c.value];addons.push(a);price+=a.price;});
    const qty=Math.max(1,parseInt(document.getElementById('rQty').value)||1);
    cart.push({id:it.id,name:it.name,variant:variant?variant.label:null,addons,qty,price,notes:document.getElementById('rNotes').value});
    renderCart(); modal.hide();
  };
  modal.show();
}
window.stepQty=d=>{const q=document.getElementById('rQty');q.value=Math.max(1,(parseInt(q.value)||1)+d);};
function renderCart(){
  const count=cart.reduce((s,c)=>s+c.qty,0), total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  document.getElementById('cartCount').textContent=count;
  document.getElementById('cartTotal').textContent=money(total);
  document.getElementById('cartbar').style.display=count>0?'flex':'none';
}
window.openCart=function(){
  if(!cart.length) return;
  const rows=cart.map((c,i)=>`<div class="d-flex justify-content-between border-bottom py-2">
    <div><b>\${esc(c.name)}</b> \${c.variant?'('+esc(c.variant)+')':''}<br><small>\${c.qty} × \${money(c.price)}\${c.notes?' · '+esc(c.notes):''}</small></div>
    <div>\${money(c.qty*c.price)} <button class="btn btn-sm text-danger" onclick="cart.splice(\${i},1);renderCart();modal.hide();openCart();">&times;</button></div></div>`).join('');
  const total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  document.getElementById('rmTitle').textContent='Review Order';
  document.getElementById('rmBody').innerHTML=rows+
    `<div class="text-end fw-bold my-2">Total: \${money(total)}</div>
     <input id="cName" class="form-control mb-2" placeholder="Customer name (optional)">
     <select id="cType" class="form-select"><option value="dinein">Dine-in</option><option value="takeaway">Takeaway</option></select>`;
  const ok=document.getElementById('rmOk'); ok.textContent='Submit Order'; ok.style.display='';
  ok.onclick=submitOrder; modal.show();
};
function submitOrder(){
  const tableSel=document.getElementById('tableSel'), token=tableSel.value, type=document.getElementById('cType').value;
  if(type==='dinein' && !token){ alert('Please select a table.'); return; }
  const name=document.getElementById('cName').value.trim();
  const payload={slug:SLUG,table_token:token,staff_id:STAFF_ID,
    customer_name:name||('Table '+(tableSel.selectedOptions[0]?.dataset.no||'')),customer_mobile:'',order_type:type,items:cart};
  document.getElementById('rmOk').disabled=true;
  fetch(B+'/api/order.php?action=place',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      document.getElementById('rmOk').disabled=false;
      if(d.status==='success'){ modal.hide(); cart=[]; renderCart();
        setTimeout(()=>{alert('Order #'+d.data.order_no+' sent to kitchen!');},120); }
      else alert(d.message||'Failed to place order');
    }).catch(()=>{document.getElementById('rmOk').disabled=false;alert('Network error');});
}

// ---- Floor: orders + service + reservations ----
const NEXT={new:'accepted',accepted:'preparing',preparing:'ready',ready:'served',served:'completed'};
const NEXTLABEL={new:'Accept',accepted:'Prepare',preparing:'Ready',ready:'Served',served:'Complete'};
function pollFloor(){ pollOrders(); pollSvc(); pollResv(); }
function pollOrders(){
  fetch(B+'/api/order.php?action=list&status=new,accepted,preparing,ready,served',{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(res=>{
      if(!res||res.status!=='success') return;
      const os=(res.data.orders||[]);
      document.getElementById('ordWrap').innerHTML = os.length ? os.map(o=>{
        const items=(o.items||[]).map(i=>`\${i.qty}× \${esc(i.item_name)}`).join(', ');
        const nxt=NEXT[o.status];
        return `<div class="ord-card"><div class="d-flex justify-content-between">
          <div><b>\${o.table_no?('Table '+esc(o.table_no)):esc(o.order_type)}</b> · #\${esc(o.order_no)}
            <span class="badge bg-secondary">\${esc(o.status)}</span><div class="small text-muted">\${esc(items)}</div>
            <div class="small">\${money(o.total)}</div></div>
          <div class="text-end">\${nxt?`<button class="btn btn-sm btn-primary mb-1" onclick="adv(\${o.id},'\${nxt}')">\${NEXTLABEL[o.status]}</button><br>`:''}
            <button class="btn btn-sm btn-outline-danger" onclick="adv(\${o.id},'cancelled')">Cancel</button></div>
        </div></div>`;
      }).join('') : '<div class="text-muted small">No active orders.</div>';
    }).catch(()=>{});
}
window.adv=function(id,status){
  fetch(B+'/api/order.php?action=status',{method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF},
    body:new URLSearchParams({order_id:id,status:status,csrf_token:CSRF}).toString()}).then(r=>r.json()).then(()=>pollOrders());
};
const SVCLABEL={call:'🔔 Call Waiter',bill:'🧾 Bring Bill',water:'💧 Water',clean:'🧹 Clean Table'};
function pollSvc(){
  fetch(B+'/api/service.php?action=list',{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(res=>{
    if(!res||res.status!=='success') return;
    const rq=res.data.requests||[];
    const dot=document.getElementById('floorDot');
    if(rq.length){ dot.style.display='inline-block'; dot.textContent=rq.length; } else dot.style.display='none';
    document.getElementById('svcWrap').innerHTML=rq.map(r=>
      `<div class="svc \${r.type==='bill'?'bill':''}"><span class="fw-semibold">\${r.table_no?('Table '+esc(r.table_no)+' · '):''}\${SVCLABEL[r.type]||esc(r.type)}</span>`+
      `<button class="btn btn-sm btn-dark py-0" onclick="svcDone(\${r.id})">Done</button></div>`).join('');
  }).catch(()=>{});
}
window.svcDone=function(id){
  fetch(B+'/api/service.php?action=done',{method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF},
    body:new URLSearchParams({id:id,csrf_token:CSRF}).toString()}).then(r=>r.json()).then(()=>pollSvc());
};
function pollResv(){
  fetch(B+'/api/reserve.php?action=list&date='+new Date().toISOString().slice(0,10),{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(res=>{
      if(!res||res.status!=='success') return;
      const rs=res.data.reservations||[];
      document.getElementById('resvWrap').innerHTML = rs.length ? rs.map(r=>{
        const t=(r.reserve_time||'').slice(0,5);
        let btns='';
        if(r.status==='pending') btns+=`<button class="btn btn-sm btn-success py-0" onclick="resvSet(\${r.id},'confirmed')">Confirm</button> `;
        if(r.status!=='cancelled'&&r.status!=='seated') btns+=`<button class="btn btn-sm btn-outline-danger py-0" onclick="resvSet(\${r.id},'cancelled')">Cancel</button>`;
        return `<div class="ord-card d-flex justify-content-between"><div><b>\${t} · \${esc(r.customer_name)}</b> (\${r.party_size})
          <span class="badge bg-secondary">\${esc(r.status)}</span><div class="small text-muted">\${esc(r.customer_mobile)}\${r.note?' · '+esc(r.note):''}</div></div>
          <div class="text-end">\${btns}</div></div>`;
      }).join('') : '<div class="text-muted small">No reservations today.</div>';
    }).catch(()=>{});
}
window.resvSet=function(id,status){
  fetch(B+'/api/reserve.php?action=status',{method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF},
    body:new URLSearchParams({id:id,status:status,csrf_token:CSRF}).toString()}).then(r=>r.json()).then(()=>pollResv());
};

// keep the floor badge live even while taking orders
setInterval(pollSvc, 8000);
pollSvc();
if('serviceWorker' in navigator){ navigator.serviceWorker.register(B+'/app/sw.js').catch(()=>{}); }
</script>
HTML;
?>
</body></html>
