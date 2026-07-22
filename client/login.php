<?php
/** Restaurant owner login (password or WhatsApp OTP). */
require_once dirname(__DIR__) . '/config/config.php';
if (isClient()) { redirect(BASE_URL . '/client/index.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $mode = $_POST['mode'] ?? 'password';
    $login = trim($_POST['login'] ?? '');
    $ident = 'client:' . $login;

    if (isLoginLocked($ident)) {
        $error = __('account_locked');
    } elseif ($mode === 'otp') {
        $otp = trim($_POST['otp'] ?? '');
        $login = normalizeMobile($login); // match how the OTP was sent/stored
        $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE mobile = :m', [':m' => $login]);
        if ($tenant && verifyOtp($login, $otp, 'login')) {
            doClientLogin($tenant, $ident);
        } else { recordFailedLogin($ident); $error = 'Invalid or expired OTP.'; }
    } else {
        // Password mode: match by mobile OR email.
        $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE mobile = :m OR email = :e', [':m' => $login, ':e' => $login]);
        if ($tenant && password_verify($_POST['password'] ?? '', $tenant['password'])) {
            doClientLogin($tenant, $ident);
        } else { recordFailedLogin($ident); $error = __('invalid_login'); }
    }
}

function doClientLogin(array $tenant, string $ident): void {
    clearLoginAttempts($ident);
    regenerateSession();
    $_SESSION['tenant_id'] = (int)$tenant['id'];
    $_SESSION['lang'] = $tenant['language'] ?: 'en';
    logActivity('tenant', (int)$tenant['id'], 'Client logged in');
    // First login -> onboarding wizard.
    redirect(BASE_URL . ($tenant['onboarded'] ? '/client/index.php' : '/client/onboarding.php'));
}
$siteName = getSetting('site_name', 'AK Menu System');
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>"><meta name="base-url" content="<?= e(BASE_URL) ?>">
<title>Restaurant Login · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#e63946,#f1a208);min-height:100vh;display:flex;align-items:center;font-family:system-ui}
.login-card{max-width:400px;margin:auto;border-radius:16px;border:0;box-shadow:0 20px 60px rgba(0,0,0,.3)}</style>
</head><body>
<div class="container"><div class="card login-card p-4">
  <div class="text-center mb-3"><i class="bi bi-shop text-danger" style="font-size:2.5rem"></i>
    <h5 class="mt-2 mb-0">Restaurant Panel</h5><small class="text-muted">Manage your digital menu</small></div>
  <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
  <ul class="nav nav-pills nav-justified mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pw">Password</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#otp">OTP</button></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="pw">
      <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="password">
        <div class="mb-3"><label class="form-label">Mobile / Email</label><input name="login" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
        <button class="btn btn-danger w-100">Login</button></form>
    </div>
    <div class="tab-pane fade" id="otp">
      <form method="post"><?= csrfField() ?><input type="hidden" name="mode" value="otp">
        <div class="mb-2"><label class="form-label">Registered Mobile</label>
          <div class="input-group"><input name="login" id="otpMobile" class="form-control" inputmode="tel" placeholder="10-digit mobile" required>
            <button type="button" class="btn btn-outline-danger" id="sendOtpBtn" onclick="sendOtp()">Send OTP</button></div></div>
        <div id="otpMsg" class="small mb-2" style="display:none"></div>
        <div class="mb-3"><label class="form-label">Enter OTP</label>
          <input name="otp" class="form-control text-center" inputmode="numeric" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" required></div>
        <button class="btn btn-danger w-100">Verify &amp; Login</button></form>
    </div>
  </div>
  <div class="text-center mt-3 pt-2 border-top">
    <span class="text-muted small">New restaurant?</span>
    <a href="<?= e(BASE_URL) ?>/signup.php" class="fw-semibold text-decoration-none">Sign up free →</a>
  </div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
<script>
const otpBtn = document.getElementById('sendOtpBtn');
const otpMsg = document.getElementById('otpMsg');
let otpTimer = null;

function showOtpMsg(text, ok){
  otpMsg.style.display = 'block';
  otpMsg.className = 'small mb-2 alert py-2 ' + (ok ? 'alert-success' : 'alert-danger');
  otpMsg.innerHTML = (ok ? '<i class="bi bi-check-circle"></i> ' : '<i class="bi bi-exclamation-circle"></i> ') + text;
}
function startCooldown(sec){
  let left = sec;
  otpBtn.disabled = true;
  const tick = () => {
    otpBtn.textContent = 'Resend in ' + left + 's';
    if(left-- <= 0){ clearInterval(otpTimer); otpBtn.disabled = false; otpBtn.textContent = 'Resend OTP'; }
  };
  tick(); otpTimer = setInterval(tick, 1000);
}
function sendOtp(){
  const m = (document.getElementById('otpMobile').value || '').trim();
  if(!m){ showOtpMsg('Please enter your registered mobile number.', false); return; }
  otpBtn.disabled = true; otpBtn.textContent = 'Sending…';
  AK.post('<?= e(BASE_URL) ?>/api/auth.php?action=send_otp', {mobile:m}).then(r=>{
    if(r && r.status === 'success'){
      showOtpMsg((r.message || 'OTP sent to your WhatsApp.'), true);
      startCooldown((r.data && r.data.cooldown) ? r.data.cooldown : 30);
    } else {
      showOtpMsg((r && r.message) || 'Could not send OTP. Please try again.', false);
      // If the server returned a cooldown (429), respect it; else re-enable.
      if(r && r.data && r.data.cooldown){ startCooldown(r.data.cooldown); }
      else { otpBtn.disabled = false; otpBtn.textContent = 'Send OTP'; }
    }
  }).catch(()=>{ showOtpMsg('Network error. Please try again.', false); otpBtn.disabled = false; otpBtn.textContent = 'Send OTP'; });
}
</script>
</body></html>
