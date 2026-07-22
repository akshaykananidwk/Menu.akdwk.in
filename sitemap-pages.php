<?php
/** Static marketing pages sitemap (cached 6h). */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/seo.php';
header('Content-Type: application/xml; charset=utf-8');

if ($c = seoCacheGet('sitemap-pages')) { echo $c; exit; }

$base = rtrim(BASE_URL, '/');
$today = date('Y-m-d');
$pages = [
    ['', 'weekly', '1.0'],
    ['signup.php', 'monthly', '0.8'],
    ['blog', 'weekly', '0.6'],
];
// Cuisine/type pages.
foreach (array_keys(seoCuisineTypes()) as $slug) { $pages[] = ["qr-menu-for/$slug", 'monthly', '0.6']; }

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $p) {
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($base . '/' . $p[0], ENT_XML1) . "</loc>\n";
    $xml .= "    <lastmod>$today</lastmod>\n    <changefreq>{$p[1]}</changefreq>\n    <priority>{$p[2]}</priority>\n  </url>\n";
}
$xml .= '</urlset>';
seoCacheSet('sitemap-pages', $xml);
echo $xml;
