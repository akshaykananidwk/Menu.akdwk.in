<?php
/**
 * Client — Sales reports. Date-range totals, daily trend, top items,
 * payment breakdown (Chart.js) + CSV export and print.
 */
$pageTitle = 'Reports';
$activeNav = 'reports';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
$currency = $tenant['currency'] ?: '₹';
$from = date('Y-m-d', strtotime('-29 days'));
$to   = date('Y-m-d');
?>
<?php if (!$canOrder): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> Reports are available once online ordering is enabled for your plan. This account is in <strong>view-only</strong> mode.</div>
<?php else: ?>

<!-- Filters -->
<div class="card mb-3"><div class="card-body">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small">From</label><input type="date" id="rFrom" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small">To</label><input type="date" id="rTo" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
    <div class="col-12 col-md-6 d-flex gap-2 flex-wrap">
      <button class="btn btn-sm btn-primary" id="applyBtn"><i class="bi bi-funnel"></i> Apply</button>
      <a class="btn btn-sm btn-outline-success" id="exportBtn" href="#"><i class="bi bi-filetype-csv"></i> Export Excel</a>
      <button class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
    </div>
  </div>
</div></div>

<!-- Summary tiles -->
<div class="row g-3 mb-3" id="tiles">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small">Revenue</div><div class="h4 mb-0" id="tRevenue">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small">Orders</div><div class="h4 mb-0" id="tOrders">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small">Avg Order</div><div class="h4 mb-0" id="tAvg">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3">
    <div class="text-muted small">Tax + Service</div><div class="h4 mb-0" id="tTax">—</div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body">
    <h6 class="card-title">Daily Sales Trend</h6><canvas id="salesChart" height="110"></canvas>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <h6 class="card-title">Payment Modes</h6><canvas id="payChart" height="180"></canvas>
  </div></div></div>
</div>

<div class="card mt-3"><div class="card-body">
  <h6 class="card-title">Top Selling Items</h6>
  <div class="table-responsive">
    <table class="table table-sm" id="topTable"><thead><tr><th>#</th><th>Item</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
      <tbody></tbody></table>
  </div>
</div></div>

<?php
$base = BASE_URL;
$cur  = json_encode($currency);
$pageScript = <<<HTML
<script>
const B = '$base', CUR = $cur;
const money = n => CUR + Number(n||0).toFixed(2);
let salesChart, payChart;

function range(){ return 'from='+document.getElementById('rFrom').value+'&to='+document.getElementById('rTo').value; }

function load(){
  AK.get(B+'/api/reports.php?action=sales&'+range()).then(res=>{
    if(!res || res.status!=='success') return;
    const d = res.data, s = d.summary||{};
    document.getElementById('tRevenue').textContent = money(s.revenue);
    document.getElementById('tOrders').textContent = s.orders||0;
    document.getElementById('tAvg').textContent = money(s.avg_order);
    document.getElementById('tTax').textContent = money((+s.tax||0)+(+s.service||0));
    drawSales(d.series||[]);
    drawPay(d.payments||[]);
  });
  AK.get(B+'/api/reports.php?action=top_items&'+range()).then(res=>{
    if(!res || res.status!=='success') return;
    const tb = document.querySelector('#topTable tbody');
    tb.innerHTML = (res.data.items||[]).map((it,i)=>
      `<tr><td>\${i+1}</td><td>\${(it.item_name||'').replace(/</g,'&lt;')}</td>`+
      `<td class="text-end">\${it.qty}</td><td class="text-end">\${money(it.revenue)}</td></tr>`).join('')
      || '<tr><td colspan="4" class="text-muted text-center">No sales in this range.</td></tr>';
  });
}

function drawSales(series){
  const labels = series.map(r=>r.d), rev = series.map(r=>+r.revenue);
  if(salesChart) salesChart.destroy();
  salesChart = new Chart(document.getElementById('salesChart'), {
    type:'line',
    data:{labels, datasets:[{label:'Revenue', data:rev, borderColor:'#e63946', backgroundColor:'rgba(230,57,70,.1)', fill:true, tension:.3}]},
    options:{plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
  });
}
function drawPay(pay){
  const labels = pay.map(r=>r.payment_mode), data = pay.map(r=>+r.revenue);
  if(payChart) payChart.destroy();
  payChart = new Chart(document.getElementById('payChart'), {
    type:'doughnut',
    data:{labels, datasets:[{data, backgroundColor:['#e63946','#1d3557','#f1a208','#2a9d8f']}]},
    options:{plugins:{legend:{position:'bottom'}}}
  });
}

document.getElementById('applyBtn').addEventListener('click', load);
document.getElementById('exportBtn').addEventListener('click', function(e){
  e.preventDefault();
  window.location = B+'/api/reports.php?action=export&'+range();
});
load();
</script>
HTML;
endif;
require __DIR__ . '/_footer.php';
?>
