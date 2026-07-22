<?php
/** Dynamic robots.txt — blocks panels/system, points to the sitemap index. */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/seo.php';
header('Content-Type: text/plain; charset=utf-8');
$base = rtrim(BASE_URL, '/');
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /client/
Disallow: /waiter/
Disallow: /kitchen/
Disallow: /install/
Disallow: /api/
Disallow: /config/
Disallow: /cron/
Disallow: /uploads/qr/
Disallow: /backups/
Disallow: /temp/
Disallow: /invoice.php

Sitemap: <?= $base ?>/sitemap.xml
