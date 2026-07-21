<?php
/**
 * Dynamic XML sitemap. Lists the marketing pages plus every live restaurant's
 * public menu, so search engines can discover and index them. Served at
 * /sitemap.xml via the .htaccess rewrite (falls back to /sitemap.php).
 */
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(BASE_URL, '/');
$today = date('Y-m-d');

$urls = [
    ['loc' => $base . '/',            'freq' => 'weekly',  'pri' => '1.0'],
    ['loc' => $base . '/signup.php',  'freq' => 'monthly', 'pri' => '0.8'],
    ['loc' => $base . '/client/login.php', 'freq' => 'yearly', 'pri' => '0.3'],
];

// Every active restaurant's public menu.
try {
    $rows = db_all('SELECT slug, created_at FROM ' . tbl('tenants') . "
                    WHERE status = 'active' AND slug <> '' ORDER BY id DESC LIMIT 5000");
    foreach ($rows as $r) {
        $urls[] = [
            'loc'     => $base . '/r/' . rawurlencode($r['slug']),
            'freq'    => 'weekly',
            'pri'     => '0.7',
            'lastmod' => !empty($r['created_at']) ? date('Y-m-d', strtotime($r['created_at'])) : $today,
        ];
    }
} catch (Throwable $e) { /* table missing / not installed — just emit static URLs */ }

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
    echo '    <lastmod>' . ($u['lastmod'] ?? $today) . "</lastmod>\n";
    echo '    <changefreq>' . $u['freq'] . "</changefreq>\n";
    echo '    <priority>' . $u['pri'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
