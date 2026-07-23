<?php
/**
 * Client — Table Reservations. Confirm / seat / cancel bookings made from the
 * public menu; the guest is notified on WhatsApp when you confirm or cancel.
 */
$pageTitle = 'Reservations';
$activeNav = 'reservations_book';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
?>
<div class="card mb-3"><div class="card-body">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small">On date</label><input type="date" id="rDate" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small">&nbsp;</label>
      <div class="d-flex gap-2"><button class="btn btn-sm btn-primary" onclick="load()">Filter</button>
      <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('rDate').value='';load()">Upcoming</button></div>
    </div>
  </div>
</div></div>

<div class="card"><div class="card-body">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>When</th><th>Guest</th><th>Mobile</th><th class="text-center">Pax</th><th>Note</th><th>Status</th><th></th></tr></thead>
      <tbody id="rBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
    </table>
  </div>
</div></div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
const esc = s => String(s==null?'':s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const waNum = m => { let d=String(m||'').replace(/\\D/g,''); if(d.length===10) d='91'+d; else if(d.length===11&&d[0]==='0') d='91'+d.slice(1); return d; };
const BADGE = {pending:'warning',confirmed:'success',seated:'info',cancelled:'secondary'};
function fmtWhen(d,t){ try{ return new Date(d+'T'+t).toLocaleString([], {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); }catch(e){ return d+' '+t; } }

function load(){
  const date = document.getElementById('rDate').value;
  AK.get(B+'/api/reserve.php?action=list'+(date?('&date='+date):'&scope=upcoming')).then(res=>{
    if(!res || res.status!=='success') return;
    const rows = res.data.reservations||[];
    document.getElementById('rBody').innerHTML = rows.length ? rows.map(r=>{
      const st = r.status;
      let actions='';
      if(st==='pending'){ actions+=`<button class="btn btn-sm btn-success py-0" onclick="setStatus(\${r.id},'confirmed')">Confirm</button> `; }
      if(st==='confirmed'){ actions+=`<button class="btn btn-sm btn-info py-0" onclick="setStatus(\${r.id},'seated')">Seated</button> `; }
      if(st!=='cancelled'&&st!=='seated'){ actions+=`<button class="btn btn-sm btn-outline-danger py-0" onclick="setStatus(\${r.id},'cancelled')">Cancel</button> `; }
      actions+=`<a class="btn btn-sm btn-outline-success py-0" target="_blank" href="https://wa.me/\${waNum(r.customer_mobile)}"><i class="bi bi-whatsapp"></i></a>`;
      return `<tr>
        <td class="fw-semibold">\${esc(fmtWhen(r.reserve_date,r.reserve_time))}</td>
        <td>\${esc(r.customer_name)}</td><td>\${esc(r.customer_mobile)}</td>
        <td class="text-center">\${r.party_size}</td>
        <td class="small text-muted">\${esc(r.note||'')}</td>
        <td><span class="badge bg-\${BADGE[st]||'secondary'}">\${esc(st)}</span></td>
        <td class="text-nowrap">\${actions}</td></tr>`;
    }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No reservations. Guests can book from your menu page.</td></tr>';
  });
}
window.setStatus=function(id,status){
  AK.post(B+'/api/reserve.php?action=status',{id:id,status:status}).then(r=>AK.handle(r,load));
};
load();
</script>
HTML;
require __DIR__ . '/_footer.php';
?>
