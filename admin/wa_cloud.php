<?php
/**
 * Admin › Meta WhatsApp Cloud API.
 * Tabs: Setup · Templates · Send · Conversations.
 * Everything talks to api/wa_cloud.php. No webhook is used — Meta only pushes
 * incoming messages & delivery receipts via webhook, so this manages templates
 * and OUTBOUND messaging in full and shows accepted/failed + the exact error.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$pageTitle = 'WhatsApp Cloud API';
$activeNav = 'wa_cloud';
$cfg = waCloudCfg();
$provider = getWaSetting('wa_provider', 'gateway');
$csrf = csrfToken();
require __DIR__ . '/_header.php';
?>
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSetup">Setup</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTpl" onclick="loadTemplates()">Templates</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSend" onclick="loadTplOptions()">Send</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabConvo" onclick="loadConvos()">Conversations</button></li>
</ul>

<div class="tab-content">

  <!-- ============ SETUP ============ -->
  <div class="tab-pane fade show active" id="tabSetup">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card"><div class="card-body">
          <h6 class="fw-semibold mb-3"><i class="bi bi-whatsapp text-success"></i> Meta WhatsApp Cloud API</h6>
          <form id="setupForm" onsubmit="saveSetup(event)">
            <div class="mb-3">
              <label class="form-label small">Provider</label>
              <select name="wa_provider" class="form-select">
                <option value="gateway" <?= $provider !== 'cloud' ? 'selected' : '' ?>>Legacy gateway (bulk.akdwk.in)</option>
                <option value="cloud" <?= $provider === 'cloud' ? 'selected' : '' ?>>Meta WhatsApp Cloud API (official)</option>
              </select>
              <div class="form-text">Switch to <strong>Cloud API</strong> to send through Meta. Every message across the whole system then goes via Meta.</div>
            </div>
            <div class="mb-3"><label class="form-label small">WhatsApp Business Number</label>
              <input name="wa_cloud_number" class="form-control" placeholder="+91 99781 23146" value="<?= e($cfg['number']) ?>"></div>
            <div class="mb-3"><label class="form-label small">Phone Number ID <span class="text-danger">*</span></label>
              <input name="wa_cloud_phone_id" class="form-control" value="<?= e($cfg['phone_id']) ?>"></div>
            <div class="mb-3"><label class="form-label small">WhatsApp Business Account (WABA) ID <span class="text-muted">— for templates</span></label>
              <input name="wa_cloud_waba_id" class="form-control" value="<?= e($cfg['waba_id']) ?>"></div>
            <div class="mb-3"><label class="form-label small">Permanent Access Token <span class="text-danger">*</span></label>
              <input name="wa_cloud_token" type="password" class="form-control" autocomplete="new-password" placeholder="<?= $cfg['token'] !== '' ? '•••••••• (saved — leave to keep)' : 'EAAG…' ?>"></div>
            <div class="mb-3"><label class="form-label small">Graph API Version</label>
              <input name="wa_cloud_version" class="form-control" value="<?= e($cfg['version']) ?>" placeholder="v21.0"></div>
            <button class="btn btn-primary"><i class="bi bi-check2"></i> Save</button>
            <button type="button" class="btn btn-outline-success" onclick="testConn()"><i class="bi bi-plug"></i> Test Connection</button>
          </form>
          <div id="testResult" class="mt-3"></div>
        </div></div>
      </div>
      <div class="col-lg-5">
        <div class="card"><div class="card-body">
          <h6 class="fw-semibold"><i class="bi bi-info-circle"></i> What works without a webhook</h6>
          <ul class="small text-muted">
            <li>✅ Send text, media, template &amp; interactive-button messages</li>
            <li>✅ Create / edit / delete templates &amp; submit to Meta for approval</li>
            <li>✅ Live template status — Approved / Pending / Rejected (with reason)</li>
            <li>✅ Outbound log with accepted vs failed + the exact Meta error</li>
          </ul>
          <h6 class="fw-semibold mt-3"><i class="bi bi-exclamation-triangle text-warning"></i> Webhook-only (Meta has no poll API)</h6>
          <ul class="small text-muted mb-0">
            <li>Incoming customer replies (two-way inbox)</li>
            <li>Delivery / read ticks (delivered / read)</li>
          </ul>
          <p class="small text-muted mt-2 mb-0">These can be added later by enabling a webhook — the rest already works today.</p>
        </div></div>
      </div>
    </div>
  </div>

  <!-- ============ TEMPLATES ============ -->
  <div class="tab-pane fade" id="tabTpl">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <button class="btn btn-sm btn-outline-secondary" onclick="loadTemplates()"><i class="bi bi-arrow-clockwise"></i> Refresh from Meta</button>
      <button class="btn btn-sm btn-primary" onclick="openBuilder()"><i class="bi bi-plus-lg"></i> New Template</button>
    </div>
    <div id="tplWrap"><div class="text-muted small p-3">Loading templates…</div></div>
  </div>

  <!-- ============ SEND ============ -->
  <div class="tab-pane fade" id="tabSend">
    <div class="card"><div class="card-body">
      <form id="sendForm" onsubmit="sendMsg(event)">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small">To (with country code)</label>
            <input name="to" class="form-control" placeholder="9199…" required></div>
          <div class="col-md-4"><label class="form-label small">Type</label>
            <select name="type" id="sendType" class="form-select" onchange="sendTypeUI()">
              <option value="text">Text</option>
              <option value="template">Template (works anytime)</option>
              <option value="media">Media (image/doc/video)</option>
              <option value="buttons">Interactive buttons</option>
            </select></div>
        </div>
        <div id="fText" class="sendf mt-2"><label class="form-label small">Message</label><textarea name="body" class="form-control" rows="3"></textarea>
          <div class="form-text">Free-form text only reaches a user who messaged you in the last 24h. Otherwise use a Template.</div></div>
        <div id="fTemplate" class="sendf mt-2" style="display:none">
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small">Template</label>
              <select name="template_name" id="tplSelect" class="form-select"></select></div>
            <div class="col-md-3"><label class="form-label small">Language</label><input name="language" id="tplLang" class="form-control" value="en"></div>
          </div>
          <label class="form-label small mt-2">Body variables (in order, separate with <code>|</code>)</label>
          <input name="body_params" class="form-control" placeholder="Ramesh | ORD-102 | ₹450">
          <label class="form-label small mt-2">Header media link (only if the template has a media header)</label>
          <input name="header_link" class="form-control" placeholder="https://…/image.jpg">
        </div>
        <div id="fMedia" class="sendf mt-2" style="display:none">
          <div class="row g-2">
            <div class="col-md-3"><label class="form-label small">Media type</label>
              <select name="media_type" class="form-select"><option>image</option><option>document</option><option>video</option><option>audio</option></select></div>
            <div class="col-md-9"><label class="form-label small">Public link</label><input name="media_link" class="form-control" placeholder="https://…"></div>
          </div>
          <label class="form-label small mt-2">Caption</label><input name="caption" class="form-control">
        </div>
        <div id="fButtons" class="sendf mt-2" style="display:none">
          <label class="form-label small">Body</label><textarea name="body2" class="form-control" rows="2"></textarea>
          <label class="form-label small mt-1">Buttons (max 3)</label>
          <div id="sendBtns"></div>
          <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="addSendBtn()"><i class="bi bi-plus"></i> Add button</button>
          <div class="form-text">Interactive buttons need an active 24h conversation window.</div>
        </div>
        <button class="btn btn-primary mt-3"><i class="bi bi-send"></i> Send</button>
      </form>
      <div id="sendResult" class="mt-3"></div>
    </div></div>
  </div>

  <!-- ============ CONVERSATIONS ============ -->
  <div class="tab-pane fade" id="tabConvo">
    <div class="row g-3">
      <div class="col-lg-4"><div class="card"><div class="card-body p-2">
        <div class="d-flex justify-content-between align-items-center px-2 py-1">
          <strong class="small">Contacts</strong>
          <button class="btn btn-sm btn-link p-0" onclick="loadConvos()"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
        <div id="convoList" style="max-height:70vh;overflow:auto"></div>
      </div></div></div>
      <div class="col-lg-8"><div class="card"><div class="card-body">
        <div id="threadHead" class="fw-semibold mb-2 text-muted">Select a contact to view messages</div>
        <div id="thread" style="max-height:70vh;overflow:auto"></div>
      </div></div></div>
    </div>
    <p class="small text-muted mt-2">Shows messages the system <strong>sent</strong> (all triggers + manual). Two dots = accepted by Meta; a red note = the exact failure reason. Delivered/read ticks and customer replies need a webhook.</p>
  </div>

</div>

<!-- Template builder modal -->
<div class="modal fade" id="builderModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title">New Template</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <form id="builderForm">
      <div class="row g-2">
        <div class="col-md-5"><label class="form-label small">Name (a-z, 0-9, _)</label><input name="name" class="form-control" placeholder="order_update"></div>
        <div class="col-md-4"><label class="form-label small">Category</label>
          <select name="category" id="bCat" class="form-select" onchange="builderCatUI()">
            <option value="UTILITY">Utility</option><option value="MARKETING">Marketing</option><option value="AUTHENTICATION">Authentication (OTP)</option></select></div>
        <div class="col-md-3"><label class="form-label small">Language</label><input name="language" class="form-control" value="en"></div>
      </div>

      <div id="bNormal">
        <hr class="my-2">
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small">Header</label>
            <select name="header_type" id="bHeaderType" class="form-select" onchange="builderHeaderUI()">
              <option value="none">None</option><option value="text">Text</option><option value="image">Image</option><option value="document">Document</option><option value="video">Video</option></select></div>
          <div class="col-md-8" id="bHeaderTextWrap" style="display:none"><label class="form-label small">Header text (use {{1}} for a variable)</label><input name="header_text" class="form-control"></div>
        </div>
        <div id="bHeaderSampleWrap" style="display:none" class="mt-2"><label class="form-label small" id="bHeaderSampleLbl">Header sample</label><input name="header_sample" class="form-control" placeholder="Sample value / media URL"></div>

        <label class="form-label small mt-2">Body <span class="text-danger">*</span> — use {{1}}, {{2}} for variables</label>
        <textarea name="body_text" class="form-control" rows="3" placeholder="Hi {{1}}, your order {{2}} is ready!"></textarea>
        <label class="form-label small mt-1">Body sample values (in order, separate with <code>|</code>)</label>
        <input name="body_samples" class="form-control" placeholder="Ramesh | ORD-102">

        <label class="form-label small mt-2">Footer (optional)</label>
        <input name="footer_text" class="form-control" placeholder="Krishna Menu">

        <label class="form-label small mt-2">Buttons (optional, max 3 for the common types)</label>
        <div id="bBtns"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="addBtn()"><i class="bi bi-plus"></i> Add button</button>
      </div>

      <div id="bAuth" style="display:none">
        <hr class="my-2">
        <div class="alert alert-info small">Meta builds the OTP message text for authentication templates. You only set the code expiry and the button label.</div>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label small">Code expiry (minutes)</label><input name="code_expiry" class="form-control" value="10"></div>
          <div class="col-md-6"><label class="form-label small">OTP button text</label><input name="otp_button_text" class="form-control" value="Copy Code"></div>
        </div>
      </div>
    </form>
    <div id="builderMsg" class="mt-2"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-primary" onclick="submitTemplate()"><i class="bi bi-send"></i> Submit for Approval</button></div>
</div></div></div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B='$base', CSRF='$csrf';
const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
function api(action, data){
  const fd = data instanceof FormData ? data : new FormData();
  if(data && !(data instanceof FormData)){ for(const k in data) fd.append(k, data[k]); }
  fd.append('csrf_token', CSRF);
  return fetch(B+'/api/wa_cloud.php?action='+action,{method:'POST',headers:{'X-CSRF-Token':CSRF},body:fd}).then(r=>r.json());
}

// ---- Setup ----
function saveSetup(e){ e.preventDefault();
  api('save_setup', new FormData(document.getElementById('setupForm'))).then(r=>{
    document.getElementById('testResult').innerHTML='<div class="alert alert-'+(r.status==='success'?'success':'danger')+' py-2 small">'+esc(r.message)+'</div>';
  });
}
function testConn(){
  const box=document.getElementById('testResult'); box.innerHTML='<div class="text-muted small">Testing…</div>';
  api('test',{}).then(r=>{
    if(r.status==='success'){ const i=r.data.info||{};
      box.innerHTML='<div class="alert alert-success py-2 small"><b>Connected ✓</b><br>Name: '+esc(i.verified_name||'-')+'<br>Number: '+esc(i.display_phone_number||'-')+'<br>Quality: '+esc(i.quality_rating||'-')+'</div>';
    } else box.innerHTML='<div class="alert alert-danger py-2 small">'+esc(r.message)+'</div>';
  });
}

// ---- Templates ----
const STBADGE={APPROVED:'success',PENDING:'warning',REJECTED:'danger',PAUSED:'secondary',DISABLED:'secondary',IN_APPEAL:'info'};
function loadTemplates(){
  const w=document.getElementById('tplWrap'); w.innerHTML='<div class="text-muted small p-3">Loading templates…</div>';
  api('tpl_list',{}).then(r=>{
    if(r.status!=='success'){ w.innerHTML='<div class="alert alert-warning small">'+esc(r.message||'Could not load')+'</div>'; return; }
    const t=r.data.templates||[];
    if(!t.length){ w.innerHTML='<div class="alert alert-light border small">No templates yet. Click “New Template”.</div>'; return; }
    w.innerHTML='<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Name</th><th>Category</th><th>Lang</th><th>Status</th><th></th></tr></thead><tbody>'+
      t.map(x=>{
        const st=(x.status||'').toUpperCase();
        const rej=x.rejected_reason&&x.rejected_reason!=='NONE'?('<div class="small text-danger">'+esc(x.rejected_reason)+'</div>'):'';
        return '<tr><td><b>'+esc(x.name)+'</b>'+rej+'</td><td>'+esc(x.category||'')+'</td><td>'+esc(x.language||'')+'</td>'+
          '<td><span class="badge bg-'+(STBADGE[st]||'secondary')+'">'+esc(st)+'</span></td>'+
          '<td class="text-end"><button class="btn btn-sm btn-outline-danger py-0" onclick="delTpl('+JSON.stringify(x.name)+')"><i class="bi bi-trash"></i></button></td></tr>';
      }).join('')+'</tbody></table></div>';
  });
}
function delTpl(name){ if(!confirm('Delete template "'+name+'"? This removes it from Meta.')) return;
  api('tpl_delete',{name:name}).then(r=>{ alert(r.message); if(r.status==='success') loadTemplates(); });
}

// ---- Template builder ----
let builderModal;
function openBuilder(){ document.getElementById('builderForm').reset(); document.getElementById('bBtns').innerHTML='';
  document.getElementById('builderMsg').innerHTML=''; builderCatUI(); builderHeaderUI();
  builderModal=builderModal||new bootstrap.Modal(document.getElementById('builderModal')); builderModal.show(); }
function builderCatUI(){ const auth=document.getElementById('bCat').value==='AUTHENTICATION';
  document.getElementById('bAuth').style.display=auth?'block':'none';
  document.getElementById('bNormal').style.display=auth?'none':'block'; }
function builderHeaderUI(){ const v=document.getElementById('bHeaderType').value;
  document.getElementById('bHeaderTextWrap').style.display=v==='text'?'block':'none';
  const sw=document.getElementById('bHeaderSampleWrap'); sw.style.display=(v==='text'||['image','document','video'].includes(v))?'block':'none';
  document.getElementById('bHeaderSampleLbl').textContent=v==='text'?'Header variable sample':'Sample media URL (public)'; }
const BTYPES=[['QUICK_REPLY','Quick reply'],['URL','Visit URL'],['PHONE_NUMBER','Call'],['COPY_CODE','Copy code']];
function addBtn(){ const d=document.createElement('div'); d.className='input-group input-group-sm mb-1 btnrow';
  d.innerHTML='<select class="form-select bt-type" style="max-width:120px">'+BTYPES.map(b=>'<option value="'+b[0]+'">'+b[1]+'</option>').join('')+'</select>'+
    '<input class="form-control bt-text" placeholder="Button text">'+
    '<input class="form-control bt-extra" placeholder="URL / phone / code sample">'+
    '<button type="button" class="btn btn-outline-danger" onclick="this.parentNode.remove()">×</button>';
  document.getElementById('bBtns').appendChild(d); }
function collectButtons(scope){
  return [...document.querySelectorAll(scope+' .btnrow')].map(r=>{
    const type=r.querySelector('.bt-type').value, text=r.querySelector('.bt-text').value.trim(), extra=r.querySelector('.bt-extra').value.trim();
    const o={type,text};
    if(type==='URL'){ o.url=extra; o.sample=extra.replace(/\{\{\d+\}\}/,'value'); }
    else if(type==='PHONE_NUMBER') o.phone=extra;
    else if(type==='COPY_CODE') o.sample=extra||'12345';
    return o;
  }).filter(b=>b.text||b.type==='COPY_CODE');
}
function submitTemplate(){
  const fd=new FormData(document.getElementById('builderForm'));
  fd.append('buttons', JSON.stringify(collectButtons('#bBtns')));
  const msg=document.getElementById('builderMsg'); msg.innerHTML='<span class="text-muted small">Submitting…</span>';
  api('tpl_create', fd).then(r=>{
    msg.innerHTML='<div class="alert alert-'+(r.status==='success'?'success':'danger')+' py-2 small">'+esc(r.message)+'</div>';
    if(r.status==='success'){ setTimeout(()=>{ builderModal.hide(); loadTemplates(); },900); }
  });
}

// ---- Send ----
function sendTypeUI(){ const t=document.getElementById('sendType').value;
  document.querySelectorAll('.sendf').forEach(x=>x.style.display='none');
  document.getElementById({text:'fText',template:'fTemplate',media:'fMedia',buttons:'fButtons'}[t]).style.display='block'; }
function loadTplOptions(){ api('tpl_list',{}).then(r=>{ if(r.status!=='success') return;
  const sel=document.getElementById('tplSelect'); const appr=(r.data.templates||[]).filter(x=>(x.status||'').toUpperCase()==='APPROVED');
  sel.innerHTML=appr.map(x=>'<option value="'+esc(x.name)+'" data-lang="'+esc(x.language)+'">'+esc(x.name)+' ('+esc(x.language)+')</option>').join('')||'<option value="">No approved templates</option>';
  sel.onchange=()=>{ const o=sel.selectedOptions[0]; if(o&&o.dataset.lang) document.getElementById('tplLang').value=o.dataset.lang; }; sel.onchange();
}); }
function addSendBtn(){ if(document.querySelectorAll('#sendBtns .btnrow').length>=3) return;
  const d=document.createElement('div'); d.className='input-group input-group-sm mb-1 btnrow';
  d.innerHTML='<input class="form-control bt-text" placeholder="Button label (max 20)"><button type="button" class="btn btn-outline-danger" onclick="this.parentNode.remove()">×</button>';
  document.getElementById('sendBtns').appendChild(d); }
function sendMsg(e){ e.preventDefault();
  const f=document.getElementById('sendForm'); const type=f.type.value; const fd=new FormData(f);
  if(type==='buttons'){ fd.set('body', f.body2.value);
    const btns=[...document.querySelectorAll('#sendBtns .bt-text')].map((x,i)=>({id:'btn_'+i,title:x.value.trim()})).filter(b=>b.title);
    fd.append('buttons', JSON.stringify(btns)); }
  const box=document.getElementById('sendResult'); box.innerHTML='<span class="text-muted small">Sending…</span>';
  api('send', fd).then(r=>{
    box.innerHTML='<div class="alert alert-'+(r.status==='success'?'success':'danger')+' py-2 small">'+esc(r.message)+(r.data&&r.data.wamid?'<br><span class="text-muted">id: '+esc(r.data.wamid)+'</span>':'')+'</div>';
  });
}

// ---- Conversations ----
function loadConvos(){ api('convo_list',{}).then(r=>{ if(r.status!=='success') return;
  const c=r.data.conversations||[];
  document.getElementById('convoList').innerHTML=c.length? c.map(x=>
    '<a href="#" class="list-group-item list-group-item-action border-0 px-2 py-2" onclick="openThread('+JSON.stringify(x.number)+');return false;">'+
    '<div class="d-flex justify-content-between"><b class="small">'+esc(x.number)+'</b><span class="badge bg-light text-dark">'+x.cnt+'</span></div>'+
    '<div class="small text-muted text-truncate">'+esc((x.message||'').slice(0,60))+'</div></a>').join('')
    : '<div class="text-muted small p-2">No messages yet.</div>';
}); }
function openThread(num){
  document.getElementById('threadHead').textContent=num;
  const box=document.getElementById('thread'); box.innerHTML='<div class="text-muted small">Loading…</div>';
  api('convo_thread',{number:num}).then(r=>{ if(r.status!=='success') return;
    box.innerHTML=(r.data.messages||[]).map(m=>{
      const ok=m.status==='sent';
      const tick=ok?'<span class="text-primary">✓✓ accepted</span>':(m.status==='pending'?'<span class="text-muted">⏳ queued</span>':'<span class="text-danger">✗ failed</span>');
      const errLine=(!ok&&m.error)?'<div class="small text-danger">'+esc(m.error)+'</div>':'';
      return '<div class="d-flex justify-content-end mb-2"><div style="max-width:80%;background:#d9fdd3;border-radius:12px;padding:8px 11px">'+
        '<div class="small">'+esc(m.message||'')+'</div>'+errLine+
        '<div class="small text-muted mt-1" style="font-size:.7rem">'+esc((m.created_at||'').slice(5,16))+' · '+esc(m.trigger_key||'')+' · '+tick+'</div></div></div>';
    }).join('')||'<div class="text-muted small">No messages.</div>';
  });
}
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
