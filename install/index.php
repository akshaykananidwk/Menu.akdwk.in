<?php
/**
 * AK Menu System - Web Installer wizard.
 * Self-contained: does NOT include the app config (which would redirect here).
 */
session_start();
define('ROOT_PATH', dirname(__DIR__));
define('LOCK_FILE', ROOT_PATH . '/config/installed.lock');

// Already installed? -> bounce to homepage.
if (file_exists(LOCK_FILE)) {
    header('Location: ../index.php?msg=already_installed');
    exit;
}

if (empty($_SESSION['inst_csrf'])) { $_SESSION['inst_csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['inst_csrf'];
$step = max(1, min(6, (int)($_GET['step'] ?? 1)));

/** Requirements evaluated for step 1. */
function requirements(): array {
    $writable = fn($p) => is_writable(ROOT_PATH . $p) ? true : @chmod(ROOT_PATH . $p, 0755);
    return [
        'PHP >= 8.0'          => version_compare(PHP_VERSION, '8.0.0', '>='),
        'PDO MySQL'           => extension_loaded('pdo_mysql'),
        'cURL'                => extension_loaded('curl'),
        'GD'                  => extension_loaded('gd'),
        'mbstring'            => extension_loaded('mbstring'),
        'fileinfo'            => extension_loaded('fileinfo'),
        'zip'                 => extension_loaded('zip'),
        'openssl'             => extension_loaded('openssl'),
        'allow_url_fopen'     => (bool)ini_get('allow_url_fopen'),
        'Writable /config'    => is_writable(ROOT_PATH . '/config'),
        'Writable /uploads'   => is_writable(ROOT_PATH . '/uploads'),
        'Writable /assets/cache' => is_writable(ROOT_PATH . '/assets/cache'),
    ];
}
$reqs = requirements();
$reqsPass = !in_array(false, $reqs, true);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · AK Menu System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#1d3557,#457b9d);min-height:100vh;font-family:system-ui,sans-serif;}
.wizard{max-width:720px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.wizard-head{background:#e63946;color:#fff;padding:22px 28px;}
.steps{display:flex;background:#f4f6fb;padding:14px;gap:6px;font-size:.78rem;overflow-x:auto;}
.steps .s{flex:1;text-align:center;color:#8a93a6;white-space:nowrap;}
.steps .s.active{color:#e63946;font-weight:600;}
.steps .s.done{color:#2a9d8f;}
.wizard-body{padding:28px;}
.req-row{display:flex;justify-content:space-between;padding:8px 12px;border-bottom:1px solid #eee;}
</style>
</head>
<body>
<div class="wizard">
  <div class="wizard-head">
    <h4 class="mb-0"><i class="bi bi-rocket-takeoff"></i> AK Menu System — Installation</h4>
    <small>menu.akdwk.in setup wizard</small>
  </div>
  <div class="steps">
    <?php $labels = ['Requirements','License','Database','Admin','Site','Finish'];
    foreach ($labels as $i => $l): $n = $i + 1;
      $cls = $n === $step ? 'active' : ($n < $step ? 'done' : ''); ?>
      <div class="s <?= $cls ?>"><?= $n ?>. <?= $l ?></div>
    <?php endforeach; ?>
  </div>
  <div class="wizard-body">
  <?php if ($step === 1): ?>
    <h5>Server Requirements</h5>
    <p class="text-muted small">All checks must pass before continuing.</p>
    <?php foreach ($reqs as $name => $ok): ?>
      <div class="req-row"><span><?= htmlspecialchars($name) ?></span>
        <span class="<?= $ok ? 'text-success' : 'text-danger' ?>">
          <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i> <?= $ok ? 'OK' : 'Failed' ?>
        </span></div>
    <?php endforeach; ?>
    <div class="d-flex justify-content-end mt-3">
      <a href="?step=2" class="btn btn-danger <?= $reqsPass ? '' : 'disabled' ?>">Next <i class="bi bi-arrow-right"></i></a>
    </div>

  <?php elseif ($step === 2): ?>
    <h5>License / Purchase Code</h5>
    <form method="post" action="ajax.php?action=license" data-inst>
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="mb-3"><label class="form-label">Purchase Code</label>
        <input name="purchase_code" class="form-control" placeholder="AK-XXXX-XXXX or AKDWK-OFFLINE" required>
        <div class="form-text">Offline mode: enter <code>AKDWK-OFFLINE</code> to activate without a license server.</div></div>
      <div class="mb-3"><label class="form-label">Domain</label>
        <input name="domain" class="form-control" value="<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>" required></div>
      <div class="d-flex justify-content-between"><a href="?step=1" class="btn btn-light">Back</a>
        <button class="btn btn-danger">Validate & Next</button></div>
    </form>

  <?php elseif ($step === 3): ?>
    <h5>Database Setup</h5>
    <form method="post" action="ajax.php?action=install_db" data-inst data-progress>
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">DB Host</label><input name="db_host" class="form-control" value="localhost" required></div>
        <div class="col-md-6"><label class="form-label">DB Name</label><input name="db_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">DB Username</label><input name="db_user" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">DB Password</label><input name="db_pass" type="password" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Table Prefix</label><input name="db_prefix" class="form-control" value="ak_"></div>
      </div>
      <div class="progress mt-3 d-none" data-bar><div class="progress-bar bg-success" style="width:0%">0%</div></div>
      <div class="mt-2 small text-muted" data-log></div>
      <div class="d-flex justify-content-between mt-3"><a href="?step=2" class="btn btn-light">Back</a>
        <button class="btn btn-danger">Test & Install Tables</button></div>
    </form>

  <?php elseif ($step === 4): ?>
    <h5>Super Admin Account</h5>
    <form method="post" action="ajax.php?action=create_admin" data-inst>
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control"></div>
        <div class="col-md-12"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Password</label><input name="password" type="password" class="form-control" minlength="6" required></div>
        <div class="col-md-6"><label class="form-label">Confirm</label><input name="password2" type="password" class="form-control" required></div>
      </div>
      <div class="d-flex justify-content-between mt-3"><a href="?step=3" class="btn btn-light">Back</a>
        <button class="btn btn-danger">Create Admin</button></div>
    </form>

  <?php elseif ($step === 5): ?>
    <h5>Site Configuration</h5>
    <form method="post" action="ajax.php?action=site_config" data-inst>
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Site Name</label><input name="site_name" class="form-control" value="AK Menu System" required></div>
        <div class="col-md-6"><label class="form-label">Site URL</label><input name="site_url" class="form-control" value="<?= htmlspecialchars((!empty($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])),'/')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Timezone</label><input name="timezone" class="form-control" value="Asia/Kolkata"></div>
        <div class="col-md-4"><label class="form-label">Currency</label><input name="currency" class="form-control" value="₹"></div>
        <div class="col-md-4"><label class="form-label">Default Language</label>
          <select name="default_language" class="form-select"><option value="en">English</option><option value="gu">ગુજરાતી</option></select></div>
      </div>
      <div class="d-flex justify-content-between mt-3"><a href="?step=4" class="btn btn-light">Back</a>
        <button class="btn btn-danger">Save & Finish</button></div>
    </form>

  <?php elseif ($step === 6): ?>
    <div class="text-center">
      <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
      <h4 class="mt-3">Installation Complete!</h4>
      <p class="text-muted">Your AK Menu System is ready.</p>
      <div class="alert alert-light text-start small">
        <strong>Admin Panel:</strong> <a href="../admin/">../admin/</a><br>
        <strong>Login:</strong> the email &amp; password you just created<br>
        <strong>Version:</strong> <?= htmlspecialchars(json_decode(file_get_contents(ROOT_PATH.'/version.json'),true)['version'] ?? '1.0.0') ?>
      </div>
      <div class="d-flex justify-content-center gap-2">
        <a href="../admin/" class="btn btn-danger">Go to Admin Panel</a>
        <button class="btn btn-outline-danger" onclick="delInstall()">Delete Install Folder</button>
      </div>
    </div>
  <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('form[data-inst]').forEach(f=>{
  f.addEventListener('submit', async ev=>{
    ev.preventDefault();
    const btn=f.querySelector('button[type=submit],button:not([type])');
    const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='Please wait...';
    const bar=f.querySelector('[data-bar]'), log=f.querySelector('[data-log]');
    if(bar){bar.classList.remove('d-none');}
    try{
      const res=await fetch(f.action,{method:'POST',body:new FormData(f)});
      const data=await res.json();
      if(bar&&data.data&&data.data.progress){
        const pb=bar.querySelector('.progress-bar'); pb.style.width='100%'; pb.textContent='100%';
      }
      if(log&&data.data&&data.data.log){log.innerHTML=data.data.log;}
      if(data.status==='success'){
        setTimeout(()=>location.href='?step='+data.data.next, bar?800:0);
      }else{
        alert(data.message||'Error'); btn.disabled=false; btn.innerHTML=orig;
      }
    }catch(e){alert('Request failed: '+e); btn.disabled=false; btn.innerHTML=orig;}
  });
});
function delInstall(){
  if(!confirm('Delete the /install folder? This cannot be undone.'))return;
  fetch('ajax.php?action=self_delete',{method:'POST',body:new URLSearchParams({csrf:'<?= $csrf ?>'})})
    .then(r=>r.json()).then(d=>{alert(d.message);location.href='../admin/';});
}
</script>
</body>
</html>
