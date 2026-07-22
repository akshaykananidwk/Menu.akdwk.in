<?php
/**
 * Programmatic SEO landing page:
 *   /digital-menu/{city}   — city page (from seo_cities)
 *   /digital-menu/{state}  — state page listing its cities
 * Unique content, live restaurant listings, LocalBusiness + Service + FAQ +
 * Breadcrumb JSON-LD. Server-rendered.
 */
require_once __DIR__ . '/config/config.php';
checkMaintenance();
seedSeoCitiesIfEmpty();

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$site = getSetting('site_name', 'AK Menu System');
$primary = getSetting('primary_color', '#e63946');
$secondary = getSetting('secondary_color', '#1d3557');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$city = getSeoCity($slug);

// Is it a state slug?
$isState = false; $stateName = '';
if (!$city) {
    try {
        $stateName = (string)db_val('SELECT state FROM ' . tbl('seo_cities') . ' WHERE LOWER(REPLACE(state," ","-")) = :s AND is_active=1 LIMIT 1', [':s' => $slug]);
    } catch (Throwable $e2) { $stateName = ''; }
    if ($stateName !== '') { $isState = true; }
}

if (!$city && !$isState) {
    require __DIR__ . '/404.php';
    exit;
}

$canonicalPath = 'digital-menu/' . $slug;

// -------------------------------------------------------------- STATE PAGE ----
if ($isState) {
    try { $cities = db_all('SELECT * FROM ' . tbl('seo_cities') . ' WHERE state = :st AND is_active=1 ORDER BY priority DESC', [':st' => $stateName]); }
    catch (Throwable $e2) { $cities = []; }
    $meta = [
        'title'       => "Digital Menu &amp; QR Code Menu Software in $stateName | $site",
        'description' => "Get a QR code menu for your restaurant anywhere in $stateName. AI menu import, online ordering, WhatsApp alerts. Free 7-day trial. Serving " . count($cities) . " cities across $stateName.",
        'canonical'   => absUrl($canonicalPath),
        'og_type'     => 'website',
    ];
    $h1 = "Digital Menu &amp; QR Code Ordering in $stateName";
    render_landing_head($meta, $primary, $secondary, $site, [
        jsonldBreadcrumb([[ 'Home', absUrl('') ], [ $stateName, absUrl($canonicalPath) ]]),
    ]);
    ?>
    <header class="lp-hero"><div class="wrap">
        <div class="eyebrow">📍 <?= $e($stateName) ?>, India</div>
        <h1><?= $h1 ?></h1>
        <p class="lead">Turn your <?= $e($stateName) ?> restaurant's menu into a smart QR code menu with online ordering — in minutes. Free 7-day trial.</p>
        <a href="<?= $e(absUrl('signup.php')) ?>" class="btn-cta">Start Free Trial</a>
    </div></header>
    <main class="wrap lp-main">
        <p>Restaurants across <strong><?= $e($stateName) ?></strong> are switching to digital QR menus with <?= $e($site) ?>. Pick your city below to learn more, or start your free trial now.</p>
        <h2>Cities we serve in <?= $e($stateName) ?></h2>
        <div class="city-grid">
            <?php foreach ($cities as $c): ?>
                <a class="city-chip" href="<?= $e(absUrl('digital-menu/' . $c['slug'])) ?>"><?= $e($c['city_name']) ?></a>
            <?php endforeach; ?>
        </div>
    </main>
    <?php
    render_landing_foot($site);
    exit;
}

// --------------------------------------------------------------- CITY PAGE ----
$restaurants = cityRestaurants($city['city_name']);
$liveCount   = count($restaurants);
try { $liveCount = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . " WHERE status='active' AND allow_indexing=1 AND city = :c", [':c' => $city['city_name']]); }
catch (Throwable $e2) {}
$nearby = nearbyCities($city);
$faq    = cityFaq($city);
$content = cityLandingHtml($city, $liveCount);
$cityName = $city['city_name'];
$stateSlug = strtolower(str_replace(' ', '-', (string)$city['state']));

$meta = [
    'title'       => $city['meta_title'] ?: "Digital Menu &amp; QR Code Ordering in $cityName | $site",
    'description' => $city['meta_description'] ?: "Create a QR code menu for your $cityName restaurant in minutes. AI import, online ordering, WhatsApp. Free 7-day trial.",
    'canonical'   => absUrl($canonicalPath),
    'og_type'     => 'website',
];
$h1 = $city['h1'] ?: "Digital Menu &amp; QR Code Ordering System in $cityName";

// JSON-LD: Service (areaServed city) + FAQPage + Breadcrumb
$ld = [
    [
        '@context' => 'https://schema.org', '@type' => 'Service',
        'name' => "Digital QR Menu Software in $cityName",
        'serviceType' => 'Digital restaurant menu & ordering',
        'provider' => ['@type' => 'Organization', 'name' => $site, 'url' => absUrl('')],
        'areaServed' => ['@type' => 'City', 'name' => $cityName,
            'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => $city['state']]],
        'url' => absUrl($canonicalPath),
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR', 'description' => '7-day free trial'],
    ],
    jsonldFaq($faq),
    jsonldBreadcrumb([['Home', absUrl('')], [$city['state'], absUrl('digital-menu/' . $stateSlug)], [$cityName, absUrl($canonicalPath)]]),
];

render_landing_head($meta, $primary, $secondary, $site, $ld);
?>
<header class="lp-hero"><div class="wrap">
    <div class="eyebrow">📍 <?= $e($cityName) ?>, <?= $e($city['state']) ?></div>
    <h1><?= $h1 ?></h1>
    <p class="lead">Give your <?= $e($cityName) ?> restaurant a smart QR code menu with online ordering, WhatsApp alerts and a live kitchen screen — free for 7 days.</p>
    <div class="cta-row">
        <a href="<?= $e(absUrl('signup.php')) ?>" class="btn-cta">Start Free Trial</a>
        <a href="<?= $e(absUrl('client/login.php')) ?>" class="btn-ghost">Login</a>
    </div>
    <?php if ($liveCount > 0): ?><div class="hero-note"><i class="bi bi-shop"></i> <?= $liveCount ?>+ restaurants in <?= $e($cityName) ?> already onboard</div><?php endif; ?>
</div></header>

<main class="wrap lp-main">
    <article class="lp-content"><?= $content ?></article>

    <?php if ($restaurants): ?>
    <h2>Restaurants using <?= $e($site) ?> in <?= $e($cityName) ?></h2>
    <div class="rest-grid">
        <?php foreach ($restaurants as $r): ?>
            <a class="rest-card" href="<?= $e(absUrl('r/' . $r['slug'])) ?>">
                <?php if ($r['logo']): ?><img src="<?= $e(mediaUrl($r['logo'])) ?>" alt="<?= $e($r['restaurant_name']) ?> menu in <?= $e($cityName) ?>" loading="lazy" width="46" height="46"><?php else: ?><span class="rlogo">🍽️</span><?php endif; ?>
                <span><?= $e($r['restaurant_name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2>Frequently asked questions</h2>
    <div class="faq">
        <?php foreach ($faq as $f): ?>
            <details><summary><?= $e($f[0]) ?></summary><p><?= $e($f[1]) ?></p></details>
        <?php endforeach; ?>
    </div>

    <?php if ($nearby): ?>
    <h2>Nearby cities</h2>
    <div class="city-grid">
        <?php foreach ($nearby as $n): ?>
            <a class="city-chip" href="<?= $e(absUrl('digital-menu/' . $n['slug'])) ?>"><?= $e($n['city_name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="lp-cta-band">
        <h2>Ready to digitise your <?= $e($cityName) ?> restaurant?</h2>
        <a href="<?= $e(absUrl('signup.php')) ?>" class="btn-cta">Start your free 7-day trial</a>
    </div>
</main>
<?php
render_landing_foot($site);
