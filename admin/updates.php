<?php
/**
 * Super Admin › System › Updates.
 * GitHub auto-update control panel: settings, version check, one-click update
 * with a live progress UI, update history, rollback and manual ZIP upload.
 *
 * All heavy lifting (GitHub calls, the update pipeline) is done by api/update.php.
 * This page renders the UI and handles the settings-save self-POST.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// -----------------------------------------------------------------------------
// Token obfuscation — mirrors api/update.php so a token saved here can be read
// back there. Simple reversible AES obfuscation when openssl is available.
// -----------------------------------------------------------------------------
function upd_secret_key(): string {
    $seed = (defined('DB_PASS') ? DB_PASS : '') . '|' . (defined('DB_NAME') ? DB_NAME : '') . '|ak-menu-update';
    return hash('sha256', $seed, true);
}
function upd_enc_token(string $plain): string {
    if ($plain === '') return '';
    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(16);
        $ct = openssl_encrypt($plain, 'aes-256-cbc', upd_secret_key(), OPENSSL_RAW_DATA, $iv);
        if ($ct !== false) return 'enc:' . base64_encode($iv . $ct);
    }
    return $plain;
}
function upd_dec_token(string $stored): string {
    if ($stored === '') return '';
    if (strncmp($stored, 'enc:', 4) === 0 && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($stored, 4), true);
        if ($raw !== false && strlen($raw) > 16) {
            $iv = substr($raw, 0, 16); $ct = substr($raw, 16);
            $pt = openssl_decrypt($ct, 'aes-256-cbc', upd_secret_key(), OPENSSL_RAW_DATA, $iv);
            if ($pt !== false) return $pt;
        }
    }
    return $stored;
}

// -----------------------------------------------------------------------------
// POST: save update settings.
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }

    // Single "owner/repo" field (like a standard GitHub slug) is split here.
    $full = trim((string)($_POST['github_repo_full'] ?? ''));
    $full = preg_replace('#^https?://github\.com/#i', '', $full);   // tolerate a pasted URL
    $full = trim($full, '/ ');
    if (strpos($full, '/') !== false) {
        [$o, $r] = explode('/', $full, 2);
        setSetting('github_owner', trim($o));
        setSetting('github_repo',  trim(preg_replace('/\.git$/', '', $r)));
    }
    // Branch is free text (branch names can be long, e.g. claude/feature-xyz).
    $branch = trim((string)($_POST['github_branch'] ?? 'main'));
    setSetting('github_branch', $branch !== '' ? $branch : 'main');
    setSetting('auto_backup', isset($_POST['auto_backup']) ? '1' : '0');

    // Only overwrite the token when a new value is actually typed (the field is
    // rendered blank so we never leak the stored token back to the browser).
    $token = (string)($_POST['github_token'] ?? '');
    if ($token !== '') {
        setSetting('github_token', upd_enc_token(trim($token)));
    }

    // Baseline the installed commit the first time settings are saved, so the
    // freshly-deployed code reads as "up to date" (future commits show as updates).
    if ((string)getSetting('installed_commit', '') === '') {
        $o = trim((string)getSetting('github_owner', ''));
        $r = trim((string)getSetting('github_repo', ''));
        $b = trim((string)getSetting('github_branch', 'main')) ?: 'main';
        $tk = upd_dec_token((string)getSetting('github_token', ''));
        if ($o !== '' && $r !== '' && function_exists('curl_init')) {
            $h = ['User-Agent: AK-Menu-System', 'Accept: application/vnd.github+json'];
            if ($tk !== '') { $h[] = 'Authorization: token ' . $tk; }
            $ch = curl_init('https://api.github.com/repos/' . rawurlencode($o) . '/' . rawurlencode($r) . '/commits/' . rawurlencode($b));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300) {
                $sha = json_decode((string)$resp, true)['sha'] ?? '';
                if ($sha) { setSetting('installed_commit', $sha); }
            }
        }
    }

    logActivity('admin', (int)($_SESSION['admin_id'] ?? 0), 'Updated GitHub update settings');
    redirect(BASE_URL . '/admin/updates.php?saved=1');
}

// -----------------------------------------------------------------------------
// Read current settings for rendering.
// -----------------------------------------------------------------------------
$owner    = (string)getSetting('github_owner', '');
$repo     = (string)getSetting('github_repo', '');
$branch   = (string)getSetting('github_branch', 'main');
$channel  = (string)getSetting('update_channel', 'manual');
$autoBk   = getSetting('auto_backup', '1') === '1';
$hasToken = trim((string)getSetting('github_token', '')) !== '';
$appVer   = (string)getSetting('app_version', APP_VERSION);

// Update history (latest 20).
$history = db_all('SELECT * FROM ' . tbl('update_logs') . ' ORDER BY id DESC LIMIT 20');

// Last 5 DB backups for the rollback panel.
$backups = db_all('SELECT * FROM ' . tbl('backups') . " WHERE type='db' ORDER BY id DESC LIMIT 5");

/** Human-friendly duration read from a log row's JSON. */
function upd_duration(?string $logText): string {
    if (!$logText) return '—';
    $j = json_decode($logText, true);
    return isset($j['duration']) ? ($j['duration'] . 's') : '—';
}

$pageTitle = 'Updates';
$activeNav = 'updates';
require __DIR__ . '/_header.php';
?>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    Settings saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- ===================== Version + actions ===================== -->
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="card-title mb-1"><i class="bi bi-cloud-arrow-down"></i> System Updates</h5>
            <div class="text-muted small">Set the repo/branch once, then Check &amp; Update straight from GitHub — no releases needed. <code>config/db.php</code> and <code>uploads/</code> are never touched.</div>
          </div>
          <?php $instShort = ($ic = (string)getSetting('installed_commit', '')) !== '' ? substr($ic, 0, 7) : ('v' . $appVer); ?>
          <span class="badge bg-secondary fs-6" title="Installed commit / version"><?= e($instShort) ?></span>
        </div>

        <div id="checkResult" class="border rounded p-3 bg-light-subtle mb-3">
          <div class="text-muted small">Click <b>Check for Updates</b> to see the latest release.</div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button id="btnCheck" class="btn btn-outline-primary">
            <i class="bi bi-arrow-repeat"></i> Check for Updates
          </button>
          <button id="btnUpdate" class="btn btn-primary" disabled>
            <i class="bi bi-download"></i> Update Now
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== Settings form ===================== -->
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-gear"></i> Update Settings</h6>
        <form method="post" action="<?= e(BASE_URL) ?>/admin/updates.php">
          <?= csrfField() ?>
          <div class="mb-2">
            <label class="form-label small mb-1">GitHub repo <span class="text-muted">(owner/repo)</span></label>
            <input type="text" name="github_repo_full" class="form-control form-control-sm"
                   value="<?= e($owner !== '' && $repo !== '' ? $owner . '/' . $repo : '') ?>"
                   placeholder="akshaykananidwk/menu.akdwk.in">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">Branch</label>
            <input type="text" name="github_branch" class="form-control form-control-sm"
                   value="<?= e($branch) ?>" placeholder="main">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">
              GitHub Token
              <i class="bi bi-shield-lock" title="Stored encrypted"></i>
            </label>
            <input type="password" name="github_token" class="form-control form-control-sm" autocomplete="new-password"
                   placeholder="<?= $hasToken ? '•••••••• (leave blank to keep)' : 'Optional — for private repos' ?>">
            <div class="form-text small">Stored encrypted. Leave blank to keep the current token.</div>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="autoBackup" name="auto_backup" <?= $autoBk ? 'checked' : '' ?>>
            <label class="form-check-label small" for="autoBackup">Auto-backup database before updating</label>
          </div>
          <button class="btn btn-sm btn-secondary w-100"><i class="bi bi-save"></i> Save Settings</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== Live progress ===================== -->
  <div class="col-12">
    <div class="card shadow-sm d-none" id="progressCard">
      <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-list-check"></i> Update Progress</h6>
        <ul class="list-group" id="progressList"></ul>
      </div>
    </div>
  </div>

  <!-- ===================== History ===================== -->
  <div class="col-lg-8">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-clock-history"></i> Update History</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr>
              <th>From → To</th><th>Status</th><th>Duration</th><th>By</th><th>Date</th>
            </tr></thead>
            <tbody>
              <?php if (!$history): ?>
                <tr><td colspan="5" class="text-muted text-center py-3">No updates performed yet.</td></tr>
              <?php else: foreach ($history as $h):
                $badge = $h['status'] === 'success' ? 'success' : ($h['status'] === 'failed' ? 'danger' : 'warning'); ?>
                <tr>
                  <td><code><?= e($h['from_version']) ?></code> → <code><?= e($h['to_version']) ?></code></td>
                  <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($h['status'])) ?></span></td>
                  <td><?= e(upd_duration($h['log_text'])) ?></td>
                  <td class="small"><?= e($h['performed_by']) ?></td>
                  <td class="small text-muted"><?= e($h['created_at']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== Rollback + manual upload ===================== -->
  <div class="col-lg-4">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-arrow-counterclockwise"></i> Rollback</h6>
        <p class="small text-muted">Restore the database from a recent backup if an update caused problems.</p>
        <ul class="list-group list-group-flush">
          <?php if (!$backups): ?>
            <li class="list-group-item px-0 text-muted small">No backups available yet.</li>
          <?php else: foreach ($backups as $b): ?>
            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
              <div class="small">
                <div><i class="bi bi-database"></i> v<?= e($b['version']) ?></div>
                <div class="text-muted"><?= e($b['created_at']) ?> · <?= e(number_format($b['size'] / 1024, 0)) ?> KB</div>
              </div>
              <button class="btn btn-sm btn-outline-danger btn-rollback" data-id="<?= (int)$b['id'] ?>">Restore</button>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-file-earmark-zip"></i> Manual Upload</h6>
        <p class="small text-muted">Upload a release ZIP to update without GitHub.</p>
        <form id="manualForm">
          <input type="file" name="package" accept=".zip" class="form-control form-control-sm mb-2" required>
          <input type="text" name="version" class="form-control form-control-sm mb-2" placeholder="Version (e.g. 1.0.2, optional)">
          <button class="btn btn-sm btn-warning w-100"><i class="bi bi-upload"></i> Upload &amp; Update</button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php
$apiUrl = BASE_URL . '/api/update.php';
$pageScript = <<<HTML
<script>
(function(){
  const API = '{$apiUrl}';
  let latestZip = null;

  // Minimal, safe markdown-ish renderer for the release body.
  function mdMini(src){
    let s = (src||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    s = s.replace(/^### (.*)$/gm,'<b>\$1</b>')
         .replace(/^## (.*)$/gm,'<b>\$1</b>')
         .replace(/^# (.*)$/gm,'<b>\$1</b>')
         .replace(/\\*\\*(.*?)\\*\\*/g,'<b>\$1</b>')
         .replace(/^[-*] (.*)$/gm,'&bull; \$1')
         .replace(/\\n/g,'<br>');
    return s;
  }

  const elResult = document.getElementById('checkResult');
  const btnCheck = document.getElementById('btnCheck');
  const btnUpdate = document.getElementById('btnUpdate');
  const progressCard = document.getElementById('progressCard');
  const progressList = document.getElementById('progressList');

  function stepIcon(state){
    if(state==='done')    return '<i class="bi bi-check-circle-fill text-success"></i>';
    if(state==='error')   return '<i class="bi bi-x-circle-fill text-danger"></i>';
    if(state==='skipped') return '<i class="bi bi-dash-circle text-muted"></i>';
    return '<span class="spinner-border spinner-border-sm text-primary"></span>';
  }
  function renderSteps(steps){
    progressCard.classList.remove('d-none');
    progressList.innerHTML = (steps||[]).map(function(s){
      return '<li class="list-group-item d-flex justify-content-between align-items-center">'+
             '<span>'+stepIcon(s.state)+' '+s.name+'</span>'+
             '<span class="small text-muted">'+(s.message||'')+'</span></li>';
    }).join('');
  }

  // ---- Check for updates ----
  btnCheck.addEventListener('click', function(){
    btnCheck.disabled = true;
    btnCheck.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Checking…';
    AK.get(API + '?action=check').then(function(res){
      const d = res.data || {};
      if(!d.reachable){
        elResult.innerHTML = '<div class="text-danger"><i class="bi bi-exclamation-triangle"></i> '+
          (d.error||'Could not reach GitHub.')+'</div>'+
          '<div class="small text-muted mt-1">Installed: '+(d.current||'?')+'</div>';
        btnUpdate.disabled = true;
      } else if(d.newer){
        latestZip = d.zipball_url;
        elResult.innerHTML =
          '<div class="d-flex align-items-center gap-2 mb-2">'+
          '<span class="badge bg-success">Update available</span>'+
          '<b>'+d.latest+'</b> <span class="text-muted small">(installed: '+d.current+')</span></div>'+
          (d.released?('<div class="small text-muted mb-2">Latest commit: '+new Date(d.released).toLocaleString()+'</div>'):'')+
          '<div class="border-top pt-2 small" style="max-height:180px;overflow:auto">'+mdMini(d.changelog||'No changelog.')+'</div>';
        btnUpdate.disabled = false;
      } else {
        elResult.innerHTML = '<div class="text-success"><i class="bi bi-check-circle"></i> You are up to date ('+d.current+').</div>';
        btnUpdate.disabled = true;
      }
    }).catch(function(){
      elResult.innerHTML = '<div class="text-danger">Check failed. Please try again.</div>';
    }).finally(function(){
      btnCheck.disabled = false;
      btnCheck.innerHTML = '<i class="bi bi-arrow-repeat"></i> Check for Updates';
    });
  });

  // ---- Run update ----
  btnUpdate.addEventListener('click', function(){
    AK.confirm('This will put the site into maintenance mode and update the system. A database backup will be taken first. Continue?','Update Now?').then(function(ok){
      if(!ok) return;
      btnUpdate.disabled = true; btnCheck.disabled = true;
      btnUpdate.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating…';
      progressCard.classList.remove('d-none');
      progressList.innerHTML = '<li class="list-group-item"><span class="spinner-border spinner-border-sm text-primary"></span> Starting…</li>';

      // Poll progress while the pipeline runs.
      const poll = setInterval(function(){
        AK.get(API + '?action=progress').then(function(r){
          if(r.data && r.data.steps) renderSteps(r.data.steps);
        });
      }, 1500);

      AK.post(API + '?action=run', {}).then(function(res){
        clearInterval(poll);
        if(res.status==='success'){
          renderSteps(res.data.steps);
          AK.toast('success','Update completed! Reloading…');
          setTimeout(function(){ location.reload(); }, 2500);
        } else {
          if(res.data && res.data.steps) renderSteps(res.data.steps);
          AK.toast('error', res.message || 'Update failed');
          btnUpdate.disabled = false; btnCheck.disabled = false;
          btnUpdate.innerHTML = '<i class="bi bi-download"></i> Update Now';
        }
      }).catch(function(){
        clearInterval(poll);
        AK.toast('error','Update request failed');
        btnUpdate.disabled = false; btnCheck.disabled = false;
        btnUpdate.innerHTML = '<i class="bi bi-download"></i> Update Now';
      });
    });
  });

  // ---- Rollback ----
  document.querySelectorAll('.btn-rollback').forEach(function(btn){
    btn.addEventListener('click', function(){
      const id = btn.getAttribute('data-id');
      AK.confirm('Restore the database from this backup? Current data will be overwritten.','Rollback?').then(function(ok){
        if(!ok) return;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        AK.post(API + '?action=rollback', {backup_id:id}).then(function(res){
          AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 1500); });
          if(res.status!=='success'){ btn.disabled=false; btn.innerHTML='Restore'; }
        });
      });
    });
  });

  // ---- Manual upload ----
  document.getElementById('manualForm').addEventListener('submit', function(ev){
    ev.preventDefault();
    AK.confirm('Upload this ZIP and update the system now?','Manual Update?').then(function(ok){
      if(!ok) return;
      const fd = new FormData(ev.target);
      progressCard.classList.remove('d-none');
      progressList.innerHTML = '<li class="list-group-item"><span class="spinner-border spinner-border-sm text-primary"></span> Uploading…</li>';
      const poll = setInterval(function(){
        AK.get(API + '?action=progress').then(function(r){
          if(r.data && r.data.steps) renderSteps(r.data.steps);
        });
      }, 1500);
      AK.post(API + '?action=manual_upload', fd).then(function(res){
        clearInterval(poll);
        if(res.data && res.data.steps) renderSteps(res.data.steps);
        AK.handle(res, function(){ setTimeout(function(){ location.reload(); }, 2500); });
      }).catch(function(){
        clearInterval(poll);
        AK.toast('error','Upload failed');
      });
    });
  });
})();
</script>
HTML;
require __DIR__ . '/_footer.php';
