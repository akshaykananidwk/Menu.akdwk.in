<?php
/**
 * AK Menu System — Standee gallery renderer (PUBLIC print endpoint).
 *
 * Renders one design of a restaurant's standee/table-tent as a high-quality PNG
 * (or a single-page PDF wrapping that PNG). A standee is a print asset, so NO
 * login is required — but the tenant is resolved ONLY by ?slug=, must exist and
 * must be active, and only that tenant's own logo (under /uploads) is ever read.
 *
 * Query parameters:
 *   slug     tenant slug                             (required)
 *   design   design id like "l3-t7"                  (default l1-t1)
 *   format   png | pdf                               (default png)
 *   size     tent | a4                               (default tent, 1080x1920)
 *   thumb    1 => ~300px cached gallery thumbnail
 *   download 1 => send as an attachment
 *
 * Output: image/png (inline or attachment) or application/pdf.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/engine.php';   // rendering engine (also pulls designs.php)

// -----------------------------------------------------------------------------
// Input validation
// -----------------------------------------------------------------------------

$slug     = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$designId = isset($_GET['design']) ? trim((string)$_GET['design']) : 'l1-t1';
$format   = strtolower(trim((string)($_GET['format'] ?? 'png')));
$size     = strtolower(trim((string)($_GET['size'] ?? 'tent')));
$thumb    = !empty($_GET['thumb']);
$download = !empty($_GET['download']);

if (!preg_match('/^[A-Za-z0-9\-]{1,120}$/', $slug)) { http_response_code(400); exit('Invalid slug.'); }
if (!in_array($format, ['png', 'pdf'], true)) { $format = 'png'; }
if (!in_array($size, ['tent', 'a4'], true))   { $size = 'tent'; }

$design = standee_resolve($designId);
if (!$design) { http_response_code(400); exit('Invalid design id.'); }

// -----------------------------------------------------------------------------
// Tenant resolution (slug only, must be active)
// -----------------------------------------------------------------------------

$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
if (!$tenant) { http_response_code(404); exit('Restaurant not found.'); }
if (($tenant['status'] ?? '') !== 'active') { http_response_code(403); exit('This restaurant is not active.'); }

// -----------------------------------------------------------------------------
// Ensure the tenant's QR PNG exists (named by slug, matching client/qr.php)
// -----------------------------------------------------------------------------

$qrAbs = UPLOAD_PATH . '/qr/' . $slug . '.png';
if (!is_file($qrAbs)) {
    $rel = generateQr(publicMenuUrl($slug), $slug);
    $qrAbs = $rel ? ROOT_PATH . '/' . $rel : '';
}
if (!$qrAbs || !is_file($qrAbs)) { http_response_code(500); exit('Could not generate QR code.'); }

// -----------------------------------------------------------------------------
// Canvas dimensions
// -----------------------------------------------------------------------------

if ($size === 'a4') { $W = 1240; $H = 1754; }  // A4 portrait @150dpi
else                { $W = 1080; $H = 1920; }  // table-tent portrait

// -----------------------------------------------------------------------------
// Thumbnail fast path (cached to assets/cache to keep the gallery snappy)
// -----------------------------------------------------------------------------

if ($thumb) {
    $tw = 300;
    $th = (int)round($tw * $H / $W);
    // Cache key includes anything that changes the pixels (name/logo/colours).
    $sig = md5($slug . '|' . $designId . '|' . $size . '|' . ($tenant['restaurant_name'] ?? '')
             . '|' . ($tenant['logo'] ?? '') . '|' . (is_file($qrAbs) ? filemtime($qrAbs) : 0)
             . '|' . (($tenant['logo'] ?? '') && is_file(ROOT_PATH . '/' . $tenant['logo']) ? filemtime(ROOT_PATH . '/' . $tenant['logo']) : 0));
    $cacheDir = ROOT_PATH . '/assets/cache';
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
    $cacheFile = $cacheDir . '/standee-' . $slug . '-' . $designId . '-' . $sig . '.png';

    if (!is_file($cacheFile)) {
        // Render small directly (all geometry is fractional -> scales cleanly).
        $img = se_render($tenant, $qrAbs, $design, $tw, $th);
        imagepng($img, $cacheFile, 6);
        imagedestroy($img);
    }
    se_stream_file($cacheFile, 'image/png', 'standee-thumb-' . $slug . '-' . $designId . '.png', false);
    exit;
}

// -----------------------------------------------------------------------------
// Full render
// -----------------------------------------------------------------------------

$img = se_render($tenant, $qrAbs, $design, $W, $H);

if ($format === 'pdf') {
    // Wrap the rendered PNG into a single full-page PDF via bundled FPDF.
    require_once LIB_PATH . '/fpdf/fpdf.php';
    $tmp = tempnam(sys_get_temp_dir(), 'std') . '.png';
    imagepng($img, $tmp);
    imagedestroy($img);

    $mm = $size === 'a4' ? [210, 297] : [101.6, 152.4]; // A4 or 4x6in tent
    $pdf = new FPDF('P', 'mm', $mm);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->Image($tmp, 0, 0, $mm[0], $mm[1], 'PNG');
    @unlink($tmp);
    $fname = 'standee-' . $slug . '-' . $designId . '.pdf';
    $pdf->Output($download ? 'D' : 'I', $fname);
    exit;
}

// PNG output.
if (!headers_sent()) {
    header('Content-Type: image/png');
    $disp = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disp . '; filename="standee-' . $slug . '-' . $designId . '.png"');
    header('Cache-Control: public, max-age=300');
}
imagepng($img);
imagedestroy($img);
exit;

// -----------------------------------------------------------------------------
// Helper: stream a cached file with the right headers.
// -----------------------------------------------------------------------------
function se_stream_file(string $path, string $mime, string $filename, bool $download): void
{
    if (!headers_sent()) {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Cache-Control: public, max-age=600');
    }
    readfile($path);
}
