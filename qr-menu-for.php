<?php
/** /qr-menu-for/{type} — business-type landing page (café, dhaba, bakery, …). */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/seo.php';
checkMaintenance();

$type = strtolower(trim((string)($_GET['type'] ?? '')));
$types = seoCuisineTypes();
if (!isset($types[$type])) { require __DIR__ . '/404.php'; exit; }

[$label, $plural, $emoji, $pitch] = $types[$type];
$site = getSetting('site_name', 'AK Menu System');
$primary = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$canonicalPath = 'qr-menu-for/' . $type;

$faq = [
    ["How does a QR menu work for a $label?",
     "Customers scan the QR code on your table or counter and your $label menu opens instantly in their browser — no app needed. They can browse items, see prices and place an order."],
    ["Can I update my $label menu anytime?",
     "Yes. Change items, prices or photos from your dashboard and the live menu updates immediately for every customer."],
    ["Is there a free trial for my $label?",
     "Yes — start free for 7 days with no card required, then choose an affordable plan in ₹."],
];

$meta = [
    'title'       => ucfirst($label) . " QR Menu &amp; Ordering Software | $site",
    'description' => seoTrim("$pitch Free 7-day trial. Built for Indian $plural.", 160),
    'canonical'   => absUrl($canonicalPath),
];
$ld = [
    ['@context' => 'https://schema.org', '@type' => 'Service',
     'name' => "QR Menu Software for {$plural}", 'serviceType' => "Digital menu for {$plural}",
     'provider' => ['@type' => 'Organization', 'name' => $site, 'url' => absUrl('')],
     'areaServed' => ['@type' => 'Country', 'name' => 'India'],
     'url' => absUrl($canonicalPath),
     'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR', 'description' => '7-day free trial']],
    jsonldFaq($faq),
    jsonldBreadcrumb([['Home', absUrl('')], ["QR menu for $label", absUrl($canonicalPath)]]),
];

render_landing_head($meta, $primary, $secondary, $site, $ld);
?>
<header class="lp-hero"><div class="wrap">
    <div class="eyebrow"><?= $emoji ?> For <?= $e($plural) ?></div>
    <h1>QR Code Menu &amp; Ordering for <?= $e($plural) ?></h1>
    <p class="lead"><?= $e($pitch) ?></p>
    <a href="<?= $e(absUrl('signup.php')) ?>" class="btn-cta">Start Free Trial</a>
</div></header>
<main class="wrap lp-main">
    <article class="lp-content">
        <p>Running a <?= $e($label) ?> in India? <?= $e($site) ?> gives you a beautiful, scannable digital menu with online ordering built specifically for <?= $e($plural) ?>. No app for your customers, no printing costs for you, and instant updates whenever your menu changes.</p>
        <h2>Why <?= $e($plural) ?> love <?= $e($site) ?></h2>
        <ul>
            <li><strong>AI menu import</strong> — build your <?= $e($label) ?> menu from a photo in seconds.</li>
            <li><strong>QR standees</strong> — print-ready designs for tables and counters.</li>
            <li><strong>Online &amp; WhatsApp ordering</strong> — take orders without commission.</li>
            <li><strong>Live kitchen screen (KOT)</strong> and payment tracking.</li>
            <li><strong>English + ગુજરાતી</strong> menus with veg/non-veg markers.</li>
        </ul>
        <h2>Simple pricing</h2>
        <p>Start free for 7 days, then pick an affordable plan in ₹. Perfect for single outlets and growing chains alike.</p>
    </article>
    <h2>FAQ</h2>
    <div class="faq">
        <?php foreach ($faq as $f): ?><details><summary><?= $e($f[0]) ?></summary><p><?= $e($f[1]) ?></p></details><?php endforeach; ?>
    </div>
    <div class="lp-cta-band">
        <h2>Give your <?= $e($label) ?> a digital menu today</h2>
        <a href="<?= $e(absUrl('signup.php')) ?>" class="btn-cta">Start your free trial</a>
    </div>
</main>
<?php
render_landing_foot($site);
