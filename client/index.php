<?php
/** Client dashboard: KPIs, plan usage, quick links, 7-day orders chart. */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid = currentTenantId();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

// --- KPI data (all tenant-scoped) --------------------------------------------
$todayOrders  = (int)db_val('SELECT COUNT(*) FROM ' . tbl('orders') . ' WHERE tenant_id = :t AND DATE(created_at) = CURDATE()', [':t' => $tid]);
$todayRevenue = (float)db_val("SELECT COALESCE(SUM(total),0) FROM " . tbl('orders') . " WHERE tenant_id = :t AND DATE(created_at) = CURDATE() AND status <> 'cancelled'", [':t' => $tid]);
$totalItems   = (int)db_val('SELECT COUNT(*) FROM ' . tbl('items') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tid]);
$totalCats    = (int)db_val('SELECT COUNT(*) FROM ' . tbl('categories') . ' WHERE tenant_id = :t AND status = 1', [':t' => $tid]);
$newOrders    = (int)db_val("SELECT COUNT(*) FROM " . tbl('orders') . " WHERE tenant_id = :t AND status = 'new'", [':t' => $tid]);

$plan      = tenantPlan($tid);
$itemLimit = checkPlanLimit($tid, 'items');
$pct       = $itemLimit['max'] > 0 ? min(100, round($itemLimit['used'] / $itemLimit['max'] * 100)) : 0;
$canOrder  = $tenant['ordering_mode'] !== 'view_only';

// --- Last 7 days orders (for the chart) --------------------------------------
$chartLabels = []; $chartData = [];
if ($canOrder) {
    $rows = db_all("SELECT DATE(created_at) d, COUNT(*) c FROM " . tbl('orders') . "
                    WHERE tenant_id = :t AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                    GROUP BY DATE(created_at)", [':t' => $tid]);
    $map = [];
    foreach ($rows as $r) { $map[$r['d']] = (int)$r['c']; }
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $chartLabels[] = date('D', strtotime($day));
        $chartData[]   = $map[$day] ?? 0;
    }
}

require __DIR__ . '/_header.php';
?>
<div class="row g-3 mb-1">
  <?php if ($canOrder): ?>
  <div class="col-6 col-lg-3"><div class="kpi-card kpi-1">
    <div class="kpi-val"><?= $todayOrders ?></div><div class="kpi-label"><i class="bi bi-receipt"></i> Today's Orders</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card kpi-2">
    <div class="kpi-val"><?= e(money($todayRevenue)) ?></div><div class="kpi-label"><i class="bi bi-cash-stack"></i> Today's Revenue</div></div></div>
  <?php endif; ?>
  <div class="col-6 col-lg-3"><div class="kpi-card kpi-3">
    <div class="kpi-val"><?= $totalItems ?></div><div class="kpi-label"><i class="bi bi-card-list"></i> Menu Items</div></div></div>
  <div class="col-6 col-lg-3"><div class="kpi-card kpi-4">
    <div class="kpi-val"><?= $totalCats ?></div><div class="kpi-label"><i class="bi bi-grid"></i> Categories</div></div></div>
</div>

<?php if ($canOrder && $newOrders > 0): ?>
<div class="alert alert-warning d-flex align-items-center justify-content-between">
  <span><i class="bi bi-bell-fill"></i> You have <strong><?= $newOrders ?></strong> new order(s) waiting.</span>
  <a href="<?= e(BASE_URL) ?>/client/orders.php" class="btn btn-sm btn-warning">View Orders</a>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- Plan usage -->
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-gem"></i> Your Plan</h6>
      <div class="d-flex justify-content-between mb-1">
        <span class="badge bg-primary fs-6"><?= e($plan['name'] ?? 'No plan') ?></span>
        <small class="text-muted">Expires: <?= e($tenant['expiry_date'] ?: '—') ?></small>
      </div>
      <div class="mt-3">
        <div class="d-flex justify-content-between small mb-1">
          <span>Menu items used</span>
          <span><?= $itemLimit['used'] ?> / <?= $itemLimit['max'] ?></span>
        </div>
        <div class="progress" style="height:10px;">
          <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
        </div>
        <?php if ($pct >= 90): ?>
          <small class="text-danger">You are close to your plan limit. Consider upgrading.</small>
        <?php endif; ?>
      </div>
      <a href="<?= e(BASE_URL) ?>/client/settings.php" class="btn btn-outline-primary btn-sm mt-3">Manage Plan</a>
    </div></div>
  </div>

  <!-- Quick links -->
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-lightning-charge"></i> Quick Actions</h6>
      <div class="row g-2">
        <div class="col-6 col-md-4"><a href="<?= e(BASE_URL) ?>/client/menu.php" class="btn btn-light w-100 py-3 border"><i class="bi bi-plus-circle d-block fs-4 text-primary"></i> Add Item</a></div>
        <div class="col-6 col-md-4"><a href="<?= e(BASE_URL) ?>/client/ai_extract.php" class="btn btn-light w-100 py-3 border"><i class="bi bi-magic d-block fs-4 text-primary"></i> AI Import</a></div>
        <div class="col-6 col-md-4"><a href="<?= e(BASE_URL) ?>/client/design.php" class="btn btn-light w-100 py-3 border"><i class="bi bi-palette d-block fs-4 text-primary"></i> Design</a></div>
        <div class="col-6 col-md-4"><a href="<?= e(publicMenuUrl($tenant['slug'])) ?>" target="_blank" class="btn btn-light w-100 py-3 border"><i class="bi bi-box-arrow-up-right d-block fs-4 text-primary"></i> View Menu</a></div>
        <div class="col-6 col-md-4"><a href="<?= e(BASE_URL) ?>/client/qr.php" class="btn btn-light w-100 py-3 border"><i class="bi bi-qr-code d-block fs-4 text-primary"></i> QR Code</a></div>
        <div class="col-6 col-md-4"><a href="<?= e(BASE_URL) ?>/client/settings.php" class="btn btn-light w-100 py-3 border"><i class="bi bi-gear d-block fs-4 text-primary"></i> Settings</a></div>
      </div>
    </div></div>
  </div>

  <?php if ($canOrder): ?>
  <!-- 7-day orders chart -->
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up"></i> Orders — Last 7 Days</h6>
      <canvas id="ordersChart" height="80"></canvas>
    </div></div>
  </div>
  <?php endif; ?>
</div>

<?php
$pageScript = '';
if ($canOrder) {
    $pageScript = '<script>
    (function(){
      const ctx = document.getElementById("ordersChart");
      if(!ctx) return;
      new Chart(ctx, {
        type: "line",
        data: {
          labels: ' . json_encode($chartLabels) . ',
          datasets: [{
            label: "Orders",
            data: ' . json_encode($chartData) . ',
            borderColor: "var(--primary)",
            backgroundColor: "rgba(230,57,70,.12)",
            fill: true, tension: .35, pointRadius: 4
          }]
        },
        options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}} }
      });
    })();
    </script>';
}
require __DIR__ . '/_footer.php';
