<?php
/**
 * Client panel — Standee Design Gallery + Customizer.
 * Browse the colourful table-tent designs, pick one, edit the text/language in a
 * live-preview customizer, then download or send to WhatsApp. Text config is
 * saved per tenant (setSetting 'standee_cfg_<id>') and honoured by every render.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/standee/engine.php';   // designs + se_resolve_config()
requireClient();

$tid    = currentTenantId();
$tenant = currentTenant();

$pageTitle = 'Standee Designs';
$activeNav = 'qr';

$slug     = (string)$tenant['slug'];
$designs  = standee_designs();
$palettes = standee_palettes();
$families = standee_families();

$rawDefault = (string)getSetting('standee_design_' . $tid, '');
$current  = standee_resolve($rawDefault) ? $rawDefault : '';
$activeId = $current ?: ($designs[0]['id'] ?? 'corners-t1');
$currentLabel = '';
foreach ($designs as $d) { if ($d['id'] === $activeId) { $currentLabel = $d['family_name'] . ' · ' . $d['palette_name']; break; } }

// Saved (or default) editable text config to pre-fill the customizer form.
$cfg = se_resolve_config($tenant, (int)$tid, []);
$renderBase = BASE_URL . '/standee/render.php';

require __DIR__ . '/_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h6 class="fw-semibold mb-1"><i class="bi bi-easel2"></i> Standee Designs &amp; Customizer</h6>
    <p class="text-muted small mb-0"><?= (int)count($designs) ?> ready-to-print designs. Pick a style, edit your text below, then download or send to WhatsApp.</p>
  </div>
  <a href="<?= e(BASE_URL) ?>/client/qr.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to QR &amp; Standee</a>
</div>

<div class="row g-3">
  <!-- ===================== LEFT: live customizer ===================== -->
  <div class="col-lg-4">
    <div class="card" style="position:sticky;top:1rem">
      <div class="card-body">
        <div class="text-center mb-2">
          <img id="pvImage" src="" alt="Standee preview" class="img-fluid border rounded shadow-sm" style="max-height:52vh;background:#f8f9fa">
        </div>
        <div class="small text-muted text-center mb-2">Editing: <strong id="activeLabel"><?= e($currentLabel) ?></strong></div>

        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label small mb-1">Print size</label>
            <select id="pvSize" class="form-select form-select-sm">
              <option value="tent">Table Tent</option>
              <option value="a4">A4 Poster</option>
            </select>
          </div>
          <div class="col-6 d-flex align-items-end">
            <button id="btnDefault" type="button" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-star"></i> Set default</button>
          </div>
        </div>

        <div class="d-grid gap-2 mb-3">
          <a id="btnPng" href="#" target="_blank" class="btn btn-primary btn-sm"><i class="bi bi-download"></i> Download PNG</a>
          <a id="btnPdf" href="#" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
          <button id="btnWa" type="button" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Send to WhatsApp</button>
        </div>

        <hr class="my-2">
        <div class="fw-semibold small mb-2"><i class="bi bi-pencil-square"></i> Edit the text</div>

        <div class="mb-2">
          <label class="form-label small mb-1">Heading label <span class="text-muted">(blank = hide)</span></label>
          <input type="text" id="f_heading" class="form-control form-control-sm cfg" maxlength="40" value="<?= e($cfg['heading']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Restaurant name (printed)</label>
          <input type="text" id="f_name" class="form-control form-control-sm cfg" maxlength="80" value="<?= e($cfg['name']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Call-to-action text</label>
          <input type="text" id="f_cta" class="form-control form-control-sm cfg" maxlength="40" value="<?= e($cfg['cta']) ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Language</label>
          <select id="f_lang" class="form-select form-select-sm cfg">
            <option value="en" <?= $cfg['lang'] === 'en' ? 'selected' : '' ?>>English only</option>
            <option value="gu" <?= $cfg['lang'] === 'gu' ? 'selected' : '' ?>>Gujarati only</option>
            <option value="both" <?= $cfg['lang'] === 'both' ? 'selected' : '' ?>>Both (English + Gujarati)</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Tagline <span class="text-muted">(optional)</span></label>
          <input type="text" id="f_tagline" class="form-control form-control-sm cfg" maxlength="60" placeholder="e.g. Pure Veg • Family Restaurant" value="<?= e($cfg['tagline']) ?>">
        </div>
        <div class="mb-2">
          <div class="form-check form-switch">
            <input class="form-check-input cfg" type="checkbox" id="f_show_contact" <?= !empty($cfg['show_contact']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="f_show_contact">Show contact line</label>
          </div>
          <input type="text" id="f_contact" class="form-control form-control-sm cfg mt-1" maxlength="80" value="<?= e($cfg['contact']) ?>">
        </div>
        <div class="mb-2 form-check form-switch">
          <input class="form-check-input cfg" type="checkbox" id="f_show_stars" <?= !empty($cfg['show_stars']) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="f_show_stars">Show 5 stars</label>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Extra footer line <span class="text-muted">(optional)</span></label>
          <input type="text" id="f_footer_extra" class="form-control form-control-sm cfg" maxlength="60" value="<?= e($cfg['footer_extra']) ?>">
          <div class="form-text" style="font-size:.72rem">“<?= e(POWERED_BY) ?>” always stays as branding.</div>
        </div>

        <div class="d-grid">
          <button id="btnSaveCfg" type="button" class="btn btn-dark btn-sm"><i class="bi bi-save"></i> Save text</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== RIGHT: gallery ===================== -->
  <div class="col-lg-8">
    <!-- Filters -->
    <div class="card mb-3"><div class="card-body py-2">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="small text-muted me-1" style="min-width:48px">Style:</span>
        <button type="button" class="btn btn-sm btn-primary family-chip active" data-family="all">All</button>
        <?php foreach ($families as $fid => $f): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary family-chip" data-family="<?= e($fid) ?>"><?= e($f['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="small text-muted me-1" style="min-width:48px">Colour:</span>
        <button type="button" class="btn btn-sm btn-primary theme-chip active" data-theme="all">All</button>
        <?php foreach ($palettes as $pid => $p): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary theme-chip" data-theme="<?= e($pid) ?>">
            <span class="d-inline-block rounded-circle align-middle me-1" style="width:12px;height:12px;background:<?= e($p['c1']) ?>;border:1px solid rgba(0,0,0,.15)"></span><?= e($p['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div></div>

    <div class="row g-3" id="galleryGrid">
      <?php foreach ($designs as $d):
        $thumbUrl = $renderBase . '?slug=' . urlencode($slug) . '&design=' . urlencode($d['id']) . '&thumb=1';
        $isActive = ($d['id'] === $activeId);
      ?>
      <div class="col-6 col-md-4 col-lg-4 col-xl-3 design-cell" data-palette="<?= e($d['palette']) ?>" data-family="<?= e($d['family']) ?>">
        <div class="card h-100 design-card <?= $isActive ? 'border-primary border-2' : '' ?>" role="button"
             data-design="<?= e($d['id']) ?>" data-label="<?= e($d['family_name'] . ' · ' . $d['palette_name']) ?>">
          <div class="ratio bg-light" style="--bs-aspect-ratio:177%">
            <img loading="lazy" src="<?= e($thumbUrl) ?>" alt="<?= e($d['id']) ?>" class="w-100 h-100" style="object-fit:cover;border-radius:.35rem">
          </div>
          <div class="card-body p-2 text-center">
            <div class="small fw-semibold text-truncate"><?= e($d['family_name']) ?></div>
            <div class="text-muted" style="font-size:.72rem"><?= e($d['palette_name']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$renderBaseJs = json_encode($renderBase);
$slugJs       = json_encode($slug);
$apiJs        = json_encode(BASE_URL . '/api/standee.php');
$ownNumberJs  = json_encode((string)($tenant['whatsapp_no'] ?: $tenant['mobile']));
$activeJs     = json_encode($activeId);
$pageScript = <<<HTML
<script>
(function(){
  const RENDER = $renderBaseJs, SLUG = $slugJs, API = $apiJs, OWN = $ownNumberJs;
  let activeDesign = $activeJs;

  // ---- Filters (style family AND colour theme) ----
  let activeFamily = 'all', activeTheme = 'all';
  function applyFilters(){
    document.querySelectorAll('.design-cell').forEach(function(cell){
      const okF = (activeFamily === 'all' || cell.getAttribute('data-family') === activeFamily);
      const okT = (activeTheme === 'all' || cell.getAttribute('data-palette') === activeTheme);
      cell.style.display = (okF && okT) ? '' : 'none';
    });
  }
  function wireChips(selector, attr, setter){
    document.querySelectorAll(selector).forEach(function(chip){
      chip.addEventListener('click', function(){
        document.querySelectorAll(selector).forEach(function(c){ c.classList.remove('active','btn-primary'); c.classList.add('btn-outline-secondary'); });
        chip.classList.add('active','btn-primary'); chip.classList.remove('btn-outline-secondary');
        setter(chip.getAttribute(attr)); applyFilters();
      });
    });
  }
  wireChips('.family-chip', 'data-family', function(v){ activeFamily = v; });
  wireChips('.theme-chip', 'data-theme', function(v){ activeTheme = v; });

  // ---- Collect the current text config from the form ----
  function val(id){ return (document.getElementById(id) || {}).value || ''; }
  function chk(id){ return document.getElementById(id) && document.getElementById(id).checked ? '1' : '0'; }
  function collectCfg(){
    return {
      heading: val('f_heading'),
      name: val('f_name'),
      cta: val('f_cta'),
      lang: val('f_lang'),
      tagline: val('f_tagline'),
      show_contact: chk('f_show_contact'),
      contact: val('f_contact'),
      show_stars: chk('f_show_stars'),
      footer_extra: val('f_footer_extra')
    };
  }
  function cfgParams(){
    const c = collectCfg();
    return Object.keys(c).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(c[k]); }).join('&');
  }

  // ---- Build render/download URLs (design controls style; fields control text) ----
  function buildUrl(format, download){
    const size = val('pvSize') || 'tent';
    let u = RENDER + '?slug=' + encodeURIComponent(SLUG) + '&design=' + encodeURIComponent(activeDesign)
          + '&size=' + size + '&format=' + format + '&' + cfgParams();
    if (download) u += '&download=1';
    return u;
  }
  function refreshLinks(){
    document.getElementById('btnPng').href = buildUrl('png', true);
    document.getElementById('btnPdf').href = buildUrl('pdf', true);
  }

  // ---- Debounced live preview ----
  let t = null;
  function refreshPreview(){
    clearTimeout(t);
    t = setTimeout(function(){
      document.getElementById('pvImage').src = buildUrl('png', false) + '&_=' + Date.now();
      refreshLinks();
    }, 350);
  }
  document.querySelectorAll('.cfg').forEach(function(el){
    el.addEventListener('input', refreshPreview);
    el.addEventListener('change', refreshPreview);
  });
  document.getElementById('pvSize').addEventListener('change', refreshPreview);

  // ---- Select a design from the gallery ----
  document.querySelectorAll('.design-card').forEach(function(card){
    card.addEventListener('click', function(){
      activeDesign = card.getAttribute('data-design');
      document.querySelectorAll('.design-card').forEach(function(c){ c.classList.remove('border-primary','border-2'); });
      card.classList.add('border-primary','border-2');
      document.getElementById('activeLabel').textContent = card.getAttribute('data-label');
      refreshPreview();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  // ---- Save text config ----
  document.getElementById('btnSaveCfg').addEventListener('click', function(){
    AK.post(API + '?action=save_cfg', collectCfg()).then(function(r){ AK.handle(r); }).catch(function(){ AK.toast('error','Network error'); });
  });

  // ---- Send to WhatsApp (WYSIWYG: current fields go with it) ----
  document.getElementById('btnWa').addEventListener('click', function(){
    Swal.fire({
      title: 'Send standee to WhatsApp', input: 'text', inputLabel: 'Mobile number',
      inputValue: OWN, inputPlaceholder: '10-digit number',
      showCancelButton: true, confirmButtonText: 'Send', confirmButtonColor: 'var(--primary)'
    }).then(function(res){
      if (!res.isConfirmed) return;
      const payload = collectCfg();
      payload.number = res.value || '';
      payload.design = activeDesign;
      AK.post(API + '?action=send_whatsapp', payload).then(function(r){ AK.handle(r); }).catch(function(){ AK.toast('error','Network error'); });
    });
  });

  // ---- Set as default design ----
  document.getElementById('btnDefault').addEventListener('click', function(){
    AK.post(API + '?action=save_default', { design: activeDesign }).then(function(r){ AK.handle(r); });
  });

  // Initial paint.
  refreshPreview();
})();
</script>
HTML;
require __DIR__ . '/_footer.php';
