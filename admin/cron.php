<?php
/**
 * Admin › Cron & Scheduled Tasks.
 * One master cron drives every background job. This page shows status/health,
 * lets you enable/disable, run manually, retry failures and view execution logs.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// Ensure a cron secret exists so the one-line setup command is ready to copy.
$secret = trim((string)getSetting('cron_secret', ''));
if ($secret === '' && isSuperAdmin()) {
    $secret = bin2hex(random_bytes(16));
    setSetting('cron_secret', $secret);
}
cronEnsureJobs();

$pageTitle = 'Cron & Scheduled Tasks';
$activeNav = 'cron';
$csrf = csrfToken();
$cronUrl = rtrim(BASE_URL, '/') . '/cron/run.php?key=' . $secret;
require __DIR__ . '/_header.php';
?>
<div id="healthBar"></div>

<div class="card mb-3"><div class="card-body">
  <h6 class="fw-semibold mb-2"><i class="bi bi-hdd-network"></i> The one &amp; only server cron</h6>
  <p class="small text-muted mb-2">Add this <strong>single</strong> cron in cPanel (every minute). It runs all due jobs below — you never add another server cron again.</p>
  <div class="input-group input-group-sm">
    <span class="input-group-text">* * * * *</span>
    <input class="form-control font-monospace" id="cronCmd" readonly value="curl -s &quot;<?= e($cronUrl) ?>&quot; >/dev/null 2>&1">
    <button class="btn btn-outline-secondary" onclick="copyCron()"><i class="bi bi-clipboard"></i> Copy</button>
  </div>
  <div class="d-flex gap-2 mt-3 flex-wrap">
    <button class="btn btn-sm btn-primary" onclick="runAll()"><i class="bi bi-play-fill"></i> Run all due now</button>
    <button class="btn btn-sm btn-outline-warning" onclick="retryFailed()"><i class="bi bi-arrow-clockwise"></i> Retry failed</button>
    <button class="btn btn-sm btn-outline-secondary" onclick="loadJobs()"><i class="bi bi-arrow-repeat"></i> Refresh</button>
  </div>
</div></div>

<div class="card"><div class="card-body">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Job</th><th>Schedule</th><th>Status</th><th>Last run</th><th>Next run</th><th>On</th><th></th></tr></thead>
      <tbody id="jobsBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
    </table>
  </div>
</div></div>

<!-- Logs modal -->
<div class="modal fade" id="logsModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title" id="logsTitle">Execution history</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Started</th><th>Status</th><th>Duration</th><th>Message</th></tr></thead>
    <tbody id="logsBody"></tbody></table></div></div>
</div></div></div>

<?php
$pageScript = '<script>window.__CRON=' . json_encode(['base' => BASE_URL, 'csrf' => $csrf]) . ';</script>' . <<<'HTML'
<script>
const B=window.__CRON.base, CSRF=window.__CRON.csrf;
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
function api(action, data, method){
  const opt={method:method||'GET',headers:{'X-CSRF-Token':CSRF}};
  if(opt.method==='POST'){ const fd=new FormData(); for(const k in (data||{})) fd.append(k,data[k]); fd.append('csrf_token',CSRF); opt.body=fd; }
  const qs = (method==='POST'||!data)?'':('&'+new URLSearchParams(data));
  return fetch(B+'/api/cron.php?action='+action+qs, opt).then(r=>r.json());
}
const SB={success:'success',failed:'danger',running:'info',idle:'secondary'};
function ago(ts){ if(!ts) return '—'; const s=Math.floor((Date.now()-new Date(ts.replace(' ','T')).getTime())/1000);
  if(s<60) return s+'s ago'; if(s<3600) return Math.floor(s/60)+'m ago'; if(s<86400) return Math.floor(s/3600)+'h ago'; return Math.floor(s/86400)+'d ago'; }
function sched(j){ if(j.at_hour!==null&&j.at_hour!=='') return 'Daily ~'+String(j.at_hour).padStart(2,'0')+':00';
  const m=+j.interval_minutes; return m<=1?'Every minute':(m<60?('Every '+m+' min'):('Every '+(m/60)+' h')); }

function loadJobs(){
  api('jobs').then(r=>{ if(r.status!=='success') return;
    const h=r.data.health||{};
    let hb='';
    if(!h.server_ok){ hb='<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-octagon"></i> The master cron has not run in the last 5 minutes'+(h.last_run?(' (last: '+esc(h.last_run)+')'):'')+'. Add the server cron below.</div>'; }
    else { hb='<div class="alert alert-success py-2 small"><i class="bi bi-check-circle"></i> Master cron healthy — last ran '+ago(h.last_run)+'.'+(h.failing>0?(' <span class="text-danger">'+h.failing+' job(s) currently failing.</span>'):'')+'</div>'; }
    document.getElementById('healthBar').innerHTML=hb;

    document.getElementById('jobsBody').innerHTML=(r.data.jobs||[]).map(j=>`
      <tr>
        <td><b>${esc(j.title)}</b><div class="small text-muted">${esc(j.desc||'')}</div></td>
        <td class="small">${sched(j)}</td>
        <td><span class="badge bg-${SB[j.last_status]||'secondary'}">${esc(j.last_status)}</span>
            ${j.last_message?('<div class="small text-muted text-truncate" style="max-width:220px" title="'+esc(j.last_message)+'">'+esc(j.last_message)+'</div>'):''}</td>
        <td class="small">${ago(j.last_run_at)}${j.last_duration_ms?(' · '+j.last_duration_ms+'ms'):''}</td>
        <td class="small">${j.enabled==1?(j.next_run_at?esc((j.next_run_at||'').slice(5,16)):'soon'):'<span class="text-muted">paused</span>'}</td>
        <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" ${j.enabled==1?'checked':''} onchange="toggle('${j.job_key}',this.checked)"></div></td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline-primary py-0" onclick="runJob('${j.job_key}',this)" title="Run now"><i class="bi bi-play"></i></button>
          <button class="btn btn-sm btn-outline-secondary py-0" onclick="viewLogs('${j.job_key}','${esc(j.title)}')" title="History"><i class="bi bi-clock-history"></i></button>
        </td>
      </tr>`).join('');
  });
}
function runJob(key,btn){ if(btn){btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';}
  api('run',{key},'POST').then(r=>{ if(window.AK&&AK.toast) AK.toast(r.status==='success'?'success':'error', r.message); else alert(r.message); loadJobs(); }); }
function runAll(){ api('run_all',{},'POST').then(r=>{ alert(r.message+(r.data&&r.data.summary&&r.data.summary.length?'\\n'+r.data.summary.join('\\n'):'')); loadJobs(); }); }
function retryFailed(){ api('retry',{},'POST').then(r=>{ alert(r.message); loadJobs(); }); }
function toggle(key,on){ api('toggle',{key,enabled:on?'1':'0'},'POST').then(()=>loadJobs()); }
function viewLogs(key,title){
  document.getElementById('logsTitle').textContent='History — '+title;
  document.getElementById('logsBody').innerHTML='<tr><td colspan="4" class="text-muted">Loading…</td></tr>';
  new bootstrap.Modal(document.getElementById('logsModal')).show();
  api('logs',{key}).then(r=>{ if(r.status!=='success') return;
    document.getElementById('logsBody').innerHTML=(r.data.logs||[]).map(l=>
      `<tr><td class="small">${esc((l.started_at||'').slice(5,19))}</td>
        <td><span class="badge bg-${SB[l.status]||'secondary'}">${esc(l.status)}</span></td>
        <td class="small">${l.duration_ms||0}ms</td>
        <td class="small">${esc(l.message||'')}</td></tr>`).join('')
      || '<tr><td colspan="4" class="text-muted">No runs yet.</td></tr>';
  });
}
function copyCron(){ const i=document.getElementById('cronCmd'); i.select(); document.execCommand('copy'); if(window.AK&&AK.toast) AK.toast('success','Copied'); }
loadJobs();
setInterval(loadJobs, 30000);
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
