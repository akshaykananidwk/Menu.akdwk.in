<?php
/** Design & Branding: template gallery, live preview, colors/fonts/toggles/social. */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid    = currentTenantId();
$tenant = currentTenant();

$pageTitle = 'Design & Branding';
$activeNav = 'design';

$templates = db_all('SELECT * FROM ' . tbl('templates') . ' WHERE status = 1 ORDER BY is_premium, id');
$canPremium = planHasFeature($tid, 'remove_branding');
$previewUrl = BASE_URL . '/r/?slug=' . urlencode($tenant['slug']);
$fonts = ['Poppins', 'Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Playfair Display', 'Merriweather'];

require __DIR__ . '/_header.php';
?>
<div class="row g-3">
  <!-- Template gallery + customize -->
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap"></i> Choose a Template</h6>
      <div class="row g-3" id="templateGallery">
        <?php foreach ($templates as $tpl):
          $isPrem = (int)$tpl['is_premium'] === 1;
          $locked = $isPrem && !$canPremium;
          $selected = (int)$tenant['template_id'] === (int)$tpl['id'];
          $prev = $tpl['preview_image'] ? BASE_URL . '/' . $tpl['preview_image'] : '';
        ?>
        <div class="col-6 col-md-4">
          <div class="card h-100 border <?= $selected ? 'border-primary border-2' : '' ?>" style="cursor:<?= $locked ? 'not-allowed' : 'pointer' ?>"
               data-id="<?= (int)$tpl['id'] ?>" data-locked="<?= $locked ? 1 : 0 ?>" onclick="selectTemplate(this)">
            <div class="ratio ratio-4x3 bg-light rounded-top d-flex align-items-center justify-content-center overflow-hidden">
              <?php if ($prev): ?><img src="<?= e($prev) ?>" style="object-fit:cover;width:100%;height:100%">
              <?php else: ?><i class="bi bi-image text-muted fs-1"></i><?php endif; ?>
            </div>
            <div class="card-body p-2 text-center">
              <div class="fw-semibold small"><?= e($tpl['name']) ?></div>
              <?php if ($isPrem): ?><span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Premium</span><?php endif; ?>
              <?php if ($locked): ?><span class="badge bg-secondary"><i class="bi bi-lock"></i> Locked</span><?php endif; ?>
              <?php if ($selected): ?><span class="badge bg-primary">Active</span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div></div>

    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-sliders"></i> Customize</h6>
      <form id="designForm" onsubmit="saveDesign(event)">
        <input type="hidden" name="template_id" id="d_template" value="<?= (int)$tenant['template_id'] ?>">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Primary Color</label><input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?= e($tenant['primary_color'] ?: '#e63946') ?>"></div>
          <div class="col-md-4"><label class="form-label">Secondary Color</label><input type="color" name="secondary_color" class="form-control form-control-color w-100" value="<?= e($tenant['secondary_color'] ?: '#1d3557') ?>"></div>
          <div class="col-md-4"><label class="form-label">Accent Color</label><input type="color" name="accent_color" class="form-control form-control-color w-100" value="<?= e($tenant['accent_color'] ?: '#f1a208') ?>"></div>
          <div class="col-md-6"><label class="form-label">Font Family</label>
            <select name="font_family" class="form-select">
              <?php foreach ($fonts as $f): ?><option value="<?= e($f) ?>" <?= ($tenant['font_family'] === $f) ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-3"><label class="form-label">Banner Image</label><input type="file" name="banner_image" class="form-control" accept="image/*"></div>
          <div class="col-md-3"><label class="form-label">Cover Image</label><input type="file" name="cover_image" class="form-control" accept="image/*"></div>

          <div class="col-12"><hr class="my-1"><span class="small text-muted">Display Options</span></div>
          <?php
          $toggles = ['show_prices'=>'Show Prices','show_images'=>'Show Images','show_veg_marker'=>'Veg/Non-veg Marker','show_descriptions'=>'Show Descriptions'];
          foreach ($toggles as $k=>$lbl): ?>
          <div class="col-6 col-md-3">
            <input type="hidden" name="has_<?= $k ?>" value="1">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="<?= $k ?>" value="1" id="tg_<?= $k ?>" <?= (int)$tenant[$k] ? 'checked' : '' ?>>
              <label class="form-check-label small" for="tg_<?= $k ?>"><?= e($lbl) ?></label>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="col-12"><hr class="my-1"><span class="small text-muted">Contact, Social & Hours</span></div>
          <div class="col-md-6"><label class="form-label">Facebook URL</label><input name="facebook_url" class="form-control" value="<?= e($tenant['facebook_url']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Instagram URL</label><input name="instagram_url" class="form-control" value="<?= e($tenant['instagram_url']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Google Review URL</label><input name="google_review_url" class="form-control" value="<?= e($tenant['google_review_url']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Google Maps URL</label><input name="maps_url" class="form-control" value="<?= e($tenant['maps_url']) ?>"></div>
          <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($tenant['address']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Opening Time</label><input type="time" name="opening_time" class="form-control" value="<?= e($tenant['opening_time']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Closing Time</label><input type="time" name="closing_time" class="form-control" value="<?= e($tenant['closing_time']) ?>"></div>
        </div>
        <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Save Design</button>
      </form>
    </div></div>
  </div>

  <!-- Live preview -->
  <div class="col-lg-4">
    <div class="card sticky-top" style="top:80px"><div class="card-body text-center">
      <h6 class="fw-semibold mb-3"><i class="bi bi-phone"></i> Live Preview</h6>
      <div style="max-width:290px;margin:auto;border:10px solid #222;border-radius:32px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.2)">
        <iframe id="previewFrame" src="<?= e($previewUrl) ?>" style="width:100%;height:520px;border:0"></iframe>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary mt-3" onclick="document.getElementById('previewFrame').src=document.getElementById('previewFrame').src"><i class="bi bi-arrow-clockwise"></i> Refresh Preview</button>
      <a href="<?= e(publicMenuUrl($tenant['slug'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-3"><i class="bi bi-box-arrow-up-right"></i> Open</a>
    </div></div>
  </div>
</div>

<?php
$pageScript = <<<'HTML'
<script>
const API = document.querySelector('meta[name="base-url"]').content + '/api/design.php';

function selectTemplate(el){
  if(el.dataset.locked === '1'){ AK.toast('error','This premium template requires a higher plan.'); return; }
  document.querySelectorAll('#templateGallery .card').forEach(c => c.classList.remove('border-primary','border-2'));
  el.classList.add('border-primary','border-2');
  document.getElementById('d_template').value = el.dataset.id;
}

function saveDesign(ev){
  ev.preventDefault();
  const fd = new FormData(document.getElementById('designForm'));
  AK.post(API + '?action=save_design', fd).then(r => AK.handle(r, () => {
    // Refresh preview to reflect new design.
    const f = document.getElementById('previewFrame'); f.src = f.src;
  }));
}
</script>
HTML;
require __DIR__ . '/_footer.php';
