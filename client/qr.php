<?php
/** QR & Standee: public menu QR, standee downloads, per-table QR codes. */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();
$tid = currentTenantId();

$pageTitle = 'QR & Standee';
$activeNav = 'qr';

$slug      = $tenant['slug'];
$publicUrl = publicMenuUrl($slug);

// Generate the main QR if it does not exist yet.
$qrRel  = 'uploads/qr/' . $slug . '.png';
$qrPath = UPLOAD_PATH . '/qr/' . $slug . '.png';
if (!file_exists($qrPath)) {
    $gen = generateQr($publicUrl, $slug);
    if ($gen) { $qrRel = $gen; }
}
$qrExists = file_exists($qrPath);

$tables = db_all('SELECT * FROM ' . tbl('tables') . ' WHERE tenant_id = :t ORDER BY id', [':t' => $tid]);

require __DIR__ . '/_header.php';
?>
<div class="row g-3">
  <!-- Main QR -->
  <div class="col-lg-5">
    <div class="card"><div class="card-body text-center">
      <h6 class="fw-semibold mb-3"><i class="bi bi-qr-code"></i> Your Menu QR</h6>
      <?php if ($qrExists): ?>
        <img src="<?= e(BASE_URL . '/' . $qrRel) ?>?v=<?= @filemtime($qrPath) ?>" class="img-fluid border rounded p-2 bg-white" style="max-width:260px">
      <?php else: ?>
        <div class="empty-state"><i class="bi bi-qr-code"></i><p>QR could not be generated. Try refreshing.</p></div>
      <?php endif; ?>
      <div class="input-group mt-3">
        <input type="text" class="form-control" id="publicUrl" value="<?= e($publicUrl) ?>" readonly>
        <button class="btn btn-outline-secondary" onclick="copyUrl()"><i class="bi bi-clipboard"></i></button>
      </div>
      <?php if ($qrExists): ?>
      <a href="<?= e(BASE_URL . '/' . $qrRel) ?>" download="menu-qr-<?= e($slug) ?>.png" class="btn btn-primary mt-3"><i class="bi bi-download"></i> Download QR PNG</a>
      <?php endif; ?>
    </div></div>
  </div>

  <!-- Standee download -->
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-file-earmark-pdf"></i> Table Standee</h6>
      <p class="text-muted small">Download a print-ready standee with your QR, restaurant name and branding.</p>
      <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label">Size</label>
          <select id="standeeSize" class="form-select" onchange="updateStandeeLink()">
            <option value="A4">A4 (Poster)</option>
            <option value="A5">A5 (Half)</option>
            <option value="tent">Table Tent</option>
            <option value="sticker">Sticker</option>
          </select>
        </div>
        <div class="col-md-6">
          <a id="standeeLink" href="<?= e(BASE_URL) ?>/standee/generate.php?slug=<?= e($slug) ?>&size=A4" target="_blank" class="btn btn-primary w-100"><i class="bi bi-download"></i> Download Standee PDF</a>
        </div>
      </div>
      <small class="text-muted d-block mt-2">Opens in a new tab as a printable PDF.</small>
    </div></div>
  </div>

  <!-- Table-wise QR -->
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h6 class="fw-semibold mb-3"><i class="bi bi-grid-3x3-gap"></i> Table-wise QR Codes</h6>
      <?php if (!$tables): ?>
        <div class="empty-state"><i class="bi bi-grid"></i>
          <p>No tables yet. Add tables in the <a href="<?= e(BASE_URL) ?>/client/tables.php">Tables</a> page to generate per-table QR codes.</p></div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($tables as $tb):
            $tUrl = BASE_URL . '/r/?table=' . urlencode($tb['qr_token']);
            $tQrApi = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($tUrl);
          ?>
          <div class="col-6 col-md-3 col-lg-2 text-center">
            <div class="border rounded p-2 h-100 bg-white">
              <img src="<?= e($tQrApi) ?>" class="img-fluid" alt="QR">
              <div class="fw-semibold small mt-1">Table <?= e($tb['table_no']) ?></div>
              <?php if (!empty($tb['section'])): ?><div class="text-muted small"><?= e($tb['section']) ?></div><?php endif; ?>
              <a href="<?= e(BASE_URL) ?>/standee/generate.php?table=<?= e($tb['qr_token']) ?>&size=sticker" target="_blank" class="btn btn-sm btn-outline-primary mt-1 w-100"><i class="bi bi-download"></i> Standee</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<?php
$pageScript = <<<'HTML'
<script>
function copyUrl(){
  const el = document.getElementById('publicUrl');
  el.select(); document.execCommand('copy');
  AK.toast('success','Link copied!');
}
function updateStandeeLink(){
  const size = document.getElementById('standeeSize').value;
  const link = document.getElementById('standeeLink');
  const u = new URL(link.href);
  u.searchParams.set('size', size);
  link.href = u.toString();
}
</script>
HTML;
require __DIR__ . '/_footer.php';
