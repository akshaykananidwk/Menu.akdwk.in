<?php
/**
 * Super Admin dashboard.
 * KPI cards, signup trend chart, recent activity, and expiring-soon clients.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

/**
 * SAFE auto-migrator (best-effort). git-pull deployments never run the update
 * pipeline, so any updates/*.sql not yet recorded in the `migrations` table is
 * applied here on dashboard load. Uses the same table + file_name key as
 * api/update.php → ak_run_migrations to avoid double-runs. Wrapped so it can
 * NEVER break the page; migrations are additive/idempotent by contract.
 */
function ak_admin_auto_migrate(): void {
    global $pdo;
    try {
        $dir = ROOT_PATH . '/updates';
        if (!is_dir($dir)) return;
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : '';

        $files = [];
        foreach (glob($dir . '/*.sql') ?: [] as $f) {
            $base = basename($f);
            if (!preg_match('/^(\d+\.\d+\.\d+)\.sql$/', $base, $m)) continue;
            $files[] = ['file' => $f, 'base' => $base, 'ver' => $m[1]];
        }
        // Run in ascending version order for deterministic application.
        usort($files, fn($a, $b) => version_compare($a['ver'], $b['ver']));

        foreach ($files as $mig) {
            // Skip anything already recorded (unique file_name gate).
            $done = (int)db_val('SELECT COUNT(*) FROM ' . tbl('migrations') . ' WHERE file_name = :f', [':f' => $mig['base']]);
            if ($done > 0) continue;

            $raw = @file_get_contents($mig['file']);
            if ($raw === false) continue;
            $raw = str_replace('{PREFIX}', $prefix, $raw);

            // Split into statements, ignoring blank/comment lines.
            $buf = ''; $stmts = [];
            foreach (preg_split('/\r\n|\n|\r/', $raw) as $line) {
                $t = trim($line);
                if ($t === '' || strncmp($t, '--', 2) === 0) continue;
                $buf .= $line . "\n";
                if (substr(rtrim($line), -1) === ';') { $stmts[] = rtrim(rtrim($buf), ";\n "); $buf = ''; }
            }
            if (trim($buf) !== '') { $stmts[] = rtrim(trim($buf), ';'); }

            try {
                foreach ($stmts as $stmt) { if (trim($stmt) !== '') { $pdo->exec($stmt); } }
                db_insert('migrations', ['version' => $mig['ver'], 'file_name' => $mig['base']]);
            } catch (Throwable $e) {
                // Best-effort: log and move on without recording (so it retries later).
                error_log('auto-migrate ' . $mig['base'] . ' failed: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('ak_admin_auto_migrate error: ' . $e->getMessage());
    }
}
ak_admin_auto_migrate();

$today = date('Y-m-d');

// ---- KPI figures ----
$totalClients = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants'));

$activeClients = (int)db_val(
    'SELECT COUNT(*) FROM ' . tbl('tenants') . "
     WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date >= :d)",
    [':d' => $today]
);

$expiredClients = (int)db_val(
    'SELECT COUNT(*) FROM ' . tbl('tenants') . "
     WHERE status = 'expired' OR (expiry_date IS NOT NULL AND expiry_date < :d)",
    [':d' => $today]
);

// Tenants that have at least one menu item.
$totalMenus = (int)db_val('SELECT COUNT(DISTINCT tenant_id) FROM ' . tbl('items'));

$ordersToday = (int)db_val(
    'SELECT COUNT(*) FROM ' . tbl('orders') . ' WHERE DATE(created_at) = :d',
    [':d' => $today]
);

$revenueMonth = (float)db_val(
    'SELECT COALESCE(SUM(total),0) FROM ' . tbl('orders') . "
     WHERE status = 'completed'
       AND YEAR(created_at) = :y AND MONTH(created_at) = :m",
    [':y' => date('Y'), ':m' => date('n')]
);

// ---- Platform-wide menu scan analytics (the growth metric) ----
$scansToday = (int)db_val('SELECT COALESCE(SUM(views),0) FROM ' . tbl('menu_views') . ' WHERE view_date = :d', [':d' => $today]);
$scansTotal = (int)db_val('SELECT COALESCE(SUM(views),0) FROM ' . tbl('menu_views'));
// Daily opens, last 30 days (platform-wide).
$scanTrend = [];
$srows = db_all('SELECT view_date d, SUM(views) v FROM ' . tbl('menu_views') . '
                 WHERE view_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY view_date');
$smap = []; foreach ($srows as $r) { $smap[$r['d']] = (int)$r['v']; }
$scanLabels = []; $scanData = [];
for ($i = 29; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i day")); $scanLabels[] = date('d M', strtotime($day)); $scanData[] = $smap[$day] ?? 0; }
// Top restaurants by total scans.
$topScanned = db_all('SELECT t.restaurant_name, t.slug, COALESCE(SUM(mv.views),0) v
                      FROM ' . tbl('tenants') . ' t
                      LEFT JOIN ' . tbl('menu_views') . ' mv ON mv.tenant_id = t.id
                      GROUP BY t.id ORDER BY v DESC LIMIT 10');

// ---- Signup trend: new tenants per month for the last 12 months ----
$rows = db_all(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
     FROM " . tbl('tenants') . "
     WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
     GROUP BY ym ORDER BY ym"
);
$byMonth = [];
foreach ($rows as $r) { $byMonth[$r['ym']] = (int)$r['c']; }

$chartLabels = [];
$chartData   = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("first day of -$i month"));
    $chartLabels[] = date('M Y', strtotime($key . '-01'));
    $chartData[]   = $byMonth[$key] ?? 0;
}

// ---- Recent activity (last 10) ----
$activity = db_all('SELECT * FROM ' . tbl('activity_logs') . ' ORDER BY id DESC LIMIT 10');

// ---- Expiring soon: within the next 7 days ----
$expiring = db_all(
    'SELECT id, restaurant_name, owner_name, expiry_date FROM ' . tbl('tenants') . "
     WHERE expiry_date IS NOT NULL AND expiry_date >= :d AND expiry_date <= :d7
     ORDER BY expiry_date ASC",
    [':d' => $today, ':d7' => date('Y-m-d', strtotime('+7 days'))]
);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-2">
    <div class="kpi-card kpi-1">
      <div class="kpi-val"><?= e($totalClients) ?></div>
      <div class="kpi-label"><i class="bi bi-shop"></i> Total Clients</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="kpi-card kpi-4">
      <div class="kpi-val"><?= e($activeClients) ?></div>
      <div class="kpi-label"><i class="bi bi-check-circle"></i> Active Clients</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="kpi-card kpi-3">
      <div class="kpi-val"><?= e($expiredClients) ?></div>
      <div class="kpi-label"><i class="bi bi-x-circle"></i> Expired Clients</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="kpi-card kpi-2">
      <div class="kpi-val"><?= e($totalMenus) ?></div>
      <div class="kpi-label"><i class="bi bi-card-list"></i> Total Menus</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="kpi-card kpi-1">
      <div class="kpi-val"><?= e($ordersToday) ?></div>
      <div class="kpi-label"><i class="bi bi-bag-check"></i> Orders Today</div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="kpi-card kpi-4">
      <div class="kpi-val"><?= e(money($revenueMonth)) ?></div>
      <div class="kpi-label"><i class="bi bi-cash-coin"></i> Revenue (Month)</div>
    </div>
  </div>
</div>

<!-- ===== Growth: platform-wide menu scans ===== -->
<div class="row g-3 mt-1">
  <div class="col-lg-8">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-qr-code-scan text-success"></i> Menu Opens / Scans — Daily (last 30 days)</h6>
        <div class="text-end">
          <span class="badge bg-success fs-6"><?= number_format($scansToday) ?> today</span>
          <span class="badge bg-secondary fs-6"><?= number_format($scansTotal) ?> total</span>
        </div>
      </div>
      <canvas id="scanChart" height="90"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-trophy text-warning"></i> Top Restaurants by Scans</h6>
      <?php if (!$topScanned): ?><p class="text-muted small mb-0">No scans yet.</p><?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($topScanned as $i => $ts): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <span class="text-truncate"><?= ($i+1) ?>. <?= e($ts['restaurant_name']) ?></span>
              <span class="badge bg-primary rounded-pill"><?= number_format((int)$ts['v']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> New Signups (Last 12 Months)</h6>
        <canvas id="signupChart" height="110"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-danger"></i> Expiring Soon (7 days)</h6>
        <?php if (!$expiring): ?>
          <p class="text-muted small mb-0">No plans expiring in the next 7 days.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($expiring as $ex): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <div>
                  <div class="fw-semibold small"><?= e($ex['restaurant_name']) ?></div>
                  <div class="text-muted" style="font-size:.78rem"><?= e($ex['owner_name'] ?? '') ?></div>
                </div>
                <span class="badge bg-warning text-dark"><?= e(date('d M', strtotime($ex['expiry_date']))) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history text-secondary"></i> Recent Activity</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr>
              <th>Time</th><th>User</th><th>Action</th><th>IP</th>
            </tr></thead>
            <tbody>
            <?php if (!$activity): ?>
              <tr><td colspan="4" class="text-muted text-center py-3">No activity yet.</td></tr>
            <?php else: foreach ($activity as $a): ?>
              <tr>
                <td class="text-nowrap small"><?= e(date('d M, H:i', strtotime($a['created_at']))) ?></td>
                <td class="small"><?= e($a['user_type']) ?> #<?= e($a['user_id']) ?></td>
                <td class="small"><?= e($a['action']) ?></td>
                <td class="small text-muted"><?= e($a['ip']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$pageScript = '<script>
(function(){
  var ctx = document.getElementById("signupChart");
  if (!ctx || !window.Chart) return;
  new Chart(ctx, {
    type: "line",
    data: {
      labels: ' . json_encode($chartLabels) . ',
      datasets: [{
        label: "New Signups",
        data: ' . json_encode($chartData) . ',
        borderColor: getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() || "#e63946",
        backgroundColor: "rgba(230,57,70,.12)",
        fill: true, tension: .35, pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
  var sc = document.getElementById("scanChart");
  if (sc && window.Chart) {
    new Chart(sc, {
      type: "bar",
      data: { labels: ' . json_encode($scanLabels) . ',
        datasets: [{ label:"Scans", data: ' . json_encode($scanData) . ',
          backgroundColor:"rgba(42,157,143,.85)", borderRadius:5 }] },
      options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}} }
    });
  }
})();
</script>';
require __DIR__ . '/_footer.php';
