<?php
/**
 * AK Menu System — Standee rendering engine (pure functions, no output).
 *
 * Turns a (tenant, QR, layout, palette) tuple into a premium, colourful GD image
 * of a table-tent / standee poster. Shared by the public renderer
 * (standee/render.php) and the client API (api/standee.php) so both produce
 * pixel-identical results.
 *
 * All geometry is expressed as FRACTIONS of the canvas width/height, so the same
 * code renders a full 1080×1920 print asset and a tiny 300px gallery thumbnail
 * just by changing $W/$H — no separate layout maths.
 */

require_once __DIR__ . '/designs.php';

if (!function_exists('se_render')) {

    // -------------------------------------------------------------------------
    // Font resolution
    // -------------------------------------------------------------------------

    /** Bold Latin font path (DejaVu ships on the server; fall back gracefully). */
    function se_font_bold(): string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ] as $f) { if (is_file($f)) { return $f; } }
        return '';
    }

    /** Regular Latin font path. */
    function se_font_reg(): string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ] as $f) { if (is_file($f)) { return $f; } }
        return '';
    }

    /** Bundled Gujarati font for the "મેનુ માટે સ્કેન કરો" line. */
    function se_font_gujarati(): string
    {
        $f = LIB_PATH . '/fonts/NotoSansGujarati-Regular.ttf';
        return is_file($f) ? $f : '';
    }

    // -------------------------------------------------------------------------
    // Colour + geometry helpers
    // -------------------------------------------------------------------------

    /** Parse #rrggbb / #rgb to [r,g,b] with a safe fallback. */
    function se_hex(string $hex, array $fallback = [0, 0, 0]): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) { return $fallback; }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Blend two [r,g,b] colours; $t=0 => a, $t=1 => b. */
    function se_mix(array $a, array $b, float $t): array
    {
        return [
            (int)round($a[0] + ($b[0] - $a[0]) * $t),
            (int)round($a[1] + ($b[1] - $a[1]) * $t),
            (int)round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    /** Allocate an [r,g,b] colour on an image. */
    function se_col($im, array $c)
    {
        return imagecolorallocate($im, $c[0], $c[1], $c[2]);
    }

    /** Fill a rectangle with a smooth vertical gradient from $c1 (top) to $c2. */
    function se_gradient_v($im, int $x1, int $y1, int $x2, int $y2, array $c1, array $c2): void
    {
        $h = max(1, $y2 - $y1);
        for ($y = 0; $y <= $h; $y++) {
            $c = se_mix($c1, $c2, $y / $h);
            $col = se_col($im, $c);
            imagefilledrectangle($im, $x1, $y1 + $y, $x2, $y1 + $y, $col);
        }
    }

    /** Draw a filled rounded rectangle. */
    function se_round_rect($im, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
    {
        $r = max(0, min($r, (int)(($x2 - $x1) / 2), (int)(($y2 - $y1) / 2)));
        if ($r <= 0) { imagefilledrectangle($im, $x1, $y1, $x2, $y2, $color); return; }
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledarc($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
    }

    /** Soft drop shadow behind an upcoming rounded card (concentric translucent rects). */
    function se_card_shadow($im, int $x1, int $y1, int $x2, int $y2, int $r): void
    {
        imagealphablending($im, true);
        $layers = max(6, (int)(($x2 - $x1) * 0.02));
        for ($i = $layers; $i >= 1; $i--) {
            $a = 118 - (int)(($layers - $i) * (110 / $layers)); // fainter outward
            if ($a < 0) { $a = 0; }
            $col = imagecolorallocatealpha($im, 0, 0, 0, 127 - (int)((127 - $a) * 0.35));
            $o = $i;
            se_round_rect($im, $x1 - $o, $y1 - $o + (int)($layers * 0.6), $x2 + $o, $y2 + $o + (int)($layers * 0.6), $r + $o, $col);
        }
    }

    /** Measure a TTF string -> [w,h]. */
    function se_measure(string $text, string $font, float $size): array
    {
        $b = @imagettfbbox($size, 0, $font, $text);
        if ($b === false) { return [0, 0]; }
        return [abs($b[2] - $b[0]), abs($b[7] - $b[1])];
    }

    /** Draw horizontally-centred TTF text at baseline $y around centre $cx. */
    function se_text_center($im, string $text, string $font, float $size, int $cx, int $y, $color): void
    {
        if ($font === '' || $text === '') { return; }
        $b = @imagettfbbox($size, 0, $font, $text);
        if ($b === false) { return; }
        $w = abs($b[2] - $b[0]);
        $x = (int)($cx - $w / 2 - $b[0]);
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    /** Draw left-aligned TTF text at baseline $y. */
    function se_text_left($im, string $text, string $font, float $size, int $x, int $y, $color): void
    {
        if ($font === '' || $text === '') { return; }
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * Largest font size (<= $start, >= $min) at which $text fits within $maxW.
     */
    function se_fit_size(string $text, string $font, int $maxW, float $start, float $min): float
    {
        for ($s = $start; $s > $min; $s -= 1) {
            [$w] = se_measure($text, $font, $s);
            if ($w <= $maxW) { return $s; }
        }
        return $min;
    }

    /**
     * Wrap a restaurant name into at most 2 lines that fit $maxW at $size.
     * Returns the lines (1 or 2). Long single words are left as-is (caller
     * shrinks the font to compensate).
     */
    function se_wrap(string $text, string $font, int $maxW, float $size): array
    {
        [$w] = se_measure($text, $font, $size);
        if ($w <= $maxW) { return [$text]; }
        $words = preg_split('/\s+/', trim($text));
        if (count($words) < 2) { return [$text]; }
        // Greedy pack into first line, remainder into second.
        $line1 = ''; $i = 0;
        for (; $i < count($words); $i++) {
            $try = $line1 === '' ? $words[$i] : $line1 . ' ' . $words[$i];
            [$tw] = se_measure($try, $font, $size);
            if ($tw > $maxW && $line1 !== '') { break; }
            $line1 = $try;
        }
        $line2 = trim(implode(' ', array_slice($words, $i)));
        return $line2 === '' ? [$line1] : [$line1, $line2];
    }

    /**
     * Resolve a tenant logo relative path to a safe absolute path.
     * Only files physically under /uploads are accepted (no traversal, no URLs).
     */
    function se_safe_logo(?string $rel): string
    {
        $rel = trim((string)$rel);
        if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, "\0") !== false) { return ''; }
        if (preg_match('#^https?://#i', $rel)) { return ''; }
        $abs  = realpath(ROOT_PATH . '/' . ltrim($rel, '/'));
        $base = realpath(UPLOAD_PATH);
        if ($abs === false || $base === false) { return ''; }
        if (strncmp($abs, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) { return ''; }
        return is_file($abs) ? $abs : '';
    }

    /** Load a logo/QR file into a truecolour GD image with alpha, or null. */
    function se_load_img(string $path)
    {
        if ($path === '' || !is_file($path)) { return null; }
        $img = @imagecreatefromstring(@file_get_contents($path));
        if (!$img) { return null; }
        imagealphablending($img, true);
        return $img;
    }

    /**
     * Draw the brand mark inside a white circle at ($cx,$cy) radius $r:
     * the tenant logo if available, else the restaurant's initials.
     */
    function se_brand_badge($im, ?array $tenant, string $logoAbs, int $cx, int $cy, int $r, array $accent, string $fontBold): void
    {
        imagealphablending($im, true);
        // White disc + accent ring.
        $ring = se_col($im, $accent);
        imagefilledellipse($im, $cx, $cy, $r * 2 + 12, $r * 2 + 12, $ring);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $white);

        $logo = se_load_img($logoAbs);
        if ($logo) {
            // Composite the logo clipped to the circle via a masked copy.
            $d = (int)($r * 1.7);
            $lw = imagesx($logo); $lh = imagesy($logo);
            $tmp = imagecreatetruecolor($d, $d);
            imagealphablending($tmp, false); imagesavealpha($tmp, true);
            $trans = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefilledrectangle($tmp, 0, 0, $d, $d, $trans);
            imagealphablending($tmp, true);
            // cover-fit
            $scale = max($d / $lw, $d / $lh);
            $nw = (int)($lw * $scale); $nh = (int)($lh * $scale);
            imagecopyresampled($tmp, $logo, (int)(($d - $nw) / 2), (int)(($d - $nh) / 2), 0, 0, $nw, $nh, $lw, $lh);
            imagecopy($im, $tmp, $cx - $d / 2, $cy - $d / 2, 0, 0, $d, $d);
            imagedestroy($tmp);
            imagedestroy($logo);
        } else {
            // Initials fallback.
            $name = trim((string)($tenant['restaurant_name'] ?? 'R'));
            $parts = preg_split('/\s+/', $name);
            $ini = '';
            foreach ($parts as $p) { if ($p !== '') { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); } if (mb_strlen($ini) >= 2) { break; } }
            if ($ini === '') { $ini = 'R'; }
            $ink = se_col($im, se_hex('#333333'));
            if ($fontBold !== '') {
                $size = $r * 0.9;
                se_text_center($im, $ini, $fontBold, $size, $cx, (int)($cy + $r * 0.35), $ink);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Background / decoration per layout style
    // -------------------------------------------------------------------------

    /**
     * Paint the base background + decorative furniture for a layout.
     * Returns the vertical band boundaries used by the foreground:
     *   ['name_y' => baseline for the name, 'name_color' => rgb, 'sub_color' => rgb,
     *    'card_top' => y where the QR card may start ]
     */
    function se_paint_background($im, array $layout, array $pal, int $W, int $H): array
    {
        $c1 = se_hex($pal['c1']); $c2 = se_hex($pal['c2']);
        $accent = se_hex($pal['accent']);
        $onbg = se_hex($pal['onbg']); $ink = se_hex($pal['ink']);
        $white = [255, 255, 255];
        $lightBg = se_mix($c1, [255, 255, 255], 0.90); // very soft tint of the theme

        // Default light canvas.
        imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));

        $style = $layout['style'];
        $r = (int)($W * 0.02);

        switch ($style) {
            case 'full':
                se_gradient_v($im, 0, 0, $W, $H, $c1, $c2);
                se_decor_dots($im, $W, $H, $accent, 26);
                break;

            case 'band-top':
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $lightBg));
                se_gradient_v($im, 0, 0, $W, (int)($H * 0.34), $c1, $c2);
                // accent seam
                imagefilledrectangle($im, 0, (int)($H * 0.34), $W, (int)($H * 0.34) + (int)($H * 0.008), se_col($im, $accent));
                break;

            case 'band-bottom':
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $lightBg));
                se_gradient_v($im, 0, (int)($H * 0.72), $W, $H, $c1, $c2);
                imagefilledrectangle($im, 0, (int)($H * 0.72) - (int)($H * 0.008), $W, (int)($H * 0.72), se_col($im, $accent));
                break;

            case 'diagonal':
                se_gradient_v($im, 0, 0, $W, $H, $c1, $c2);
                // lighter diagonal wedge for depth
                imagealphablending($im, true);
                $ov = imagecolorallocatealpha($im, 255, 255, 255, 105);
                imagefilledpolygon($im, [0, 0, $W, 0, $W, (int)($H * 0.28), 0, (int)($H * 0.5)], $ov);
                break;

            case 'sidebar':
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));
                se_gradient_v($im, 0, 0, (int)($W * 0.26), $H, $c1, $c2);
                imagefilledrectangle($im, (int)($W * 0.26), 0, (int)($W * 0.26) + (int)($W * 0.01), $H, se_col($im, $accent));
                break;

            case 'circle':
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $lightBg));
                imagealphablending($im, true);
                // big soft accent circle behind the QR zone
                $soft = imagecolorallocatealpha($im, $c1[0], $c1[1], $c1[2], 96);
                imagefilledellipse($im, (int)($W / 2), (int)($H * 0.60), (int)($W * 1.15), (int)($W * 1.15), $soft);
                break;

            case 'minimal':
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));
                imagefilledrectangle($im, 0, 0, $W, (int)($H * 0.018), se_col($im, $c1));
                imagefilledrectangle($im, 0, $H - (int)($H * 0.018), $W, $H, se_col($im, $c1));
                // thin centred accent rule under header
                imagefilledrectangle($im, (int)($W * 0.35), (int)($H * 0.235), (int)($W * 0.65), (int)($H * 0.235) + (int)($H * 0.004), se_col($im, $accent));
                break;

            case 'festive':
                se_gradient_v($im, 0, 0, $W, $H, $c1, $c2);
                se_decor_confetti($im, $W, $H, $accent, se_mix($accent, $white, 0.4));
                break;

            case 'frame':
                se_gradient_v($im, 0, 0, $W, $H, $c1, $c2);
                $m = (int)($W * 0.06);
                se_round_rect($im, $m, $m, $W - $m, $H - $m, (int)($W * 0.04), se_col($im, $white));
                // inner accent hairline
                imagesetthickness($im, max(2, (int)($W * 0.004)));
                $ac = se_col($im, $accent);
                $mm = $m + (int)($W * 0.02);
                imagerectangle($im, $mm, $mm, $W - $mm, $H - $mm, $ac);
                imagesetthickness($im, 1);
                break;

            case 'ribbon':
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $lightBg));
                // rounded gradient header banner
                $hb = (int)($H * 0.30);
                se_round_rect($im, (int)($W * 0.06), (int)($H * 0.05), (int)($W * 0.94), $hb, (int)($W * 0.04), se_col($im, $c1));
                se_gradient_v($im, (int)($W * 0.06) + 4, (int)($H * 0.05) + 4, (int)($W * 0.94) - 4, $hb - 4, $c1, $c2);
                // little accent ribbon tails
                $ac = se_col($im, $accent);
                imagefilledpolygon($im, [(int)($W * 0.06), $hb - (int)($H * 0.02), (int)($W * 0.14), $hb - (int)($H * 0.02), (int)($W * 0.10), $hb + (int)($H * 0.03)], $ac);
                imagefilledpolygon($im, [(int)($W * 0.94), $hb - (int)($H * 0.02), (int)($W * 0.86), $hb - (int)($H * 0.02), (int)($W * 0.90), $hb + (int)($H * 0.03)], $ac);
                break;

            default:
                se_gradient_v($im, 0, 0, $W, $H, $c1, $c2);
        }

        // Decide text colours + name baseline based on where the name sits.
        $onBand = ($layout['name_area'] === 'band');
        return [
            'name_color' => $onBand ? $onbg : $ink,
            'sub_color'  => $onBand ? se_mix($onbg, $c2, 0.15) : se_mix($ink, [255, 255, 255], 0.25),
        ];
    }

    /** Scatter faint accent dots for the 'full' style. */
    function se_decor_dots($im, int $W, int $H, array $accent, int $count): void
    {
        imagealphablending($im, true);
        mt_srand(42);
        for ($i = 0; $i < $count; $i++) {
            $x = mt_rand(0, $W); $y = mt_rand(0, $H);
            $rad = mt_rand((int)($W * 0.006), (int)($W * 0.03));
            $a = mt_rand(90, 118);
            $col = imagecolorallocatealpha($im, $accent[0], $accent[1], $accent[2], $a);
            imagefilledellipse($im, $x, $y, $rad, $rad, $col);
        }
        mt_srand();
    }

    /** Playful confetti (dots + triangles) for the 'festive' style. */
    function se_decor_confetti($im, int $W, int $H, array $accent, array $accent2): void
    {
        imagealphablending($im, true);
        mt_srand(7);
        for ($i = 0; $i < 40; $i++) {
            $x = mt_rand(0, $W); $y = mt_rand(0, $H);
            $s = mt_rand((int)($W * 0.008), (int)($W * 0.025));
            $c = ($i % 2) ? $accent : $accent2;
            $col = imagecolorallocatealpha($im, $c[0], $c[1], $c[2], mt_rand(60, 100));
            if ($i % 3 === 0) {
                imagefilledpolygon($im, [$x, $y - $s, $x + $s, $y + $s, $x - $s, $y + $s], $col);
            } else {
                imagefilledellipse($im, $x, $y, $s, $s, $col);
            }
        }
        mt_srand();
    }

    // -------------------------------------------------------------------------
    // Master renderer
    // -------------------------------------------------------------------------

    /**
     * Render the full standee into a GD truecolour image and return it.
     * Caller owns the image (imagedestroy / imagepng).
     *
     * @param array  $tenant  tenant row (name, logo, whatsapp_no/mobile…)
     * @param string $qrAbs   absolute path to the tenant's QR PNG
     * @param array  $design  resolved design (['layout'=>…, 'palette'=>…])
     * @param int    $W,$H    output canvas size in px
     */
    function se_render(array $tenant, string $qrAbs, array $design, int $W, int $H)
    {
        $layout = $design['layout'];
        $pal    = $design['palette'];

        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        $fontBold = se_font_bold();
        $fontReg  = se_font_reg();
        $fontGu   = se_font_gujarati();

        $accent = se_hex($pal['accent']);
        $ink    = se_hex($pal['ink']);

        $meta = se_paint_background($im, $layout, $pal, $W, $H);
        $nameColor = se_col($im, $meta['name_color']);
        $subColor  = se_col($im, $meta['sub_color']);

        $logoAbs = se_safe_logo($tenant['logo'] ?? '');
        $isSidebar = $layout['style'] === 'sidebar';

        // --- Header: brand badge + restaurant name ---------------------------
        $cx = $isSidebar ? (int)($W * 0.63) : (int)($W / 2);
        $contentW = $isSidebar ? (int)($W * 0.62) : (int)($W * 0.86);

        // Brand badge (skip for minimal to keep it airy; sidebar puts logo on bar).
        $badgeR = (int)($W * 0.085);
        if ($isSidebar) {
            se_brand_badge($im, $tenant, $logoAbs, (int)($W * 0.13), (int)($H * 0.12), (int)($W * 0.075), $accent, $fontBold);
        } elseif ($layout['style'] !== 'minimal') {
            se_brand_badge($im, $tenant, $logoAbs, $cx, (int)($H * 0.11), $badgeR, $accent, $fontBold);
        } else {
            se_brand_badge($im, $tenant, $logoAbs, $cx, (int)($H * 0.10), (int)($W * 0.07), $accent, $fontBold);
        }

        // Restaurant name (auto-fit + up to 2 lines).
        $name = trim((string)($tenant['restaurant_name'] ?? 'Restaurant'));
        if ($fontBold !== '' && $name !== '') {
            $startSize = $W * 0.075;
            $lines = se_wrap($name, $fontBold, $contentW, $startSize);
            // shrink until the widest line fits
            $size = $startSize;
            foreach ($lines as $ln) { $size = min($size, se_fit_size($ln, $fontBold, $contentW, $startSize, $W * 0.03)); }
            $nameTop = (int)($H * 0.205);
            $lh = (int)($size * 1.15);
            foreach ($lines as $k => $ln) {
                $y = $nameTop + $k * $lh;
                if ($isSidebar) { se_text_center($im, $ln, $fontBold, $size, $cx, $y, $nameColor); }
                else { se_text_center($im, $ln, $fontBold, $size, $cx, $y, $nameColor); }
            }
        }

        // --- QR card ---------------------------------------------------------
        // Position the white QR card in the lower-middle of the poster.
        $qrCardSize = (int)($W * 0.62);
        $qcx = $cx;
        $qcy = (int)($H * 0.58);
        $qx1 = $qcx - $qrCardSize / 2; $qy1 = $qcy - $qrCardSize / 2;
        $qx2 = $qcx + $qrCardSize / 2; $qy2 = $qcy + $qrCardSize / 2;
        $cardR = (int)($W * 0.035);

        se_card_shadow($im, (int)$qx1, (int)$qy1, (int)$qx2, (int)$qy2, $cardR);
        // accent border card then white inner
        se_round_rect($im, (int)$qx1, (int)$qy1, (int)$qx2, (int)$qy2, $cardR, se_col($im, $accent));
        $pad = (int)($W * 0.018);
        se_round_rect($im, (int)$qx1 + $pad, (int)$qy1 + $pad, (int)$qx2 - $pad, (int)$qy2 - $pad, (int)($cardR * 0.7), imagecolorallocate($im, 255, 255, 255));

        // QR image itself
        $qr = se_load_img($qrAbs);
        if ($qr) {
            $inner = $qrCardSize - 2 * $pad - (int)($W * 0.04);
            $qix = (int)($qcx - $inner / 2);
            $qiy = (int)($qcy - $inner / 2);
            imagecopyresampled($im, $qr, $qix, $qiy, 0, 0, $inner, $inner, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        // --- "Scan for Menu" + Gujarati --------------------------------------
        $scanY = (int)($qy2 + $H * 0.055);
        $onLightScan = in_array($layout['style'], ['full', 'diagonal', 'festive', 'frame'], true) ? false : true;
        // On full-colour styles the area below the card is coloured -> use onbg;
        // otherwise it is a light tint -> use ink.
        $scanColor = $onLightScan ? se_col($im, $ink) : se_col($im, se_hex($pal['onbg']));
        if ($layout['style'] === 'frame') { $scanColor = se_col($im, $ink); } // inside white frame
        if ($layout['style'] === 'band-bottom') { $scanColor = se_col($im, se_hex($pal['onbg'])); } // over bottom band

        if ($fontBold !== '') {
            se_text_center($im, 'SCAN FOR MENU', $fontBold, $W * 0.045, $cx, $scanY, $scanColor);
        }
        if ($fontGu !== '') {
            se_text_center($im, 'મેનુ માટે સ્કેન કરો', $fontGu, $W * 0.036, $cx, (int)($scanY + $H * 0.045), $scanColor);
        }

        // --- Contact line ----------------------------------------------------
        $contact = trim((string)($tenant['whatsapp_no'] ?? ($tenant['mobile'] ?? '')));
        if ($contact !== '' && $fontReg !== '') {
            se_text_center($im, 'Call / WhatsApp: ' . $contact, $fontReg, $W * 0.030, $cx, (int)($scanY + $H * 0.093), $scanColor);
        }

        // --- Footer branding -------------------------------------------------
        $footerY = $H - (int)($H * 0.03);
        $footStyle = $layout['style'];
        // Draw a subtle footer strip for readability on light layouts.
        if (in_array($footStyle, ['band-top', 'sidebar', 'circle', 'minimal', 'ribbon'], true)) {
            $barTop = $H - (int)($H * 0.06);
            imagefilledrectangle($im, 0, $barTop, $W, $H, se_col($im, se_hex($pal['c2'])));
            $footColor = se_col($im, se_hex($pal['onbg']));
            if ($fontReg !== '') { se_text_center($im, defined('POWERED_BY') ? POWERED_BY : 'Powered by AK Computer, Dwarka', $fontReg, $W * 0.026, (int)($W / 2), (int)($footerY + $H * 0.005), $footColor); }
        } else {
            $footColor = se_col($im, se_hex($pal['onbg']));
            if ($layout['style'] === 'frame') { $footColor = se_col($im, $ink); }
            if ($fontReg !== '') { se_text_center($im, defined('POWERED_BY') ? POWERED_BY : 'Powered by AK Computer, Dwarka', $fontReg, $W * 0.026, (int)($W / 2), $footerY, $footColor); }
        }

        return $im;
    }
}
