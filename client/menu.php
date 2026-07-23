<?php
/** Menu Management: two-panel categories + items with drag-drop, modals, bulk & CSV. */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid = currentTenantId();

$pageTitle = 'Menu Management';
$activeNav = 'menu';
$itemLimit = checkPlanLimit($tid, 'items');
$catLimit  = checkPlanLimit($tid, 'categories');
$apiUrl    = BASE_URL . '/api/menu.php';

require __DIR__ . '/_header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <div>
    <span class="badge bg-light text-dark border">Items: <span id="itemUsed"><?= $itemLimit['used'] ?></span>/<?= $itemLimit['max'] ?></span>
    <span class="badge bg-light text-dark border">Categories: <span id="catUsed"><?= $catLimit['used'] ?></span>/<?= $catLimit['max'] ?></span>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-success" onclick="loadDemoMenu()"><i class="bi bi-stars"></i> Load Demo Menu</button>
    <button class="btn btn-sm btn-outline-success" onclick="autoAddPhotos()"><i class="bi bi-image"></i> Auto-add Photos</button>
    <a href="<?= e($apiUrl) ?>?action=export_csv" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Export CSV</a>
    <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('importFile').click()"><i class="bi bi-upload"></i> Import CSV</button>
    <input type="file" id="importFile" accept=".csv" class="d-none" onchange="importCsv(this)">
  </div>
</div>
<script>
// Load a ready-made 50+ item vegetarian demo menu (with photos) for this restaurant.
function loadDemoMenu(){
  Swal.fire({
    title: 'Load Demo Menu?',
    html: 'This adds a full <b>50+ item vegetarian menu</b> (English + ગુજરાતી, with photos).<br>Choose how to apply it:',
    icon: 'question',
    showDenyButton: true, showCancelButton: true,
    confirmButtonText: 'Replace my menu',
    denyButtonText: 'Add to existing',
    confirmButtonColor: '#e63946', denyButtonColor: '#2a9d8f',
  }).then(r=>{
    if(r.isDismissed) return;
    const replace = r.isConfirmed ? 1 : 0;
    Swal.fire({title:'Loading demo menu…', didOpen:()=>Swal.showLoading(), allowOutsideClick:false});
    AK.post('<?= e(BASE_URL) ?>/api/load_demo.php?action=load', {replace})
      .then(res=>{
        if(res.status==='success'){
          Swal.fire({icon:'success', title:'Done!', text:res.message}).then(()=>location.reload());
        } else { Swal.fire({icon:'error', title:'Failed', text:res.message||'Error'}); }
      })
      .catch(()=>Swal.fire({icon:'error',title:'Request failed'}));
  });
}

// Bulk-assign relevant bundled food photos to items that have no image yet.
function autoAddPhotos(){
  Swal.fire({
    title: 'Auto-add photos?',
    html: 'Items <b>without an image</b> will get a relevant food photo automatically.<br>You can still replace any photo later.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Add photos',
    confirmButtonColor: '#2a9d8f',
  }).then(r=>{
    if(!r.isConfirmed) return;
    Swal.fire({title:'Adding photos…', didOpen:()=>Swal.showLoading(), allowOutsideClick:false});
    AK.post('<?= e(BASE_URL) ?>/api/autophoto.php?action=fill', {})
      .then(res=>{
        if(res.status==='success'){
          Swal.fire({icon:'success', title:'Done!', text:res.message}).then(()=>location.reload());
        } else { Swal.fire({icon:'error', title:'Failed', text:res.message||'Error'}); }
      })
      .catch(()=>Swal.fire({icon:'error',title:'Request failed'}));
  });
}
</script>

<div class="row g-3">
  <!-- LEFT: categories -->
  <div class="col-lg-4">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0">Categories</h6>
        <button class="btn btn-sm btn-primary" onclick="openCategory()"><i class="bi bi-plus"></i> Add</button>
      </div>
      <small class="text-muted d-block mb-2">Drag to reorder</small>
      <ul class="list-group" id="catList"></ul>
    </div></div>
  </div>

  <!-- RIGHT: items -->
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0" id="itemsTitle">Items</h6>
        <button class="btn btn-sm btn-primary" onclick="openItem()" id="addItemBtn" disabled><i class="bi bi-plus"></i> Add Item</button>
      </div>

      <!-- Bulk actions bar -->
      <div class="d-none bg-light border rounded p-2 mb-2 align-items-center gap-2" id="bulkBar">
        <span class="small fw-semibold"><span id="bulkCount">0</span> selected:</span>
        <button class="btn btn-sm btn-outline-secondary" onclick="bulk('unavailable')">Mark Unavailable</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="bulk('available')">Mark Available</button>
        <div class="input-group input-group-sm" style="width:180px">
          <input type="number" id="pctInput" class="form-control" placeholder="% e.g. 10 or -5">
          <button class="btn btn-outline-secondary" onclick="bulkPct()">Price ±%</button>
        </div>
        <button class="btn btn-sm btn-outline-danger" onclick="bulk('delete')">Delete</button>
      </div>

      <div id="itemsWrap"><div class="empty-state"><i class="bi bi-arrow-left-circle"></i><p>Select a category to manage its items.</p></div></div>
    </div></div>
  </div>
</div>

<!-- CATEGORY MODAL -->
<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="catForm" onsubmit="saveCategory(event)">
    <div class="modal-header"><h6 class="modal-title" id="catModalTitle">Add Category</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="cat_id">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Name *</label><input name="name" id="cat_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Name (Gujarati)</label><input name="name_gu" id="cat_name_gu" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Icon (bootstrap-icon class)</label><input name="icon" id="cat_icon" class="form-control" placeholder="bi-cup-hot"></div>
        <div class="col-md-6"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
        <div class="col-md-6"><label class="form-label">Available From</label><input type="time" name="available_from" id="cat_from" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Available To</label><input type="time" name="available_to" id="cat_to" class="form-control"></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<!-- ITEM MODAL -->
<div class="modal fade" id="itemModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form id="itemForm" onsubmit="saveItem(event)">
    <div class="modal-header"><h6 class="modal-title" id="itemModalTitle">Add Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="it_id">
      <input type="hidden" name="category_id" id="it_cat">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Name *</label><input name="name" id="it_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Name (Gujarati)</label><input name="name_gu" id="it_name_gu" class="form-control"></div>
        <div class="col-md-6">
          <label class="form-label d-flex justify-content-between align-items-center">
            <span>Description</span>
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="genDescBtn" onclick="genDesc()" title="Let AI write an appetizing description"><i class="bi bi-magic"></i> AI Write</button>
          </label>
          <textarea name="description" id="it_desc" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-6"><label class="form-label">Description (Gujarati)</label><textarea name="description_gu" id="it_desc_gu" class="form-control" rows="2"></textarea></div>
        <div class="col-md-3"><label class="form-label">Price *</label><input type="number" step="0.01" name="price" id="it_price" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Discount Price</label><input type="number" step="0.01" name="discount_price" id="it_disc" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Food Type</label>
          <select name="food_type" id="it_food" class="form-select">
            <option value="veg">Veg</option><option value="nonveg">Non-Veg</option><option value="egg">Egg</option><option value="jain">Jain</option>
          </select></div>
        <div class="col-md-3"><label class="form-label">Spice Level</label>
          <select name="spice_level" id="it_spice" class="form-select">
            <option value="0">None</option><option value="1">Mild</option><option value="2">Medium</option><option value="3">Hot</option>
          </select></div>
        <div class="col-md-3"><label class="form-label">Prep Time (min)</label><input type="number" name="prep_time" id="it_prep" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
        <div class="col-md-6"><label class="form-label">Tags (comma separated)</label><input name="tags" id="it_tags" class="form-control" placeholder="Chef Special, Popular"></div>
        <div class="col-12 d-flex gap-3 flex-wrap">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="is_available" id="it_avail" value="1" checked><label class="form-check-label" for="it_avail">Available</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="is_bestseller" id="it_best" value="1"><label class="form-check-label" for="it_best">Bestseller</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="is_new" id="it_new" value="1"><label class="form-check-label" for="it_new">New</label></div>
        </div>

        <!-- Variants -->
        <div class="col-md-6"><label class="form-label mb-1">Variants (e.g. Half / Full)</label>
          <div id="variantRows"></div>
          <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="addVariant()"><i class="bi bi-plus"></i> Add Variant</button></div>
        <!-- Addons -->
        <div class="col-md-6"><label class="form-label mb-1">Add-ons (e.g. Extra Cheese)</label>
          <div id="addonRows"></div>
          <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="addAddon()"><i class="bi bi-plus"></i> Add Add-on</button></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Item</button></div>
  </form>
</div></div></div>

<?php
$pageScript = <<<'HTML'
<script>
const API = document.querySelector('meta[name="base-url"]').content + '/api/menu.php';
let STATE = { categories: [], items: [], selectedCat: null };
const catModal  = new bootstrap.Modal('#catModal');
const itemModal = new bootstrap.Modal('#itemModal');

function moneyN(v){ return Number(v).toFixed(2); }
function itemsOf(catId){ return STATE.items.filter(i => Number(i.category_id) === Number(catId)); }

// ---- Load & render ----------------------------------------------------------
function loadMenu(){
  AK.get(API + '?action=list').then(res => {
    if(res.status !== 'success'){ AK.toast('error', res.message); return; }
    STATE.categories = res.data.categories || [];
    STATE.items = res.data.items || [];
    document.getElementById('itemUsed').textContent = res.data.items_limit.used;
    document.getElementById('catUsed').textContent = res.data.categories_limit.used;
    if(STATE.selectedCat && !STATE.categories.some(c => c.id == STATE.selectedCat)) STATE.selectedCat = null;
    if(!STATE.selectedCat && STATE.categories.length) STATE.selectedCat = STATE.categories[0].id;
    renderCats(); renderItems();
  });
}

function renderCats(){
  const ul = document.getElementById('catList');
  ul.innerHTML = '';
  if(!STATE.categories.length){ ul.innerHTML = '<li class="list-group-item text-muted small">No categories yet. Click Add.</li>'; return; }
  STATE.categories.forEach(c => {
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center' + (c.id == STATE.selectedCat ? ' active' : '');
    li.dataset.id = c.id;
    li.innerHTML = `<span class="flex-grow-1" style="cursor:pointer">
        <i class="bi bi-grip-vertical text-muted"></i> ${escapeHtml(c.name)}
        <span class="badge bg-secondary ms-1">${itemsOf(c.id).length}</span></span>
      <span class="btn-group btn-group-sm">
        <button class="btn btn-sm btn-outline-${c.id==STATE.selectedCat?'light':'secondary'} py-0" onclick="editCategory(${c.id});event.stopPropagation()"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteCategory(${c.id});event.stopPropagation()"><i class="bi bi-trash"></i></button>
      </span>`;
    li.querySelector('span').onclick = () => { STATE.selectedCat = c.id; renderCats(); renderItems(); };
    ul.appendChild(li);
  });
  new Sortable(ul, { animation:150, handle:'.bi-grip-vertical', onEnd(){
    const ids = [...ul.querySelectorAll('li')].map(li => li.dataset.id).filter(Boolean);
    AK.post(API+'?action=category_reorder', {ids: JSON.stringify(ids)}).then(r=>{ if(r.status!=='success') AK.toast('error',r.message); });
  }});
}

function renderItems(){
  const wrap = document.getElementById('itemsWrap');
  const addBtn = document.getElementById('addItemBtn');
  const cat = STATE.categories.find(c => c.id == STATE.selectedCat);
  document.getElementById('itemsTitle').textContent = cat ? ('Items · ' + cat.name) : 'Items';
  addBtn.disabled = !cat;
  document.getElementById('bulkBar').classList.add('d-none');
  if(!cat){ wrap.innerHTML = '<div class="empty-state"><i class="bi bi-arrow-left-circle"></i><p>Select a category.</p></div>'; return; }
  const list = itemsOf(cat.id);
  if(!list.length){ wrap.innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>No items in this category yet.</p></div>'; return; }

  let html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody id="itemRows">';
  list.forEach(it => {
    const veg = Number(it.is_jain)===1 ? '<span class="badge bg-success">Jain</span>' : (Number(it.is_veg)===1 ? '<span class="veg-marker"></span>' : '<span class="veg-marker nonveg"></span>');
    html += `<tr data-id="${it.id}">
      <td style="width:28px"><input type="checkbox" class="form-check-input itChk" value="${it.id}" onchange="updateBulk()"></td>
      <td style="width:22px"><i class="bi bi-grip-vertical text-muted" style="cursor:grab"></i></td>
      <td>${veg} <strong>${escapeHtml(it.name)}</strong>
        ${Number(it.is_bestseller)?'<span class="badge bg-warning text-dark">★</span>':''}
        ${Number(it.is_new)?'<span class="badge bg-info">New</span>':''}
        ${it.tags?`<br><small class="text-muted">${escapeHtml(it.tags)}</small>`:''}</td>
      <td class="text-nowrap">${it.discount_price?`<s class="text-muted small">${moneyN(it.price)}</s> `:''}${moneyN(it.discount_price||it.price)}</td>
      <td class="text-center"><div class="form-check form-switch d-inline-block"><input type="checkbox" class="form-check-input" ${Number(it.is_available)?'checked':''} onchange="toggleAvail(${it.id},this.checked)"></div></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-secondary py-0" onclick="editItem(${it.id})"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteItem(${it.id})"><i class="bi bi-trash"></i></button></td></tr>`;
  });
  html += '</tbody></table></div>';
  wrap.innerHTML = html;

  const tb = document.getElementById('itemRows');
  new Sortable(tb, { animation:150, handle:'.bi-grip-vertical', onEnd(){
    const ids = [...tb.querySelectorAll('tr')].map(tr => tr.dataset.id);
    AK.post(API+'?action=item_reorder', {ids: JSON.stringify(ids)}).then(r=>{ if(r.status!=='success') AK.toast('error',r.message); });
  }});
}

// ---- Bulk -------------------------------------------------------------------
function checkedIds(){ return [...document.querySelectorAll('.itChk:checked')].map(c => c.value); }
function updateBulk(){
  const ids = checkedIds();
  const bar = document.getElementById('bulkBar');
  document.getElementById('bulkCount').textContent = ids.length;
  bar.classList.toggle('d-none', ids.length === 0);
  bar.classList.toggle('d-flex', ids.length > 0);
}
function bulk(op){
  const ids = checkedIds(); if(!ids.length) return;
  const go = op==='delete' ? AK.confirm('Delete '+ids.length+' item(s)?') : Promise.resolve(true);
  go.then(ok => { if(!ok) return;
    AK.post(API+'?action=bulk_action', {op:op, ids:JSON.stringify(ids)}).then(r=>AK.handle(r, loadMenu));
  });
}
function bulkPct(){
  const ids = checkedIds(); if(!ids.length) return;
  const pct = document.getElementById('pctInput').value;
  if(pct==='') { AK.toast('error','Enter a percentage'); return; }
  AK.post(API+'?action=bulk_action', {op:'price_pct', pct:pct, ids:JSON.stringify(ids)}).then(r=>AK.handle(r, loadMenu));
}

// ---- Category CRUD ----------------------------------------------------------
function openCategory(){
  document.getElementById('catForm').reset();
  document.getElementById('cat_id').value = '';
  document.getElementById('catModalTitle').textContent = 'Add Category';
  catModal.show();
}
function editCategory(id){
  const c = STATE.categories.find(x => x.id == id); if(!c) return;
  document.getElementById('catForm').reset();
  document.getElementById('cat_id').value = c.id;
  document.getElementById('cat_name').value = c.name || '';
  document.getElementById('cat_name_gu').value = c.name_gu || '';
  document.getElementById('cat_icon').value = c.icon || '';
  document.getElementById('cat_from').value = c.available_from || '';
  document.getElementById('cat_to').value = c.available_to || '';
  document.getElementById('catModalTitle').textContent = 'Edit Category';
  catModal.show();
}
function saveCategory(ev){
  ev.preventDefault();
  const fd = new FormData(document.getElementById('catForm'));
  AK.post(API+'?action=category_save', fd).then(r => AK.handle(r, () => { catModal.hide(); loadMenu(); }));
}
function deleteCategory(id){
  AK.confirm('Delete this category? Its items will be uncategorised.').then(ok => { if(!ok) return;
    AK.post(API+'?action=category_delete', {id:id}).then(r => AK.handle(r, loadMenu));
  });
}

// ---- Item CRUD --------------------------------------------------------------
function openItem(){
  if(!STATE.selectedCat){ AK.toast('error','Select a category first'); return; }
  resetItemForm();
  document.getElementById('it_cat').value = STATE.selectedCat;
  document.getElementById('itemModalTitle').textContent = 'Add Item';
  itemModal.show();
}
function resetItemForm(){
  document.getElementById('itemForm').reset();
  document.getElementById('it_id').value = '';
  document.getElementById('variantRows').innerHTML = '';
  document.getElementById('addonRows').innerHTML = '';
  document.getElementById('it_avail').checked = true;
}
function editItem(id){
  const it = STATE.items.find(x => x.id == id); if(!it) return;
  resetItemForm();
  document.getElementById('it_id').value = it.id;
  document.getElementById('it_cat').value = it.category_id;
  document.getElementById('it_name').value = it.name || '';
  document.getElementById('it_name_gu').value = it.name_gu || '';
  document.getElementById('it_desc').value = it.description || '';
  document.getElementById('it_desc_gu').value = it.description_gu || '';
  document.getElementById('it_price').value = it.price || '';
  document.getElementById('it_disc').value = it.discount_price || '';
  document.getElementById('it_food').value = Number(it.is_jain)===1 ? 'jain' : (Number(it.is_veg)===1 ? 'veg' : 'nonveg');
  document.getElementById('it_spice').value = it.spice_level || 0;
  document.getElementById('it_prep').value = it.prep_time || '';
  document.getElementById('it_tags').value = it.tags || '';
  document.getElementById('it_avail').checked = Number(it.is_available)===1;
  document.getElementById('it_best').checked = Number(it.is_bestseller)===1;
  document.getElementById('it_new').checked = Number(it.is_new)===1;
  (it.variants||[]).forEach(v => addVariant(v.label, v.price));
  (it.addons||[]).forEach(a => addAddon(a.name, a.price));
  document.getElementById('itemModalTitle').textContent = 'Edit Item';
  itemModal.show();
}
function addVariant(label='', price=''){
  const div = document.createElement('div');
  div.className = 'input-group input-group-sm mb-1';
  div.innerHTML = `<input class="form-control v-label" placeholder="Label" value="${escapeAttr(label)}">
    <input type="number" step="0.01" class="form-control v-price" placeholder="Price" value="${price}">
    <button type="button" class="btn btn-outline-danger" onclick="this.parentNode.remove()"><i class="bi bi-x"></i></button>`;
  document.getElementById('variantRows').appendChild(div);
}
function addAddon(name='', price=''){
  const div = document.createElement('div');
  div.className = 'input-group input-group-sm mb-1';
  div.innerHTML = `<input class="form-control a-name" placeholder="Name" value="${escapeAttr(name)}">
    <input type="number" step="0.01" class="form-control a-price" placeholder="Price" value="${price}">
    <button type="button" class="btn btn-outline-danger" onclick="this.parentNode.remove()"><i class="bi bi-x"></i></button>`;
  document.getElementById('addonRows').appendChild(div);
}
function genDesc(){
  const name = document.getElementById('it_name').value.trim();
  if(!name){ AK.toast('error','Enter the dish name first.'); return; }
  const btn = document.getElementById('genDescBtn');
  const food = document.getElementById('it_food').value;
  const catSel = document.getElementById('it_cat');
  const catName = (catSel && catSel.options && catSel.selectedIndex>=0) ? catSel.options[catSel.selectedIndex].text : '';
  const fd = new FormData();
  fd.set('name', name);
  fd.set('category', catName || '');
  fd.set('is_veg', (food==='nonveg'||food==='egg') ? '0' : '1');
  const old = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
  AK.post('<?= e(BASE_URL) ?>/api/ai.php?action=describe', fd).then(r => {
    btn.disabled = false; btn.innerHTML = old;
    if(r && r.status==='success'){
      if(r.data.en) document.getElementById('it_desc').value = r.data.en;
      if(r.data.gu) document.getElementById('it_desc_gu').value = r.data.gu;
      AK.toast('success','Description generated.');
    } else {
      AK.toast('error', (r && r.message) || 'Could not generate.');
    }
  }).catch(()=>{ btn.disabled=false; btn.innerHTML=old; AK.toast('error','Network error.'); });
}
function saveItem(ev){
  ev.preventDefault();
  const variants = [...document.querySelectorAll('#variantRows .input-group')].map(g => ({
    label: g.querySelector('.v-label').value, price: g.querySelector('.v-price').value || 0
  })).filter(v => v.label.trim());
  const addons = [...document.querySelectorAll('#addonRows .input-group')].map(g => ({
    name: g.querySelector('.a-name').value, price: g.querySelector('.a-price').value || 0
  })).filter(a => a.name.trim());
  const fd = new FormData(document.getElementById('itemForm'));
  fd.set('variants_json', JSON.stringify(variants));
  fd.set('addons_json', JSON.stringify(addons));
  AK.post(API+'?action=item_save', fd).then(r => AK.handle(r, () => { itemModal.hide(); loadMenu(); }));
}
function deleteItem(id){
  AK.confirm('Delete this item?').then(ok => { if(!ok) return;
    AK.post(API+'?action=item_delete', {id:id}).then(r => AK.handle(r, loadMenu));
  });
}
function toggleAvail(id, val){
  AK.post(API+'?action=item_toggle_available', {id:id, is_available: val?1:0}).then(r => {
    if(r.status!=='success'){ AK.toast('error', r.message); loadMenu(); }
  });
}

// ---- CSV import -------------------------------------------------------------
function importCsv(input){
  if(!input.files.length) return;
  const fd = new FormData();
  fd.append('file', input.files[0]);
  AK.post(API+'?action=import_csv', fd).then(r => AK.handle(r, loadMenu));
  input.value = '';
}

// ---- helpers ----------------------------------------------------------------
function escapeHtml(s){ return String(s==null?'':s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }

document.addEventListener('DOMContentLoaded', loadMenu);
</script>
HTML;
require __DIR__ . '/_footer.php';
