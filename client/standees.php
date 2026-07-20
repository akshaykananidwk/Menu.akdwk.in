<?php
/**
 * Client panel — Standee Design Gallery.
 * Browse 100+ colourful table-tent designs, preview, download, send to WhatsApp,
 * or save one as the restaurant's default. Thumbnails are served (and cached) by
 * standee/render.php?thumb=1.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/standee/designs.php';
requireClient();

$tid    = currentTenantId();
$tenant = currentTenant();

$pageTitle = 'Standee Designs';
$activeNav = 'qr';

$slug     = (string)$tenant['slug'];
$designs  = standee_designs();
$palettes = standee_palettes();
$layouts  = standee_layouts();
$current  = (string)getSetting('standee_design_' . $tid, '');
$renderBase = BASE_URL . '/standee/render.php';

require __DIR__ . '/_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h6 class="fw-semibold mb-1"><i class="bi bi-easel2"></i> Standee Design Gallery</h6>
    <p class="text-muted small mb-0"><?= (int)count($designs) ?>+ ready-to-print designs with your logo, name &amp; QR. Pick one, then download or send it to WhatsApp.</p>
  </div>
  <a href="<?= e(BASE_URL) ?>/client/qr.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to QR &amp; Standee</a>
</div>

<!-- Theme filter chips -->
<div class="card mb-3"><div class="card-body py-2">
  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="small text-muted me-1">Theme:</span>
    <button type="button" class="btn btn-sm btn-primary filter-chip active" data-filter="all">All</button>
    <?php foreach ($palettes as $pid => $p): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary filter-chip" data-filter="<?= e($pid) ?>">
        <span class="d-inline-block rounded-circle align-middle me-1" style="width:12px;height:12px;background:<?= e($p['c1']) ?>;border:1px solid rgba(0,0,0,.15)"></span><?= e($p['name']) ?>
      </button>
    <?php endforeach; ?>
  </div>
</div></div>

<?php if ($current): ?>
<div class="alert alert-success py-2 small" id="currentDefaultBar">
  <i class="bi bi-star-fill"></i> Your default design is <strong id="currentDefaultLabel"><?= e($current) ?></strong>.
</div>
<?php endif; ?>

<!-- Gallery grid -->
<div class="row g-3" id="galleryGrid">
  <?php foreach ($designs as $d):
    $thumbUrl = $renderBase . '?slug=' . urlencode($slug) . '&design=' . urlencode($d['id']) . '&thumb=1';
    $isCur = ($d['id'] === $current);
  ?>
  <div class="col-6 col-md-4 col-lg-3 col-xl-2 design-cell" data-palette="<?= e($d['palette']) ?>">
    <div class="card h-100 design-card <?= $isCur ? 'border-primary' : '' ?>" role="button"
         data-design="<?= e($d['id']) ?>"
         data-label="<?= e($d['layout_name'] . ' · ' . $d['palette_name']) ?>">
      <div class="ratio bg-light" style="--bs-aspect-ratio:177%">
        <img loading="lazy" src="<?= e($thumbUrl) ?>" alt="<?= e($d['id']) ?>" class="w-100 h-100" style="object-fit:cover;border-radius:.35rem">
      </div>
      <div class="card-body p-2 text-center">
        <div class="small fw-semibold text-truncate"><?= e($d['palette_name']) ?></div>
        <div class="text-muted" style="font-size:.72rem"><?= e($d['layout_name']) ?></div>
        <?php if ($isCur): ?><span class="badge bg-primary mt-1 default-badge"><i class="bi bi-star-fill"></i> Default</span><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Preview modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="pvTitle">Standee Preview</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6 text-center">
            <img id="pvImage" src="" alt="Standee preview" class="img-fluid border rounded shadow-sm" style="max-height:70vh">
          </div>
          <div class="col-md-6">
            <p class="text-muted small">This standee shows <strong><?= e($tenant['restaurant_name']) ?></strong>'s logo, name, a large QR that opens your menu, and your contact — ready to print or share.</p>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Print size</label>
              <select id="pvSize" class="form-select form-select-sm">
                <option value="tent">Table Tent (portrait)</option>
                <option value="a4">A4 Poster</option>
              </select>
            </div>
            <div class="d-grid gap-2">
              <a id="pvDownloadPng" href="#" target="_blank" class="btn btn-primary"><i class="bi bi-download"></i> Download PNG</a>
              <a id="pvDownloadPdf" href="#" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
              <button id="pvWhatsapp" type="button" class="btn btn-success"><i class="bi bi-whatsapp"></i> Send to WhatsApp</button>
              <button id="pvDefault" type="button" class="btn btn-outline-secondary"><i class="bi bi-star"></i> Save as my default</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$renderBaseJs = json_encode($renderBase);
$slugJs       = json_encode($slug);
$apiJs        = json_encode(BASE_URL . '/api/standee.php');
$ownNumberJs  = json_encode((string)($tenant['whatsapp_no'] ?: $tenant['mobile']));
$pageScript = <<<HTML
<script>
(function(){
  const RENDER = $renderBaseJs, SLUG = $slugJs, API = $apiJs, OWN = $ownNumberJs;
  let activeDesign = null;

  // Theme filter chips.
  document.querySelectorAll('.filter-chip').forEach(function(chip){
    chip.addEventListener('click', function(){
      document.querySelectorAll('.filter-chip').forEach(function(c){ c.classList.remove('active','btn-primary'); c.classList.add('btn-outline-secondary'); });
      chip.classList.add('active','btn-primary'); chip.classList.remove('btn-outline-secondary');
      const f = chip.getAttribute('data-filter');
      document.querySelectorAll('.design-cell').forEach(function(cell){
        cell.style.display = (f === 'all' || cell.getAttribute('data-palette') === f) ? '' : 'none';
      });
    });
  });

  function buildUrl(size, format, download){
    let u = RENDER + '?slug=' + encodeURIComponent(SLUG) + '&design=' + encodeURIComponent(activeDesign) + '&size=' + size + '&format=' + format;
    if (download) u += '&download=1';
    return u;
  }

  function refreshLinks(){
    const size = document.getElementById('pvSize').value;
    document.getElementById('pvImage').src = buildUrl(size, 'png', false);
    document.getElementById('pvDownloadPng').href = buildUrl(size, 'png', true);
    document.getElementById('pvDownloadPdf').href = buildUrl(size, 'pdf', true);
  }

  const modalEl = document.getElementById('previewModal');
  const modal = new bootstrap.Modal(modalEl);

  document.querySelectorAll('.design-card').forEach(function(card){
    card.addEventListener('click', function(){
      activeDesign = card.getAttribute('data-design');
      document.getElementById('pvTitle').textContent = card.getAttribute('data-label') + '  (' + activeDesign + ')';
      document.getElementById('pvSize').value = 'tent';
      refreshLinks();
      modal.show();
    });
  });

  document.getElementById('pvSize').addEventListener('change', refreshLinks);

  // Send to WhatsApp.
  document.getElementById('pvWhatsapp').addEventListener('click', function(){
    Swal.fire({
      title: 'Send standee to WhatsApp',
      input: 'text',
      inputLabel: 'Mobile number',
      inputValue: OWN,
      inputPlaceholder: '10-digit number',
      showCancelButton: true,
      confirmButtonText: 'Send',
      confirmButtonColor: 'var(--primary)'
    }).then(function(res){
      if (!res.isConfirmed) return;
      AK.post(API + '?action=send_whatsapp', { number: res.value || '', design: activeDesign })
        .then(function(r){ AK.handle(r); })
        .catch(function(){ AK.toast('error','Network error'); });
    });
  });

  // Save default.
  document.getElementById('pvDefault').addEventListener('click', function(){
    AK.post(API + '?action=save_default', { design: activeDesign }).then(function(r){
      AK.handle(r, function(){
        // Update badges in the grid.
        document.querySelectorAll('.design-card').forEach(function(c){
          c.classList.remove('border-primary');
          const b = c.querySelector('.default-badge'); if (b) b.remove();
        });
        const card = document.querySelector('.design-card[data-design="' + activeDesign + '"]');
        if (card) {
          card.classList.add('border-primary');
          const body = card.querySelector('.card-body');
          if (body && !body.querySelector('.default-badge')) {
            const span = document.createElement('span');
            span.className = 'badge bg-primary mt-1 default-badge';
            span.innerHTML = '<i class="bi bi-star-fill"></i> Default';
            body.appendChild(span);
          }
        }
      });
    });
  });
})();
</script>
HTML;
require __DIR__ . '/_footer.php';
