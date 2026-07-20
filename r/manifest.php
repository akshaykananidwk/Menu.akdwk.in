<?php
/** Dynamic PWA manifest per restaurant. */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$slug = $_GET['slug'] ?? '';
$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
$name = $tenant['restaurant_name'] ?? 'Menu';
$color = $tenant['primary_color'] ?? '#e63946';
$logo = $tenant['logo'] ? BASE_URL . '/' . $tenant['logo'] : BASE_URL . '/assets/img/icon-512.png';
echo json_encode([
  'name' => $name . ' Menu', 'short_name' => $name,
  'start_url' => BASE_URL . '/r/' . $slug, 'display' => 'standalone',
  'background_color' => '#ffffff', 'theme_color' => $color,
  'icons' => [
    ['src' => $logo, 'sizes' => '192x192', 'type' => 'image/png'],
    ['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png'],
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
