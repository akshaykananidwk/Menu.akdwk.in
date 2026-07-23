<?php
/**
 * Client — Repeat Customers (CRM).
 * Groups every non-cancelled order by customer mobile to show who your regulars
 * are: visits, total spend, average order, last visit, favourite items + a
 * one-tap WhatsApp link. All data is derived live from orders (no new tables).
 */
$pageTitle = 'Customers';
$activeNav = 'customers';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
$currency = $tenant['currency'] ?: '₹';
?>
<?php if (!$canOrder): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> The customer book fills up once online ordering is enabled — customers are recognised by the mobile number they enter while ordering.</div>
<?php else: ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small"><i class="bi bi-people"></i> Total Customers</div><div class="h4 mb-0" id="tCustomers">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small"><i class="bi bi-arrow-repeat"></i> Repeat Customers</div><div class="h4 mb-0 text-success" id="tRepeat">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small"><i class="bi bi-receipt"></i> Total Orders</div><div class="h4 mb-0" id="tOrders">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small"><i class="bi bi-cash-stack"></i> Lifetime Revenue</div><div class="h4 mb-0" id="tRevenue">—</div></div></div></div>
</div>

<div class="card mb-3"><div class="card-body">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-6">
      <label class="form-label small">Search by name or mobile</label>
      <input type="search" id="cSearch" class="form-control form-control-sm" placeholder="Type a name or number…">
    </div>
    <div class="col-12 col-md-6 d-flex gap-2 flex-wrap justify-content-md-end">
      <a class="btn btn-sm btn-outline-success" id="exportBtn" href="<?= e(BASE_URL) ?>/api/reports.php?action=export_customers"><i class="bi bi-filetype-csv"></i> Export Excel</a>
    </div>
  </div>
</div></div>

<div class="card"><div class="card-body">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0" id="custTable">
      <thead><tr>
        <th>#</th><th>Customer</th><th>Mobile</th>
        <th class="text-end">Visits</th><th class="text-end">Spend</th>
        <th class="text-end">Avg</th><th>Last Visit</th><th></th>
      </tr></thead>
      <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr></tbody>
    </table>
  </div>
</div></div>

<!-- Detail modal -->
<div class="modal fade" id="custModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable">
  <div class="modal-content">
    <div class="modal-header">
      <h6 class="modal-title" id="cmName">Customer</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="d-flex justify-content-between mb-3">
        <a id="cmWa" href="#" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i> Message</a>
        <span class="text-muted small" id="cmMobile"></span>
      </div>
      <h6 class="small text-uppercase text-muted">Favourite Items</h6>
      <div id="cmFavs" class="mb-3 d-flex flex-wrap gap-2"></div>
      <h6 class="small text-uppercase text-muted">Recent Orders</h6>
      <div class="table-responsive">
        <table class="table table-sm mb-0"><thead><tr><th>Order</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr></thead>
          <tbody id="cmOrders"></tbody></table>
      </div>
    </div>
  </div>
</div></div>

<?php
$base = BASE_URL;
$cur  = json_encode($currency);
$pageScript = <<<HTML
<script>
const B = '$base', CUR = $cur;
const money = n => CUR + Number(n||0).toFixed(0);
const esc = s => String(s==null?'':s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const waNum = m => { let d=String(m||'').replace(/\\D/g,''); if(d.length===10) d='91'+d; else if(d.length===11&&d[0]==='0') d='91'+d.slice(1); return d; };
let searchTimer;

function load(q){
  AK.get(B+'/api/reports.php?action=customers'+(q?('&q='+encodeURIComponent(q)):'')).then(res=>{
    if(!res || res.status!=='success') return;
    const d = res.data, t = d.totals||{};
    document.getElementById('tCustomers').textContent = t.customers||0;
    document.getElementById('tRepeat').textContent = d.repeat_count||0;
    document.getElementById('tOrders').textContent = t.orders||0;
    document.getElementById('tRevenue').textContent = money(t.revenue);
    const tb = document.querySelector('#custTable tbody');
    const rows = d.customers||[];
    tb.innerHTML = rows.length ? rows.map((c,i)=>{
      const wa = waNum(c.customer_mobile);
      return `<tr>
        <td>\${i+1}</td>
        <td class="fw-semibold">\${esc(c.customer_name||'Guest')}</td>
        <td>\${esc(c.customer_mobile)}</td>
        <td class="text-end"><span class="badge bg-\${c.visits>1?'success':'secondary'}">\${c.visits}</span></td>
        <td class="text-end">\${money(c.spend)}</td>
        <td class="text-end">\${money(c.avg_order)}</td>
        <td class="small">\${(c.last_visit||'').slice(0,10)}</td>
        <td class="text-nowrap">
          <a href="https://wa.me/\${wa}" target="_blank" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-whatsapp"></i></a>
          <button class="btn btn-sm btn-outline-primary py-0" onclick='detail(\${JSON.stringify(c.customer_mobile)}, \${JSON.stringify(c.customer_name||"Guest")})'><i class="bi bi-eye"></i></button>
        </td></tr>`;
    }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No customers yet — they appear here after their first order.</td></tr>';
  });
}

function detail(mobile, name){
  document.getElementById('cmName').textContent = name;
  document.getElementById('cmMobile').textContent = mobile;
  document.getElementById('cmWa').href = 'https://wa.me/'+waNum(mobile);
  document.getElementById('cmFavs').innerHTML = '<span class="text-muted small">Loading…</span>';
  document.getElementById('cmOrders').innerHTML = '';
  new bootstrap.Modal(document.getElementById('custModal')).show();
  AK.get(B+'/api/reports.php?action=customer_detail&mobile='+encodeURIComponent(mobile)).then(res=>{
    if(!res || res.status!=='success') return;
    const favs = res.data.favourites||[], orders = res.data.orders||[];
    document.getElementById('cmFavs').innerHTML = favs.length
      ? favs.map(f=>`<span class="badge bg-light text-dark border">\${esc(f.item_name)} <span class="text-muted">×\${f.qty}</span></span>`).join('')
      : '<span class="text-muted small">No items recorded.</span>';
    document.getElementById('cmOrders').innerHTML = orders.length
      ? orders.map(o=>`<tr><td>\${esc(o.order_no)}</td><td class="small">\${(o.created_at||'').slice(0,16)}</td>`+
          `<td class="text-end">\${money(o.total)}</td><td><span class="badge bg-secondary">\${esc(o.status)}</span></td></tr>`).join('')
      : '<tr><td colspan="4" class="text-muted">No orders.</td></tr>';
  });
}

document.getElementById('cSearch').addEventListener('input', function(){
  clearTimeout(searchTimer);
  const q = this.value.trim();
  searchTimer = setTimeout(()=>load(q), 300);
});
load('');
</script>
HTML;
endif;
require __DIR__ . '/_footer.php';
?>
