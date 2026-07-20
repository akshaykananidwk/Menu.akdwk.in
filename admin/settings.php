<?php
/**
 * Admin › White-label / Global Settings.
 * Tabbed form (Bootstrap nav-tabs). All values live in the `settings` table
 * and are read via getSetting() / written via setSetting(). Self-POST handler.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// ---- POST handler (save all settings) ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (!isSuperAdmin()) { http_response_code(403); die('Forbidden'); }

    // Scalar text/select/color/textarea keys that map 1:1 to POST fields.
    $textKeys = [
        // Branding
        'site_name', 'tagline', 'footer_text', 'powered_by',
        // Appearance
        'primary_color', 'secondary_color', 'accent_color', 'theme_mode', 'font_family',
        // Contact
        'contact_email', 'support_whatsapp',
        // SMTP
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from_name',
        // AI
        'gemini_api_key', 'gemini_model', 'gemini_monthly_limit', 'ai_cost_per_call',
        // Payments
        'razorpay_key_id', 'razorpay_secret',
        // Localization
        'currency', 'timezone', 'date_format', 'default_language',
        // Legal
        'terms_content', 'privacy_content',
    ];
    foreach ($textKeys as $k) {
        if (array_key_exists($k, $_POST)) {
            setSetting($k, is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]);
        }
    }

    // File uploads: only replace when a new file is actually provided.
    if (!empty($_FILES['logo']['name'])) {
        $p = uploadImage($_FILES['logo'], 'logos');
        if ($p) { setSetting('logo', $p); }
    }
    if (!empty($_FILES['favicon']['name'])) {
        $p = uploadImage($_FILES['favicon'], 'logos');
        if ($p) { setSetting('favicon', $p); }
    }

    logActivity('super_admin', $_SESSION['admin_id'] ?? null, 'Updated global settings');
    redirect(BASE_URL . '/admin/settings.php?saved=1');
}

// ---- Load current values ----------------------------------------------------
$saved = isset($_GET['saved']);
$v = function (string $k, $d = '') { return getSetting($k, $d); };
$logo    = getSetting('logo', '');
$favicon = getSetting('favicon', '');

$fonts = ['Poppins', 'Inter', 'Roboto', 'Montserrat', 'Lato', 'Open Sans', 'Nunito', 'Playfair Display'];
$timezones = ['Asia/Kolkata', 'Asia/Dubai', 'UTC', 'America/New_York', 'Europe/London', 'Asia/Singapore'];

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/_header.php';
?>
<?php if ($saved): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> Settings saved successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
  <?= csrfField() ?>
  <div class="card-body">
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-branding" type="button">Branding</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-appearance" type="button">Appearance</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-contact" type="button">Contact</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-smtp" type="button">SMTP</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ai" type="button">AI</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button">Payments</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-localization" type="button">Localization</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-legal" type="button">Legal</button></li>
    </ul>

    <div class="tab-content">
      <!-- Branding -->
      <div class="tab-pane fade show active" id="tab-branding">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Site Name</label>
            <input name="site_name" class="form-control" value="<?= e($v('site_name')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tagline</label>
            <input name="tagline" class="form-control" value="<?= e($v('tagline')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Logo</label>
            <?php if ($logo): ?><div class="mb-2"><img src="<?= e(BASE_URL . '/' . $logo) ?>" alt="logo" style="max-height:48px"></div><?php endif; ?>
            <input type="file" name="logo" accept="image/*" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Favicon</label>
            <?php if ($favicon): ?><div class="mb-2"><img src="<?= e(BASE_URL . '/' . $favicon) ?>" alt="favicon" style="max-height:32px"></div><?php endif; ?>
            <input type="file" name="favicon" accept="image/*" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Footer Text</label>
            <input name="footer_text" class="form-control" value="<?= e($v('footer_text')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Powered By</label>
            <input name="powered_by" class="form-control" value="<?= e($v('powered_by')) ?>">
          </div>
        </div>
      </div>

      <!-- Appearance -->
      <div class="tab-pane fade" id="tab-appearance">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Primary Color</label>
            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= e($v('primary_color', '#e63946')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Secondary Color</label>
            <input type="color" name="secondary_color" class="form-control form-control-color" value="<?= e($v('secondary_color', '#1d3557')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Accent Color</label>
            <input type="color" name="accent_color" class="form-control form-control-color" value="<?= e($v('accent_color', '#f1a208')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Theme Mode</label>
            <select name="theme_mode" class="form-select">
              <?php foreach (['light', 'dark', 'auto'] as $tm): ?>
                <option value="<?= e($tm) ?>" <?= $v('theme_mode', 'light') === $tm ? 'selected' : '' ?>><?= ucfirst($tm) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Font Family</label>
            <select name="font_family" class="form-select">
              <?php foreach ($fonts as $f): ?>
                <option value="<?= e($f) ?>" <?= $v('font_family', 'Poppins') === $f ? 'selected' : '' ?>><?= e($f) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Contact -->
      <div class="tab-pane fade" id="tab-contact">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Contact Email</label>
            <input type="email" name="contact_email" class="form-control" value="<?= e($v('contact_email')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Support WhatsApp</label>
            <input name="support_whatsapp" class="form-control" value="<?= e($v('support_whatsapp')) ?>">
          </div>
        </div>
      </div>

      <!-- SMTP -->
      <div class="tab-pane fade" id="tab-smtp">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">SMTP Host</label>
            <input name="smtp_host" class="form-control" value="<?= e($v('smtp_host')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">SMTP Port</label>
            <input name="smtp_port" class="form-control" value="<?= e($v('smtp_port', '587')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">SMTP Username</label>
            <input name="smtp_user" class="form-control" value="<?= e($v('smtp_user')) ?>" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label">SMTP Password</label>
            <input type="password" name="smtp_pass" class="form-control" value="<?= e($v('smtp_pass')) ?>" autocomplete="new-password">
          </div>
          <div class="col-md-6">
            <label class="form-label">From Name</label>
            <input name="smtp_from_name" class="form-control" value="<?= e($v('smtp_from_name')) ?>">
          </div>
        </div>
      </div>

      <!-- AI -->
      <div class="tab-pane fade" id="tab-ai">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Gemini API Key</label>
            <input type="password" name="gemini_api_key" class="form-control" value="<?= e($v('gemini_api_key')) ?>" autocomplete="new-password">
          </div>
          <div class="col-md-6">
            <label class="form-label">Gemini Model</label>
            <input name="gemini_model" class="form-control" value="<?= e($v('gemini_model', 'gemini-2.0-flash')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Monthly Limit (calls)</label>
            <input type="number" name="gemini_monthly_limit" class="form-control" value="<?= e($v('gemini_monthly_limit', '1000')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">AI Cost Per Call</label>
            <input type="number" step="0.01" name="ai_cost_per_call" class="form-control" value="<?= e($v('ai_cost_per_call', '0.50')) ?>">
          </div>
        </div>
      </div>

      <!-- Payments -->
      <div class="tab-pane fade" id="tab-payments">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Razorpay Key ID</label>
            <input name="razorpay_key_id" class="form-control" value="<?= e($v('razorpay_key_id')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Razorpay Secret</label>
            <input type="password" name="razorpay_secret" class="form-control" value="<?= e($v('razorpay_secret')) ?>" autocomplete="new-password">
          </div>
        </div>
      </div>

      <!-- Localization -->
      <div class="tab-pane fade" id="tab-localization">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Currency Symbol</label>
            <input name="currency" class="form-control" value="<?= e($v('currency', '₹')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Timezone</label>
            <select name="timezone" class="form-select">
              <?php foreach ($timezones as $tz): ?>
                <option value="<?= e($tz) ?>" <?= $v('timezone', 'Asia/Kolkata') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Date Format</label>
            <input name="date_format" class="form-control" value="<?= e($v('date_format', 'd-m-Y')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Language</label>
            <select name="default_language" class="form-select">
              <option value="en" <?= $v('default_language', 'en') === 'en' ? 'selected' : '' ?>>English</option>
              <option value="gu" <?= $v('default_language', 'en') === 'gu' ? 'selected' : '' ?>>ગુજરાતી</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Legal -->
      <div class="tab-pane fade" id="tab-legal">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Terms &amp; Conditions</label>
            <textarea name="terms_content" class="form-control" rows="8"><?= e($v('terms_content')) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Privacy Policy</label>
            <textarea name="privacy_content" class="form-control" rows="8"><?= e($v('privacy_content')) ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer bg-white text-end">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
  </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
