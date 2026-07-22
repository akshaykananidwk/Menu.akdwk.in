<?php
/**
 * Admin › Website Analytics.
 * How many people visit the whole platform (deduped per device/day), the trend
 * over 30 days, and which pages they open most. Data comes from `site_visits`
 * (path='*' is the site-wide daily aggregate).
 */
$pageTitle = 'Website Analytics';
$activeNav = 'analytics';
require __DIR__ . '/_header.php';

$today = date('Y-m-d');
$d30   = date('Y-m-d', strtotime('-29 days'));
$d7    = date('Y-m-d', strtotime('-6 days'));

$q = function (string $sql, array $p = []) {
    try { return db_val($sql, $p); } catch (Throwable $e) { return 0; }
};
$sv = tbl('site_visits');

$todayVisitors = (int)$q("SELECT visitors FROM $sv WHERE path='*' AND visit_date=CURDATE()");
$todayViews    = (int)$q("SELECT views    FROM $sv WHERE path='*' AND visit_date=CURDATE()");
$visitors7     = (int)$q("SELECT COALESCE(SUM(visitors),0) FROM $sv WHERE path='*' AND visit_date>=:a", [':a' => $d7]);
$totalVisitors = (int)$q("SELECT COALESCE(SUM(visitors),0) FROM $sv WHERE path='*'");
$totalViews    = (int)$q("SELECT COALESCE(SUM(views),0)    FROM $sv WHERE path='*'");

// 30-day daily series (path='*').
try {
    $rows = db_all("SELECT visit_date, visitors, views FROM $sv WHERE path='*' AND visit_date>=:a ORDER BY visit_date", [':a' => $d30]);
} catch (Throwable $e) { $rows = []; }
$byDate = [];
foreach ($rows as $r) { $byDate[$r['visit_date']] = $r; }
$labels = $vis = $vw = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d M', strtotime($d));
    $vis[] = (int)($byDate[$d]['visitors'] ?? 0);
    $vw[]  = (int)($byDate[$d]['views'] ?? 0);
}

// Top pages (exclude the '*' aggregate), all-time.
try {
    $top = db_all("SELECT path, SUM(views) AS v, SUM(visitors) AS u FROM $sv WHERE path<>'*' GROUP BY path ORDER BY u DESC, v DESC LIMIT 20");
} catch (Throwable $e) { $top = []; }
$maxU = 0; foreach ($top as $t) { $maxU = max($maxU, (int)$t['u']); }
?>
<style>
.kpi{border:1px solid #eef0f5;border-radius:16px;padding:1.1rem 1.2rem}
.kpi .n{font-size:1.9rem;font-weight:800;line-height:1}
.kpi .l{color:#64748b;font-size:.82rem;margin-top:.2rem}
.kpi .ic{width:42px;height:42px;border-radius:11px;display:grid;place-items:center;font-size:1.2rem;color:#fff}
.bar{height:8px;border-radius:6px;background:#eef2ff;overflow:hidden}
.bar > i{display:block;height:100%;background:linear-gradient(90deg,var(--primary),var(--accent))}
</style>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="kpi d-flex align-items-center gap-3">
    <div class="ic" style="background:linear-gradient(135deg,var(--primary),var(--secondary))"><i class="bi bi-people-fill"></i></div>
    <div><div class="n"><?= number_format($todayVisitors) ?></div><div class="l">Visitors today</div></div>
  </div></div>
  <div class="col-6 col-lg-3"><div class="kpi d-flex align-items-center gap-3">
    <div class="ic" style="background:linear-gradient(135deg,#0ea5e9,#2563eb)"><i class="bi bi-eye-fill"></i></div>
    <div><div class="n"><?= number_format($todayViews) ?></div><div class="l">Page views today</div></div>
  </div></div>
  <div class="col-6 col-lg-3"><div class="kpi d-flex align-items-center gap-3">
    <div class="ic" style="background:linear-gradient(135deg,#16a34a,#0d9488)"><i class="bi bi-calendar-week"></i></div>
    <div><div class="n"><?= number_format($visitors7) ?></div><div class="l">Visitors (7 days)</div></div>
  </div></div>
  <div class="col-6 col-lg-3"><div class="kpi d-flex align-items-center gap-3">
    <div class="ic" style="background:linear-gradient(135deg,#f59e0b,#ef4444)"><i class="bi bi-graph-up-arrow"></i></div>
    <div><div class="n"><?= number_format($totalVisitors) ?></div><div class="l">Total visitors (all-time)</div></div>
  </div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0">Daily visitors — last 30 days</h6>
        <span class="text-muted small">Unique per device/day · <?= number_format($totalViews) ?> total views</span>
      </div>
      <canvas id="trend" height="120"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3">Top pages</h6>
      <?php if (!$top): ?>
        <p class="text-muted small mb-0">No visits recorded yet. Data appears as people open your site.</p>
      <?php else: foreach ($top as $t): $pct = $maxU ? round((int)$t['u'] / $maxU * 100) : 0; ?>
        <div class="mb-2">
          <div class="d-flex justify-content-between small">
            <span class="text-truncate" style="max-width:65%" title="<?= e($t['path']) ?>"><?= e(siteVisitLabel($t['path'])) ?></span>
            <span class="fw-semibold"><?= number_format((int)$t['u']) ?> <span class="text-muted fw-normal">/ <?= number_format((int)$t['v']) ?> views</span></span>
          </div>
          <div class="bar mt-1"><i style="width:<?= $pct ?>%"></i></div>
        </div>
      <?php endforeach; endif; ?>
    </div></div>
  </div>
</div>

<p class="text-muted small mt-3">
  <i class="bi bi-info-circle"></i> “Visitors” are unique devices per day (refreshes and repeat opens are not double-counted). “Page views” count every open. Restaurant menus are grouped as one row. Super-admin visits and bots are excluded.
</p>

<?php
$labelsJson = json_encode($labels);
$visJson = json_encode($vis);
$vwJson = json_encode($vw);
$pageScript = <<<HTML
<script>
new Chart(document.getElementById('trend'), {
  type: 'line',
  data: {
    labels: {$labelsJson},
    datasets: [
      { label:'Visitors', data:{$visJson}, borderColor:'#e63946', backgroundColor:'rgba(230,57,70,.12)', fill:true, tension:.35, borderWidth:2, pointRadius:0 },
      { label:'Page views', data:{$vwJson}, borderColor:'#2563eb', backgroundColor:'transparent', fill:false, tension:.35, borderWidth:1.5, borderDash:[5,4], pointRadius:0 }
    ]
  },
  options: { responsive:true, plugins:{legend:{display:true,position:'bottom'}}, scales:{y:{beginAtZero:true,ticks:{precision:0}}} }
});
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
