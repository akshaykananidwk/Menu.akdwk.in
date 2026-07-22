<?php
/**
 * SEO helper library — meta rendering, structured data (JSON-LD), programmatic
 * city/cuisine content, indexing toggles, search-engine pings and a small file
 * cache for sitemaps. Loaded by config.php after functions.php.
 */
defined('ROOT_PATH') or exit;
require_once CONFIG_PATH . '/seo_data.php';

/** Absolute URL for a site-relative path. */
function absUrl(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/** Current request path without query string, leading slash kept. */
function currentPath(): string {
    $p = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
    if ($basePath && strpos($p, $basePath) === 0) { $p = substr($p, strlen($basePath)); }
    return '/' . ltrim($p, '/');
}

/** Trim a string to a clean length on a word boundary (for meta description). */
function seoTrim(string $s, int $max = 160): string {
    $s = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
    if (mb_strlen($s) <= $max) return $s;
    $cut = mb_substr($s, 0, $max);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp ? mb_substr($cut, 0, $sp) : $cut, " ,.;:") . '…';
}

/**
 * Render a complete, unique set of <head> SEO tags.
 * $d keys: title, description, canonical, robots, image, og_type, lang,
 *          theme_color, hreflang (['en'=>url,'gu'=>url]).
 * Also injects Search Console / Bing verification and GA4 / GTM when configured.
 */
function renderMeta(array $d): string {
    $site    = getSetting('site_name', 'AK Menu System');
    $title   = $d['title'] ?? $site;
    $desc    = seoTrim($d['description'] ?? getSetting('seo_description', $site), 160);
    $canon   = $d['canonical'] ?? absUrl(ltrim(currentPath(), '/'));
    $robots  = $d['robots'] ?? 'index,follow';
    $ogType  = $d['og_type'] ?? 'website';
    $lang    = $d['lang'] ?? 'en';
    $theme   = $d['theme_color'] ?? getSetting('primary_color', '#e63946');
    $img     = $d['image'] ?? (getSetting('logo') ? mediaUrl(getSetting('logo')) : absUrl('assets/img/food/paneer-tikka.jpg'));
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $h  = '<title>' . $e($title) . "</title>\n";
    $h .= '<meta name="description" content="' . $e($desc) . "\">\n";
    $h .= '<meta name="robots" content="' . $e($robots) . "\">\n";
    $h .= '<link rel="canonical" href="' . $e($canon) . "\">\n";
    // hreflang alternates
    if (!empty($d['hreflang']) && is_array($d['hreflang'])) {
        foreach ($d['hreflang'] as $lc => $url) {
            $h .= '<link rel="alternate" hreflang="' . $e($lc) . '" href="' . $e($url) . "\">\n";
        }
        $h .= '<link rel="alternate" hreflang="x-default" href="' . $e($canon) . "\">\n";
    }
    $h .= '<meta name="theme-color" content="' . $e($theme) . "\">\n";
    // Open Graph
    $h .= '<meta property="og:site_name" content="' . $e($site) . "\">\n";
    $h .= '<meta property="og:type" content="' . $e($ogType) . "\">\n";
    $h .= '<meta property="og:title" content="' . $e($title) . "\">\n";
    $h .= '<meta property="og:description" content="' . $e($desc) . "\">\n";
    $h .= '<meta property="og:url" content="' . $e($canon) . "\">\n";
    $h .= '<meta property="og:image" content="' . $e($img) . "\">\n";
    $h .= '<meta property="og:locale" content="' . ($lang === 'gu' ? 'gu_IN' : 'en_IN') . "\">\n";
    // Twitter
    $h .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $h .= '<meta name="twitter:title" content="' . $e($title) . "\">\n";
    $h .= '<meta name="twitter:description" content="' . $e($desc) . "\">\n";
    $h .= '<meta name="twitter:image" content="' . $e($img) . "\">\n";
    // Verification + analytics (site-wide)
    if ($g = getSetting('google_site_verification', '')) { $h .= '<meta name="google-site-verification" content="' . $e($g) . "\">\n"; }
    if ($b = getSetting('bing_site_verification', ''))   { $h .= '<meta name="msvalidate.01" content="' . $e($b) . "\">\n"; }
    if ($ga = getSetting('ga4_id', '')) {
        $h .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $e($ga) . '"></script>' . "\n";
        $h .= "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $e($ga) . "');</script>\n";
    }
    if ($gtm = getSetting('gtm_id', '')) {
        $h .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . $e($gtm) . "');</script>\n";
    }
    return $h;
}

/** Output a JSON-LD <script> block for a schema.org array. */
function jsonldBlock(array $data): string {
    return '<script type="application/ld+json">' .
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

/** BreadcrumbList schema from [[name,url],...]. */
function jsonldBreadcrumb(array $items): array {
    $list = [];
    foreach ($items as $i => $it) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => $it[1]];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/** FAQPage schema from [[q,a],...]. */
function jsonldFaq(array $qa): array {
    $main = [];
    foreach ($qa as $x) {
        $main[] = ['@type' => 'Question', 'name' => $x[0],
                   'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x[1]]];
    }
    return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $main];
}

// -----------------------------------------------------------------------------
// Shared public landing layout (city / cuisine / blog / 404 pages)
// -----------------------------------------------------------------------------

/** Open a branded public page: doctype, head (meta + JSON-LD + CSS), nav. */
function render_landing_head(array $meta, string $primary, string $secondary, string $site, array $jsonld = [], string $lang = 'en'): void {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html>\n<html lang=\"" . $e($lang) . "\">\n<head>\n";
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo renderMeta($meta);
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Gujarati:wght@400;600&display=swap" rel="stylesheet">' . "\n";
    foreach ($jsonld as $ld) { echo jsonldBlock($ld) . "\n"; }
    $logo = getSetting('logo', '');
    ?>
<style>
:root{--p:<?= $e($primary) ?>;--s:<?= $e($secondary) ?>;--ink:#0f172a;--muted:#64748b;--line:#e9edf5;--soft:#f7f8fc}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Noto Sans Gujarati',system-ui,sans-serif;color:var(--ink);line-height:1.6}
a{text-decoration:none}img{max-width:100%}
.wrap{max-width:1000px;margin:0 auto;padding:0 18px}
.lp-nav{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.9);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.lp-nav .in{max-width:1000px;margin:0 auto;padding:12px 18px;display:flex;justify-content:space-between;align-items:center}
.brand{font-weight:800;font-size:1.1rem;color:var(--ink);display:flex;gap:.5rem;align-items:center}
.brand .dot{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,var(--p),var(--s));display:grid;place-items:center;color:#fff}
.btn-cta{display:inline-block;background:var(--p);color:#fff;font-weight:700;padding:.7rem 1.3rem;border-radius:12px}
.btn-ghost{display:inline-block;border:1px solid rgba(255,255,255,.6);color:#fff;padding:.7rem 1.2rem;border-radius:12px;font-weight:600}
.lp-hero{background:linear-gradient(135deg,var(--s),var(--p));color:#fff;padding:60px 0 66px;text-align:center}
.lp-hero .eyebrow{display:inline-block;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);padding:.3rem .8rem;border-radius:999px;font-size:.85rem;font-weight:600;margin-bottom:1rem}
.lp-hero h1{font-size:clamp(1.7rem,4.5vw,2.6rem);font-weight:800;margin:.2rem 0;letter-spacing:-.02em}
.lp-hero .lead{opacity:.94;max-width:640px;margin:.8rem auto 1.4rem}
.cta-row{display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap}
.hero-note{margin-top:1rem;font-size:.9rem;opacity:.9}
.lp-main{padding:44px 18px 20px}
.lp-content h2{font-size:1.4rem;margin:1.6rem 0 .6rem}
.lp-content ul{padding-left:1.1rem}.lp-content li{margin:.3rem 0}
.city-grid{display:flex;flex-wrap:wrap;gap:.5rem;margin:.6rem 0 1.4rem}
.city-chip{background:var(--soft);border:1px solid var(--line);border-radius:999px;padding:.45rem .9rem;color:var(--ink);font-weight:600;font-size:.9rem}
.city-chip:hover{border-color:var(--p);color:var(--p)}
.rest-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.7rem;margin:.6rem 0 1.4rem}
.rest-card{display:flex;align-items:center;gap:.6rem;border:1px solid var(--line);border-radius:12px;padding:.6rem .8rem;color:var(--ink);font-weight:600}
.rest-card:hover{box-shadow:0 8px 20px -12px rgba(0,0,0,.25)}
.rest-card img,.rest-card .rlogo{width:46px;height:46px;border-radius:10px;object-fit:cover;display:grid;place-items:center;background:var(--soft);font-size:1.3rem}
.faq details{border:1px solid var(--line);border-radius:12px;padding:.7rem 1rem;margin-bottom:.6rem}
.faq summary{font-weight:700;cursor:pointer}
.faq p{color:var(--muted);margin:.5rem 0 0}
.lp-cta-band{background:var(--soft);border:1px solid var(--line);border-radius:18px;text-align:center;padding:2rem;margin:2rem 0}
.lp-foot{border-top:1px solid var(--line);padding:2rem 18px;color:var(--muted);font-size:.9rem}
.lp-foot .in{max-width:1000px;margin:0 auto;display:flex;flex-wrap:wrap;gap:1.4rem;justify-content:space-between}
.lp-foot a{color:var(--muted)}.lp-foot a:hover{color:var(--p)}
</style>
</head>
<body>
<nav class="lp-nav"><div class="in">
  <a class="brand" href="<?= $e(absUrl('')) ?>">
    <?php if ($logo): ?><img src="<?= $e(mediaUrl($logo)) ?>" alt="<?= $e($site) ?>" style="height:26px;border-radius:6px"><?php else: ?><span class="dot"><i class="bi bi-grid-1x2-fill"></i></span><?php endif; ?>
    <?= $e($site) ?>
  </a>
  <a href="<?= $e(absUrl('signup.php')) ?>" class="btn-cta" style="padding:.5rem 1rem">Start Free</a>
</div></nav>
<?php
}

/** Close a branded public page: footer + internal links. */
function render_landing_foot(string $site): void {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    // A few top city links for internal linking (3-click reachability).
    try { $topCities = db_all('SELECT city_name, slug FROM ' . tbl('seo_cities') . ' WHERE is_active=1 ORDER BY priority DESC LIMIT 8'); }
    catch (Throwable $ex) { $topCities = []; }
    ?>
<footer class="lp-foot"><div class="in">
  <div style="max-width:280px">
    <div class="brand" style="color:var(--ink);margin-bottom:.4rem"><?= $e($site) ?></div>
    <div>Digital menu &amp; QR ordering for Indian restaurants.</div>
  </div>
  <div>
    <strong>Product</strong><br>
    <a href="<?= $e(absUrl('')) ?>">Home</a> · <a href="<?= $e(absUrl('signup.php')) ?>">Sign up</a> · <a href="<?= $e(absUrl('blog')) ?>">Blog</a>
  </div>
  <div style="max-width:340px">
    <strong>Popular cities</strong><br>
    <?php foreach ($topCities as $i => $c): ?><?= $i ? ' · ' : '' ?><a href="<?= $e(absUrl('digital-menu/' . $c['slug'])) ?>"><?= $e($c['city_name']) ?></a><?php endforeach; ?>
  </div>
</div></footer>
</body></html>
<?php
}

// -----------------------------------------------------------------------------
// Public menu SEO
// -----------------------------------------------------------------------------

/** SEO <title> for a public menu. */
function menuSeoTitle(array $tenant): string {
    $city = $tenant['city'] ? ' — ' . $tenant['city'] : '';
    return $tenant['restaurant_name'] . ' Menu & Prices' . $city . ' | Order Online';
}

/** Auto meta description for a public menu (item count + top items + freshness). */
function menuSeoDescription(array $tenant, array $categories): string {
    $count = 0; $names = [];
    foreach ($categories as $c) {
        foreach ($c['items'] as $it) { $count++; if (count($names) < 3 && !empty($it['name'])) $names[] = $it['name']; }
    }
    $city = $tenant['city'] ? ' in ' . $tenant['city'] : '';
    $top  = $names ? ' including ' . implode(', ', $names) : '';
    return seoTrim("View {$tenant['restaurant_name']}'s menu{$city}. {$count}+ dishes{$top}. Order online or scan the QR code. Updated " . date('F Y') . '.', 160);
}

/**
 * Full Restaurant + nested Menu JSON-LD for a public menu page.
 * aggregateRating is included ONLY when real feedback exists (no fake reviews).
 */
function jsonldRestaurantMenu(array $tenant, array $categories, string $lang = 'en'): array {
    $menuUrl = publicMenuUrl($tenant['slug']);
    $logoUrl = !empty($tenant['logo']) ? mediaUrl($tenant['logo']) : '';
    $pick = fn($row, $f) => ($lang === 'gu' && !empty($row[$f . '_gu'])) ? $row[$f . '_gu'] : ($row[$f] ?? '');

    $sections = [];
    foreach ($categories as $c) {
        $items = [];
        foreach ($c['items'] as $it) {
            $mi = ['@type' => 'MenuItem', 'name' => $pick($it, 'name')];
            if (!empty($it['description'])) { $mi['description'] = $it['description']; }
            $price = ($it['discount_price'] ?? 0) > 0 ? $it['discount_price'] : ($it['price'] ?? 0);
            if ((float)$price > 0) { $mi['offers'] = ['@type' => 'Offer', 'price' => (string)(float)$price, 'priceCurrency' => 'INR']; }
            if (!empty($it['is_veg'])) { $mi['suitableForDiet'] = 'https://schema.org/VegetarianDiet'; }
            if (!empty($tenant['show_images']) && !empty($it['image'])) { $mi['image'] = mediaUrl($it['image']); }
            $items[] = $mi;
        }
        $sec = ['@type' => 'MenuSection', 'name' => $pick($c, 'name')];
        if ($items) { $sec['hasMenuItem'] = $items; }
        $sections[] = $sec;
    }

    $r = [
        '@context' => 'https://schema.org', '@type' => 'Restaurant',
        'name' => $tenant['restaurant_name'], 'url' => $menuUrl,
        'servesCuisine' => 'Indian', 'priceRange' => '₹₹',
        'hasMenu' => ['@type' => 'Menu', 'hasMenuSection' => $sections],
    ];
    if ($logoUrl) { $r['image'] = $logoUrl; $r['logo'] = $logoUrl; }
    if (!empty($tenant['mobile'])) { $r['telephone'] = '+91' . preg_replace('/\D/', '', substr($tenant['mobile'], -10)); }
    $addr = ['@type' => 'PostalAddress', 'addressCountry' => 'IN'];
    if (!empty($tenant['address'])) { $addr['streetAddress'] = $tenant['address']; }
    if (!empty($tenant['city']))    { $addr['addressLocality'] = $tenant['city']; }
    $r['address'] = $addr;
    if (!empty($tenant['opening_time']) && !empty($tenant['closing_time'])) {
        $r['openingHoursSpecification'] = ['@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
            'opens' => substr($tenant['opening_time'], 0, 5), 'closes' => substr($tenant['closing_time'], 0, 5)];
    }
    $same = array_values(array_filter([$tenant['facebook_url'] ?? '', $tenant['instagram_url'] ?? '', $tenant['maps_url'] ?? '']));
    if ($same) { $r['sameAs'] = $same; }
    try {
        $fb = db_one('SELECT COUNT(*) c, AVG(rating) a FROM ' . tbl('feedback') . ' WHERE tenant_id = :t', [':t' => (int)$tenant['id']]);
        if ($fb && (int)$fb['c'] > 0) {
            $r['aggregateRating'] = ['@type' => 'AggregateRating',
                'ratingValue' => round((float)$fb['a'], 1), 'reviewCount' => (int)$fb['c'],
                'bestRating' => 5, 'worstRating' => 1];
        }
    } catch (Throwable $e) { /* no feedback table */ }
    return $r;
}

// -----------------------------------------------------------------------------
// Indexing toggle
// -----------------------------------------------------------------------------

/** Whether a tenant's public menu may be indexed by search engines. */
function tenantAllowsIndexing(array $tenant): bool {
    return (int)($tenant['allow_indexing'] ?? 1) === 1 && ($tenant['status'] ?? 'active') === 'active';
}

/** robots value for a public menu page. */
function menuRobots(array $tenant): string {
    return tenantAllowsIndexing($tenant) ? 'index,follow' : 'noindex,nofollow';
}

// -----------------------------------------------------------------------------
// Programmatic city / cuisine content
// -----------------------------------------------------------------------------

/** Seed seo_cities from the code data on first use (idempotent). */
function seedSeoCitiesIfEmpty(): void {
    try {
        $n = (int)db_val('SELECT COUNT(*) FROM ' . tbl('seo_cities'));
        if ($n > 0) return;
        foreach (seoCitySeed() as $c) {
            $foods = implode(', ', $c['foods']);
            db_insert('seo_cities', [
                'city_name'    => $c['city_name'],
                'city_name_gu' => $c['city_name_gu'],
                'state'        => $c['state'],
                'slug'         => $c['slug'],
                'population_tier' => $c['population_tier'],
                'meta_title'   => "Digital Menu & QR Code Ordering in {$c['city_name']} | " . getSetting('site_name', 'AK Menu System'),
                'meta_description' => seoTrim("Create a QR code menu for your {$c['city_name']} restaurant in minutes. AI menu import, online ordering, WhatsApp alerts. Popular local food: {$foods}. Free 7-day trial.", 160),
                'h1'           => "Digital Menu & QR Code Ordering System in {$c['city_name']}",
                'content_html' => 'Local specialities: ' . $foods . '. ' . ucfirst($c['hook']) . '.',
                'latitude'     => $c['latitude'],
                'longitude'    => $c['longitude'],
                'is_active'    => 1,
                'priority'     => $c['population_tier'] === 1 ? 100 : ($c['population_tier'] === 2 ? 70 : 40),
            ]);
        }
    } catch (Throwable $e) { error_log('seedSeoCities: ' . $e->getMessage()); }
}

/** Seed attributes (foods, hook) for a city slug, or null. */
function seoCitySeedBySlug(string $slug): ?array {
    foreach (seoCitySeed() as $c) { if ($c['slug'] === $slug) return $c; }
    return null;
}

/**
 * Build genuinely unique, 400+ word landing content for a city page from its
 * real attributes (state, local dishes, hook, live restaurant count).
 */
function cityLandingHtml(array $city, int $liveCount): string {
    $name  = $city['city_name'];
    $state = $city['state'] ?: 'India';
    $seed  = seoCitySeedBySlug($city['slug']);
    $foods = $seed['foods'] ?? [];
    $hook  = $seed['hook'] ?? "a vibrant food destination in $state";
    $site  = getSetting('site_name', 'AK Menu System');
    $foodList = $foods ? implode(', ', $foods) : 'local favourites';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $h  = "<p>$name is " . $e($hook) . ". From busy family diners and roadside eateries to trendy cafés, restaurants across $name are moving away from printed cards to a faster, cleaner <strong>digital QR code menu</strong>. With $site, any restaurant in $name can turn its paper menu into a scannable digital menu in minutes — no app, no designer, no reprinting costs.</p>";

    $h .= "<h2>Why restaurants in $name are going digital</h2>";
    $h .= "<p>Diners in $name expect quick, contactless service. A QR menu on every table lets them scan, browse dishes with photos and prices, and place an order without waiting for a waiter. Owners save on printing, update prices instantly when costs change, and capture every order digitally. Whether you run a thali house, a café, a sweet shop or a cloud kitchen in $name, a digital menu means fewer mistakes, faster tables and happier customers.</p>";

    $h .= "<h2>Local food culture of $name</h2>";
    $h .= "<p>$name is known for dishes like <strong>" . $e($foodList) . "</strong>. Whatever the cuisine, $site shows every item beautifully in English and ગુજરાતી, with veg/non-veg markers, categories and add-ons — so your $name customers see exactly what you serve, the way you want it presented.</p>";

    $h .= "<h2>Everything your $name restaurant needs</h2>";
    $h .= "<ul>
        <li><strong>AI menu import</strong> — snap a photo of your existing $name menu and let AI build it.</li>
        <li><strong>QR code &amp; standee</strong> — print-ready designs for every table.</li>
        <li><strong>Online ordering &amp; KOT</strong> — direct or waiter ordering with a live kitchen screen.</li>
        <li><strong>WhatsApp alerts</strong> — order and reminder messages, on your own number.</li>
        <li><strong>Reports &amp; analytics</strong> — daily scans, sales and payment breakdowns.</li>
    </ul>";

    if ($liveCount > 0) {
        $h .= "<p>Already <strong>$liveCount " . ($liveCount === 1 ? 'restaurant' : 'restaurants') . " in $name</strong> " . ($liveCount === 1 ? 'uses' : 'use') . " $site for their digital menu. Join them and give your customers a modern ordering experience.</p>";
    } else {
        $h .= "<p>Be the first restaurant in $name to offer a smart digital QR menu with $site and stand out from the competition.</p>";
    }

    $h .= "<h2>How to set up your $name restaurant menu</h2>";
    $h .= "<p>Getting started in $name takes minutes. First, create a free account and add your restaurant details. Next, upload a photo of your existing menu and let the AI import every dish, price and category automatically — or add items yourself. Then download a print-ready QR standee, place it on your $name restaurant's tables, and you're live. Customers scan, browse in English or ગુજરાતી, and order directly. You manage every order from a simple dashboard, mark payments, and print bills — no technical skill required.</p>";

    $h .= "<h2>Give $name diners a modern experience</h2>";
    $h .= "<p>Today's customers in $name and across $state are used to scanning QR codes for everything. A clean digital menu makes your restaurant look professional, reduces waiting time, and lets guests order at their own pace. Photos help them discover dishes they might have missed on a printed card, which naturally increases the average order value. And because everything updates instantly, you never hand out a menu with a wrong price or a sold-out item again.</p>";

    $h .= "<h2>Pricing for $name restaurants</h2>";
    $h .= "<p>Start completely free with a 7-day trial — no card required. Paid plans are affordable and priced in ₹ for Indian restaurants, with everything from unlimited menu items to online ordering and WhatsApp automation. Whether you are a small family eatery or a busy multi-outlet brand in $name, there is a plan that fits your budget. Join the growing number of $name restaurants going digital with $site today.</p>";
    return $h;
}

/** City-specific FAQ (used for on-page + FAQPage schema). */
function cityFaq(array $city): array {
    $name = $city['city_name'];
    $site = getSetting('site_name', 'AK Menu System');
    return [
        ["How do I create a QR code menu for my restaurant in $name?",
         "Sign up free on $site, add your menu (or let AI build it from a photo), and download your QR code standee. Place it on your tables in $name and customers can scan to view the menu and order — all within minutes."],
        ["How much does a digital menu cost in $name?",
         "You can start free with a 7-day trial. After that, $site offers affordable monthly and yearly plans in ₹, suitable for small and large $name restaurants alike."],
        ["Do customers in $name need to install an app?",
         "No. Customers simply scan the QR code with their phone camera and the menu opens in the browser — no app download needed."],
        ["Can the menu show items in Gujarati?",
         "Yes. $site supports full English and ગુજરાતી menus, so your $name customers can read the menu in their preferred language."],
    ];
}

/** Load an active city row by slug. */
function getSeoCity(string $slug): ?array {
    try {
        return db_one('SELECT * FROM ' . tbl('seo_cities') . ' WHERE slug = :s AND is_active = 1', [':s' => strtolower($slug)]);
    } catch (Throwable $e) { return null; }
}

/** Restaurants live in a city (indexable, active). */
function cityRestaurants(string $city, int $limit = 24): array {
    try {
        return db_all('SELECT restaurant_name, slug, logo, city FROM ' . tbl('tenants') . "
                       WHERE status='active' AND allow_indexing=1 AND city = :c
                       ORDER BY id DESC LIMIT " . (int)$limit, [':c' => $city]);
    } catch (Throwable $e) { return []; }
}

/** Nearby cities (same state first) for internal linking. */
function nearbyCities(array $city, int $limit = 8): array {
    try {
        return db_all('SELECT city_name, slug, state FROM ' . tbl('seo_cities') . '
                       WHERE is_active=1 AND slug <> :s
                       ORDER BY (state = :st) DESC, priority DESC LIMIT ' . (int)$limit,
                       [':s' => $city['slug'], ':st' => $city['state']]);
    } catch (Throwable $e) { return []; }
}

// -----------------------------------------------------------------------------
// Sitemap file cache (6h)
// -----------------------------------------------------------------------------

function seoCacheGet(string $key, int $ttl = 21600): ?string {
    $f = UPLOAD_PATH . '/cache/' . preg_replace('/[^a-z0-9_\-]/i', '', $key) . '.xml';
    if (is_file($f) && (time() - filemtime($f)) < $ttl) {
        $c = @file_get_contents($f);
        return $c === false ? null : $c;
    }
    return null;
}
function seoCacheSet(string $key, string $content): void {
    $dir = UPLOAD_PATH . '/cache';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $key) . '.xml', $content);
}
function seoCacheClear(): void {
    foreach (glob(UPLOAD_PATH . '/cache/sitemap*.xml') ?: [] as $f) { @unlink($f); }
}

// -----------------------------------------------------------------------------
// Search-engine pings (IndexNow + Google sitemap)
// -----------------------------------------------------------------------------

/** Stable IndexNow key (generated + stored once). */
function indexNowKey(): string {
    $k = getSetting('indexnow_key', '');
    if (!$k) { $k = bin2hex(random_bytes(16)); setSetting('indexnow_key', $k); }
    return $k;
}

/**
 * Notify search engines that a URL changed (best-effort, non-blocking).
 * IndexNow -> Bing/Yandex; also pings Google + Bing with the sitemap.
 */
function pingSearchEngines(string $url): void {
    if (!function_exists('curl_init')) return;
    seoCacheClear(); // menu/city changed -> rebuild sitemaps next request
    $host = parse_url(BASE_URL, PHP_URL_HOST) ?: '';
    $key  = indexNowKey();
    $targets = [
        'https://api.indexnow.org/indexnow?url=' . urlencode($url) . '&key=' . $key . '&keyLocation=' . urlencode(absUrl($key . '.txt')),
        'https://www.google.com/ping?sitemap=' . urlencode(absUrl('sitemap.xml')),
        'https://www.bing.com/ping?sitemap=' . urlencode(absUrl('sitemap.xml')),
    ];
    foreach ($targets as $t) {
        $ch = curl_init($t);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 800,
            CURLOPT_NOSIGNAL => true, CURLOPT_CONNECTTIMEOUT_MS => 500,
        ]);
        @curl_exec($ch); @curl_close($ch);
    }
}
