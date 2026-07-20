<?php
/**
 * Landing page + entry router.
 * If not installed, config.php auto-redirects to /install/. Otherwise show a
 * simple marketing landing with entry points to each panel.
 */
require_once __DIR__ . '/config/config.php';
checkMaintenance();
$siteName = getSetting('site_name', 'AK Menu System');
$tagline  = getSetting('tagline', 'Digital Restaurant Menu & Ordering');
$primary  = getSetting('primary_color', '#e63946');
$secondary= getSetting('secondary_color', '#1d3557');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteName) ?> — <?= e($tagline) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Poppins',system-ui,sans-serif;}
.hero{background:linear-gradient(135deg,<?= e($primary) ?>,<?= e($secondary) ?>);color:#fff;padding:90px 0 100px;}
.feature-ic{width:56px;height:56px;border-radius:14px;background:<?= e($primary) ?>1a;color:<?= e($primary) ?>;display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.btn-p{background:<?= e($primary) ?>;border-color:<?= e($primary) ?>;color:#fff;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#"><i class="bi bi-grid-1x2-fill" style="color:<?= e($primary) ?>"></i> <?= e($siteName) ?></a>
    <div class="d-flex gap-2">
      <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-outline-secondary btn-sm">Login</a>
      <a href="<?= e(BASE_URL) ?>/signup.php" class="btn btn-p btn-sm">Start Free</a>
    </div>
  </div>
</nav>

<header class="hero text-center">
  <div class="container">
    <h1 class="fw-bold display-5"><?= e($tagline) ?></h1>
    <p class="lead opacity-75 mt-3">QR menus, AI menu import, direct & waiter ordering, KOT screen, WhatsApp automation — built for Indian restaurants.</p>
    <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
      <a href="<?= e(BASE_URL) ?>/signup.php" class="btn btn-light btn-lg fw-semibold">🎉 Start 7-Day Free Trial</a>
      <a href="<?= e(BASE_URL) ?>/client/login.php" class="btn btn-outline-light btn-lg">Restaurant Login</a>
    </div>
  </div>
</header>

<section class="py-5"><div class="container">
  <div class="row g-4">
    <?php
    $features = [
      ['bi-magic', 'AI Menu Import', 'Upload menu photos — Google Gemini extracts items, prices & Gujarati names automatically.'],
      ['bi-qr-code', 'QR & Standee', 'Auto QR codes and print-ready standee PDFs with your logo.'],
      ['bi-phone', 'Digital Menu', 'Beautiful mobile-first menu, English + ગુજરાતી, installable PWA.'],
      ['bi-receipt-cutoff', 'Ordering & KOT', 'Direct customer or waiter ordering with a live kitchen display.'],
      ['bi-whatsapp', 'WhatsApp Automation', 'Welcome, order, and reminder messages sent automatically.'],
      ['bi-graph-up', 'Analytics', 'Sales reports, feedback routing, and multi-outlet plans.'],
    ];
    foreach ($features as $f): ?>
      <div class="col-md-4"><div class="p-4 h-100 border rounded-4">
        <div class="feature-ic mb-3"><i class="bi <?= $f[0] ?>"></i></div>
        <h5><?= e($f[1]) ?></h5><p class="text-muted small mb-0"><?= e($f[2]) ?></p>
      </div></div>
    <?php endforeach; ?>
  </div>
</div></section>

<footer class="text-center text-muted py-4 border-top">
  <div class="container">
    <?= e(getSetting('footer_text', '© ' . $siteName)) ?> · <?= e(POWERED_BY) ?>
  </div>
</footer>
</body>
</html>
