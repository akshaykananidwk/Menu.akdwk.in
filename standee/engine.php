<?php
/**
 * AK Menu System — Standee rendering engine (pure functions, no output).
 *
 * Turns a (tenant, QR, family, palette) tuple into a premium, print-ready GD
 * image of a menu standee / table-tent. Every STYLE FAMILY draws its own
 * distinct background + framing; a shared foreground then places the always-hero
 * restaurant name, the big QR, the call-to-action, gold stars, the contact and
 * the "Powered by AK Computer, Dwarka" footer.
 *
 * All geometry is a FRACTION of the canvas width/height, so the same code
 * renders a full 1080×1920 print asset and a 300px gallery thumbnail just by
 * changing $W/$H.
 *
 * Public entrypoint: se_render($tenant, $qrAbs, $design, $W, $H) -> GD image.
 */

require_once __DIR__ . '/designs.php';

if (!function_exists('se_render')) {

    // =========================================================================
    // Fonts
    // =========================================================================

    // Fonts are BUNDLED in libs/fonts first (shared cPanel hosts usually have no
    // system DejaVu fonts, which made all standee text silently disappear).
    function se_font_bold(): string
    {
        foreach ([
            LIB_PATH . '/fonts/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ] as $f) { if (is_file($f)) { return $f; } }
        return se_font_reg();
    }
    function se_font_reg(): string
    {
        foreach ([
            LIB_PATH . '/fonts/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            LIB_PATH . '/fonts/NotoSansGujarati-Regular.ttf', // last-resort: still a real TTF
        ] as $f) { if (is_file($f)) { return $f; } }
        return '';
    }
    function se_font_serif(): string
    {
        foreach ([
            LIB_PATH . '/fonts/DejaVuSerif-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSerif-Bold.ttf',
        ] as $f) { if (is_file($f)) { return $f; } }
        return se_font_bold();
    }
    /** Bundled Gujarati font for "મેનુ માટે સ્કેન કરો". */
    function se_font_gujarati(): string
    {
        $f = LIB_PATH . '/fonts/NotoSansGujarati-Regular.ttf';
        return is_file($f) ? $f : '';
    }

    // =========================================================================
    // Colour + geometry primitives
    // =========================================================================

    function se_hex(string $hex, array $fallback = [0, 0, 0]): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) { return $fallback; }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
    function se_mix(array $a, array $b, float $t): array
    {
        return [
            (int)round($a[0] + ($b[0] - $a[0]) * $t),
            (int)round($a[1] + ($b[1] - $a[1]) * $t),
            (int)round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }
    function se_col($im, array $c) { return imagecolorallocate($im, max(0, min(255, $c[0])), max(0, min(255, $c[1])), max(0, min(255, $c[2]))); }

    /** Vertical gradient fill. */
    function se_gradient_v($im, int $x1, int $y1, int $x2, int $y2, array $c1, array $c2): void
    {
        $h = max(1, $y2 - $y1);
        for ($y = 0; $y <= $h; $y++) {
            imagefilledrectangle($im, $x1, $y1 + $y, $x2, $y1 + $y, se_col($im, se_mix($c1, $c2, $y / $h)));
        }
    }

    /** Filled rounded rectangle. */
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

    /** Rounded rectangle OUTLINE of a given thickness (drawn as border ring). */
    function se_round_rect_border($im, int $x1, int $y1, int $x2, int $y2, int $r, int $bw, array $color): void
    {
        se_round_rect($im, $x1, $y1, $x2, $y2, $r, se_col($im, $color));
    }

    /** Soft drop shadow behind an upcoming rounded card. */
    function se_card_shadow($im, int $x1, int $y1, int $x2, int $y2, int $r): void
    {
        imagealphablending($im, true);
        $layers = 14;
        for ($i = $layers; $i >= 1; $i--) {
            $a = 116 - (int)(($layers - $i) * (100 / $layers));
            $col = imagecolorallocatealpha($im, 0, 0, 0, 127 - (int)($a * 0.30));
            $o = $i;
            se_round_rect($im, $x1 - $o, $y1 - $o + 10, $x2 + $o, $y2 + $o + 10, $r + $o, $col);
        }
    }

    function se_measure(string $text, string $font, float $size): array
    {
        $b = @imagettfbbox($size, 0, $font, $text);
        if ($b === false) { return [0, 0]; }
        return [abs($b[2] - $b[0]), abs($b[7] - $b[1])];
    }
    function se_text_center($im, string $text, string $font, float $size, int $cx, int $y, $color): void
    {
        if ($font === '' || $text === '') { return; }
        $b = @imagettfbbox($size, 0, $font, $text);
        if ($b === false) { return; }
        $x = (int)($cx - abs($b[2] - $b[0]) / 2 - $b[0]);
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }
    /** Letter-spaced centred text (elegant caps). */
    function se_text_center_tracked($im, string $text, string $font, float $size, int $cx, int $y, $color, float $track): void
    {
        if ($font === '' || $text === '') { return; }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $total = 0; $ws = [];
        foreach ($chars as $ch) { [$w] = se_measure($ch, $font, $size); $ws[] = $w; $total += $w + $track; }
        $total -= $track;
        $x = (int)($cx - $total / 2);
        foreach ($chars as $i => $ch) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $ch);
            $x += (int)($ws[$i] + $track);
        }
    }

    function se_fit_size(string $text, string $font, int $maxW, float $start, float $min): float
    {
        for ($s = $start; $s > $min; $s -= 1) {
            [$w] = se_measure($text, $font, $s);
            if ($w <= $maxW) { return $s; }
        }
        return $min;
    }

    /** Wrap into at most 2 lines that fit $maxW at $size. */
    function se_wrap(string $text, string $font, int $maxW, float $size): array
    {
        [$w] = se_measure($text, $font, $size);
        if ($w <= $maxW) { return [$text]; }
        $words = preg_split('/\s+/', trim($text));
        if (count($words) < 2) { return [$text]; }
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
     * Fit a restaurant name into a box: wrap to <=2 lines and shrink until it
     * fits both $maxW and $maxH. Returns ['lines'=>[], 'size'=>float, 'lh'=>int].
     */
    function se_name_fit(string $name, string $font, int $maxW, int $maxH, float $start, float $min): array
    {
        for ($s = $start; $s >= $min; $s -= 2) {
            $lines = se_wrap($name, $font, $maxW, $s);
            $lh = (int)($s * 1.16);
            $fits = (count($lines) * $lh) <= $maxH;
            foreach ($lines as $ln) { [$w] = se_measure($ln, $font, $s); if ($w > $maxW) { $fits = false; break; } }
            if ($fits) { return ['lines' => $lines, 'size' => $s, 'lh' => $lh]; }
        }
        $lines = se_wrap($name, $font, $maxW, $min);
        return ['lines' => $lines, 'size' => $min, 'lh' => (int)($min * 1.16)];
    }

    /** Draw a centred, fitted name block; returns the fit info used. */
    function se_draw_name_block($im, string $name, string $font, int $cx, int $centerY, int $maxW, int $maxH, float $start, float $min, $color): array
    {
        $fit = se_name_fit($name, $font, $maxW, $maxH, $start, $min);
        $n = count($fit['lines']);
        $blockH = $n * $fit['size'] + ($n - 1) * ($fit['lh'] - $fit['size']);
        $y = (int)($centerY - $blockH / 2 + $fit['size'] * 0.82);
        foreach ($fit['lines'] as $ln) {
            se_text_center($im, $ln, $font, $fit['size'], $cx, $y, $color);
            $y += $fit['lh'];
        }
        return $fit;
    }

    // =========================================================================
    // Logo / brand badge
    // =========================================================================

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
    function se_load_img(string $path)
    {
        if ($path === '' || !is_file($path)) { return null; }
        $img = @imagecreatefromstring(@file_get_contents($path));
        if (!$img) { return null; }
        imagealphablending($img, true);
        return $img;
    }

    /** Restaurant initials (up to 2 letters). */
    function se_initials(?array $tenant): string
    {
        $name = trim((string)($tenant['restaurant_name'] ?? 'R'));
        $ini = '';
        foreach (preg_split('/\s+/', $name) as $p) {
            if ($p !== '') { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
            if (mb_strlen($ini) >= 2) { break; }
        }
        return $ini !== '' ? $ini : 'R';
    }

    /** White circular brand badge holding the logo (cover-fit) or the initials. */
    function se_brand_badge($im, ?array $tenant, string $logoAbs, int $cx, int $cy, int $r, array $ring, string $fontBold): void
    {
        imagealphablending($im, true);
        imagefilledellipse($im, $cx, $cy, $r * 2 + 14, $r * 2 + 14, se_col($im, $ring));
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, imagecolorallocate($im, 255, 255, 255));

        $logo = se_load_img($logoAbs);
        if ($logo) {
            $d = (int)($r * 1.7);
            $lw = imagesx($logo); $lh = imagesy($logo);
            $tmp = imagecreatetruecolor($d, $d);
            imagealphablending($tmp, false); imagesavealpha($tmp, true);
            imagefilledrectangle($tmp, 0, 0, $d, $d, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
            imagealphablending($tmp, true);
            $scale = max($d / $lw, $d / $lh);
            $nw = (int)($lw * $scale); $nh = (int)($lh * $scale);
            imagecopyresampled($tmp, $logo, (int)(($d - $nw) / 2), (int)(($d - $nh) / 2), 0, 0, $nw, $nh, $lw, $lh);
            imagecopy($im, $tmp, (int)($cx - $d / 2), (int)($cy - $d / 2), 0, 0, $d, $d);
            imagedestroy($tmp); imagedestroy($logo);
        } elseif ($fontBold !== '') {
            se_text_center($im, se_initials($tenant), $fontBold, $r * 0.95, $cx, (int)($cy + $r * 0.36), imagecolorallocate($im, 51, 51, 51));
        }
    }

    // =========================================================================
    // Decorative primitives
    // =========================================================================

    /** Five-point star. */
    function se_star($im, int $cx, int $cy, int $r, $color): void
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $ang = -M_PI / 2 + $i * M_PI / 5;
            $rad = ($i % 2 === 0) ? $r : $r * 0.42;
            $pts[] = (int)($cx + $rad * cos($ang));
            $pts[] = (int)($cy + $rad * sin($ang));
        }
        imagefilledpolygon($im, $pts, $color);
    }
    /** A centred row of 5 gold stars. */
    function se_star_row($im, int $cx, int $y, int $r, array $gold): void
    {
        $col = se_col($im, $gold);
        $gap = (int)($r * 2.5);
        for ($i = -2; $i <= 2; $i++) { se_star($im, $cx + $i * $gap, $y, $r, $col); }
    }

    /** Translucent soft blob (for the gradient-mesh family). */
    function se_blob($im, int $cx, int $cy, int $r, array $color, int $alpha): void
    {
        imagealphablending($im, true);
        for ($k = 3; $k >= 1; $k--) {
            $col = imagecolorallocatealpha($im, $color[0], $color[1], $color[2], min(127, $alpha + ($k - 1) * 22));
            imagefilledellipse($im, $cx, $cy, (int)($r * (0.6 + $k * 0.16)), (int)($r * (0.6 + $k * 0.16)), $col);
        }
    }

    /** Wavy-bottom coloured band across the top. */
    function se_wave_top($im, int $W, int $baseH, int $amp, array $c1, array $c2, array $page): void
    {
        se_gradient_v($im, 0, 0, $W, $baseH + $amp, $c1, $c2);
        $pageCol = se_col($im, $page);
        for ($x = 0; $x <= $W; $x++) {
            $wy = (int)($baseH + $amp * sin($x / $W * 2 * M_PI * 1.5));
            imagefilledrectangle($im, $x, $wy, $x, $baseH + $amp, $pageCol);
        }
    }
    /** Wavy-top coloured band across the bottom. */
    function se_wave_bottom($im, int $W, int $H, int $bandH, int $amp, array $c1, array $c2, array $page): void
    {
        $top = $H - $bandH;
        se_gradient_v($im, 0, $top - $amp, $W, $H, $c1, $c2);
        $pageCol = se_col($im, $page);
        for ($x = 0; $x <= $W; $x++) {
            $wy = (int)($top - $amp * sin($x / $W * 2 * M_PI * 1.5));
            imagefilledrectangle($im, $x, $top - $amp, $x, $wy, $pageCol);
        }
    }

    // =========================================================================
    // QR block (framing varies by style)
    // =========================================================================

    function se_qr_block($im, string $qrAbs, int $cx, int $cy, int $qs, string $style, array $frame, array $page): void
    {
        $white = imagecolorallocate($im, 255, 255, 255);
        $frameCol = se_col($im, $frame);
        $x1 = (int)($cx - $qs / 2); $y1 = (int)($cy - $qs / 2);
        $x2 = (int)($cx + $qs / 2); $y2 = (int)($cy + $qs / 2);
        $qr = se_load_img($qrAbs);

        $drawQr = function ($side) use ($im, $qr, $cx, $cy) {
            if (!$qr) { return; }
            $ix = (int)($cx - $side / 2); $iy = (int)($cy - $side / 2);
            imagecopyresampled($im, $qr, $ix, $iy, 0, 0, $side, $side, imagesx($qr), imagesy($qr));
        };

        switch ($style) {
            case 'seal': {
                // Concentric circular seal with scalloped edge.
                $R = (int)($qs / 2);
                imagealphablending($im, true);
                // scallops
                $sc = se_col($im, $frame);
                $n = 28;
                for ($i = 0; $i < $n; $i++) {
                    $ang = $i / $n * 2 * M_PI;
                    $sx = (int)($cx + ($R + $R * 0.06) * cos($ang));
                    $sy = (int)($cy + ($R + $R * 0.06) * sin($ang));
                    imagefilledellipse($im, $sx, $sy, (int)($R * 0.14), (int)($R * 0.14), $sc);
                }
                imagefilledellipse($im, $cx, $cy, $R * 2, $R * 2, $frameCol);        // ring
                imagefilledellipse($im, $cx, $cy, (int)($R * 1.82), (int)($R * 1.82), $white); // white disc
                $inner = se_col($im, se_mix($frame, [255, 255, 255], 0.5));
                imagesetthickness($im, max(2, (int)($qs * 0.008)));
                imageellipse($im, $cx, $cy, (int)($R * 1.55), (int)($R * 1.55), $inner);
                imagesetthickness($im, 1);
                $drawQr((int)($qs * 0.62));
                break;
            }
            case 'arch': {
                // Arch / window: semicircle top over a rectangle body.
                imagealphablending($im, true);
                $r = (int)($qs / 2);
                $topY = (int)($cy - $qs * 0.62);
                // outer accent arch
                imagefilledarc($im, $cx, $topY + $r, $qs, $qs, 180, 360, $frameCol, IMG_ARC_PIE);
                imagefilledrectangle($im, $x1, $topY + $r, $x2, $y2 + (int)($qs * 0.12), $frameCol);
                // inner white arch (inset by border)
                $b = (int)($qs * 0.05);
                imagefilledarc($im, $cx, $topY + $r, $qs - 2 * $b, $qs - 2 * $b, 180, 360, $white, IMG_ARC_PIE);
                imagefilledrectangle($im, $x1 + $b, $topY + $r, $x2 - $b, $y2 + (int)($qs * 0.12) - $b, $white);
                $drawQr((int)($qs * 0.74));
                break;
            }
            case 'double': {
                // Elegant white card with a thin double-line frame.
                se_card_shadow($im, $x1, $y1, $x2, $y2, (int)($qs * 0.05));
                se_round_rect($im, $x1, $y1, $x2, $y2, (int)($qs * 0.05), $white);
                imagesetthickness($im, max(2, (int)($qs * 0.006)));
                $g = (int)($qs * 0.03);
                imagerectangle($im, $x1 + $g, $y1 + $g, $x2 - $g, $y2 - $g, $frameCol);
                imagerectangle($im, $x1 + (int)($g * 1.9), $y1 + (int)($g * 1.9), $x2 - (int)($g * 1.9), $y2 - (int)($g * 1.9), $frameCol);
                imagesetthickness($im, 1);
                $drawQr((int)($qs * 0.72));
                break;
            }
            case 'card':
            default: {
                se_card_shadow($im, $x1, $y1, $x2, $y2, (int)($qs * 0.06));
                se_round_rect($im, $x1, $y1, $x2, $y2, (int)($qs * 0.06), $frameCol);        // accent border
                $p = (int)($qs * 0.035);
                se_round_rect($im, $x1 + $p, $y1 + $p, $x2 - $p, $y2 - $p, (int)($qs * 0.04), $white);
                $drawQr((int)($qs * 0.80));
                break;
            }
        }
        if ($qr) { imagedestroy($qr); }
    }

    // =========================================================================
    // Editable per-tenant configuration (text + language overrides)
    // =========================================================================

    /** Default standee text config for a tenant (all-English by default). */
    function se_config_defaults(array $tenant): array
    {
        $mobile = trim((string)($tenant['whatsapp_no'] ?? ($tenant['mobile'] ?? '')));
        return [
            'heading'      => 'WELCOME TO',
            'name'         => (string)($tenant['restaurant_name'] ?? ''),
            'cta'          => 'SCAN FOR MENU',
            'lang'         => 'en',           // en | gu | both  (English is the default)
            'tagline'      => '',
            'show_contact' => true,
            'contact'      => $mobile !== '' ? 'Call / WhatsApp: ' . $mobile : '',
            'show_stars'   => true,
            'footer_extra' => '',
        ];
    }

    /** Strip control chars, trim and length-cap a free-text field. */
    function se_clean_text($v, int $max): string
    {
        $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$v);
        $v = trim((string)$v);
        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    /** Pull only the recognised, sanitised config keys out of a raw array. */
    function se_extract_overrides(array $raw): array
    {
        $o = [];
        $text = ['heading' => 40, 'name' => 80, 'cta' => 40, 'tagline' => 60, 'contact' => 80, 'footer_extra' => 60];
        foreach ($text as $k => $max) {
            if (array_key_exists($k, $raw)) { $o[$k] = se_clean_text($raw[$k], $max); }
        }
        if (array_key_exists('lang', $raw)) {
            $l = strtolower(trim((string)$raw['lang']));
            $o['lang'] = in_array($l, ['en', 'gu', 'both'], true) ? $l : 'en';
        }
        foreach (['show_contact', 'show_stars'] as $b) {
            if (array_key_exists($b, $raw)) { $o[$b] = in_array((string)$raw[$b], ['1', 'true', 'on', 'yes'], true); }
        }
        return $o;
    }

    /**
     * Resolve the effective config.
     * Priority: raw overrides (GET/POST live preview) > saved per-tenant JSON > defaults.
     */
    function se_resolve_config(array $tenant, ?int $tid, array $raw): array
    {
        $cfg = se_config_defaults($tenant);
        if ($tid) {
            $saved = getSetting('standee_cfg_' . $tid, '');
            if ($saved) {
                $j = json_decode((string)$saved, true);
                if (is_array($j)) { $cfg = array_merge($cfg, se_extract_overrides($j)); }
            }
        }
        return array_merge($cfg, se_extract_overrides($raw));
    }

    // =========================================================================
    // Name hero pill
    // =========================================================================

    /**
     * The visual hero: a rounded pill with an optional heading label and the
     * BIG auto-fitting restaurant name. Long names wrap to 2 lines and shrink.
     */
    function se_name_hero($im, string $printName, string $heading, int $cx, int $y1, int $y2, int $boxW, array $o, int $W): void
    {
        $x1 = (int)($cx - $boxW / 2); $x2 = (int)($cx + $boxW / 2);
        $r  = (int)(($y2 - $y1) * 0.32);

        // Pill: border ring then inner fill (gives a clean rounded border).
        if (!empty($o['pill_border'])) {
            $bw = (int)max(3, $W * 0.006);
            se_round_rect($im, $x1, $y1, $x2, $y2, $r, se_col($im, $o['pill_border']));
            se_round_rect($im, $x1 + $bw, $y1 + $bw, $x2 - $bw, $y2 - $bw, (int)($r * 0.85), se_col($im, $o['pill_bg']));
        } else {
            se_round_rect($im, $x1, $y1, $x2, $y2, $r, se_col($im, $o['pill_bg']));
        }

        $fontReg  = se_font_reg();
        $fontBold = $o['name_font'] ?? se_font_bold();

        // Optional heading label near the top of the pill (blank => hidden).
        $hasHeading = trim($heading) !== '';
        if ($hasHeading && $fontReg !== '') {
            $labelY = (int)($y1 + ($y2 - $y1) * 0.30);
            se_text_center_tracked($im, $heading, $fontReg, $W * 0.026, $cx, $labelY, se_col($im, $o['welcome_text']), $W * 0.006);
        }

        // Big restaurant name filling the rest of the pill.
        $name = trim($printName) !== '' ? trim($printName) : 'Restaurant';
        $nameTop = (int)($y1 + ($y2 - $y1) * ($hasHeading ? 0.40 : 0.16));
        $nameCY  = (int)(($nameTop + $y2) / 2);
        $maxW = (int)($boxW * 0.86);
        $maxH = (int)($y2 - $nameTop - ($y2 - $y1) * 0.10);
        se_draw_name_block($im, $name, $fontBold, $cx, $nameCY, $maxW, $maxH, $W * 0.072, $W * 0.032, se_col($im, $o['name_text']));
    }

    // =========================================================================
    // Backgrounds — one per style family (each returns the foreground $opts)
    // =========================================================================

    /** Build sensible default foreground options for a palette. */
    function se_default_opts(array $pal, int $W): array
    {
        $c1 = se_hex($pal['c1']); $c2 = se_hex($pal['c2']); $accent = se_hex($pal['accent']);
        $ink = se_hex($pal['ink']); $white = [255, 255, 255];
        $gold = [231, 177, 10];
        return [
            'cx' => (int)($W / 2),
            'cw' => (int)($W * 0.82),
            'qr_size' => (int)($W * 0.56),
            'qr_style' => 'card',
            'qr_frame' => $accent,
            'page' => $white,
            // name pill
            'pill_bg' => se_mix($c1, $white, 0.88),
            'pill_border' => $c1,
            'welcome_text' => se_mix($ink, $white, 0.30),
            'welcome_text_label' => 'WELCOME TO',
            'name_text' => $ink,
            'name_font' => se_font_bold(),
            // texts
            'cta_color' => $c1,
            'gu_color' => $ink,
            'contact_color' => se_mix($ink, $white, 0.10),
            'footer_color' => se_mix($ink, $white, 0.30),
            'star_gold' => $gold,
            'badge_ring' => $accent,
            'skip_badge' => false,
            'skip_name' => false,
            'badge_cy' => (int)($W / 2 * 0), // placeholder, real value set in compose
        ];
    }

    function se_bg($im, string $style, array $pal, int $W, int $H): array
    {
        $c1 = se_hex($pal['c1']); $c2 = se_hex($pal['c2']); $accent = se_hex($pal['accent']);
        $ink = se_hex($pal['ink']); $white = [255, 255, 255];
        $soft = se_mix($c1, $white, 0.90);
        $o = se_default_opts($pal, $W);

        // Every background starts from a clean page fill.
        imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));

        switch ($style) {

            case 'corners': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $soft));
                $s = (int)($W * 0.42);
                imagefilledpolygon($im, [0, 0, $s, 0, 0, $s], se_col($im, $c1));
                imagefilledpolygon($im, [$W, 0, $W - $s, 0, $W, $s], se_col($im, $accent));
                imagefilledpolygon($im, [0, $H, $s, $H, 0, $H - $s], se_col($im, $accent));
                imagefilledpolygon($im, [$W, $H, $W - $s, $H, $W, $H - $s], se_col($im, $c2));
                // floating white card
                $m = (int)($W * 0.055);
                se_card_shadow($im, $m, (int)($H * 0.03), $W - $m, (int)($H * 0.97), (int)($W * 0.05));
                se_round_rect($im, $m, (int)($H * 0.03), $W - $m, (int)($H * 0.97), (int)($W * 0.05), se_col($im, $white));
                $o['cw'] = (int)($W * 0.78);
                break;
            }

            case 'menucard': {
                se_gradient_v($im, 0, 0, $W, $H, $c1, $c2);
                se_blob($im, (int)($W * 0.15), (int)($H * 0.12), (int)($W * 0.4), $accent, 96);
                se_blob($im, (int)($W * 0.9), (int)($H * 0.9), (int)($W * 0.45), $c1, 96);
                $m = (int)($W * 0.05);
                se_card_shadow($im, $m, (int)($H * 0.04), $W - $m, (int)($H * 0.96), (int)($W * 0.055));
                se_round_rect($im, $m, (int)($H * 0.04), $W - $m, (int)($H * 0.96), (int)($W * 0.055), se_col($im, $white));
                $o['cw'] = (int)($W * 0.78);
                break;
            }

            case 'mesh': {
                se_gradient_v($im, 0, 0, $W, $H, se_mix($c1, $white, 0.15), $c2);
                se_blob($im, (int)($W * 0.2), (int)($H * 0.18), (int)($W * 0.55), $accent, 90);
                se_blob($im, (int)($W * 0.85), (int)($H * 0.3), (int)($W * 0.5), $c1, 88);
                se_blob($im, (int)($W * 0.75), (int)($H * 0.85), (int)($W * 0.6), $accent, 92);
                se_blob($im, (int)($W * 0.12), (int)($H * 0.8), (int)($W * 0.45), $c2, 86);
                $m = (int)($W * 0.06);
                se_card_shadow($im, $m, (int)($H * 0.045), $W - $m, (int)($H * 0.965), (int)($W * 0.06));
                se_round_rect($im, $m, (int)($H * 0.045), $W - $m, (int)($H * 0.965), (int)($W * 0.06), se_col($im, $white));
                $o['cw'] = (int)($W * 0.76);
                break;
            }

            case 'ribbon': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $soft));
                // slanted ribbon band near the top with the name on it
                $p = [0, (int)($H * 0.15), $W, (int)($H * 0.08), $W, (int)($H * 0.30), 0, (int)($H * 0.37)];
                imagefilledpolygon($im, $p, se_col($im, $c1));
                imagealphablending($im, true);
                $wedge = imagecolorallocatealpha($im, 255, 255, 255, 108);
                imagefilledpolygon($im, [0, (int)($H * 0.15), $W, (int)($H * 0.08), $W, (int)($H * 0.17), 0, (int)($H * 0.24)], $wedge);
                // ribbon tails
                imagefilledpolygon($im, [0, (int)($H * 0.37), (int)($W * 0.10), (int)($H * 0.35), 0, (int)($H * 0.43)], se_col($im, $c2));
                imagefilledpolygon($im, [$W, (int)($H * 0.30), (int)($W * 0.90), (int)($H * 0.285), $W, (int)($H * 0.36)], se_col($im, $c2));
                $o['skip_name'] = true;  // the name is drawn on the ribbon below
                $o['qr_frame'] = $c1;
                break;
            }

            case 'wave': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $soft));
                se_wave_top($im, $W, (int)($H * 0.14), (int)($H * 0.025), $c1, $c2, $soft);
                se_wave_bottom($im, $W, $H, (int)($H * 0.11), (int)($H * 0.022), $c2, $c1, $soft);
                // Contact + footer sit on the dark bottom band -> use light text.
                $onbg = se_hex($pal['onbg']);
                $o['contact_color'] = $onbg;
                $o['footer_color'] = se_mix($onbg, $c1, 0.12);
                break;
            }

            case 'confetti': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));
                se_confetti_border($im, $W, $H, [$c1, $accent, $c2]);
                break;
            }

            case 'finedining': {
                $cream = se_mix($accent, $white, 0.78);
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $cream));
                // thin double-line frame
                imagesetthickness($im, max(2, (int)($W * 0.004)));
                $gold = se_col($im, $c1 === [43, 43, 43] ? $accent : se_mix($c1, [0, 0, 0], 0.1));
                $goldCol = se_col($im, se_mix($accent, [90, 70, 20], 0.35));
                $m1 = (int)($W * 0.05); $m2 = (int)($W * 0.065);
                imagerectangle($im, $m1, $m1, $W - $m1, $H - $m1, $goldCol);
                imagerectangle($im, $m2, $m2, $W - $m2, $H - $m2, $goldCol);
                imagesetthickness($im, 1);
                // small corner diamonds
                foreach ([[$m2, $m2], [$W - $m2, $m2], [$m2, $H - $m2], [$W - $m2, $H - $m2]] as $cnr) {
                    se_diamond($im, $cnr[0], $cnr[1], (int)($W * 0.018), $goldCol);
                }
                $o['pill_bg'] = $cream;
                $o['pill_border'] = se_mix($accent, [90, 70, 20], 0.35);
                $o['welcome_text_label'] = 'WELCOME TO';
                $o['cta_color'] = se_mix($ink, [0, 0, 0], 0.0);
                $o['qr_style'] = 'double';
                $o['qr_frame'] = se_mix($accent, [90, 70, 20], 0.35);
                $o['star_gold'] = se_mix($accent, [120, 90, 20], 0.3);
                $o['badge_ring'] = se_mix($accent, [120, 90, 20], 0.3);
                $o['name_font'] = se_font_serif();
                break;
            }

            case 'memphis': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));
                se_memphis($im, $W, $H, [$c1, $accent, $c2, se_mix($c1, $white, 0.5)]);
                break;
            }

            case 'seal': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $soft));
                // faint concentric rings behind everything
                imagealphablending($im, true);
                $ring = imagecolorallocatealpha($im, $c1[0], $c1[1], $c1[2], 112);
                for ($i = 0; $i < 4; $i++) { imageellipse($im, (int)($W / 2), (int)($H * 0.56), (int)($W * (0.7 + $i * 0.14)), (int)($W * (0.7 + $i * 0.14)), $ring); }
                $o['qr_style'] = 'seal';
                break;
            }

            case 'sidebar': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $white));
                $bw = (int)($W * 0.24);
                se_gradient_v($im, 0, 0, $bw, $H, $c1, $c2);
                imagefilledrectangle($im, $bw, 0, $bw + (int)($W * 0.012), $H, se_col($im, $accent));
                $o['cx'] = (int)($bw + ($W - $bw) / 2);
                $o['cw'] = (int)(($W - $bw) * 0.86);
                $o['qr_size'] = (int)($W * 0.50);
                $o['skip_badge'] = true; // badge drawn on the bar
                break;
            }

            case 'rangoli': {
                imagefilledrectangle($im, 0, 0, $W, $H, se_col($im, $soft));
                foreach ([[0, 0], [$W, 0], [0, $H], [$W, $H]] as $cnr) {
                    se_rangoli($im, $cnr[0], $cnr[1], (int)($W * 0.26), $c1, $accent);
                }
                $o['qr_frame'] = $c1;
                break;
            }

            case 'arch': {
                se_gradient_v($im, 0, 0, $W, $H, se_mix($c1, $white, 0.82), $white);
                // subtle ground shadow
                imagealphablending($im, true);
                $g = imagecolorallocatealpha($im, $c2[0], $c2[1], $c2[2], 116);
                imagefilledrectangle($im, 0, (int)($H * 0.90), $W, $H, $g);
                $o['qr_style'] = 'arch';
                $o['qr_frame'] = $c1;
                break;
            }
        }
        return $o;
    }

    /** A small filled diamond. */
    function se_diamond($im, int $cx, int $cy, int $r, $color): void
    {
        imagefilledpolygon($im, [$cx, $cy - $r, $cx + $r, $cy, $cx, $cy + $r, $cx - $r, $cy], $color);
    }

    /** Confetti dots/rings/triangles around the border only (centre stays clean). */
    function se_confetti_border($im, int $W, int $H, array $cols): void
    {
        imagealphablending($im, true);
        mt_srand(11);
        $safeX1 = (int)($W * 0.13); $safeX2 = (int)($W * 0.87);
        $safeY1 = (int)($H * 0.10); $safeY2 = (int)($H * 0.92);
        $placed = 0; $tries = 0;
        while ($placed < 70 && $tries < 900) {
            $tries++;
            $x = mt_rand(0, $W); $y = mt_rand(0, $H);
            if ($x > $safeX1 && $x < $safeX2 && $y > $safeY1 && $y < $safeY2) { continue; }
            $c = $cols[$placed % count($cols)];
            $s = mt_rand((int)($W * 0.008), (int)($W * 0.022));
            $col = se_col($im, $c);
            $k = $placed % 3;
            if ($k === 0) { imagefilledellipse($im, $x, $y, $s, $s, $col); }
            elseif ($k === 1) { imagesetthickness($im, 3); imageellipse($im, $x, $y, $s, $s, $col); imagesetthickness($im, 1); }
            else { imagefilledpolygon($im, [$x, $y - $s, $x + $s, $y + $s, $x - $s, $y + $s], $col); }
            $placed++;
        }
        mt_srand();
    }

    /** Memphis-style geometric shapes clustered in the four corners. */
    function se_memphis($im, int $W, int $H, array $cols): void
    {
        imagealphablending($im, true);
        $corners = [[0.0, 0.0], [1.0, 0.0], [0.0, 1.0], [1.0, 1.0]];
        foreach ($corners as $ci => $cn) {
            $bx = (int)($cn[0] * $W); $by = (int)($cn[1] * $H);
            $dir = $cn[0] < 0.5 ? 1 : -1; $diy = $cn[1] < 0.5 ? 1 : -1;
            $c = $cols[$ci % count($cols)];
            // big quarter circle
            imagefilledarc($im, $bx, $by, (int)($W * 0.30), (int)($W * 0.30), 0, 360, se_col($im, $c), IMG_ARC_PIE);
            // dots grid
            $dc = se_col($im, $cols[($ci + 1) % count($cols)]);
            for ($i = 1; $i <= 3; $i++) {
                for ($j = 1; $j <= 3; $j++) {
                    imagefilledellipse($im, $bx + $dir * (int)($W * (0.30 + $i * 0.035)), $by + $diy * (int)($W * ($j * 0.035)), (int)($W * 0.012), (int)($W * 0.012), $dc);
                }
            }
            // triangle
            $tc = se_col($im, $cols[($ci + 2) % count($cols)]);
            $tx = $bx + $dir * (int)($W * 0.10); $ty = $by + $diy * (int)($W * 0.34);
            $ts = (int)($W * 0.05);
            imagefilledpolygon($im, [$tx, $ty - $ts, $tx + $ts, $ty + $ts, $tx - $ts, $ty + $ts], $tc);
            // zigzag line
            imagesetthickness($im, max(3, (int)($W * 0.006)));
            $zc = se_col($im, $cols[($ci + 3) % count($cols)]);
            $zx = $bx + $dir * (int)($W * 0.34); $zy = $by + $diy * (int)($W * 0.16);
            for ($i = 0; $i < 4; $i++) {
                imageline($im, $zx + $dir * $i * (int)($W * 0.03), $zy + ($i % 2) * (int)($W * 0.03), $zx + $dir * ($i + 1) * (int)($W * 0.03), $zy + (($i + 1) % 2) * (int)($W * 0.03), $zc);
            }
            imagesetthickness($im, 1);
        }
    }

    /** A rangoli / mandala style corner motif (concentric arcs + petals). */
    function se_rangoli($im, int $cx, int $cy, int $r, array $c1, array $accent): void
    {
        imagealphablending($im, true);
        imagesetthickness($im, max(3, (int)($r * 0.03)));
        $a = se_col($im, $accent); $p = se_col($im, $c1);
        for ($i = 1; $i <= 4; $i++) {
            imagearc($im, $cx, $cy, (int)($r * $i * 0.42), (int)($r * $i * 0.42), 0, 360, ($i % 2) ? $a : $p);
        }
        imagesetthickness($im, 1);
        // petals around the arc
        $n = 12;
        for ($i = 0; $i < $n; $i++) {
            $ang = $i / $n * 2 * M_PI;
            $px = (int)($cx + $r * 0.86 * cos($ang));
            $py = (int)($cy + $r * 0.86 * sin($ang));
            imagefilledellipse($im, $px, $py, (int)($r * 0.10), (int)($r * 0.10), ($i % 2) ? $a : $p);
        }
        // solid centre
        imagefilledellipse($im, $cx, $cy, (int)($r * 0.16), (int)($r * 0.16), $p);
    }

    // =========================================================================
    // Master renderer
    // =========================================================================

    /**
     * Render the full standee into a GD truecolour image and return it.
     * @param array  $tenant  tenant row
     * @param string $qrAbs   absolute path to the tenant's QR PNG
     * @param array  $design  resolved design (['family'=>…, 'palette'=>…])
     * @param int    $W,$H    output size in px
     * @param array  $cfg     resolved text/language config (see se_resolve_config).
     *                        When empty, defaults for this tenant are used.
     */
    function se_render(array $tenant, string $qrAbs, array $design, int $W, int $H, array $cfg = [])
    {
        $family = $design['family'];
        $pal     = $design['palette'];
        $style   = $family['style'] ?? 'corners';

        // Merge in defaults so callers may pass a partial (or empty) config.
        $cfg = array_merge(se_config_defaults($tenant), $cfg);
        $printName = trim((string)$cfg['name']) !== '' ? (string)$cfg['name'] : (string)($tenant['restaurant_name'] ?? 'Restaurant');
        $tenantR = $tenant; $tenantR['restaurant_name'] = $printName; // badge initials follow the printed name

        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        $fontBold = se_font_bold();
        $fontReg  = se_font_reg();
        $fontGu   = se_font_gujarati();

        // 1) Background + per-style foreground options.
        $o = se_bg($im, $style, $pal, $W, $H);
        $cx = $o['cx'];
        $taglineColor = se_col($im, se_mix(se_hex($pal['ink']), [255, 255, 255], 0.12));

        // 2) Brand badge (top). Sidebar draws its own on the bar.
        $logoAbs = se_safe_logo($tenant['logo'] ?? '');
        if (empty($o['skip_badge'])) {
            se_brand_badge($im, $tenantR, $logoAbs, $cx, (int)($H * 0.085), (int)($W * 0.062), $o['badge_ring'], $fontBold);
        } else {
            se_brand_badge($im, $tenantR, $logoAbs, (int)($W * 0.12), (int)($H * 0.11), (int)($W * 0.072), $o['badge_ring'], $fontBold);
        }

        // 3) Name — hero pill, OR on the ribbon for the ribbon family.
        if (empty($o['skip_name'])) {
            se_name_hero($im, $printName, (string)$cfg['heading'], $cx, (int)($H * 0.15), (int)($H * 0.285), $o['cw'], $o, $W);
        } else {
            // Ribbon: optional heading small then the big name, both on the band.
            $onbg = se_hex($pal['onbg']);
            if (trim((string)$cfg['heading']) !== '' && $fontReg !== '') {
                se_text_center_tracked($im, (string)$cfg['heading'], $fontReg, $W * 0.026, $cx, (int)($H * 0.165), se_col($im, $onbg), $W * 0.006);
            }
            se_draw_name_block($im, $printName, $fontBold, $cx, (int)($H * 0.235), (int)($W * 0.82), (int)($H * 0.14), $W * 0.072, $W * 0.032, se_col($im, $onbg));
        }

        // 3b) Optional tagline line under the name.
        if (trim((string)$cfg['tagline']) !== '' && $fontReg !== '') {
            $tagY = (int)($H * ($style === 'ribbon' ? 0.44 : 0.335));
            $tagSize = se_fit_size((string)$cfg['tagline'], $fontReg, (int)($o['cw'] * 0.92), $W * 0.034, $W * 0.020);
            se_text_center($im, (string)$cfg['tagline'], $fontReg, $tagSize, $cx, $tagY, $taglineColor);
        }

        // 4) QR block.
        $qs  = $o['qr_size'];
        $qcy = (int)($H * 0.55);
        se_qr_block($im, $qrAbs, $cx, $qcy, $qs, $o['qr_style'], $o['qr_frame'], $o['page']);

        // Sidebar tagline down the coloured bar.
        if (!empty($o['skip_badge']) && $style === 'sidebar' && $fontBold !== '') {
            $onbg = se_hex($pal['onbg']);
            imagettftext($im, $W * 0.032, 90, (int)($W * 0.15), (int)($H * 0.80), se_col($im, $onbg), $fontBold, 'SCAN  FOR  MENU');
        }

        // 5) Call to action — language-aware. English is the default and
        //    English-only fully removes the Gujarati line.
        $lang = in_array($cfg['lang'], ['en', 'gu', 'both'], true) ? $cfg['lang'] : 'en';
        if ($fontGu === '' && $lang !== 'en') { $lang = 'en'; } // no Gujarati font -> fall back
        $ctaText = trim((string)$cfg['cta']) !== '' ? (string)$cfg['cta'] : 'SCAN FOR MENU';
        $ctaY = (int)($H * 0.775);
        $guText = 'મેનુ માટે સ્કેન કરો';
        if ($lang === 'gu') {
            if ($fontGu !== '') { se_text_center($im, $guText, $fontGu, $W * 0.044, $cx, $ctaY, se_col($im, $o['cta_color'])); }
        } elseif ($lang === 'both') {
            if ($fontBold !== '') { se_text_center($im, $ctaText, $fontBold, $W * 0.048, $cx, $ctaY, se_col($im, $o['cta_color'])); }
            if ($fontGu !== '')   { se_text_center($im, $guText, $fontGu, $W * 0.036, $cx, (int)($ctaY + $H * 0.040), se_col($im, $o['gu_color'])); }
        } else { // en (default)
            if ($fontBold !== '') { se_text_center($im, $ctaText, $fontBold, $W * 0.048, $cx, $ctaY, se_col($im, $o['cta_color'])); }
        }

        // 6) Gold stars (optional).
        if (!empty($cfg['show_stars'])) {
            se_star_row($im, $cx, (int)($H * 0.855), (int)($W * 0.020), $o['star_gold']);
        }

        // 7) Contact (optional, editable text).
        $contact = trim((string)$cfg['contact']);
        if (!empty($cfg['show_contact']) && $contact !== '' && $fontReg !== '') {
            se_text_center($im, $contact, $fontReg, $W * 0.030, $cx, (int)($H * 0.897), se_col($im, $o['contact_color']));
        }

        // 8) Optional extra footer line (tenant's own), then AK branding (kept).
        $footY = (int)($H * 0.958);
        if (trim((string)$cfg['footer_extra']) !== '' && $fontReg !== '') {
            se_text_center($im, (string)$cfg['footer_extra'], $fontReg, $W * 0.026, $cx, (int)($H * 0.928), se_col($im, $o['footer_color']));
        }
        if ($fontReg !== '') {
            se_text_center($im, defined('POWERED_BY') ? POWERED_BY : 'Powered by AK Computer, Dwarka', $fontReg, $W * 0.026, $cx, $footY, se_col($im, $o['footer_color']));
        }

        return $im;
    }
}
