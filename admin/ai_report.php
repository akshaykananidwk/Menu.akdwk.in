<?php
/**
 * Admin › AI Usage Report.
 * Summaries from ai_logs grouped by tenant and by month, with a Chart.js bar
 * of AI calls per month and a cost estimate (calls × configurable rate).
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$rate = (float)getSetting('ai_cost_per_call', '0.50');
$cur  = getSetting('currency', '₹');

// Per-tenant summary.
$byTenant = db_all('SELECT a.tenant_id, tn.restaurant_name,
                    COUNT(*) AS calls,
                    SUM(a.status = "success") AS ok,
                    SUM(a.status <> "success") AS failed,
                    SUM(a.tokens) AS tokens
                    FROM ' . tbl('ai_logs') . ' a
                    LEFT JOIN ' . tbl('tenants') . ' tn ON tn.id = a.tenant_id
                    GROUP BY a.tenant_id, tn.restaurant_name
                    ORDER BY calls DESC');

// Per-month summary (last 12 months first).
$byMonth = db_all('SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym,
                   COUNT(*) AS calls,
                   SUM(status = "success") AS ok,
                   SUM(status <> "success") AS failed
                   FROM ' . tbl('ai_logs') . '
                   GROUP BY ym ORDER BY ym DESC LIMIT 12');

$totalCalls = (int)db_val('SELECT COUNT(*) FROM ' . tbl('ai_logs'));
$totalCost  = $totalCalls * $rate;

// Chart data (chronological order).
$chartRows = array_reverse($byMonth);
$chartLabels = array_map(fn($r) => $r['ym'], $chartRows);
$chartData   = array_map(fn($r) => (int)$r['calls'], $chartRows);

$pageTitle = 'AI Usage';
$activeNav = 'ai_report';
require __DIR__ . '/_header.php';
?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Total AI Calls</div><div class="fs-4 fw-bold"><?= number_format($totalCalls) ?></div>
  </div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Cost / Call</div><div class="fs-4 fw-bold"><?= e($cur . number_format($rate, 2)) ?></div>
  </div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Estimated Cost</div><div class="fs-4 fw-bold"><?= e($cur . number_format($totalCost, 2)) ?></div>
  </div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Active Tenants (AI)</div><div class="fs-4 fw-bold"><?= number_format(count($byTenant)) ?></div>
  </div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <h6 class="mb-3">AI Calls per Month</h6>
    <canvas id="aiChart" height="90"></canvas>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-3">By Tenant</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Restaurant</th><th class="text-end">Calls</th><th class="text-end">OK</th><th class="text-end">Failed</th><th class="text-end">Tokens</th><th class="text-end">Est. Cost</th></tr></thead>
            <tbody>
            <?php if (!$byTenant): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No AI usage recorded yet.</td></tr>
            <?php else: foreach ($byTenant as $r): ?>
              <tr>
                <td><?= e($r['restaurant_name'] ?? 'Unknown / System') ?></td>
                <td class="text-end"><?= (int)$r['calls'] ?></td>
                <td class="text-end text-success"><?= (int)$r['ok'] ?></td>
                <td class="text-end text-danger"><?= (int)$r['failed'] ?></td>
                <td class="text-end"><?= number_format((int)$r['tokens']) ?></td>
                <td class="text-end"><?= e($cur . number_format((int)$r['calls'] * $rate, 2)) ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-3">By Month</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Month</th><th class="text-end">Calls</th><th class="text-end">OK</th><th class="text-end">Failed</th><th class="text-end">Est. Cost</th></tr></thead>
            <tbody>
            <?php if (!$byMonth): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No data.</td></tr>
            <?php else: foreach ($byMonth as $r): ?>
              <tr>
                <td><?= e($r['ym']) ?></td>
                <td class="text-end"><?= (int)$r['calls'] ?></td>
                <td class="text-end text-success"><?= (int)$r['ok'] ?></td>
                <td class="text-end text-danger"><?= (int)$r['failed'] ?></td>
                <td class="text-end"><?= e($cur . number_format((int)$r['calls'] * $rate, 2)) ?></td>
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
$labelsJson = json_encode($chartLabels);
$dataJson   = json_encode($chartData);
$pageScript = <<<HTML
<script>
const aiPrimary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#e63946';
new Chart(document.getElementById('aiChart'), {
  type: 'bar',
  data: {
    labels: $labelsJson,
    datasets: [{ label: 'AI Calls', data: $dataJson, backgroundColor: aiPrimary, borderRadius: 4 }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
