<?php
/** PWA manifest for the master staff app (installable on phones & tablets). */
require_once dirname(__DIR__) . '/config/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name'             => 'Restaurant Staff',
    'short_name'       => 'Staff',
    'description'      => 'Waiter, Kitchen and Reception — one app for the restaurant floor.',
    'start_url'        => BASE_URL . '/app/',
    'scope'            => BASE_URL . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#0f1729',
    'theme_color'      => '#0f1729',
    'icons'            => [
        ['src' => BASE_URL . '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => BASE_URL . '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
