<?php
/** AI Menu Extraction: upload photos -> Gemini OCR -> editable review -> save. */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid = currentTenantId();

$pageTitle = 'AI Menu Import';
$activeNav = 'ai';
$aiLimit   = checkPlanLimit($tid, 'ai');
$remaining = max(0, $aiLimit['max'] - $aiLimit['used']);

require __DIR__ . '/_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <p class="text-muted mb-0">Upload one or more menu photos and let AI build your digital menu. Review before saving.</p>
  <span class="badge bg-primary fs-6">AI Credits Left: <span id="aiRemaining"><?= $remaining ?></span></span>
</div>

<!-- STEP 1: Upload -->
<div class="card mb-3" id="stepUpload"><div class="card-body">
  <h6 class="fw-semibold mb-3"><i class="bi bi-1-circle"></i> Upload Menu Photos</h6>
  <?php if ($remaining <= 0): ?>
    <div class="alert alert-warning">You have no AI credits left. <a href="<?= e(BASE_URL) ?>/client/settings.php">Upgrade your plan</a> or <a href="<?= e(BASE_URL) ?>/client/menu.php">add items manually</a>.</div>
  <?php else: ?>
  <div id="dropZone" class="border border-2 border-dashed rounded p-5 text-center" style="cursor:pointer;border-style:dashed!important">
    <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
    <p class="mb-1">Drag &amp; drop images here, or click to browse</p>
    <small class="text-muted">JPG, PNG or PDF · multiple files allowed</small>
    <input type="file" id="fileInput" class="d-none" accept="image/*,application/pdf" multiple>
  </div>
  <div id="fileList" class="mt-3 d-flex flex-wrap gap-2"></div>
  <button class="btn btn-primary mt-3" id="extractBtn" onclick="runExtract()" disabled><i class="bi bi-magic"></i> Extract Menu</button>
  <?php endif; ?>
</div></div>

<!-- STEP 2: Loader -->
<div class="card mb-3 d-none" id="stepLoader"><div class="card-body text-center py-5">
  <div class="spinner-border text-primary mb-3" role="status"></div>
  <h6>Reading your menu with AI…</h6>
  <small class="text-muted">This can take up to a minute for large menus.</small>
</div></div>

<!-- Error state -->
<div class="card mb-3 d-none" id="stepError"><div class="card-body text-center py-4">
  <i class="bi bi-exclamation-triangle text-danger fs-1"></i>
  <h6 class="mt-2">AI extraction failed</h6>
  <p class="text-muted" id="errMsg"></p>
  <button class="btn btn-primary" onclick="retry()"><i class="bi bi-arrow-clockwise"></i> Retry</button>
  <a href="<?= e(BASE_URL) ?>/client/menu.php" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Enter Manually</a>
</div></div>

<!-- STEP 3: Editable review -->
<div class="card d-none" id="stepReview"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="fw-semibold mb-0"><i class="bi bi-2-circle"></i> Review &amp; Edit</h6>
    <button class="btn btn-sm btn-outline-secondary" onclick="addReviewCat()"><i class="bi bi-plus"></i> Add Category</button>
  </div>
  <p class="text-muted small">Fix any OCR errors before saving. Uncheck items you don't want.</p>
  <div id="reviewWrap"></div>
  <button class="btn btn-primary mt-3" onclick="saveMenu()"><i class="bi bi-save"></i> Save to Menu</button>
  <button class="btn btn-outline-secondary mt-3" onclick="retry()">Start Over</button>
</div></div>

<?php
$pageScript = <<<'HTML'
<script>
const API = document.querySelector('meta[name="base-url"]').content + '/api/ai.php';
let FILES = [];

const dz = document.getElementById('dropZone');
const fi = document.getElementById('fileInput');
if(dz){
  dz.onclick = () => fi.click();
  ['dragover','dragenter'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('bg-light'); }));
  ['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('bg-light'); }));
  dz.addEventListener('drop', ev => addFiles(ev.dataTransfer.files));
  fi.addEventListener('change', () => addFiles(fi.files));
}
function addFiles(list){
  for(const f of list){ FILES.push(f); }
  renderFiles();
}
function renderFiles(){
  const wrap = document.getElementById('fileList');
  wrap.innerHTML = '';
  FILES.forEach((f,i) => {
    const chip = document.createElement('span');
    chip.className = 'badge bg-secondary d-flex align-items-center gap-1 py-2';
    chip.innerHTML = `<i class="bi bi-file-earmark"></i> ${f.name} <i class="bi bi-x-circle" style="cursor:pointer" onclick="removeFile(${i})"></i>`;
    wrap.appendChild(chip);
  });
  document.getElementById('extractBtn').disabled = FILES.length === 0;
}
function removeFile(i){ FILES.splice(i,1); renderFiles(); }

function show(id){ ['stepUpload','stepLoader','stepError','stepReview'].forEach(s => document.getElementById(s).classList.add('d-none')); document.getElementById(id).classList.remove('d-none'); }

function runExtract(){
  if(!FILES.length) return;
  const fd = new FormData();
  FILES.forEach(f => fd.append('files[]', f));
  show('stepLoader');
  AK.post(API + '?action=extract', fd).then(r => {
    if(r.status === 'success'){
      if(r.data && r.data.remaining != null) document.getElementById('aiRemaining').textContent = r.data.remaining;
      renderReview(r.data.menu);
      show('stepReview');
    } else {
      document.getElementById('errMsg').textContent = r.message || 'Something went wrong.';
      show('stepError');
    }
  }).catch(() => { document.getElementById('errMsg').textContent = 'Network error.'; show('stepError'); });
}
function retry(){ show('stepUpload'); }

// ---- Editable review grid ---------------------------------------------------
function renderReview(menu){
  const wrap = document.getElementById('reviewWrap');
  wrap.innerHTML = '';
  const cats = (menu && menu.categories) ? menu.categories : [];
  if(!cats.length){ wrap.innerHTML = '<div class="alert alert-warning">No items detected. Try another photo.</div>'; return; }
  cats.forEach(c => wrap.appendChild(catCard(c)));
}
function catCard(c){
  const card = document.createElement('div');
  card.className = 'card mb-2 review-cat';
  card.innerHTML = `<div class="card-body p-2">
    <div class="input-group input-group-sm mb-2">
      <span class="input-group-text">Category</span>
      <input class="form-control cat-name" value="${escapeAttr(c.name||'')}">
      <button class="btn btn-outline-danger" onclick="this.closest('.review-cat').remove()"><i class="bi bi-trash"></i></button>
    </div>
    <table class="table table-sm mb-1"><thead><tr><th style="width:28px"></th><th>Item</th><th style="width:110px">Price</th><th style="width:70px">Veg</th><th style="width:30px"></th></tr></thead>
    <tbody class="item-body"></tbody></table>
    <button class="btn btn-sm btn-outline-secondary" onclick="addRow(this.closest('.review-cat'))"><i class="bi bi-plus"></i> Add Item</button>
  </div>`;
  const tb = card.querySelector('.item-body');
  (c.items||[]).forEach(it => tb.appendChild(itemRow(it)));
  return card;
}
function itemRow(it){
  const tr = document.createElement('tr');
  tr.className = 'review-item';
  const veg = (it.is_veg===false || it.is_veg===0) ? '' : 'checked';
  tr.innerHTML = `<td><input type="checkbox" class="form-check-input it-keep" checked></td>
    <td><input class="form-control form-control-sm it-name" value="${escapeAttr(it.name||'')}"></td>
    <td><input type="number" step="0.01" class="form-control form-control-sm it-price" value="${it.price||0}"></td>
    <td class="text-center"><input type="checkbox" class="form-check-input it-veg" ${veg}></td>
    <td><i class="bi bi-x text-danger" style="cursor:pointer" onclick="this.closest('tr').remove()"></i></td>`;
  return tr;
}
function addRow(card){ card.querySelector('.item-body').appendChild(itemRow({name:'',price:0,is_veg:true})); }
function addReviewCat(){ document.getElementById('reviewWrap').appendChild(catCard({name:'New Category',items:[{name:'',price:0,is_veg:true}]})); }

function collect(){
  const cats = [];
  document.querySelectorAll('.review-cat').forEach(card => {
    const name = card.querySelector('.cat-name').value.trim();
    if(!name) return;
    const items = [];
    card.querySelectorAll('.review-item').forEach(tr => {
      if(!tr.querySelector('.it-keep').checked) return;
      const iname = tr.querySelector('.it-name').value.trim();
      if(!iname) return;
      items.push({ name:iname, price: parseFloat(tr.querySelector('.it-price').value)||0, is_veg: tr.querySelector('.it-veg').checked ? 1 : 0 });
    });
    if(items.length) cats.push({ name:name, items:items });
  });
  return cats;
}
function saveMenu(){
  const cats = collect();
  if(!cats.length){ AK.toast('error','Nothing to save.'); return; }
  AK.post(API + '?action=save', { categories: JSON.stringify(cats) }).then(r => AK.handle(r, () => {
    setTimeout(() => location.href = document.querySelector('meta[name="base-url"]').content + '/client/menu.php', 900);
  }));
}

function escapeHtml(s){ return String(s==null?'':s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }
</script>
HTML;
require __DIR__ . '/_footer.php';
