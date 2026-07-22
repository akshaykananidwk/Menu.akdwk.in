<?php
/**
 * PUBLIC restaurant self-signup page.
 * Step 1: details · Step 2: upload menu photos/PDF · AI builds the menu ·
 * Done: shows menu link + QR + login. 7-day free trial.
 */
require_once __DIR__ . '/config/config.php';
checkMaintenance();
if (getSetting('allow_signup', '1') !== '1') { redirect(BASE_URL . '/client/login.php'); }
if (isClient()) { redirect(BASE_URL . '/client/index.php'); }

$siteName = getSetting('site_name', 'AK Menu System');
$primary  = getSetting('primary_color', '#e63946');
$secondary= getSetting('secondary_color', '#1d3557');
$trial    = (int)(db_val('SELECT validity_days FROM ' . tbl('plans') . ' WHERE price = 0 AND status = 1 ORDER BY validity_days ASC LIMIT 1') ?: 7);
// If arriving via a referral link, greet them with the referrer's name.
$refCode  = trim((string)($_GET['ref'] ?? ''));
$refBy    = '';
if ($refCode !== '') {
    $rid = referrerIdFromCode($refCode);
    if ($rid) { $refBy = (string)db_val('SELECT restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $rid]); }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title>Free Signup · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Noto+Sans+Gujarati:wght@400;600&display=swap" rel="stylesheet">
<style>
body{font-family:'Poppins','Noto Sans Gujarati',system-ui,sans-serif;background:linear-gradient(135deg,<?= e($primary) ?>18,<?= e($secondary) ?>10);min-height:100vh;}
.wrap{max-width:640px;margin:24px auto;padding:0 14px;}
.card{border:0;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.12);}
.brandbar{background:linear-gradient(135deg,<?= e($primary) ?>,<?= e($secondary) ?>);color:#fff;border-radius:18px 18px 0 0;padding:22px 24px;}
.step{display:none;} .step.active{display:block;}
.stepdots{display:flex;gap:6px;justify-content:center;margin:14px 0;}
.stepdots span{width:9px;height:9px;border-radius:50%;background:#dcdfe6;}
.stepdots span.on{background:<?= e($primary) ?>;width:24px;border-radius:6px;transition:.3s;}
.dropzone{border:2px dashed #cdd3e0;border-radius:14px;padding:28px;text-align:center;cursor:pointer;transition:.2s;background:#fafbff;}
.dropzone.drag{border-color:<?= e($primary) ?>;background:<?= e($primary) ?>0d;}
.thumb{position:relative;width:74px;height:74px;border-radius:10px;overflow:hidden;border:1px solid #eee;}
.thumb img{width:100%;height:100%;object-fit:cover;} .thumb .pdf{display:flex;align-items:center;justify-content:center;height:100%;background:#f1f3f9;color:#c1121f;font-size:1.6rem;}
.thumb .x{position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:50%;width:20px;height:20px;font-size:.7rem;line-height:1;}
.btn-p{background:<?= e($primary) ?>;border-color:<?= e($primary) ?>;color:#fff;}
.btn-p:hover{filter:brightness(.93);color:#fff;}
.trial-badge{background:#d1e7dd;color:#0f5132;border-radius:20px;padding:3px 12px;font-size:.8rem;font-weight:600;}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="brandbar text-center">
      <h4 class="mb-1"><i class="bi bi-shop"></i> <?= e($siteName) ?></h4>
      <div class="small opacity-90">Create your restaurant's digital QR menu — <b><?= $trial ?> days free</b> 🎉</div>
    </div>
    <div class="card-body p-4">
      <?php if ($refBy !== ''): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 py-2 small mb-3">
          <i class="bi bi-gift-fill"></i> <div>Invited by <strong><?= e($refBy) ?></strong> — sign up and enjoy your free trial! 🎉</div>
        </div>
      <?php endif; ?>
      <div class="stepdots"><span class="on" data-dot="1"></span><span data-dot="2"></span><span data-dot="3"></span></div>

      <form id="signupForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="ref" value="<?= e(trim((string)($_GET['ref'] ?? ''))) ?>">
        <!-- honeypot -->
        <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

        <!-- ===== STEP 1: details ===== -->
        <div class="step active" id="step1">
          <h5 class="mb-3">Restaurant details</h5>
          <div class="row g-3">
            <div class="col-md-12"><label class="form-label">Restaurant Name *</label>
              <input name="restaurant_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Owner Name *</label>
              <input name="owner_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Mobile (WhatsApp) *</label>
              <input name="mobile" class="form-control" inputmode="tel" maxlength="18" placeholder="e.g. 9876543210 or +91 98765 43210" required></div>
            <div class="col-md-6"><label class="form-label">Email</label>
              <input name="email" type="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">City</label>
              <input name="city" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Password *</label>
              <input name="password" type="password" class="form-control" minlength="6" required></div>
            <div class="col-md-6"><label class="form-label">Menu Language</label>
              <select name="language" class="form-select">
                <option value="en">English</option>
                <option value="gu">ગુજરાતી</option>
              </select></div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-link text-decoration-none">Already registered? Login</a>
            <button type="button" class="btn btn-p px-4" onclick="goStep(2)">Next <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>

        <!-- ===== STEP 2: menu upload ===== -->
        <div class="step" id="step2">
          <h5 class="mb-1">Upload your menu</h5>
          <p class="text-muted small">Add photos or a PDF of your existing menu — our AI reads it and builds your digital menu category-wise. You can also skip and add items yourself later.</p>
          <div class="dropzone" id="dropzone">
            <i class="bi bi-cloud-arrow-up fs-1 text-secondary"></i>
            <div class="fw-semibold">Tap to upload or drag files here</div>
            <div class="small text-muted">JPG, PNG or PDF · up to 8 files</div>
            <input type="file" id="fileInput" name="menu_files[]" accept="image/*,application/pdf" multiple hidden>
          </div>
          <div id="previews" class="d-flex flex-wrap gap-2 mt-3"></div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-light" onclick="goStep(1)"><i class="bi bi-arrow-left"></i> Back</button>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary" onclick="submitSignup(true)">Skip &amp; create</button>
              <button type="button" class="btn btn-p px-4" onclick="submitSignup(false)"><i class="bi bi-magic"></i> Create with AI</button>
            </div>
          </div>
        </div>

        <!-- ===== STEP 3: processing / done ===== -->
        <div class="step text-center py-3" id="step3">
          <div id="processing">
            <div class="spinner-border text-danger mb-3" style="width:3rem;height:3rem"></div>
            <h5 id="procTitle">Creating your account…</h5>
            <p class="text-muted small" id="procMsg">Our AI is reading your menu. This can take up to a minute.</p>
          </div>
          <div id="done" style="display:none"></div>
        </div>
      </form>
    </div>
  </div>
  <p class="text-center text-muted small mt-3"><?= e(POWERED_BY) ?></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
<script>
const BASE = '<?= e(BASE_URL) ?>';
let files = [];

function goStep(n){
  document.querySelectorAll('.step').forEach(s=>s.classList.remove('active'));
  document.getElementById('step'+n).classList.add('active');
  document.querySelectorAll('.stepdots span').forEach(d=>d.classList.toggle('on', +d.dataset.dot<=n));
  window.scrollTo({top:0,behavior:'smooth'});
}
// Step 1 -> 2 validation
function validStep1(){
  const f = document.getElementById('signupForm');
  for(const nm of ['restaurant_name','owner_name','mobile','password']){
    if(!f[nm].value.trim()){ f[nm].focus(); AK.toast('error','Please fill '+nm.replace('_',' ')); return false; }
  }
  if(!/^[6-9]\d{9}$/.test(f.mobile.value.trim())){ f.mobile.focus(); AK.toast('error','Enter a valid 10-digit mobile'); return false; }
  if(f.password.value.length<6){ f.password.focus(); AK.toast('error','Password min 6 characters'); return false; }
  return true;
}
const _goStep = goStep;
goStep = function(n){ if(n===2 && !validStep1()) return; _goStep(n); };

// ---- File handling ----
const dz = document.getElementById('dropzone'), fi = document.getElementById('fileInput');
dz.addEventListener('click', ()=>fi.click());
['dragover','dragenter'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('drag');}));
['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('drag');}));
dz.addEventListener('drop', ev=>{ addFiles(ev.dataTransfer.files); });
fi.addEventListener('change', ()=>addFiles(fi.files));
function addFiles(list){
  for(const f of list){ if(files.length>=8) break; files.push(f); }
  renderPreviews();
}
function renderPreviews(){
  const box = document.getElementById('previews'); box.innerHTML='';
  files.forEach((f,i)=>{
    const d=document.createElement('div'); d.className='thumb';
    if(f.type==='application/pdf'){ d.innerHTML='<div class="pdf"><i class="bi bi-file-earmark-pdf"></i></div>'; }
    else { const img=document.createElement('img'); img.src=URL.createObjectURL(f); d.appendChild(img); }
    const x=document.createElement('button'); x.type='button'; x.className='x'; x.innerHTML='&times;';
    x.onclick=()=>{ files.splice(i,1); renderPreviews(); }; d.appendChild(x);
    box.appendChild(d);
  });
}

// ---- Submit ----
function submitSignup(skip){
  if(!validStep1()){ _goStep(1); return; }
  goStep(3);
  const fd = new FormData(document.getElementById('signupForm'));
  fd.delete('menu_files[]');
  fd.delete('website'); // drop the honeypot (mobile browsers auto-fill it)
  if(!skip){ files.forEach(f=>fd.append('menu_files[]', f)); }
  document.getElementById('procMsg').textContent = skip
    ? 'Setting up your restaurant…'
    : 'Our AI is reading your menu. This can take up to a minute.';

  fetch(BASE+'/api/signup.php', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd})
    .then(r=>r.json())
    .then(res=>{
      if(res.status==='success'){ showDone(res.data); }
      else { showError(res.message||'Signup failed'); }
    })
    .catch(()=>showError('Network error. Please try again.'));
}
function showError(msg){
  document.getElementById('processing').style.display='none';
  const d=document.getElementById('done'); d.style.display='block';
  d.innerHTML='<i class="bi bi-exclamation-triangle text-danger" style="font-size:2.5rem"></i>'+
    '<h5 class="mt-2">Could not complete</h5><p class="text-muted">'+msg.replace(/[<>]/g,'')+'</p>'+
    '<button class="btn btn-p" onclick="location.reload()">Try again</button>';
}
function showDone(d){
  document.getElementById('processing').style.display='none';
  const done=document.getElementById('done'); done.style.display='block';
  const aiLine = d.ai_ok
    ? '<div class="alert alert-success py-2 small mb-3">✅ AI added <b>'+d.items+'</b> items in <b>'+d.categories+'</b> categories. You can edit them in your panel.</div>'
    : (d.ai_error ? '<div class="alert alert-warning py-2 small mb-3">'+d.ai_error.replace(/[<>]/g,'')+'</div>' : '');
  done.innerHTML =
    '<i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>'+
    '<h4 class="mt-2 mb-1">You\'re all set! 🎉</h4>'+
    '<span class="trial-badge">'+d.plan+' · valid till '+d.expiry+'</span>'+
    '<div class="my-3">'+aiLine+'</div>'+
    '<div class="text-center mb-3"><img src="'+d.qr_url+'" onerror="this.style.display=\'none\'" style="width:150px;height:150px;border:1px solid #eee;border-radius:12px"></div>'+
    '<div class="input-group mb-2"><input class="form-control" value="'+d.menu_url+'" readonly>'+
      '<button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(\''+d.menu_url+'\');AK.toast(\'success\',\'Link copied\')"><i class="bi bi-clipboard"></i></button></div>'+
    '<div class="d-grid gap-2 mt-3">'+
      '<a class="btn btn-p" href="'+d.panel_url+'"><i class="bi bi-speedometer2"></i> Go to my Dashboard</a>'+
      '<a class="btn btn-outline-secondary" href="'+d.menu_url+'" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View my menu</a>'+
    '</div>'+
    '<p class="small text-muted mt-3 mb-0">Login anytime with mobile <b>'+d.login_mobile+'</b> and your password. Details sent to your WhatsApp.</p>';
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>
</body>
</html>
