<?php
/**
 * Master Staff App — one installable entry point for the whole restaurant floor.
 * Open it → pick your role (Waiter / Kitchen / Reception) → log in with the
 * restaurant's Property Code + your 4-digit PIN. Installable (PWA) on any phone
 * or tablet. Reuses the existing waiter/kitchen screens and the new reception one.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/waiter/_staff_auth.php';

// Already signed in → jump straight to the right screen.
if (isStaff()) {
    $r = $_SESSION['staff_role'] ?? 'waiter';
    $map = ['waiter' => '/waiter/index.php', 'kitchen' => '/kitchen/index.php', 'reception' => '/reception/index.php'];
    redirect(BASE_URL . ($map[$r] ?? '/waiter/index.php'));
}

$error = '';
$postedRole = $_POST['role'] ?? ($_GET['role'] ?? '');
if (!in_array($postedRole, ['waiter', 'kitchen', 'reception'], true)) { $postedRole = ''; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postedRole !== '') {
    // staffLoginHandler validates CSRF, resolves the tenant by property code,
    // checks the PIN and redirects to the role screen on success.
    $error = staffLoginHandler($postedRole);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0f1729">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Staff App">
<link rel="manifest" href="<?= e(BASE_URL) ?>/app/manifest.php">
<link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>/assets/img/icon-192.png">
<title>Staff App</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  body{margin:0;min-height:100dvh;font-family:'Poppins',system-ui,sans-serif;color:#eaf0ff;
    background:radial-gradient(1200px 600px at 50% -10%,#1e3a8a,#0f1729 60%);display:flex;flex-direction:column}
  .wrap{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:26px 20px;max-width:640px;margin:0 auto;width:100%}
  .logo{font-size:1.5rem;font-weight:800;letter-spacing:.5px;margin-bottom:6px}
  .logo i{color:#60a5fa}
  .sub{opacity:.7;margin-bottom:28px;text-align:center}
  .roles{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;width:100%}
  @media(max-width:540px){.roles{grid-template-columns:1fr;gap:12px}}
  .role{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:26px 14px;text-align:center;
    cursor:pointer;transition:.15s;color:inherit}
  .role:active{transform:scale(.97)}
  .role:hover{background:rgba(255,255,255,.11);border-color:#60a5fa}
  .role i{font-size:2.6rem;display:block;margin-bottom:10px;color:#60a5fa}
  .role b{font-size:1.15rem;font-weight:700}
  .role span{display:block;opacity:.6;font-size:.8rem;margin-top:4px}
  .card2{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:22px;padding:26px 22px;width:100%;max-width:400px}
  .card2 h2{margin:0 0 4px;font-size:1.3rem;display:flex;align-items:center;gap:10px}
  .card2 .rl{opacity:.65;margin:0 0 18px;font-size:.9rem}
  label{display:block;font-size:.85rem;opacity:.8;margin:14px 0 6px}
  input{width:100%;padding:14px 16px;border-radius:14px;border:1px solid rgba(255,255,255,.18);background:rgba(0,0,0,.25);color:#fff;font-size:1.1rem;outline:none}
  input:focus{border-color:#60a5fa}
  .pin{letter-spacing:.5rem;text-align:center;font-size:1.5rem}
  .btn{width:100%;margin-top:20px;padding:15px;border:0;border-radius:14px;background:linear-gradient(120deg,#2563eb,#1d4ed8);color:#fff;font-size:1.05rem;font-weight:700;cursor:pointer}
  .btn:active{transform:scale(.98)}
  .back{background:transparent;border:0;color:#93c5fd;margin-top:14px;cursor:pointer;font-size:.9rem}
  .err{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);color:#fecaca;border-radius:12px;padding:10px 12px;font-size:.88rem;margin-bottom:14px}
  .install{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#fff;color:#0f1729;border:0;border-radius:30px;padding:11px 20px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.4);display:none;cursor:pointer}
  .foot{opacity:.5;font-size:.75rem;text-align:center;padding:14px}
  .hidden{display:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo"><i class="bi bi-shop"></i> Restaurant Staff</div>
  <div class="sub">Choose your role to sign in</div>

  <!-- STEP 1: role picker -->
  <div id="rolePick" class="roles <?= $postedRole !== '' ? 'hidden' : '' ?>">
    <button class="role" onclick="pick('waiter')"><i class="bi bi-person-badge"></i><b>Waiter</b><span>Take orders</span></button>
    <button class="role" onclick="pick('kitchen')"><i class="bi bi-fire"></i><b>Kitchen</b><span>KOT display</span></button>
    <button class="role" onclick="pick('reception')"><i class="bi bi-clipboard2-check"></i><b>Reception</b><span>Run the floor</span></button>
  </div>

  <!-- STEP 2: login -->
  <div id="loginBox" class="card2 <?= $postedRole === '' ? 'hidden' : '' ?>">
    <h2><i id="roleIcon" class="bi bi-person-badge"></i> <span id="roleName">Waiter</span></h2>
    <p class="rl">Enter your restaurant's Property Code and your PIN.</p>
    <?php if ($error): ?><div class="err"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>
    <form method="post" id="loginForm">
      <?= csrfField() ?>
      <input type="hidden" name="role" id="roleField" value="<?= e($postedRole ?: 'waiter') ?>">
      <label>Property Code</label>
      <input name="rcode" id="rcode" inputmode="numeric" autocomplete="off" placeholder="4-digit code" value="<?= e($_POST['rcode'] ?? '') ?>" required <?= $postedRole !== '' ? 'autofocus' : '' ?>>
      <label>PIN</label>
      <input name="pin" class="pin" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="••••" required>
      <button class="btn" type="submit">Sign In</button>
    </form>
    <button class="back" onclick="backToRoles()"><i class="bi bi-arrow-left"></i> Choose a different role</button>
  </div>
</div>

<button class="install" id="installBtn"><i class="bi bi-download"></i> Install App</button>
<div class="foot">Add to Home Screen to use it like an app · works offline for the login screen</div>

<script>
const ROLE = {waiter:['bi-person-badge','Waiter'],kitchen:['bi-fire','Kitchen'],reception:['bi-clipboard2-check','Reception']};
function pick(r){
  document.getElementById('roleField').value=r;
  document.getElementById('roleIcon').className='bi '+ROLE[r][0];
  document.getElementById('roleName').textContent=ROLE[r][1];
  document.getElementById('rolePick').classList.add('hidden');
  document.getElementById('loginBox').classList.remove('hidden');
  setTimeout(()=>document.getElementById('rcode').focus(),80);
}
function backToRoles(){
  document.getElementById('loginBox').classList.add('hidden');
  document.getElementById('rolePick').classList.remove('hidden');
}
<?php if ($postedRole !== ''): ?>
(function(){ const r='<?= e($postedRole) ?>'; if(ROLE[r]){ document.getElementById('roleIcon').className='bi '+ROLE[r][0]; document.getElementById('roleName').textContent=ROLE[r][1]; } })();
<?php endif; ?>

// PWA install
if('serviceWorker' in navigator){ navigator.serviceWorker.register('<?= e(BASE_URL) ?>/app/sw.js').catch(()=>{}); }
let deferred;
window.addEventListener('beforeinstallprompt', e=>{ e.preventDefault(); deferred=e; document.getElementById('installBtn').style.display='block'; });
document.getElementById('installBtn').addEventListener('click', async()=>{
  if(!deferred) return; deferred.prompt(); await deferred.userChoice; deferred=null; document.getElementById('installBtn').style.display='none';
});
</script>
</body>
</html>
