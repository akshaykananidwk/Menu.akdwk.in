<?php
/**
 * Forgot password — step 1.
 *
 * One field, two channels (api/auth.php works out which):
 *   e-mail  → a single-use reset LINK is mailed (client/reset_password.php)
 *   mobile  → a 6-digit WhatsApp OTP, finished on this same page
 *
 * The page never reveals whether an account exists.
 */
require_once dirname(__DIR__) . '/config/config.php';
if (isClient()) { redirect(BASE_URL . '/client/index.php'); }

$siteName  = getSetting('site_name', 'AK Menu System');
$mailReady = mailerEnabled();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>"><meta name="base-url" content="<?= e(BASE_URL) ?>">
<title>Forgot Password · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#e63946,#f1a208);min-height:100vh;display:flex;align-items:center;font-family:system-ui}
.login-card{max-width:420px;margin:auto;border-radius:16px;border:0;box-shadow:0 20px 60px rgba(0,0,0,.3)}</style>
</head><body>
<div class="container"><div class="card login-card p-4">
  <div class="text-center mb-3">
    <i class="bi bi-key text-danger" style="font-size:2.3rem"></i>
    <h5 class="mt-2 mb-0">Forgot your password?</h5>
    <small class="text-muted">Enter your e-mail or mobile and we'll help you back in.</small>
  </div>

  <div id="msg" class="small mb-3" style="display:none"></div>

  <!-- Step 1: ask for the identifier -->
  <div id="step1">
    <div class="mb-3">
      <label class="form-label">E-mail address or mobile number</label>
      <input id="ident" class="form-control" placeholder="you@example.com or 9876543210" autocomplete="username">
      <div class="form-text">
        <?php if ($mailReady): ?>
          E-mail → we send a reset link. Mobile → we send a 6-digit code on WhatsApp.
        <?php else: ?>
          Enter your registered mobile number — we'll send a 6-digit code on WhatsApp.
        <?php endif; ?>
      </div>
    </div>
    <button id="sendBtn" class="btn btn-danger w-100" onclick="requestReset()">Send reset instructions</button>
  </div>

  <!-- Step 2 (mobile channel only): OTP + new password -->
  <div id="step2" style="display:none">
    <div class="alert alert-light border small py-2">
      <i class="bi bi-whatsapp text-success"></i>
      Code sent on WhatsApp to <strong id="shownMobile"></strong>.
    </div>
    <div class="mb-3">
      <label class="form-label">6-digit code</label>
      <input id="otp" class="form-control text-center" inputmode="numeric" maxlength="6"
             placeholder="------" autocomplete="one-time-code" style="letter-spacing:6px;font-size:1.2rem">
    </div>
    <div class="mb-3">
      <label class="form-label">New password</label>
      <input id="pass1" type="password" class="form-control" placeholder="At least 6 characters" autocomplete="new-password">
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm new password</label>
      <input id="pass2" type="password" class="form-control" autocomplete="new-password">
    </div>
    <button id="resetBtn" class="btn btn-danger w-100" onclick="finishReset()">Change password</button>
    <button class="btn btn-link w-100 mt-1 small" onclick="backToStep1()">Use a different number or e-mail</button>
  </div>

  <div class="text-center mt-3 pt-2 border-top">
    <a href="<?= e(BASE_URL) ?>/client/login.php" class="small text-decoration-none">
      <i class="bi bi-arrow-left"></i> Back to login
    </a>
  </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
<script>
const API  = '<?= e(BASE_URL) ?>/api/auth.php';
const msgEl = document.getElementById('msg');

function say(text, ok){
  msgEl.style.display = 'block';
  msgEl.className = 'small mb-3 alert py-2 ' + (ok ? 'alert-success' : 'alert-danger');
  msgEl.innerHTML = (ok ? '<i class="bi bi-check-circle"></i> ' : '<i class="bi bi-exclamation-circle"></i> ') + text;
}
function backToStep1(){
  document.getElementById('step2').style.display = 'none';
  document.getElementById('step1').style.display = '';
  msgEl.style.display = 'none';
}

function requestReset(){
  const v = (document.getElementById('ident').value || '').trim();
  if(!v){ say('Please enter your e-mail address or mobile number.', false); return; }
  const btn = document.getElementById('sendBtn');
  btn.disabled = true; btn.textContent = 'Sending…';

  AK.post(API + '?action=forgot_password', {login: v}).then(r => {
    btn.disabled = false; btn.textContent = 'Send reset instructions';
    if(r && r.status === 'success'){
      say(r.message, true);
      // WhatsApp channel finishes here on the same page; e-mail continues in the inbox.
      if(r.data && r.data.channel === 'whatsapp'){
        document.getElementById('shownMobile').textContent = v;
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = '';
        document.getElementById('otp').focus();
      }
    } else {
      say((r && r.message) || 'Could not send the reset instructions. Please try again.', false);
    }
  }).catch(() => {
    btn.disabled = false; btn.textContent = 'Send reset instructions';
    say('Network error. Please try again.', false);
  });
}

function finishReset(){
  const otp = (document.getElementById('otp').value || '').trim();
  const p1  = document.getElementById('pass1').value || '';
  const p2  = document.getElementById('pass2').value || '';
  if(otp.length < 4){ say('Please enter the 6-digit code from WhatsApp.', false); return; }
  if(p1.length < 6){ say('The new password must be at least 6 characters.', false); return; }
  if(p1 !== p2){ say('The two passwords do not match.', false); return; }

  const btn = document.getElementById('resetBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  AK.post(API + '?action=reset_with_otp', {
    mobile: (document.getElementById('ident').value || '').trim(),
    otp: otp, password: p1
  }).then(r => {
    if(r && r.status === 'success'){
      say(r.message + ' Redirecting to login…', true);
      document.getElementById('step2').style.display = 'none';
      setTimeout(() => { location.href = '<?= e(BASE_URL) ?>/client/login.php'; }, 1600);
    } else {
      btn.disabled = false; btn.textContent = 'Change password';
      say((r && r.message) || 'Could not change the password. Please try again.', false);
    }
  }).catch(() => {
    btn.disabled = false; btn.textContent = 'Change password';
    say('Network error. Please try again.', false);
  });
}

document.getElementById('ident').addEventListener('keydown', e => { if(e.key === 'Enter'){ requestReset(); } });
</script>
</body></html>
