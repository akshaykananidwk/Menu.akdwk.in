<?php
/**
 * Restaurant menus sitemap. Only active, indexable menus. Split into 40k chunks
 * (?p=N via /sitemap-restaurants-N.xml). Cached 6h per chunk.
 */
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

$per  = 40000;
$page = max(1, (int)($_GET['p'] ?? 1));
$key  = 'sitemap-restaurants-' . $page;
if ($c = seoCacheGet($key)) { echo $c; exit; }

$base = rtrim(BASE_URL, '/');
$off  = ($page - 1) * $per;

try {
    $rows = db_all('SELECT slug, updated_col FROM (
                      SELECT slug, GREATEST(COALESCE(created_at,0)) AS updated_col
                      FROM ' . tbl('tenants') . "
                      WHERE status='active' AND allow_indexing=1 AND slug<>''
                    ) x ORDER BY slug LIMIT $per OFFSET $off");
} catch (Throwable $e) { $rows = []; }

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($rows as $r) {
    $lastmod = !empty($r['updated_col']) ? date('Y-m-d', strtotime($r['updated_col'])) : date('Y-m-d');
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($base . '/r/' . rawurlencode($r['slug']), ENT_XML1) . "</loc>\n";
    $xml .= "    <lastmod>$lastmod</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.7</priority>\n  </url>\n";
}
$xml .= '</urlset>';
seoCacheSet($key, $xml);
echo $xml;
