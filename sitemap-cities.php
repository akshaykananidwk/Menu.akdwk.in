<?php
/** City + state + cuisine landing pages sitemap (cached 6h). */
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

if ($c = seoCacheGet('sitemap-cities')) { echo $c; exit; }

seedSeoCitiesIfEmpty();
$base = rtrim(BASE_URL, '/');
$today = date('Y-m-d');

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$add = function (string $path, string $freq, string $pri) use (&$xml, $base, $today) {
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($base . '/' . $path, ENT_XML1) . "</loc>\n";
    $xml .= "    <lastmod>$today</lastmod>\n    <changefreq>$freq</changefreq>\n    <priority>$pri</priority>\n  </url>\n";
};

try {
    $cities = db_all('SELECT slug, state FROM ' . tbl('seo_cities') . ' WHERE is_active=1 ORDER BY priority DESC');
} catch (Throwable $e) { $cities = []; }
$states = [];
foreach ($cities as $c) {
    $add('digital-menu/' . $c['slug'], 'monthly', '0.7');
    if ($c['state']) { $states[strtolower(str_replace(' ', '-', $c['state']))] = true; }
}
foreach (array_keys($states) as $st) { $add('digital-menu/' . $st, 'monthly', '0.6'); }

$xml .= '</urlset>';
seoCacheSet('sitemap-cities', $xml);
echo $xml;
