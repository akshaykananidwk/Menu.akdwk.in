<?php
/**
 * Waiter panel — mobile-first order taking.
 * Pick a table, tap menu items into a cart (qty + per-item notes), submit to
 * api/order.php?action=place with staff context. Shows own recent orders.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireStaff('waiter');

$tid    = (int)($_SESSION['staff_tenant_id'] ?? 0);
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $tid]);
$menu   = getTenantMenu($tid);
$tables = db_all('SELECT id, table_no, qr_token, status FROM ' . tbl('tables') . ' WHERE tenant_id = :t ORDER BY table_no', [':t' => $tid]);
$currency = $tenant['currency'] ?: '₹';

// Waiter's own recent orders.
$recent = db_all('SELECT o.*, t.table_no FROM ' . tbl('orders') . ' o
                  LEFT JOIN ' . tbl('tables') . ' t ON t.id = o.table_id
                  WHERE o.tenant_id = :t AND o.staff_id = :s
                  ORDER BY o.id DESC LIMIT 15', [':t' => $tid, ':s' => $staffId]);
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title>Waiter · <?= e($tenant['restaurant_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f2f4f8;padding-bottom:90px;font-family:system-ui}
.topbar{background:#1d3557;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:30}
.cat-h{font-weight:700;margin:14px 4px 6px;color:#1d3557}
.mitem{background:#fff;border-radius:12px;padding:10px 12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.mitem .add{min-width:52px;min-height:44px;font-size:1.3rem;border-radius:10px}
.tabbar{display:flex;overflow-x:auto;gap:8px;padding:10px;background:#fff;position:sticky;top:52px;z-index:20;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.cartbar{position:fixed;bottom:0;left:0;right:0;background:#e63946;color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;z-index:40;cursor:pointer}
.qtybtn{width:40px;height:40px;font-size:1.2rem}
.veg{width:12px;height:12px;border:2px solid #0a0;border-radius:2px;display:inline-block}
.veg.nv{border-color:#c00}
.tab-chip.active{background:#1d3557 !important;color:#fff !important}
</style>
</head><body>

<div class="topbar">
  <div><i class="bi bi-person-badge"></i> <strong><?= e($_SESSION['staff_name'] ?? 'Waiter') ?></strong>
    <div class="small opacity-75"><?= e($tenant['restaurant_name']) ?></div></div>
  <a href="<?= e(BASE_URL) ?>/waiter/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i></a>
</div>

<div class="p-3 bg-white">
  <label class="form-label small fw-bold mb-1">Select Table</label>
  <select id="tableSel" class="form-select form-select-lg">
    <option value="">— Choose table —</option>
    <?php foreach ($tables as $t): ?>
      <option value="<?= e($t['qr_token']) ?>" data-no="<?= e($t['table_no']) ?>">
        Table <?= e($t['table_no']) ?> (<?= e(ucfirst($t['status'])) ?>)</option>
    <?php endforeach; ?>
  </select>
</div>

<!-- Category quick-nav -->
<div class="tabbar" id="catbar">
  <?php foreach ($menu['categories'] as $i => $c): ?>
    <button class="btn btn-sm btn-light tab-chip <?= $i === 0 ? 'active' : '' ?>" data-cat="cat<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
  <?php endforeach; ?>
</div>

<main class="px-3 pt-2">
  <?php if (empty($menu['categories'])): ?>
    <div class="alert alert-info mt-3">No menu items yet.</div>
  <?php endif; ?>
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
        ], JSON_UNESCAPED_UNICODE);
      ?>
        <div class="mitem">
          <div>
            <span class="veg <?= $it['is_veg'] ? '' : 'nv' ?>"></span>
            <span class="fw-semibold"><?= e($it['name']) ?></span>
            <div class="text-muted small"><?= e($currency) . number_format($price, 0) ?><?= !empty($it['variants']) ? ' onwards' : '' ?></div>
          </div>
          <button class="btn btn-primary add" onclick='addItem(<?= e($itemJson) ?>)'>+</button>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <?php if ($recent): ?>
    <h6 class="cat-h mt-4">My Recent Orders</h6>
    <?php foreach ($recent as $o): ?>
      <div class="mitem">
        <div><strong>#<?= e($o['order_no']) ?></strong>
          <div class="small text-muted"><?= $o['table_no'] ? 'Table ' . e($o['table_no']) : e($o['order_type']) ?> · <?= e($currency) . number_format($o['total'], 0) ?></div></div>
        <span class="badge bg-secondary align-self-center"><?= e($o['status']) ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<div class="cartbar" id="cartbar" style="display:none" onclick="openCart()">
  <span><i class="bi bi-cart"></i> <span id="cartCount">0</span> items</span>
  <span><span id="cartTotal"><?= e($currency) ?>0</span> · Review <i class="bi bi-arrow-right"></i></span>
</div>

<!-- Modal -->
<div class="modal fade" id="wModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header py-2"><h6 class="modal-title" id="wmTitle"></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="wmBody"></div>
    <div class="modal-footer py-2"><button class="btn btn-primary" id="wmOk"></button></div>
  </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$base = BASE_URL;
$csrf = csrfToken();
$staffJs = (int)$staffId;
$curJs = json_encode($currency);
echo <<<HTML
<script>
const B='$base', CSRF='$csrf', STAFF_ID=$staffJs, CUR=$curJs;
const money=n=>CUR+Number(n||0).toFixed(0);
const esc=s=>(s==null?'':String(s)).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let cart=[];
const modal=new bootstrap.Modal(document.getElementById('wModal'));

// Category quick-nav.
document.querySelectorAll('#catbar .tab-chip').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('#catbar .tab-chip').forEach(x=>x.classList.remove('active'));
  b.classList.add('active');
  document.getElementById(b.dataset.cat)?.scrollIntoView({behavior:'smooth',block:'start'});
}));

function addItem(it){
  document.getElementById('wmTitle').textContent=it.name;
  let vh='';
  if(it.variants&&it.variants.length){ vh='<div class="mb-2"><label class="small fw-bold">Variant</label>'+
    it.variants.map((v,i)=>`<div class="form-check"><input class="form-check-input" type="radio" name="wv" value="\${i}" \${i===0?'checked':''}><label class="form-check-label">\${esc(v.label)} — \${money(v.price)}</label></div>`).join('')+'</div>'; }
  let ah='';
  if(it.addons&&it.addons.length){ ah='<div class="mb-2"><label class="small fw-bold">Add-ons</label>'+
    it.addons.map((a,i)=>`<div class="form-check"><input class="form-check-input wa" type="checkbox" value="\${i}"><label class="form-check-label">\${esc(a.name)} +\${money(a.price)}</label></div>`).join('')+'</div>'; }
  document.getElementById('wmBody').innerHTML=vh+ah+
    `<div class="mb-2"><label class="small fw-bold">Notes</label><input id="wNotes" class="form-control" placeholder="e.g. less spicy"></div>
     <div class="d-flex align-items-center gap-2"><label class="fw-bold">Qty</label>
       <button type="button" class="btn btn-outline-secondary qtybtn" onclick="stepQty(-1)">−</button>
       <input id="wQty" type="number" value="1" min="1" class="form-control text-center" style="width:70px">
       <button type="button" class="btn btn-outline-secondary qtybtn" onclick="stepQty(1)">+</button></div>`;
  const ok=document.getElementById('wmOk'); ok.textContent='Add to Cart'; ok.style.display='';
  ok.onclick=()=>{
    let price=it.price, variant=null;
    const vr=document.querySelector('input[name=wv]:checked');
    if(vr){ variant=it.variants[vr.value]; price=variant.price; }
    let addons=[]; document.querySelectorAll('.wa:checked').forEach(c=>{const a=it.addons[c.value];addons.push(a);price+=a.price;});
    const qty=Math.max(1,parseInt(document.getElementById('wQty').value)||1);
    cart.push({id:it.id,name:it.name,variant:variant?variant.label:null,addons,qty,price,notes:document.getElementById('wNotes').value});
    renderCart(); modal.hide();
  };
  modal.show();
}
window.stepQty=d=>{const q=document.getElementById('wQty');q.value=Math.max(1,(parseInt(q.value)||1)+d);};

function renderCart(){
  const count=cart.reduce((s,c)=>s+c.qty,0), total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  document.getElementById('cartCount').textContent=count;
  document.getElementById('cartTotal').textContent=money(total);
  document.getElementById('cartbar').style.display=count>0?'flex':'none';
}
window.openCart=function(){
  if(!cart.length) return;
  const tableSel=document.getElementById('tableSel');
  const rows=cart.map((c,i)=>`<div class="d-flex justify-content-between border-bottom py-2">
    <div><b>\${esc(c.name)}</b> \${c.variant?'('+esc(c.variant)+')':''}<br><small>\${c.qty} × \${money(c.price)}\${c.notes?' · '+esc(c.notes):''}</small></div>
    <div>\${money(c.qty*c.price)} <button class="btn btn-sm text-danger" onclick="cart.splice(\${i},1);renderCart();modal.hide();openCart();">&times;</button></div></div>`).join('');
  const total=cart.reduce((s,c)=>s+c.qty*c.price,0);
  document.getElementById('wmTitle').textContent='Review Order';
  document.getElementById('wmBody').innerHTML=rows+
    `<div class="text-end fw-bold my-2">Total: \${money(total)}</div>
     <input id="cName" class="form-control mb-2" placeholder="Customer name (optional)">
     <select id="cType" class="form-select"><option value="dinein">Dine-in</option><option value="takeaway">Takeaway</option></select>`;
  const ok=document.getElementById('wmOk'); ok.textContent='Submit Order'; ok.style.display='';
  ok.onclick=submitOrder;
  modal.show();
};
function submitOrder(){
  const tableSel=document.getElementById('tableSel');
  const token=tableSel.value;
  const type=document.getElementById('cType').value;
  if(type==='dinein' && !token){ alert('Please select a table.'); return; }
  const name=document.getElementById('cName').value.trim();
  const payload={slug:'',table_token:token,staff_id:STAFF_ID,
    customer_name:name||('Table '+(tableSel.selectedOptions[0]?.dataset.no||'')),
    customer_mobile:'',order_type:type,items:cart};
  document.getElementById('wmOk').disabled=true;
  fetch(B+'/api/order.php?action=place',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(r=>r.json()).then(d=>{
      document.getElementById('wmOk').disabled=false;
      if(d.status==='success'){ modal.hide(); cart=[]; renderCart();
        setTimeout(()=>{alert('Order #'+d.data.order_no+' sent to kitchen!');location.reload();},150); }
      else alert(d.message||'Failed to place order');
    }).catch(()=>{document.getElementById('wmOk').disabled=false;alert('Network error');});
}
</script>
HTML;
?>
</body></html>
