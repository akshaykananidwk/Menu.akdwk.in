<?php
/**
 * Client — AI Business Advisor.
 * On demand, reads this restaurant's last-30-day numbers and asks the AI for
 * concrete growth suggestions (Gujarati + English). Cost is metered under AI.
 */
$pageTitle = 'AI Advisor';
$activeNav = 'insights';
require __DIR__ . '/_header.php';

$canOrder = $tenant['ordering_mode'] !== 'view_only';
?>
<?php if (!$canOrder): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> The AI Advisor studies your orders and sales — enable online ordering to start collecting the data it needs.</div>
<?php else: ?>

<div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h6 class="fw-semibold mb-1"><i class="bi bi-lightbulb"></i> Your AI Business Advisor</h6>
    <p class="text-muted small mb-0">It reads your last 30 days — sales, best/slow items, busiest hours, repeat customers — and suggests practical ways to earn more.</p>
  </div>
  <button class="btn btn-primary" id="genBtn" onclick="genInsights()"><i class="bi bi-magic"></i> Generate Advice</button>
</div></div>

<div id="insightsWrap" class="row g-3"></div>
<div id="emptyHint" class="text-center text-muted py-5">
  <i class="bi bi-robot" style="font-size:2.5rem"></i>
  <p class="mt-2">Tap <strong>Generate Advice</strong> to get personalised suggestions for your restaurant.</p>
</div>

<?php
$base = BASE_URL;
$pageScript = <<<HTML
<script>
const B = '$base';
const esc = s => String(s==null?'':s).replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

function genInsights(){
  const btn = document.getElementById('genBtn');
  const old = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Thinking…';
  document.getElementById('emptyHint').style.display = 'none';
  AK.post(B+'/api/ai.php?action=insights', new FormData()).then(res => {
    btn.disabled = false; btn.innerHTML = old;
    if(!res || res.status !== 'success'){ AK.toast('error',(res&&res.message)||'Could not generate.'); return; }
    if(!res.data.has_data){ AK.toast('info','Tip: advice gets sharper once you have more orders.'); }
    const wrap = document.getElementById('insightsWrap');
    wrap.innerHTML = (res.data.insights||[]).map((it,i)=>{
      const tg = it.title_gu||''; const dg = it.detail_gu||'';
      return `<div class="col-md-6"><div class="card h-100"><div class="card-body">
        <div class="d-flex align-items-start gap-2">
          <span class="badge bg-primary rounded-pill">\${i+1}</span>
          <div>
            <div class="fw-semibold">\${esc(it.title_en||'')}</div>
            \${tg?`<div class="fw-semibold text-secondary">\${esc(tg)}</div>`:''}
            <div class="small mt-1">\${esc(it.detail_en||'')}</div>
            \${dg?`<div class="small text-muted mt-1">\${esc(dg)}</div>`:''}
          </div>
        </div>
      </div></div></div>`;
    }).join('') || '<div class="col-12 text-muted text-center py-4">No suggestions returned. Try again.</div>';
  }).catch(()=>{ btn.disabled=false; btn.innerHTML=old; AK.toast('error','Network error.'); });
}
</script>
HTML;
endif;
require __DIR__ . '/_footer.php';
?>
