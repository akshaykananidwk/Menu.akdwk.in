<?php
/** Blog posts + categories sitemap (cached 6h). */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/seo.php';
header('Content-Type: application/xml; charset=utf-8');

if ($c = seoCacheGet('sitemap-blog')) { echo $c; exit; }

$base = rtrim(BASE_URL, '/');
$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

try {
    $posts = db_all('SELECT slug, updated_at FROM ' . tbl('blog_posts') . " WHERE status='published' ORDER BY published_at DESC LIMIT 5000");
} catch (Throwable $e) { $posts = []; }
foreach ($posts as $p) {
    $lm = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : date('Y-m-d');
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($base . '/blog/' . rawurlencode($p['slug']), ENT_XML1) . "</loc>\n";
    $xml .= "    <lastmod>$lm</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n";
}
try {
    $cats = db_all('SELECT slug FROM ' . tbl('blog_categories'));
} catch (Throwable $e) { $cats = []; }
foreach ($cats as $c) {
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($base . '/blog/category/' . rawurlencode($c['slug']), ENT_XML1) . "</loc>\n";
    $xml .= "    <changefreq>weekly</changefreq>\n    <priority>0.4</priority>\n  </url>\n";
}
$xml .= '</urlset>';
seoCacheSet('sitemap-blog', $xml);
echo $xml;
