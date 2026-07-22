<?php
/**
 * Admin › AI Usage & Cost.
 * Tabs: Overview · User-wise · User detail · Rates & Settings · Billing.
 * Reads exact token counts logged by config/ai_metering.php. Never estimates.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$T = tbl('ai_usage_logs');
$sym = aiLocalSymbol();

// ---- POST: save settings / pricing (before any output) ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'settings') {
            foreach (['usd_to_local_rate','local_symbol','markup_percentage','max_input_tokens',
                      'alert_threshold_tokens','log_retention_days'] as $k) {
                if (array_key_exists($k, $_POST)) { setSetting('ai_' . $k, trim((string)$_POST[$k])); }
            }
            if (isset($_POST['timezone'])) { setSetting('timezone', trim((string)$_POST['timezone'])); }
        } elseif ($do === 'price_save') {
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'provider' => trim((string)$_POST['provider']), 'model_name' => trim((string)$_POST['model_name']),
                'input_per_million' => (float)$_POST['input_per_million'], 'output_per_million' => (float)$_POST['output_per_million'],
                'cached_per_million' => (float)($_POST['cached_per_million'] ?? 0),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0, 'notes' => trim((string)($_POST['notes'] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($id) { db_update('ai_pricing_config', $data, ['id' => $id]); }
            else {
                try { db_insert('ai_pricing_config', $data); }
                catch (Throwable $e) { db_update('ai_pricing_config', $data, ['provider' => $data['provider'], 'model_name' => $data['model_name']]); }
            }
        } elseif ($do === 'price_toggle') {
            db_query('UPDATE ' . tbl('ai_pricing_config') . ' SET is_active = 1 - is_active WHERE id = :id', [':id' => (int)$_POST['id']]);
        }
        logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'AI usage: ' . $do);
    } catch (Throwable $e) { error_log('ai_usage save: ' . $e->getMessage()); }
    redirect(BASE_URL . '/admin/ai_usage.php?tab=' . urlencode($_GET['tab'] ?? 'rates') . '&saved=1');
}

// ---- Date range -------------------------------------------------------------
$range = $_GET['range'] ?? 'month';
$today = date('Y-m-d');
switch ($range) {
    case 'today':      $from = "$today 00:00:00"; $to = "$today 23:59:59"; $label = 'Today'; break;
    case '7d':         $from = date('Y-m-d 00:00:00', strtotime('-6 days')); $to = "$today 23:59:59"; $label = 'Last 7 days'; break;
    case 'last_month': $from = date('Y-m-01 00:00:00', strtotime('first day of last month')); $to = date('Y-m-t 23:59:59', strtotime('last day of last month')); $label = 'Last month'; break;
    case 'custom':     $from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01')) . ' 00:00:00';
                       $to   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : $today) . ' 23:59:59'; $label = 'Custom'; break;
    default:           $range = 'month'; $from = date('Y-m-01 00:00:00'); $to = "$today 23:59:59"; $label = 'This month';
}
$q = function (string $sql, array $p = []) { try { return db_one($sql, $p); } catch (Throwable $e) { return null; } };
$qa = function (string $sql, array $p = []) { try { return db_all($sql, $p); } catch (Throwable $e) { return []; } };

$tab = $_GET['tab'] ?? 'overview';

// ---- CSV export (user-wise) -------------------------------------------------
if (($_GET['export'] ?? '') === 'users') {
    $rows = $qa("SELECT user_id, MAX(username) username, key_owner, COUNT(*) calls,
                   SUM(input_tokens) itok, SUM(output_tokens) otok, SUM(total_tokens) ttok,
                   SUM(total_cost_usd) usd, SUM(total_cost_local) local, MAX(created_at) last_used,
                   COUNT(DISTINCT DATE(created_at)) days_active
                 FROM $T WHERE created_at BETWEEN :a AND :b GROUP BY user_id, key_owner ORDER BY usd DESC", [':a' => $from, ':b' => $to]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ai_usage_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['User ID','Username','Key','Calls','Input Tokens','Output Tokens','Total Tokens','Cost USD','Cost Local','Days Active','Last Used']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['user_id'], $r['username'], $r['key_owner'], $r['calls'], $r['itok'], $r['otok'], $r['ttok'],
                       number_format((float)$r['usd'], 6, '.', ''), number_format((float)$r['local'], 4, '.', ''), $r['days_active'], $r['last_used']]);
    }
    fclose($out); exit;
}

$pageTitle = 'AI Usage & Cost';
$activeNav = 'ai_usage';
require __DIR__ . '/_header.php';

$money = fn($n, $sy = '$') => $sy . number_format((float)$n, 4);
function rlink($tab, $range) { return e(BASE_URL . '/admin/ai_usage.php?tab=' . $tab . '&range=' . $range); }
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2 small">Saved.</div><?php endif; ?>

<!-- Range filter + tabs -->
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <ul class="nav nav-pills gap-1">
    <?php foreach (['overview'=>'Overview','users'=>'User-wise','rates'=>'Rates & Settings','billing'=>'Billing'] as $tk=>$tl): ?>
      <li class="nav-item"><a class="nav-link <?= $tab===$tk?'active':'' ?>" href="<?= e(BASE_URL.'/admin/ai_usage.php?tab='.$tk.'&range='.$range) ?>"><?= e($tl) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php if (in_array($tab, ['overview','users','billing'], true)): ?>
  <div class="btn-group btn-group-sm">
    <?php foreach (['today'=>'Today','7d'=>'7 Days','month'=>'This Month','last_month'=>'Last Month'] as $rk=>$rl): ?>
      <a class="btn btn-outline-secondary <?= $range===$rk?'active':'' ?>" href="<?= e(BASE_URL.'/admin/ai_usage.php?tab='.$tab.'&range='.$rk) ?>"><?= e($rl) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php
// ============================ OVERVIEW ============================
if ($tab === 'overview'):
    $sum = $q("SELECT COUNT(*) calls, COALESCE(SUM(input_tokens),0) itok, COALESCE(SUM(output_tokens),0) otok,
                 COALESCE(SUM(total_tokens),0) ttok, COALESCE(SUM(total_cost_usd),0) usd,
                 COALESCE(SUM(total_cost_local),0) local, COUNT(DISTINCT user_id) users
               FROM $T WHERE created_at BETWEEN :a AND :b", [':a'=>$from,':b'=>$to]);
    $plat = $q("SELECT COALESCE(SUM(total_tokens),0) ttok, COALESCE(SUM(total_cost_usd),0) usd, COALESCE(SUM(total_cost_local),0) local FROM $T WHERE key_owner='platform' AND created_at BETWEEN :a AND :b",[':a'=>$from,':b'=>$to]);
    $usr  = $q("SELECT COALESCE(SUM(total_tokens),0) ttok, COALESCE(SUM(total_cost_usd),0) usd, COALESCE(SUM(total_cost_local),0) local FROM $T WHERE key_owner='user' AND created_at BETWEEN :a AND :b",[':a'=>$from,':b'=>$to]);
    $calls = (int)($sum['calls'] ?? 0);
    $avg   = $calls ? (float)$sum['usd'] / $calls : 0;
    $daily = $qa("SELECT DATE(created_at) d, SUM(total_cost_usd) usd FROM $T WHERE created_at BETWEEN :a AND :b GROUP BY DATE(created_at) ORDER BY d",[':a'=>$from,':b'=>$to]);
    $top   = $qa("SELECT user_id, MAX(username) uname, SUM(total_cost_usd) usd, SUM(total_tokens) ttok FROM $T WHERE created_at BETWEEN :a AND :b GROUP BY user_id ORDER BY usd DESC LIMIT 10",[':a'=>$from,':b'=>$to]);
    // warnings
    $noKey = trim((string)getSetting('gemini_api_key','')) === '';
    $unpriced = $qa("SELECT DISTINCT l.model_name FROM $T l LEFT JOIN ".tbl('ai_pricing_config')." p ON p.model_name=l.model_name WHERE p.id IS NULL AND l.model_name IS NOT NULL AND l.model_name<>'' LIMIT 10");
    $bigCalls = (int)($q("SELECT COUNT(*) c FROM $T WHERE total_tokens > :t AND created_at BETWEEN :a AND :b",[':t'=>aiAlertThreshold(),':a'=>$from,':b'=>$to])['c'] ?? 0);
?>
  <?php if ($noKey): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-octagon"></i> No platform Gemini API key set — all AI usage is counted as billable to you. Add it in Settings → AI.</div><?php endif; ?>
  <?php if ($unpriced): ?><div class="alert alert-warning py-2 small"><i class="bi bi-tag"></i> These models used have no price in Rates &amp; Settings (cost shows 0): <strong><?= e(implode(', ', array_column($unpriced,'model_name'))) ?></strong></div><?php endif; ?>
  <?php if ($bigCalls > 0): ?><div class="alert alert-warning py-2 small"><i class="bi bi-lightning-charge"></i> <?= $bigCalls ?> call(s) in this range exceeded the alert threshold of <?= number_format(aiAlertThreshold()) ?> tokens.</div><?php endif; ?>

  <div class="row g-2 mb-3">
    <?php
    $cards = [
      ['Total Calls', number_format($calls), 'bi-list-check'],
      ['Input Tokens', number_format((int)($sum['itok']??0)), 'bi-box-arrow-in-down'],
      ['Output Tokens', number_format((int)($sum['otok']??0)), 'bi-box-arrow-up'],
      ['Total Tokens', number_format((int)($sum['ttok']??0)), 'bi-hash'],
      ['Total Cost (USD)', '$'.number_format((float)($sum['usd']??0),4), 'bi-currency-dollar'],
      ['Total Cost ('.$sym.')', $sym.number_format((float)($sum['local']??0),2), 'bi-cash-stack'],
      ['Avg Cost / Call', '$'.number_format($avg,6), 'bi-calculator'],
      ['Active Users', number_format((int)($sum['users']??0)), 'bi-people'],
    ];
    foreach ($cards as $c): ?>
      <div class="col-6 col-lg-3"><div class="card"><div class="card-body py-2">
        <div class="text-muted small"><i class="bi <?= $c[2] ?>"></i> <?= e($c[0]) ?></div>
        <div class="fs-5 fw-bold"><?= $c[1] ?></div>
      </div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card border-danger"><div class="card-body">
      <div class="text-muted small">On MY key (platform) — this is your actual expense</div>
      <div class="fs-4 fw-bold text-danger">$<?= number_format((float)($plat['usd']??0),4) ?> · <?= $sym.number_format((float)($plat['local']??0),2) ?></div>
      <div class="small text-muted"><?= number_format((int)($plat['ttok']??0)) ?> tokens</div>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body">
      <div class="text-muted small">On users' own keys — not your expense (what it WOULD cost on your key)</div>
      <div class="fs-4 fw-bold">$<?= number_format((float)($usr['usd']??0),4) ?> · <?= $sym.number_format((float)($usr['local']??0),2) ?></div>
      <div class="small text-muted"><?= number_format((int)($usr['ttok']??0)) ?> tokens</div>
    </div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-body">
      <h6 class="fw-semibold">Daily cost (USD) — <?= e($label) ?></h6>
      <canvas id="dailyChart" height="110"></canvas>
    </div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body">
      <h6 class="fw-semibold">Top 10 users by cost</h6>
      <?php if (!$top): ?><p class="text-muted small mb-0">No AI usage in this range.</p>
      <?php else: foreach ($top as $t): ?>
        <div class="d-flex justify-content-between small py-1 border-bottom">
          <a href="<?= e(BASE_URL.'/admin/ai_usage.php?tab=user&user='.(int)$t['user_id'].'&range='.$range) ?>" class="text-truncate" style="max-width:60%"><?= e($t['uname'] ?: ('User #'.$t['user_id'])) ?></a>
          <span class="fw-semibold">$<?= number_format((float)$t['usd'],4) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div></div></div>
  </div>

  <?php
  $labels = []; $data = [];
  $map = []; foreach ($daily as $d) { $map[$d['d']] = (float)$d['usd']; }
  $cur = strtotime(substr($from,0,10)); $end = strtotime(substr($to,0,10));
  while ($cur <= $end && count($labels) < 120) { $ds = date('Y-m-d',$cur); $labels[] = date('d M',$cur); $data[] = round($map[$ds]??0,6); $cur = strtotime('+1 day',$cur); }
  $pageScript = '<script>new Chart(document.getElementById("dailyChart"),{type:"bar",data:{labels:'.json_encode($labels).',datasets:[{label:"USD",data:'.json_encode($data).',backgroundColor:"#e63946"}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});</script>';
endif; ?>

<?php
// ============================ USER-WISE ============================
if ($tab === 'users'):
  $rows = $qa("SELECT user_id, MAX(username) uname, MAX(key_owner) kowner, COUNT(*) calls,
                 SUM(input_tokens) itok, SUM(output_tokens) otok, SUM(total_tokens) ttok,
                 SUM(total_cost_usd) usd, SUM(total_cost_local) local, MAX(created_at) last_used,
                 COUNT(DISTINCT DATE(created_at)) days_active
               FROM $T WHERE created_at BETWEEN :a AND :b GROUP BY user_id ORDER BY usd DESC", [':a'=>$from,':b'=>$to]);
?>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <input type="search" id="userSearch" class="form-control form-control-sm" style="max-width:260px" placeholder="Search user…">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL.'/admin/ai_usage.php?tab=users&range='.$range.'&export=users') ?>"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" id="uTable">
    <thead class="table-light"><tr>
      <th>User</th><th>Key</th><th>Calls</th><th>In</th><th>Out</th><th>Total Tok</th>
      <th>Cost USD</th><th>Cost <?= e($sym) ?></th><th>Avg/Call</th><th>Est. Monthly</th><th>Days</th><th>Last Used</th>
    </tr></thead><tbody>
    <?php foreach ($rows as $r):
      $avg = $r['calls'] ? (float)$r['usd']/$r['calls'] : 0;
      $est = ($r['days_active'] > 0) ? (float)$r['local'] / $r['days_active'] * 30 : (float)$r['local']; ?>
      <tr>
        <td><a href="<?= e(BASE_URL.'/admin/ai_usage.php?tab=user&user='.(int)$r['user_id'].'&range='.$range) ?>"><?= e($r['uname'] ?: ('User #'.$r['user_id'])) ?></a></td>
        <td><span class="badge bg-<?= $r['kowner']==='user'?'info':'secondary' ?>"><?= e(ucfirst($r['kowner'])) ?></span></td>
        <td><?= number_format((int)$r['calls']) ?></td>
        <td><?= number_format((int)$r['itok']) ?></td><td><?= number_format((int)$r['otok']) ?></td>
        <td><?= number_format((int)$r['ttok']) ?></td>
        <td>$<?= number_format((float)$r['usd'],4) ?></td>
        <td><?= e($sym).number_format((float)$r['local'],2) ?></td>
        <td>$<?= number_format($avg,6) ?></td>
        <td><?= e($sym).number_format($est,2) ?></td>
        <td><?= (int)$r['days_active'] ?></td>
        <td class="small text-muted text-nowrap"><?= e($r['last_used'] ? date('d M, H:i', strtotime($r['last_used'])) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="12" class="text-center text-muted py-3">No AI usage in this range.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <?php $pageScript = '<script>document.getElementById("userSearch").addEventListener("input",function(){var q=this.value.toLowerCase();document.querySelectorAll("#uTable tbody tr").forEach(function(tr){tr.style.display=tr.innerText.toLowerCase().includes(q)?"":"none";});});</script>';
endif; ?>

<?php
// ============================ USER DETAIL ============================
if ($tab === 'user'):
  $uid = (int)($_GET['user'] ?? 0);
  $uname = (string)($q("SELECT MAX(username) u FROM $T WHERE user_id=:u",[':u'=>$uid])['u'] ?? '');
  if ($uname === '' && $uid > 0) { $uname = (string)db_val('SELECT restaurant_name FROM '.tbl('tenants').' WHERE id=:id',[':id'=>$uid]); }
  $s = $q("SELECT COUNT(*) calls, COALESCE(SUM(total_tokens),0) ttok, COALESCE(SUM(total_cost_usd),0) usd, COALESCE(SUM(total_cost_local),0) local FROM $T WHERE user_id=:u AND created_at BETWEEN :a AND :b",[':u'=>$uid,':a'=>$from,':b'=>$to]);
  $byModel = $qa("SELECT model_name, COUNT(*) calls, SUM(total_tokens) ttok, SUM(total_cost_usd) usd FROM $T WHERE user_id=:u AND created_at BETWEEN :a AND :b GROUP BY model_name ORDER BY usd DESC",[':u'=>$uid,':a'=>$from,':b'=>$to]);
  $bySource = $qa("SELECT source, COUNT(*) calls, SUM(total_cost_usd) usd FROM $T WHERE user_id=:u AND created_at BETWEEN :a AND :b GROUP BY source ORDER BY usd DESC",[':u'=>$uid,':a'=>$from,':b'=>$to]);
  $daily = $qa("SELECT DATE(created_at) d, SUM(total_cost_usd) usd FROM $T WHERE user_id=:u AND created_at>=:a GROUP BY DATE(created_at) ORDER BY d",[':u'=>$uid,':a'=>date('Y-m-d',strtotime('-29 days'))]);
  $recent = $qa("SELECT * FROM $T WHERE user_id=:u ORDER BY id DESC LIMIT 100",[':u'=>$uid]);
?>
  <a href="<?= e(BASE_URL.'/admin/ai_usage.php?tab=users&range='.$range) ?>" class="small">&larr; All users</a>
  <h5 class="fw-semibold mt-1 mb-3"><?= e($uname ?: ('User #'.$uid)) ?> <span class="text-muted small">(<?= e($label) ?>)</span></h5>
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="text-muted small">Calls</div><div class="fs-5 fw-bold"><?= number_format((int)($s['calls']??0)) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="text-muted small">Total Tokens</div><div class="fs-5 fw-bold"><?= number_format((int)($s['ttok']??0)) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="text-muted small">Cost USD</div><div class="fs-5 fw-bold">$<?= number_format((float)($s['usd']??0),4) ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="text-muted small">Cost <?= e($sym) ?></div><div class="fs-5 fw-bold"><?= e($sym).number_format((float)($s['local']??0),2) ?></div></div></div></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card"><div class="card-body"><h6 class="fw-semibold">Daily cost (30 days)</h6><canvas id="uDaily" height="90"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-body"><h6 class="fw-semibold">By model</h6>
      <?php foreach ($byModel as $m): ?><div class="d-flex justify-content-between small py-1 border-bottom"><span><?= e($m['model_name'] ?: '—') ?> <span class="text-muted">×<?= (int)$m['calls'] ?></span></span><span>$<?= number_format((float)$m['usd'],4) ?></span></div><?php endforeach; ?>
      <h6 class="fw-semibold mt-3">By feature</h6>
      <?php foreach ($bySource as $sr): ?><div class="d-flex justify-content-between small py-1 border-bottom"><span><?= e($sr['source'] ?: '—') ?> <span class="text-muted">×<?= (int)$sr['calls'] ?></span></span><span>$<?= number_format((float)$sr['usd'],4) ?></span></div><?php endforeach; ?>
    </div></div></div>
  </div>
  <div class="card"><div class="card-body"><h6 class="fw-semibold">Recent 100 calls</h6>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Time</th><th>Model</th><th>Feature</th><th>In</th><th>Out</th><th>Total</th><th>Cost USD</th><th>ms</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recent as $r): ?><tr>
      <td class="small text-nowrap"><?= e(date('d M H:i:s', strtotime($r['created_at']))) ?></td>
      <td class="small"><?= e($r['model_name']) ?></td><td class="small"><?= e($r['source']) ?></td>
      <td><?= number_format((int)$r['input_tokens']) ?></td><td><?= number_format((int)$r['output_tokens']) ?></td>
      <td><?= number_format((int)$r['total_tokens']) ?></td><td>$<?= number_format((float)$r['total_cost_usd'],6) ?></td>
      <td><?= (int)$r['response_time_ms'] ?></td>
      <td><?php $st=$r['status']; $bg=['success'=>'success','error'=>'danger','blocked'=>'warning','incomplete'=>'secondary'][$st]??'secondary'; ?><span class="badge bg-<?= $bg ?>"><?= e($st) ?></span><?php if((int)$r['total_tokens']>aiAlertThreshold()): ?> <span class="badge bg-danger">big</span><?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="9" class="text-center text-muted py-3">No calls.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <?php
  $lab=[];$dd=[];$m=[];foreach($daily as $d){$m[$d['d']]=(float)$d['usd'];}
  for($i=29;$i>=0;$i--){$ds=date('Y-m-d',strtotime("-$i days"));$lab[]=date('d M',strtotime($ds));$dd[]=round($m[$ds]??0,6);}
  $pageScript='<script>new Chart(document.getElementById("uDaily"),{type:"line",data:{labels:'.json_encode($lab).',datasets:[{label:"USD",data:'.json_encode($dd).',borderColor:"#e63946",tension:.3,fill:false}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});</script>';
endif; ?>

<?php
// ============================ RATES & SETTINGS ============================
if ($tab === 'rates'):
  $prices = $qa('SELECT * FROM '.tbl('ai_pricing_config').' ORDER BY provider, model_name');
?>
  <div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3">Model prices (USD per 1M tokens)</h6>
      <div class="table-responsive"><table class="table table-sm align-middle"><thead class="table-light"><tr><th>Provider</th><th>Model</th><th>Input</th><th>Output</th><th>Cached</th><th></th></tr></thead><tbody>
      <?php foreach ($prices as $p): ?>
        <tr><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="price_save"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="provider" value="<?= e($p['provider']) ?>"><input type="hidden" name="model_name" value="<?= e($p['model_name']) ?>"><input type="hidden" name="is_active" value="<?= (int)$p['is_active'] ?>">
          <td class="small"><?= e($p['provider']) ?></td><td class="small"><?= e($p['model_name']) ?></td>
          <td><input name="input_per_million" value="<?= e($p['input_per_million']) ?>" class="form-control form-control-sm" style="width:90px"></td>
          <td><input name="output_per_million" value="<?= e($p['output_per_million']) ?>" class="form-control form-control-sm" style="width:90px"></td>
          <td><input name="cached_per_million" value="<?= e($p['cached_per_million']) ?>" class="form-control form-control-sm" style="width:90px"></td>
          <td class="text-nowrap"><button class="btn btn-sm btn-primary">Save</button>
            <?= $p['is_active'] ? '<span class="badge bg-success">on</span>' : '<span class="badge bg-secondary">off</span>' ?></td>
        </form></tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <details class="mt-2"><summary class="small text-primary">+ Add a model</summary>
        <form method="post" class="row g-2 mt-1"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="price_save"><input type="hidden" name="is_active" value="1">
          <div class="col-3"><input name="provider" class="form-control form-control-sm" placeholder="provider" required></div>
          <div class="col-4"><input name="model_name" class="form-control form-control-sm" placeholder="model" required></div>
          <div class="col-2"><input name="input_per_million" type="number" step="0.000001" class="form-control form-control-sm" placeholder="in" value="0"></div>
          <div class="col-2"><input name="output_per_million" type="number" step="0.000001" class="form-control form-control-sm" placeholder="out" value="0"></div>
          <div class="col-1"><button class="btn btn-sm btn-primary">Add</button></div>
        </form></details>
    </div></div></div>
    <div class="col-lg-5"><form method="post" class="card"><div class="card-body">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="do" value="settings">
      <h6 class="fw-semibold mb-3">Settings</h6>
      <div class="mb-2"><label class="form-label small">USD → local rate</label><input name="usd_to_local_rate" value="<?= e(aiUsdToLocal()) ?>" class="form-control form-control-sm"></div>
      <div class="mb-2"><label class="form-label small">Local currency symbol</label><input name="local_symbol" value="<?= e($sym) ?>" class="form-control form-control-sm"></div>
      <div class="mb-2"><label class="form-label small">Markup %</label><input name="markup_percentage" value="<?= e(aiMarkupPct()) ?>" class="form-control form-control-sm"></div>
      <div class="mb-2"><label class="form-label small">Max input tokens / request</label><input name="max_input_tokens" value="<?= e(aiMaxInputTokens()) ?>" class="form-control form-control-sm"></div>
      <div class="mb-2"><label class="form-label small">Alert threshold (tokens)</label><input name="alert_threshold_tokens" value="<?= e(aiAlertThreshold()) ?>" class="form-control form-control-sm"></div>
      <div class="mb-2"><label class="form-label small">Log retention (days)</label><input name="log_retention_days" value="<?= e(aiRetentionDays()) ?>" class="form-control form-control-sm"></div>
      <div class="mb-2"><label class="form-label small">Timezone</label><input name="timezone" value="<?= e(getSetting('timezone', DEFAULT_TIMEZONE)) ?>" class="form-control form-control-sm"></div>
      <button class="btn btn-primary btn-sm w-100">Save Settings</button>
      <p class="text-muted small mt-2 mb-0">Platform API key is the Gemini key in Settings → AI. Price changes affect new calls only; historical rows keep their logged cost.</p>
    </div></form></div>
  </div>
<?php endif; ?>

<?php
// ============================ BILLING ============================
if ($tab === 'billing'):
  $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
  $bf = $month.'-01 00:00:00'; $bt = date('Y-m-t 23:59:59', strtotime($bf));
  $markup = aiMarkupPct();
  $rows = $qa("SELECT user_id, MAX(username) uname, COUNT(*) calls, SUM(total_tokens) ttok, SUM(total_cost_local) local
               FROM $T WHERE key_owner='platform' AND created_at BETWEEN :a AND :b GROUP BY user_id ORDER BY local DESC",[':a'=>$bf,':b'=>$bt]);
?>
  <form class="d-flex gap-2 mb-3 align-items-end">
    <input type="hidden" name="tab" value="billing">
    <div><label class="form-label small mb-0">Month</label><input type="month" name="month" value="<?= e($month) ?>" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-primary">Generate</button>
    <button class="btn btn-sm btn-outline-dark" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
  </form>
  <div class="card"><div class="card-body">
    <div class="small text-muted mb-2">Billable (platform key) usage for <?= e(date('F Y', strtotime($bf))) ?> · rate 1 USD = <?= e($sym).aiUsdToLocal() ?> · markup <?= e($markup) ?>%</div>
    <div class="table-responsive"><table class="table table-sm"><thead class="table-light"><tr><th>User</th><th>Calls</th><th>Total Tokens</th><th>Raw Cost (<?= e($sym) ?>)</th><th>Markup</th><th>Final Billable (<?= e($sym) ?>)</th></tr></thead><tbody>
    <?php $grand=0; foreach ($rows as $r): $final=(float)$r['local']*(1+$markup/100); $grand+=$final; ?>
      <tr><td><?= e($r['uname'] ?: ('User #'.$r['user_id'])) ?></td><td><?= number_format((int)$r['calls']) ?></td><td><?= number_format((int)$r['ttok']) ?></td>
        <td><?= e($sym).number_format((float)$r['local'],2) ?></td><td><?= e($markup) ?>%</td><td class="fw-semibold"><?= e($sym).number_format($final,2) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-3">No billable usage this month.</td></tr><?php endif; ?>
    </tbody><?php if($rows): ?><tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total</td><td><?= e($sym).number_format($grand,2) ?></td></tr></tfoot><?php endif; ?></table></div>
  </div></div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
