<?php
/**
 * First-login onboarding wizard (standalone, JS-driven steps).
 * Reuses api/design.php (profile, template, finish) and api/ai.php (extract).
 * Works even if AI fails — the user can skip to manual entry.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid    = currentTenantId();
$tenant = currentTenant();

// If already onboarded, go straight to the dashboard.
if (!empty($tenant['onboarded'])) { redirect(BASE_URL . '/client/index.php'); }

$templates = db_all('SELECT * FROM ' . tbl('templates') . ' WHERE status = 1 ORDER BY is_premium, id');
$canPremium = planHasFeature($tid, 'remove_branding');
$aiLimit = checkPlanLimit($tid, 'ai');
$aiRemaining = max(0, $aiLimit['max'] - $aiLimit['used']);
$slug = $tenant['slug'];
$publicUrl = publicMenuUrl($slug);
$siteName = getSetting('site_name', 'AK Menu System');
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>"><meta name="base-url" content="<?= e(BASE_URL) ?>">
<title>Welcome · <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
<style>
body{background:#f4f6fb;font-family:system-ui}
.wiz{max-width:820px;margin:30px auto;}
.steps{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
.steps .st{flex:1;min-width:70px;text-align:center;font-size:.72rem;color:#9aa;border-top:3px solid #dee2e6;padding-top:6px}
.steps .st.active{color:var(--bs-primary,#0d6efd);border-color:#0d6efd;font-weight:600}
.steps .st.done{color:#2a9d8f;border-color:#2a9d8f}
.wiz-card{border:0;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.tpl-tile{cursor:pointer;border:2px solid transparent;border-radius:12px;overflow:hidden}
.tpl-tile.sel{border-color:#0d6efd}
.border-dashed{border:2px dashed #ccc!important}
</style>
</head><body>
<div class="wiz">
  <div class="text-center mb-3"><h4 class="mb-0"><i class="bi bi-shop text-danger"></i> Welcome to <?= e($siteName) ?></h4>
    <small class="text-muted">Let's set up <?= e($tenant['restaurant_name']) ?> in a few steps.</small></div>

  <div class="steps" id="stepBar">
    <div class="st active">1 · Details</div><div class="st">2 · Photos</div><div class="st">3 · AI</div>
    <div class="st">4 · Review</div><div class="st">5 · Design</div><div class="st">6 · Ordering</div><div class="st">7 · Done</div>
  </div>

  <div class="card wiz-card"><div class="card-body p-4">

    <!-- STEP 1 -->
    <div class="wstep" data-step="1">
      <h6 class="fw-semibold mb-3">Restaurant Details</h6>
      <form id="detForm">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Restaurant Name *</label><input name="restaurant_name" class="form-control" value="<?= e($tenant['restaurant_name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Owner Name</label><input name="owner_name" class="form-control" value="<?= e($tenant['owner_name']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= e($tenant['mobile']) ?>"></div>
          <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($tenant['city']) ?>"></div>
          <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($tenant['address']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
        </div>
      </form>
      <div class="text-end mt-3"><button class="btn btn-primary" onclick="saveDetails()">Continue <i class="bi bi-arrow-right"></i></button></div>
    </div>

    <!-- STEP 2 -->
    <div class="wstep d-none" data-step="2">
      <h6 class="fw-semibold mb-3">Upload Menu Photos <small class="text-muted">(optional)</small></h6>
      <?php if ($aiRemaining <= 0): ?>
        <div class="alert alert-warning">No AI credits available. You can skip and add items manually later.</div>
      <?php endif; ?>
      <div id="dropZone" class="border-dashed rounded p-5 text-center" style="cursor:pointer">
        <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
        <p class="mb-0">Drag &amp; drop or click to add menu images (JPG/PNG/PDF)</p>
        <input type="file" id="fileInput" class="d-none" accept="image/*,application/pdf" multiple>
      </div>
      <div id="fileList" class="mt-3 d-flex flex-wrap gap-2"></div>
      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-light" onclick="goStep(1)"><i class="bi bi-arrow-left"></i> Back</button>
        <div>
          <button class="btn btn-outline-secondary" onclick="skipToDesign()">Skip &amp; add manually</button>
          <button class="btn btn-primary" id="toAiBtn" onclick="startAi()" <?= $aiRemaining<=0?'disabled':'' ?>>Extract with AI</button>
        </div>
      </div>
    </div>

    <!-- STEP 3 -->
    <div class="wstep d-none text-center py-5" data-step="3">
      <div class="spinner-border text-primary mb-3"></div>
      <h6>Reading your menu…</h6><small class="text-muted">Please wait.</small>
    </div>

    <!-- STEP 4 -->
    <div class="wstep d-none" data-step="4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0">Review &amp; Edit Menu</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="addReviewCat()"><i class="bi bi-plus"></i> Category</button>
      </div>
      <div id="reviewWrap"></div>
      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-light" onclick="goStep(2)"><i class="bi bi-arrow-left"></i> Back</button>
        <button class="btn btn-primary" onclick="saveReview()">Save &amp; Continue <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- STEP 5 -->
    <div class="wstep d-none" data-step="5">
      <h6 class="fw-semibold mb-3">Choose a Design</h6>
      <div class="row g-3" id="tplGrid">
        <?php foreach ($templates as $tpl):
          $isPrem=(int)$tpl['is_premium']===1; $locked=$isPrem && !$canPremium;
          $sel=(int)$tenant['template_id']===(int)$tpl['id'];
          $prev=$tpl['preview_image']?BASE_URL.'/'.$tpl['preview_image']:''; ?>
        <div class="col-6 col-md-3">
          <div class="tpl-tile <?= $sel?'sel':'' ?>" data-id="<?= (int)$tpl['id'] ?>" data-locked="<?= $locked?1:0 ?>" onclick="pickTpl(this)">
            <div class="ratio ratio-4x3 bg-light d-flex align-items-center justify-content-center">
              <?php if($prev):?><img src="<?= e($prev) ?>" style="object-fit:cover;width:100%;height:100%"><?php else:?><i class="bi bi-image text-muted fs-2"></i><?php endif;?>
            </div>
            <div class="text-center small py-1"><?= e($tpl['name']) ?>
              <?php if($isPrem):?><span class="badge bg-warning text-dark">Premium</span><?php endif;?>
              <?php if($locked):?><span class="badge bg-secondary"><i class="bi bi-lock"></i></span><?php endif;?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-light" onclick="goStep(4)"><i class="bi bi-arrow-left"></i> Back</button>
        <button class="btn btn-primary" onclick="saveTemplate()">Continue <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- STEP 6 -->
    <div class="wstep d-none" data-step="6">
      <h6 class="fw-semibold mb-3">How do you want to take orders?</h6>
      <div class="row g-3">
        <div class="col-md-4"><div class="card h-100 order-opt" data-mode="direct" onclick="pickMode(this)"><div class="card-body text-center"><i class="bi bi-phone fs-2 text-primary"></i><div class="fw-semibold">Direct Ordering</div><small class="text-muted">Customers order from their phone</small></div></div></div>
        <div class="col-md-4"><div class="card h-100 order-opt" data-mode="waiter" onclick="pickMode(this)"><div class="card-body text-center"><i class="bi bi-person-badge fs-2 text-primary"></i><div class="fw-semibold">Waiter Ordering</div><small class="text-muted">Staff take orders on tablets</small></div></div></div>
        <div class="col-md-4"><div class="card h-100 order-opt selected border-primary" data-mode="view_only" onclick="pickMode(this)"><div class="card-body text-center"><i class="bi bi-eye fs-2 text-primary"></i><div class="fw-semibold">View Only</div><small class="text-muted">Digital menu, no ordering</small></div></div></div>
      </div>
      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-light" onclick="goStep(5)"><i class="bi bi-arrow-left"></i> Back</button>
        <button class="btn btn-primary" onclick="finish()">Finish Setup <i class="bi bi-check2"></i></button>
      </div>
    </div>

    <!-- STEP 7 -->
    <div class="wstep d-none text-center py-4" data-step="7">
      <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
      <h5 class="mt-2">You're all set!</h5>
      <p class="text-muted">Your digital menu is live. Share it with your customers.</p>
      <div class="input-group my-3" style="max-width:480px;margin:auto">
        <input class="form-control" id="finalUrl" value="<?= e($publicUrl) ?>" readonly>
        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('finalUrl').value);toastr.success('Copied!')"><i class="bi bi-clipboard"></i></button>
      </div>
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($publicUrl) ?>" class="mb-3">
      <div>
        <a href="<?= e($publicUrl) ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i> View Menu</a>
        <a href="<?= e(BASE_URL) ?>/standee/generate.php?slug=<?= e($slug) ?>&size=A4" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> Standee PDF</a>
        <a href="<?= e(BASE_URL) ?>/client/index.php" class="btn btn-primary"><i class="bi bi-speedometer2"></i> Go to Dashboard</a>
      </div>
    </div>

  </div></div>
  <div class="text-center mt-2"><a href="<?= e(BASE_URL) ?>/client/logout.php" class="small text-muted">Logout</a></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
<script>
const AIAPI = AK.base + '/api/ai.php';
const DAPI  = AK.base + '/api/design.php';
let FILES = [], ORDER_MODE = 'view_only', TEMPLATE_ID = '<?= (int)$tenant['template_id'] ?>';
toastr.options={positionClass:'toast-top-right',timeOut:3000};

function goStep(n){
  document.querySelectorAll('.wstep').forEach(s => s.classList.toggle('d-none', +s.dataset.step !== n));
  document.querySelectorAll('#stepBar .st').forEach((s,i) => {
    s.classList.toggle('active', i === n-1);
    s.classList.toggle('done', i < n-1);
  });
  window.scrollTo(0,0);
}

// Step 1
function saveDetails(){
  const fd = new FormData(document.getElementById('detForm'));
  fd.set('section','profile');
  if(!fd.get('restaurant_name').trim()){ toastr.error('Restaurant name is required'); return; }
  AK.post(DAPI + '?action=settings', fd).then(r => { if(r.status==='success'){ goStep(2); } else { toastr.error(r.message); } });
}

// Step 2 uploads
const dz = document.getElementById('dropZone'), fi = document.getElementById('fileInput');
dz.onclick = () => fi.click();
['dragover','dragenter'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('bg-light');}));
['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('bg-light');}));
dz.addEventListener('drop',ev=>addFiles(ev.dataTransfer.files));
fi.addEventListener('change',()=>addFiles(fi.files));
function addFiles(list){ for(const f of list) FILES.push(f); renderFiles(); }
function renderFiles(){
  const w=document.getElementById('fileList'); w.innerHTML='';
  FILES.forEach((f,i)=>{ const c=document.createElement('span'); c.className='badge bg-secondary py-2';
    c.innerHTML=`${f.name} <i class="bi bi-x-circle" style="cursor:pointer" onclick="FILES.splice(${i},1);renderFiles()"></i>`; w.appendChild(c); });
  document.getElementById('toAiBtn').disabled = FILES.length===0<?= $aiRemaining<=0?'||true':'' ?>;
}
function skipToDesign(){ document.getElementById('reviewWrap').innerHTML=''; goStep(5); }

// Step 3 AI
function startAi(){
  if(!FILES.length){ toastr.error('Add at least one photo'); return; }
  goStep(3);
  const fd = new FormData(); FILES.forEach(f=>fd.append('files[]',f));
  AK.post(AIAPI + '?action=extract', fd).then(r => {
    if(r.status==='success'){ renderReview(r.data.menu); goStep(4); }
    else { toastr.error(r.message || 'AI failed'); goStep(2); }
  }).catch(()=>{ toastr.error('Network error'); goStep(2); });
}

// Step 4 review (same shape as ai_extract)
function renderReview(menu){
  const wrap=document.getElementById('reviewWrap'); wrap.innerHTML='';
  const cats=(menu&&menu.categories)?menu.categories:[];
  if(!cats.length){ wrap.innerHTML='<div class="alert alert-warning">No items detected. Add manually below.</div>'; }
  cats.forEach(c=>wrap.appendChild(catCard(c)));
}
function catCard(c){
  const card=document.createElement('div'); card.className='card mb-2 review-cat';
  card.innerHTML=`<div class="card-body p-2">
    <div class="input-group input-group-sm mb-2"><span class="input-group-text">Category</span>
      <input class="form-control cat-name" value="${esc(c.name||'')}">
      <button class="btn btn-outline-danger" onclick="this.closest('.review-cat').remove()"><i class="bi bi-trash"></i></button></div>
    <table class="table table-sm mb-1"><tbody class="item-body"></tbody></table>
    <button class="btn btn-sm btn-outline-secondary" onclick="addRow(this.closest('.review-cat'))"><i class="bi bi-plus"></i> Item</button></div>`;
  const tb=card.querySelector('.item-body');
  (c.items||[]).forEach(it=>tb.appendChild(itemRow(it)));
  return card;
}
function itemRow(it){
  const tr=document.createElement('tr'); tr.className='review-item';
  const veg=(it.is_veg===false||it.is_veg===0)?'':'checked';
  tr.innerHTML=`<td><input type="checkbox" class="form-check-input it-keep" checked></td>
    <td><input class="form-control form-control-sm it-name" value="${esc(it.name||'')}"></td>
    <td style="width:100px"><input type="number" step="0.01" class="form-control form-control-sm it-price" value="${it.price||0}"></td>
    <td style="width:50px" class="text-center"><input type="checkbox" class="form-check-input it-veg" ${veg}></td>
    <td style="width:26px"><i class="bi bi-x text-danger" style="cursor:pointer" onclick="this.closest('tr').remove()"></i></td>`;
  return tr;
}
function addRow(card){ card.querySelector('.item-body').appendChild(itemRow({name:'',price:0,is_veg:true})); }
function addReviewCat(){ document.getElementById('reviewWrap').appendChild(catCard({name:'New Category',items:[{name:'',price:0,is_veg:true}]})); }
function collect(){
  const cats=[];
  document.querySelectorAll('.review-cat').forEach(card=>{
    const name=card.querySelector('.cat-name').value.trim(); if(!name) return;
    const items=[];
    card.querySelectorAll('.review-item').forEach(tr=>{
      if(!tr.querySelector('.it-keep').checked) return;
      const n=tr.querySelector('.it-name').value.trim(); if(!n) return;
      items.push({name:n,price:parseFloat(tr.querySelector('.it-price').value)||0,is_veg:tr.querySelector('.it-veg').checked?1:0});
    });
    if(items.length) cats.push({name:name,items:items});
  });
  return cats;
}
function saveReview(){
  const cats=collect();
  if(!cats.length){ goStep(5); return; } // allow empty -> design
  AK.post(AIAPI + '?action=save', {categories: JSON.stringify(cats)}).then(r=>{
    if(r.status==='success'){ toastr.success(r.message); goStep(5); } else { toastr.error(r.message); }
  });
}

// Step 5 template
function pickTpl(el){
  if(el.dataset.locked==='1'){ toastr.error('Premium template needs a higher plan'); return; }
  document.querySelectorAll('.tpl-tile').forEach(t=>t.classList.remove('sel'));
  el.classList.add('sel'); TEMPLATE_ID = el.dataset.id;
}
function saveTemplate(){
  if(!TEMPLATE_ID){ goStep(6); return; }
  AK.post(DAPI + '?action=save_design', {template_id: TEMPLATE_ID}).then(r=>{
    if(r.status==='success'){ goStep(6); } else { toastr.error(r.message); }
  });
}

// Step 6 ordering
function pickMode(el){
  document.querySelectorAll('.order-opt').forEach(o=>o.classList.remove('selected','border-primary'));
  el.classList.add('selected','border-primary'); ORDER_MODE = el.dataset.mode;
}
function finish(){
  AK.post(DAPI + '?action=finish_onboarding', {ordering_mode: ORDER_MODE}).then(r=>{
    if(r.status==='success'){ goStep(7); } else { toastr.error(r.message); }
  });
}
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
</script>
</body></html>
