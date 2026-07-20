<?php
/**
 * Client — Live Orders board (Kanban) + history.
 * Polls api/order.php?action=list every 5s, plays a sound on new orders,
 * supports status advance, KOT/Bill printing, and a filterable history table.
 */
$pageTitle = 'Orders';
$activeNav = 'orders';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
$currency = $tenant['currency'] ?: '₹';
?>

<?php if (!$canOrder): ?>
  <div class="alert alert-warning">
    <i class="bi bi-info-circle"></i> This account is in <strong>view-only</strong> mode. Online ordering is disabled, so there are no live orders to manage.
  </div>
<?php else: ?>

<!-- Toolbar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-success-subtle text-success"><i class="bi bi-broadcast"></i> Live</span>
    <span class="text-muted small">Auto-refreshing every 5s</span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="form-check form-switch mb-0">
      <input class="form-check-input" type="checkbox" id="soundToggle" checked>
      <label class="form-check-label small" for="soundToggle"><i class="bi bi-volume-up"></i> Sound</label>
    </div>
    <button class="btn btn-sm btn-outline-secondary" id="notifyBtn"><i class="bi bi-bell"></i> Enable alerts</button>
  </div>
</div>

<!-- Kanban board -->
<div class="orders-board d-flex gap-3 pb-2" id="board" style="overflow-x:auto">
  <?php
  $columns = [
    'new'       => ['New', 'secondary'],
    'accepted'  => ['Accepted', 'info'],
    'preparing' => ['Preparing', 'warning'],
    'ready'     => ['Ready', 'primary'],
    'served'    => ['Served', 'success'],
    'done'      => ['Completed / Cancelled', 'dark'],
  ];
  foreach ($columns as $key => $col): ?>
    <div class="kanban-col flex-shrink-0" style="width:300px" data-col="<?= e($key) ?>">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><span class="badge bg-<?= e($col[1]) ?>"><?= e($col[0]) ?></span></h6>
        <span class="badge bg-light text-dark" id="count-<?= e($key) ?>">0</span>
      </div>
      <div class="kanban-cards d-flex flex-column gap-2" id="col-<?= e($key) ?>"></div>
    </div>
  <?php endforeach; ?>
</div>

<hr class="my-4">

<!-- History -->
<h5 class="mb-3"><i class="bi bi-clock-history"></i> Order History</h5>
<div class="card mb-3"><div class="card-body">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small">From</label><input type="date" id="hFrom" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small">To</label><input type="date" id="hTo" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small">Status</label>
      <select id="hStatus" class="form-select form-select-sm">
        <option value="">All</option>
        <?php foreach (['new','accepted','preparing','ready','served','completed','cancelled'] as $s): ?>
          <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2"><label class="form-label small">Table</label><input type="text" id="hTable" class="form-control form-control-sm" placeholder="Table no"></div>
    <div class="col-12 col-md-4 d-flex gap-2">
      <button class="btn btn-sm btn-primary" id="hApply"><i class="bi bi-funnel"></i> Apply</button>
      <button class="btn btn-sm btn-outline-secondary" id="hReset">Reset</button>
    </div>
  </div>
</div></div>
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle" id="historyTable" style="width:100%">
    <thead><tr>
      <th>Order</th><th>Table/Type</th><th>Items</th><th>Total</th><th>Status</th><th>Time</th><th></th>
    </tr></thead>
    <tbody></tbody>
  </table>
</div>

<!-- Order detail modal -->
<div class="modal fade" id="orderModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header py-2"><h6 class="modal-title" id="omTitle">Order</h6>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="omBody"></div>
    <div class="modal-footer py-2 flex-wrap gap-1" id="omFooter"></div>
  </div>
</div></div>

<audio id="dingSound" preload="auto" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU"></audio>

<?php
$cfg = json_encode([
  'base'     => BASE_URL,
  'currency' => $currency,
  'gstNo'    => $tenant['gst_no'],
  'cgst'     => (float)$tenant['cgst'],
  'sgst'     => (float)$tenant['sgst'],
  'svc'      => (float)$tenant['service_charge'],
  'name'     => $tenant['restaurant_name'],
  'address'  => $tenant['address'],
  'mobile'   => $tenant['mobile'],
], JSON_UNESCAPED_UNICODE);
?>
<?php $pageScript = <<<HTML
<script>
const CFG = {$cfg};
const money = n => CFG.currency + Number(n||0).toFixed(2);
const esc = s => (s==null?'':String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let lastMaxId = 0, firstLoad = true, allOrders = [], dt = null;

// Map DB status -> board column key.
function colOf(s){ if(s==='completed'||s==='cancelled') return 'done'; return s; }

// Next status buttons per current status.
const FLOW = {new:['accepted','cancelled'], accepted:['preparing','cancelled'], preparing:['ready'], ready:['served'], served:['completed'], completed:[], cancelled:[]};
const LABEL = {accepted:'Accept', preparing:'Start Preparing', ready:'Mark Ready', served:'Mark Served', completed:'Complete', cancelled:'Cancel'};

function itemsSummary(o){ return (o.items||[]).map(i => i.qty+'× '+esc(i.item_name)).join(', '); }

function cardHtml(o){
  return `<div class="card order-card shadow-sm" style="cursor:pointer" onclick="openOrder(\${o.id})">
    <div class="card-body p-2">
      <div class="d-flex justify-content-between">
        <strong>#\${esc(o.order_no)}</strong>
        <small class="text-muted">\${timeAgo(o.created_at)}</small>
      </div>
      <div class="small text-muted">\${o.table_no?('Table '+esc(o.table_no)):esc(o.order_type)}</div>
      <div class="small text-truncate">\${itemsSummary(o)}</div>
      <div class="fw-semibold mt-1">\${money(o.total)}</div>
    </div></div>`;
}

function timeAgo(ts){
  const d = new Date((ts||'').replace(' ','T'));
  const m = Math.floor((Date.now()-d.getTime())/60000);
  if(isNaN(m)) return '';
  if(m<1) return 'just now'; if(m<60) return m+'m ago';
  return Math.floor(m/60)+'h '+(m%60)+'m';
}

function render(orders){
  allOrders = orders;
  const cols = {new:[],accepted:[],preparing:[],ready:[],served:[],done:[]};
  orders.forEach(o => { const c = colOf(o.status); if(cols[c]) cols[c].push(o); });
  Object.keys(cols).forEach(k => {
    document.getElementById('col-'+k).innerHTML = cols[k].map(cardHtml).join('') || '<div class="text-muted small text-center py-3">—</div>';
    document.getElementById('count-'+k).textContent = cols[k].length;
  });
  renderHistory();
}

function poll(){
  AK.get(CFG.base+'/api/order.php?action=list').then(res => {
    if(!res || res.status!=='success') return;
    const orders = res.data.orders||[];
    const maxId = orders.reduce((m,o)=>Math.max(m,+o.id),0);
    // New order arrived since last poll -> alert.
    if(!firstLoad && maxId>lastMaxId){ notifyNew(orders.find(o=>+o.id===maxId)); }
    lastMaxId = Math.max(lastMaxId, maxId);
    firstLoad = false;
    render(orders);
  });
}

function notifyNew(o){
  if(document.getElementById('soundToggle').checked){
    try{ document.getElementById('dingSound').play(); }catch(e){}
  }
  AK.toast('info','New order #'+(o?o.order_no:''));
  if(window.Notification && Notification.permission==='granted' && o){
    new Notification('New order #'+o.order_no, {body:(o.table_no?'Table '+o.table_no+' · ':'')+money(o.total)});
  }
}

// ---- Detail modal ----
let omModal;
function openOrder(id){
  const o = allOrders.find(x=>+x.id===+id); if(!o) return;
  document.getElementById('omTitle').textContent = 'Order #'+o.order_no;
  let rows = (o.items||[]).map(i=>{
    let ad=''; try{ (JSON.parse(i.addons_json)||[]).forEach(a=>ad+=' + '+esc(a.name)); }catch(e){}
    return `<tr><td>\${esc(i.item_name)}\${i.variant_label?' ('+esc(i.variant_label)+')':''}\${ad}\${i.notes?'<br><small class="text-danger">'+esc(i.notes)+'</small>':''}</td>
      <td class="text-center">\${i.qty}</td><td class="text-end">\${money(i.total)}</td></tr>`;
  }).join('');
  document.getElementById('omBody').innerHTML = `
    <div class="small mb-2">
      <div><b>Customer:</b> \${esc(o.customer_name)||'-'} \${o.customer_mobile?('· '+esc(o.customer_mobile)):''}</div>
      <div><b>Type:</b> \${esc(o.order_type)} \${o.table_no?('· Table '+esc(o.table_no)):''} \${o.staff_name?('· by '+esc(o.staff_name)):''}</div>
      <div><b>Status:</b> <span class="badge bg-secondary">\${esc(o.status)}</span></div>
    </div>
    <table class="table table-sm"><thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Total</th></tr></thead>
    <tbody>\${rows}</tbody>
    <tfoot>
      <tr><td colspan="2" class="text-end">Subtotal</td><td class="text-end">\${money(o.subtotal)}</td></tr>
      <tr><td colspan="2" class="text-end">Tax</td><td class="text-end">\${money(o.tax)}</td></tr>
      <tr><td colspan="2" class="text-end">Service</td><td class="text-end">\${money(o.service_charge)}</td></tr>
      <tr class="fw-bold"><td colspan="2" class="text-end">Total</td><td class="text-end">\${money(o.total)}</td></tr>
    </tfoot></table>`;
  const foot = document.getElementById('omFooter');
  let btns = (FLOW[o.status]||[]).map(ns=>`<button class="btn btn-sm \${ns==='cancelled'?'btn-outline-danger':'btn-primary'}" onclick="advance(\${o.id},'\${ns}')">\${LABEL[ns]}</button>`).join('');
  foot.innerHTML = btns +
    `<button class="btn btn-sm btn-outline-secondary" onclick="printKOT(\${o.id})"><i class="bi bi-printer"></i> KOT</button>
     <button class="btn btn-sm btn-outline-dark" onclick="printBill(\${o.id})"><i class="bi bi-receipt"></i> Bill</button>`;
  omModal = omModal || new bootstrap.Modal(document.getElementById('orderModal'));
  omModal.show();
}

function advance(id,status){
  AK.post(CFG.base+'/api/order.php?action=status', {order_id:id, status:status}).then(res=>{
    AK.handle(res, ()=>{ omModal.hide(); poll(); });
  });
}

// ---- Printing (thermal-friendly popups) ----
function printWindow(html){
  const w = window.open('', '_blank', 'width=380,height=600');
  w.document.write('<html><head><title>Print</title><style>'+
    'body{font-family:monospace;font-size:12px;width:76mm;margin:0 auto;padding:6px}'+
    'h3,h4{text-align:center;margin:2px 0}.line{border-top:1px dashed #000;margin:4px 0}'+
    'table{width:100%;border-collapse:collapse}td{vertical-align:top}.r{text-align:right}.c{text-align:center}'+
    '@media print{@page{margin:0}}</style></head><body>'+html+
    '<script>window.onload=function(){window.print();}<\\/script></body></html>');
  w.document.close();
}
function printKOT(id){
  const o = allOrders.find(x=>+x.id===+id); if(!o) return;
  let rows = (o.items||[]).map(i=>{
    let ad=''; try{(JSON.parse(i.addons_json)||[]).forEach(a=>ad+=' +'+esc(a.name));}catch(e){}
    return `<tr><td>\${i.qty} × \${esc(i.item_name)}\${i.variant_label?' ('+esc(i.variant_label)+')':''}\${ad}`+
      `\${i.notes?'<br><b>* '+esc(i.notes)+'</b>':''}</td></tr>`;
  }).join('');
  printKOT._ = 1;
  printWindow(`<h3>*** KOT ***</h3><h4>\${esc(CFG.name)}</h4>
    <div class="c">#\${esc(o.order_no)} · \${new Date().toLocaleString()}</div>
    <div class="c">\${o.table_no?('Table '+esc(o.table_no)):esc(o.order_type)}</div>
    <div class="line"></div><table>\${rows}</table><div class="line"></div>
    <div class="c">\${esc(o.order_type)}</div>`);
}
function printBill(id){
  const o = allOrders.find(x=>+x.id===+id); if(!o) return;
  let rows = (o.items||[]).map(i=>`<tr><td>\${esc(i.item_name)}\${i.variant_label?' ('+esc(i.variant_label)+')':''}</td>`+
    `<td class="c">\${i.qty}</td><td class="r">\${money(i.total)}</td></tr>`).join('');
  printWindow(`<h3>\${esc(CFG.name)}</h3>\${CFG.address?('<div class="c">'+esc(CFG.address)+'</div>'):''}
    \${CFG.mobile?('<div class="c">Ph: '+esc(CFG.mobile)+'</div>'):''}
    \${CFG.gstNo?('<div class="c">GSTIN: '+esc(CFG.gstNo)+'</div>'):''}
    <div class="line"></div><div class="c">Bill · #\${esc(o.order_no)} · \${new Date().toLocaleString()}</div>
    <div>\${o.table_no?('Table '+esc(o.table_no)):esc(o.order_type)} · \${esc(o.customer_name)||''}</div>
    <div class="line"></div>
    <table><tr><td><b>Item</b></td><td class="c"><b>Qty</b></td><td class="r"><b>Amt</b></td></tr>\${rows}</table>
    <div class="line"></div>
    <table>
      <tr><td>Subtotal</td><td class="r">\${money(o.subtotal)}</td></tr>
      <tr><td>CGST (\${CFG.cgst}%)</td><td class="r">\${money(o.tax/2)}</td></tr>
      <tr><td>SGST (\${CFG.sgst}%)</td><td class="r">\${money(o.tax/2)}</td></tr>
      <tr><td>Service (\${CFG.svc}%)</td><td class="r">\${money(o.service_charge)}</td></tr>
      <tr><td><b>TOTAL</b></td><td class="r"><b>\${money(o.total)}</b></td></tr>
    </table><div class="line"></div><div class="c">Thank you! Visit again.</div>`);
}

// ---- History DataTable ----
function renderHistory(){
  const f = document.getElementById('hFrom').value, t = document.getElementById('hTo').value;
  const st = document.getElementById('hStatus').value, tbl = document.getElementById('hTable').value.trim().toLowerCase();
  const rows = allOrders.filter(o=>{
    const d = (o.created_at||'').slice(0,10);
    if(f && d<f) return false; if(t && d>t) return false;
    if(st && o.status!==st) return false;
    if(tbl && !String(o.table_no||'').toLowerCase().includes(tbl)) return false;
    return true;
  });
  const data = rows.map(o=>[
    esc(o.order_no),
    o.table_no?('Table '+esc(o.table_no)):esc(o.order_type),
    esc(itemsSummary(o)),
    money(o.total),
    '<span class="badge bg-secondary">'+esc(o.status)+'</span>',
    esc((o.created_at||'').replace('T',' ')),
    '<button class="btn btn-sm btn-outline-primary" onclick="openOrder('+o.id+')">View</button>'
  ]);
  if(dt){ dt.clear(); dt.rows.add(data); dt.draw(false); }
  else { dt = new DataTable('#historyTable', {data:data, order:[[5,'desc']], pageLength:10,
           columnDefs:[{targets:[6],orderable:false}]}); }
}

document.getElementById('hApply').addEventListener('click', renderHistory);
document.getElementById('hReset').addEventListener('click', ()=>{
  ['hFrom','hTo','hTable'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('hStatus').value=''; renderHistory();
});
document.getElementById('notifyBtn').addEventListener('click', ()=>{
  if(window.Notification) Notification.requestPermission().then(()=>AK.toast('success','Alerts enabled'));
});

poll();
setInterval(poll, 5000);
</script>
HTML;
require __DIR__ . '/_footer.php';
endif;
?>
<?php if (!$canOrder) { require __DIR__ . '/_footer.php'; } ?>
