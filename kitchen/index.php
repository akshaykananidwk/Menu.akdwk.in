<?php
/**
 * Kitchen KOT display — full-screen dark board.
 * Polls api/order.php?action=list (new/accepted/preparing) every 5s, alerts on
 * new tickets, shows elapsed time (turns .late red after 15 min), advances status.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireStaff('kitchen');

$tid    = (int)($_SESSION['staff_tenant_id'] ?? 0);
$tenant = db_one('SELECT restaurant_name FROM ' . tbl('tenants') . ' WHERE id = :i', [':i' => $tid]);
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title>Kitchen · <?= e($tenant['restaurant_name'] ?? '') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/assets/css/app.css" rel="stylesheet">
<style>
body{margin:0}
.kot-top{background:#161b22;color:#fff;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:20}
.kot-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;padding:16px}
.kot-card{padding:12px}
.kot-card h5{margin:0}
.kot-item{font-size:1.05rem;padding:3px 0;border-bottom:1px solid rgba(255,255,255,.08)}
.kot-note{color:#f1a208;font-weight:600;font-size:.9rem}
.kot-time{font-variant-numeric:tabular-nums}
.kot-btn{min-height:46px;font-weight:600}
</style>
</head><body class="kot-screen">

<div class="kot-top">
  <div><i class="bi bi-fire text-danger"></i> <strong><?= e($tenant['restaurant_name'] ?? 'Kitchen') ?></strong> — KOT Display</div>
  <div class="d-flex align-items-center gap-3">
    <div class="form-check form-switch text-white mb-0">
      <input class="form-check-input" type="checkbox" id="soundToggle" checked>
      <label class="form-check-label small" for="soundToggle">Sound</label>
    </div>
    <a href="<?= e(BASE_URL) ?>/kitchen/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </div>
</div>

<div id="grid" class="kot-grid"></div>
<div id="empty" class="text-center text-secondary py-5" style="display:none"><i class="bi bi-check2-circle" style="font-size:3rem"></i><p>No active tickets. All caught up!</p></div>

<audio id="ding" preload="auto" src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU"></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $base = BASE_URL; $csrf = csrfToken(); ?>
<script>
const B='<?= e($base) ?>', CSRF='<?= e($csrf) ?>';
const esc=s=>(s==null?'':String(s)).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let lastMaxId=0, firstLoad=true;

// Next status per current status (kitchen flow).
const NEXT={new:'accepted', accepted:'preparing', preparing:'ready'};
const NEXTLABEL={new:'Accept', accepted:'Start Preparing', preparing:'Mark Ready'};

function mins(ts){ const d=new Date((ts||'').replace(' ','T')); const m=(Date.now()-d.getTime())/60000; return isNaN(m)?0:m; }
function fmt(ts){ const m=Math.floor(mins(ts)); return m<60?(m+'m'):(Math.floor(m/60)+'h '+(m%60)+'m'); }

function cardHtml(o){
  const late = mins(o.created_at) > 15;
  const items=(o.items||[]).map(i=>{
    let ad=''; try{(JSON.parse(i.addons_json)||[]).forEach(a=>ad+=' +'+esc(a.name));}catch(e){}
    return `<div class="kot-item">\${i.qty}× \${esc(i.item_name)}\${i.variant_label?' ('+esc(i.variant_label)+')':''}\${ad}`+
      `\${i.notes?'<div class="kot-note">↳ '+esc(i.notes)+'</div>':''}</div>`;
  }).join('');
  const nxt=NEXT[o.status];
  return `<div class="kot-card \${late?'late':''}">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div><h5>\${o.table_no?('Table '+esc(o.table_no)):esc(o.order_type)}</h5>
        <small class="text-secondary">#\${esc(o.order_no)} · \${esc(o.status)}</small></div>
      <div class="kot-time \${late?'text-danger fw-bold':'text-secondary'}"><i class="bi bi-clock"></i> \${fmt(o.created_at)}</div>
    </div>
    \${items}
    <div class="mt-2 d-grid">\${nxt?`<button class="btn btn-primary kot-btn" onclick="adv(\${o.id},'\${nxt}')">\${NEXTLABEL[o.status]}</button>`:''}</div>
  </div>`;
}

function poll(){
  fetch(B+'/api/order.php?action=list&status=new,accepted,preparing',{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(res=>{
      if(!res||res.status!=='success') return;
      const orders=(res.data.orders||[]).sort((a,b)=>new Date(a.created_at)-new Date(b.created_at));
      const maxId=orders.reduce((m,o)=>Math.max(m,+o.id),0);
      if(!firstLoad && maxId>lastMaxId && document.getElementById('soundToggle').checked){
        try{ document.getElementById('ding').play(); }catch(e){}
      }
      lastMaxId=Math.max(lastMaxId,maxId); firstLoad=false;
      document.getElementById('grid').innerHTML=orders.map(cardHtml).join('');
      document.getElementById('empty').style.display=orders.length?'none':'block';
    }).catch(()=>{});
}

window.adv=function(id,status){
  const body=new URLSearchParams({order_id:id,status:status,csrf_token:CSRF});
  fetch(B+'/api/order.php?action=status',{method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest','X-CSRF-Token':CSRF},
    body:body.toString()}).then(r=>r.json()).then(()=>poll());
};

poll();
setInterval(poll,5000);
</script>
</body></html>
