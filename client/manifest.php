<?php
/**
 * PWA manifest for the restaurant owner panel, so it can be installed on a
 * computer (Chrome / Edge "Install app") or phone and opens in its own window
 * with a desktop / Start-menu icon. Public on purpose: the browser fetches it
 * without cookies.
 */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$site = (string)getSetting('site_name', 'AK Menu System');
// Short name under the desktop icon: whole words only, max ~15 characters.
$short = '';
foreach (preg_split('/\s+/', trim($site)) as $w) {
    if (mb_strlen(trim($short . ' ' . $w)) > 15) break;
    $short = trim($short . ' ' . $w);
}
$short = $short ?: mb_substr($site, 0, 15);
echo json_encode([
    'id'               => BASE_URL . '/client/',
    'name'             => $site . ' — Restaurant Panel',
    'short_name'       => $short ?: 'AK Menu',
    'description'      => 'Orders, KOT, menu, reports and billing for your restaurant.',
    'start_url'        => BASE_URL . '/client/index.php?source=app',
    'scope'            => BASE_URL . '/client/',
    'display'          => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone'],
    'background_color' => '#ffffff',
    'theme_color'      => (string)getSetting('primary_color', '#e63946'),
    'icons'            => [
        ['src' => BASE_URL . '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts'        => [
        ['name' => 'Orders',  'url' => BASE_URL . '/client/orders.php', 'icons' => [['src' => BASE_URL . '/assets/img/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Menu',    'url' => BASE_URL . '/client/menu.php',   'icons' => [['src' => BASE_URL . '/assets/img/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Reports', 'url' => BASE_URL . '/client/reports.php','icons' => [['src' => BASE_URL . '/assets/img/icon-192.png', 'sizes' => '192x192']]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
