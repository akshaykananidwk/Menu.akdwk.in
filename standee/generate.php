<?php
/**
 * AK Menu System - Standee / QR print-asset generator (public print endpoint).
 *
 * Produces a print-ready standee for a restaurant menu (by ?slug=) or a specific
 * table QR (by ?table=<qr_token>). No login is required - a standee is a public
 * print asset - but the tenant/table MUST exist and the tenant must be active.
 *
 * Query parameters:
 *   slug   = tenant slug            (menu-level standee)
 *   table  = table qr_token         (table-level standee; overrides slug)
 *   size   = A4 | A5 | tent | sticker           (default A4)
 *   tpl    = standee_templates.folder            (optional; overrides size design)
 *   format = pdf | png                           (default pdf)
 *   dl     = 1 to force download (attachment)    (optional)
 *
 * Output: a PDF (via libs/fpdf) or a PNG (via GD), streamed inline by default.
 */

require_once dirname(__DIR__) . '/config/config.php';

require_once LIB_PATH . '/fpdf/fpdf.php';

// =============================================================================
// Input validation + tenant/table resolution
// =============================================================================

$slug      = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$tableToken = isset($_GET['table']) ? trim((string)$_GET['table']) : '';
$size      = strtolower(trim((string)($_GET['size'] ?? 'A4')));
$tplFolder = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_GET['tpl'] ?? '')));
$format    = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
$forceDl   = !empty($_GET['dl']);

if (!in_array($size, ['a4', 'a5', 'tent', 'sticker'], true)) { $size = 'a4'; }
if (!in_array($format, ['pdf', 'png'], true)) { $format = 'pdf'; }

$tenant  = null;
$table   = null;
$tableNo = '';

if ($tableToken !== '') {
    // Table-level: token is [A-Za-z0-9]; resolve table then its tenant.
    if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $tableToken)) {
        http_response_code(400);
        exit('Invalid table token.');
    }
    $table = db_one('SELECT * FROM ' . tbl('tables') . ' WHERE qr_token = :q', [':q' => $tableToken]);
    if (!$table) { http_response_code(404); exit('Table not found.'); }
    $tenant  = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => $table['tenant_id']]);
    $tableNo = (string)$table['table_no'];
} elseif ($slug !== '') {
    if (!preg_match('/^[A-Za-z0-9\-]{1,120}$/', $slug)) {
        http_response_code(400);
        exit('Invalid slug.');
    }
    $tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE slug = :s', [':s' => $slug]);
} else {
    http_response_code(400);
    exit('Missing slug or table parameter.');
}

if (!$tenant) { http_response_code(404); exit('Restaurant not found.'); }
if (($tenant['status'] ?? '') !== 'active') { http_response_code(403); exit('This restaurant is not active.'); }

// =============================================================================
// Ensure a high-resolution QR PNG exists for the target URL
// =============================================================================

// This script lives in /standee, which config.php does NOT strip when deriving
// BASE_URL, so BASE_URL can end in "/standee". Strip that segment to build the
// real public app URLs (do not rely on publicMenuUrl() from this directory).
$appBase = preg_replace('#/standee(/.*)?$#', '', BASE_URL);

if ($table) {
    $targetUrl = $appBase . '/t/' . $table['qr_token'];
    $qrFile    = 'table_' . (int)$table['id'];
} else {
    $targetUrl = $appBase . '/r/' . $tenant['slug'];
    $qrFile    = 'menu_' . (int)$tenant['id'];
}

// generateQr() writes uploads/qr/<file>.png (bundled encoder preferred).
$qrRel = generateQr($targetUrl, $qrFile);
$qrAbs = $qrRel ? ROOT_PATH . '/' . $qrRel : '';
if (!$qrAbs || !is_file($qrAbs)) {
    http_response_code(500);
    exit('Could not generate QR code.');
}

// =============================================================================
// Build the render context (colours, logo, contact, Gujarati label)
// =============================================================================

$ctx = [
    'primary'   => hexToRgb($tenant['primary_color']   ?? '#e63946', [230, 57, 70]),
    'secondary' => hexToRgb($tenant['secondary_color'] ?? '#1d3557', [29, 53, 87]),
    'accent'    => hexToRgb($tenant['accent_color']    ?? '#f1a208', [241, 162, 8]),
    'logo'      => safeTenantLogo($tenant['logo'] ?? ''),
    'contact'   => standeeContact($tenant),
    'subtitle'  => standeeSubtitle($tenant),
    'powered'   => POWERED_BY,
    'table_no'  => $tableNo,
    'gujarati'  => renderGujaratiLabel('સ્કેન કરો'),
];

// =============================================================================
// PNG output path (simple single-canvas standee via GD)
// =============================================================================

if ($format === 'png') {
    streamStandeePng($tenant, $qrAbs, $ctx, $tableNo);
    exit;
}

// =============================================================================
// PDF output path (libs/fpdf + a standee design template)
// =============================================================================

// Map the requested size to page dimensions (mm) and a default design folder.
switch ($size) {
    case 'a5':      $pageSize = 'A5';            $defFolder = 'a5';   break;
    case 'tent':    $pageSize = [101.6, 152.4];  $defFolder = 'tent'; break; // 4x6 in
    case 'sticker': $pageSize = [80.0, 80.0];    $defFolder = 'a5';   break; // small square
    case 'a4':
    default:        $pageSize = 'A4';            $defFolder = 'a4';   break;
}

// A ?tpl= folder (validated against standee_templates) overrides the default.
$folder = $defFolder;
if ($tplFolder !== '') {
    $known = db_val('SELECT folder FROM ' . tbl('standee_templates') . ' WHERE folder = :f AND status = 1', [':f' => $tplFolder]);
    if ($known && is_file(__DIR__ . '/' . $known . '.php')) { $folder = $known; }
}

// Load the design file and resolve its render function.
$designFile = __DIR__ . '/' . $folder . '.php';
if (!is_file($designFile)) { $designFile = __DIR__ . '/a4.php'; $folder = 'a4'; }
require_once $designFile;
$renderFn = 'render_standee_' . $folder;
if (!function_exists($renderFn)) { $renderFn = 'render_standee_a4'; }

$pdf = new FPDF('P', 'mm', $pageSize);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();
$renderFn($pdf, $tenant, $qrAbs, $ctx);

$fileBase = 'standee-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($tenant['slug']))
          . ($tableNo !== '' ? '-table-' . preg_replace('/[^a-z0-9]+/', '', strtolower($tableNo)) : '')
          . '-' . $size . '.pdf';
$pdf->Output($forceDl ? 'D' : 'I', $fileBase);
exit;

// =============================================================================
// Helpers
// =============================================================================

/** Parse a #rrggbb / #rgb colour to an [r,g,b] array, with a fallback. */
function hexToRgb(string $hex, array $fallback): array
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) { return $fallback; }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * Resolve a tenant logo to a safe absolute path.
 * Only files that physically live under /uploads are accepted (no traversal).
 */
function safeTenantLogo(?string $rel): string
{
    $rel = trim((string)$rel);
    if ($rel === '') { return ''; }
    // Reject absolute paths / traversal attempts outright.
    if (strpos($rel, '..') !== false || strpos($rel, "\0") !== false) { return ''; }
    $abs = realpath(ROOT_PATH . '/' . ltrim($rel, '/'));
    $base = realpath(UPLOAD_PATH);
    if ($abs === false || $base === false) { return ''; }
    if (strncmp($abs, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) { return ''; }
    // Must be a raster GD can read (webp logos included).
    return is_file($abs) ? $abs : '';
}

/** Build the contact line (phone) shown on the standee. */
function standeeContact(array $tenant): string
{
    $num = $tenant['whatsapp_no'] ?? ($tenant['mobile'] ?? '');
    $num = trim((string)$num);
    return $num !== '' ? 'Call / WhatsApp: ' . $num : '';
}

/** Build the sub-title (address / city) shown under the name. */
function standeeSubtitle(array $tenant): string
{
    $parts = [];
    if (!empty($tenant['address'])) { $parts[] = trim((string)$tenant['address']); }
    if (!empty($tenant['city']))    { $parts[] = trim((string)$tenant['city']); }
    $s = implode(', ', $parts);
    return mb_strlen($s) > 70 ? mb_substr($s, 0, 67) . '...' : $s;
}

/**
 * Rasterise a short Gujarati phrase to a transparent-on-white PNG using the
 * bundled Noto Sans Gujarati font, returning its absolute path (or '' when the
 * font/GD are unavailable, in which case layouts fall back to a transliteration).
 * Core PDF fonts cannot render Gujarati glyphs, so we embed this tiny image.
 */
function renderGujaratiLabel(string $text): string
{
    $font = LIB_PATH . '/fonts/NotoSansGujarati-Regular.ttf';
    if (!is_file($font) || !function_exists('imagettftext')) { return '';
    }
    $cacheDir = ROOT_PATH . '/temp';
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
    $cache = $cacheDir . '/gujr_scan_menu.png';
    if (is_file($cache)) { return $cache; }

    $fontSize = 64; // high-res for crisp print scaling
    $box = @imagettfbbox($fontSize, 0, $font, $text);
    if ($box === false) { return ''; }
    $tw = abs($box[2] - $box[0]);
    $th = abs($box[7] - $box[1]);
    $padX = 24; $padY = 20;
    $w = $tw + 2 * $padX;
    $h = $th + 2 * $padY;

    $im = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $w, $h, $white);
    $ink = imagecolorallocate($im, 29, 53, 87); // secondary-ish ink
    // Baseline: place using bbox offsets.
    $x = $padX - $box[0];
    $y = $padY - $box[7];
    imagettftext($im, $fontSize, 0, (int)$x, (int)$y, $ink, $font, $text);
    imagepng($im, $cache);
    imagedestroy($im);
    return is_file($cache) ? $cache : '';
}

/**
 * Render a simple single-canvas PNG standee via GD (for ?format=png).
 * Uses DejaVuSans for Latin text and Noto Sans Gujarati for the Gujarati line.
 */
function streamStandeePng(array $tenant, string $qrAbs, array $ctx, string $tableNo): void
{
    $W = 800; $H = 1130; // ~A4 ratio at print-ish size
    $im = imagecreatetruecolor($W, $H);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $W, $H, $white);

    $pri = $ctx['primary']; $sec = $ctx['secondary']; $acc = $ctx['accent'];
    $cPri = imagecolorallocate($im, $pri[0], $pri[1], $pri[2]);
    $cSec = imagecolorallocate($im, $sec[0], $sec[1], $sec[2]);
    $cAcc = imagecolorallocate($im, $acc[0], $acc[1], $acc[2]);
    $cWhite = $white;
    $cGrey = imagecolorallocate($im, 90, 90, 90);

    $latin = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    $latinR = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    $gujr  = LIB_PATH . '/fonts/NotoSansGujarati-Regular.ttf';
    $hasTtf = is_file($latin) && function_exists('imagettftext');

    // Header band.
    $bandH = 240;
    imagefilledrectangle($im, 0, 0, $W, $bandH, $cPri);

    // Logo.
    $nameY = 150;
    if (!empty($ctx['logo']) && is_file($ctx['logo'])) {
        $logo = @imagecreatefromstring(@file_get_contents($ctx['logo']));
        if ($logo) {
            $ls = 110;
            imagecopyresampled($im, $logo, ($W - $ls) / 2, 30, 0, 0, $ls, $ls, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }
    }

    // Restaurant name.
    if ($hasTtf) {
        pngCenteredText($im, $tenant['restaurant_name'], $latin, 40, $nameY, $cWhite, $W);
        pngCenteredText($im, 'SCAN FOR MENU', $latin, 34, 330, $cSec, $W);
        if (is_file($gujr)) {
            pngCenteredText($im, 'સ્કેન કરો', $gujr, 30, 385, $cSec, $W);
        }
    }

    // QR (centred, with accent frame).
    $qr = @imagecreatefromstring(@file_get_contents($qrAbs));
    if ($qr) {
        $qs = 520; $qx = ($W - $qs) / 2; $qy = 430;
        imagefilledrectangle($im, $qx - 16, $qy - 16, $qx + $qs + 16, $qy + $qs + 16, $cAcc);
        imagefilledrectangle($im, $qx - 10, $qy - 10, $qx + $qs + 10, $qy + $qs + 10, $cWhite);
        imagecopyresampled($im, $qr, $qx, $qy, 0, 0, $qs, $qs, imagesx($qr), imagesy($qr));
        imagedestroy($qr);

        if ($hasTtf) {
            pngCenteredText($im, 'Point your camera at the code', $latinR, 20, $qy + $qs + 70, $cGrey, $W);
            if ($tableNo !== '') {
                pngCenteredText($im, 'Table ' . $tableNo, $latin, 28, $qy + $qs + 110, $cSec, $W);
            }
        }
    }

    // Footer.
    imagefilledrectangle($im, 0, $H - 90, $W, $H, $cSec);
    if ($hasTtf) {
        if (!empty($ctx['contact'])) {
            pngCenteredText($im, $ctx['contact'], $latin, 22, $H - 52, $cWhite, $W);
        }
        pngCenteredText($im, POWERED_BY, $latinR, 16, $H - 22, $cWhite, $W);
    }

    if (!headers_sent()) {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="standee-' . e($tenant['slug']) . '.png"');
    }
    imagepng($im);
    imagedestroy($im);
}

/** Draw horizontally-centred TTF text at a given baseline y. */
function pngCenteredText($im, string $text, string $font, int $size, int $y, int $color, int $W): void
{
    $box = @imagettfbbox($size, 0, $font, $text);
    if ($box === false) { return; }
    $tw = abs($box[2] - $box[0]);
    $x = (int)(($W - $tw) / 2 - $box[0]);
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}
