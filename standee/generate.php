<?php
/**
 * AK Menu System — legacy standee endpoint.
 *
 * The old FPDF-based standee designs had layout problems (name missing or text
 * overlapping the QR). Standees are now produced by the much nicer, editable
 * gallery engine in standee/render.php. This file stays only for backward
 * compatibility (old bookmarks, WhatsApp-sent links, table QR buttons) and
 * simply forwards to render.php, preserving the slug/table + size.
 */
require_once dirname(__DIR__) . '/config/config.php';

$slug   = isset($_GET['slug'])  ? trim((string)$_GET['slug'])  : '';
$table  = isset($_GET['table']) ? trim((string)$_GET['table']) : '';
$sizeIn = strtolower(trim((string)($_GET['size'] ?? 'a4')));
$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
$format = in_array($format, ['pdf', 'png'], true) ? $format : 'pdf';

// Map the old size labels (A4 / A5 / tent / sticker) onto the new sizes.
$size = ($sizeIn === 'a4') ? 'a4' : 'tent';

$params = ['format' => $format, 'size' => $size, 'download' => 1];
if ($table !== '') { $params['table'] = $table; }
else               { $params['slug']  = $slug; }

header('Location: ' . BASE_URL . '/standee/render.php?' . http_build_query($params));
exit;
