<?php
/** Serves the IndexNow key file at /{key}.txt (validates ownership for Bing/Yandex). */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/seo.php';
header('Content-Type: text/plain; charset=utf-8');
$req = basename(strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?'), '.txt');
$key = indexNowKey();
echo (hash_equals($key, (string)$req)) ? $key : '';
