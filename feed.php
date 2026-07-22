<?php
/** RSS 2.0 feed of published blog posts at /feed.xml. */
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/rss+xml; charset=utf-8');

$site = getSetting('site_name', 'AK Menu System');
$base = rtrim(BASE_URL, '/');
try { $posts = db_all('SELECT * FROM ' . tbl('blog_posts') . " WHERE status='published' ORDER BY published_at DESC LIMIT 30"); }
catch (Throwable $e) { $posts = []; }

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0"><channel>' . "\n";
echo '<title>' . htmlspecialchars($site . ' Blog', ENT_XML1) . "</title>\n";
echo '<link>' . htmlspecialchars($base . '/blog', ENT_XML1) . "</link>\n";
echo '<description>Digital menu &amp; restaurant tips</description>' . "\n";
echo '<language>en-in</language>' . "\n";
foreach ($posts as $p) {
    $url = $base . '/blog/' . rawurlencode($p['slug']);
    echo "<item>\n";
    echo '  <title>' . htmlspecialchars($p['title'], ENT_XML1) . "</title>\n";
    echo '  <link>' . htmlspecialchars($url, ENT_XML1) . "</link>\n";
    echo '  <guid>' . htmlspecialchars($url, ENT_XML1) . "</guid>\n";
    echo '  <description>' . htmlspecialchars((string)$p['excerpt'], ENT_XML1) . "</description>\n";
    if (!empty($p['published_at'])) { echo '  <pubDate>' . date(DATE_RSS, strtotime($p['published_at'])) . "</pubDate>\n"; }
    echo "</item>\n";
}
echo '</channel></rss>';
