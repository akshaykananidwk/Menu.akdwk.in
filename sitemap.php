<?php
/**
 * Sitemap INDEX — points to the child sitemaps. Served at /sitemap.xml.
 * Children: pages, restaurants (split if huge), cities, blog.
 */
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(BASE_URL, '/');
$now  = date('c');

$children = ['sitemap-pages.xml', 'sitemap-cities.xml', 'sitemap-blog.xml'];

// Restaurants may need splitting into 40k chunks.
try { $rc = (int)db_val('SELECT COUNT(*) FROM ' . tbl('tenants') . " WHERE status='active' AND allow_indexing=1 AND slug<>''"); }
catch (Throwable $e) { $rc = 0; }
$chunks = max(1, (int)ceil($rc / 40000));
for ($i = 1; $i <= $chunks; $i++) {
    $children[] = $chunks === 1 ? 'sitemap-restaurants.xml' : "sitemap-restaurants-$i.xml";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($children as $c) {
    echo "  <sitemap>\n    <loc>" . htmlspecialchars($base . '/' . $c, ENT_XML1) . "</loc>\n    <lastmod>$now</lastmod>\n  </sitemap>\n";
}
echo '</sitemapindex>';
