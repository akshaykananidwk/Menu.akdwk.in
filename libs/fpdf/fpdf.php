<?php
/**
 * AK Menu System - Self-contained PDF generator, API-compatible with FPDF.
 *
 * This is NOT the full 2000-line FPDF 1.86 distribution. It is a compact, real,
 * from-scratch implementation of the FPDF public API SUBSET actually used by the
 * standee and invoice generators - correctness over completeness, as required:
 *
 *   AddPage, SetFont, SetFontSize, Cell, MultiCell, Image (PNG/JPEG/GIF/WebP via
 *   GD), SetXY, SetX, SetY, GetX, GetY, Ln, SetTextColor, SetFillColor,
 *   SetDrawColor, SetLineWidth, Rect, Line, GetStringWidth, SetMargins,
 *   SetAutoPageBreak, AliasNbPages, Output.
 *
 * It emits a valid PDF 1.3 document. The 14 standard ("core") fonts are used, so
 * no font files are embedded; Helvetica/Arial map to Helvetica. Images are
 * decoded with GD and re-encoded as zlib/FlateDecode DeviceRGB streams (alpha is
 * composited onto white), which is robust for any GD-readable source including
 * the QR PNGs produced by libs/phpqrcode.
 *
 * Coordinate model matches FPDF: origin top-left, user unit configurable
 * (default mm), y increases downward.
 */

if (!class_exists('FPDF')) {

class FPDF
{
    protected int    $page = 0;            // current page number
    protected int    $n = 2;               // current object number
    protected array  $offsets = [];        // object byte offsets in the file
    protected string $buffer = '';         // document buffer
    protected array  $pages = [];          // page content strings
    protected int    $state = 0;           // 0 uninit, 1 doc open, 2 page open, 3 closed
    protected float  $k;                    // scale: points per user unit
    protected float  $wPt;                  // page width in points
    protected float  $hPt;                  // page height in points
    protected float  $w;                    // page width in user units
    protected float  $h;                    // page height in user units
    protected float  $lMargin;
    protected float  $tMargin;
    protected float  $rMargin;
    protected float  $bMargin;
    protected float  $cMargin;              // cell padding
    protected float  $x = 0;
    protected float  $y = 0;
    protected float  $lasth = 0;            // height of last cell
    protected float  $lineWidth;
    protected array  $coreFonts = ['helvetica', 'helveticaB', 'helveticaI', 'helveticaBI', 'courier', 'courierB'];
    protected array  $fonts = [];           // used fonts
    protected array  $fontWidths = [];      // width tables by key
    protected string $fontFamily = '';
    protected string $fontStyle = '';
    protected float  $fontSizePt = 12;
    protected float  $fontSize = 0;         // in user units
    protected string $currentFontKey = '';
    protected string $drawColor = '0 G';
    protected string $fillColor = '0 g';
    protected string $textColor = '0 g';
    protected bool   $colorFlag = false;    // true when fill != text color
    protected array  $images = [];          // embedded images keyed by file
    protected bool   $autoPageBreak = true;
    protected float  $pageBreakTrigger;
    protected string $aliasNbPages = '';
    protected string $orientationDefault;
    protected array  $defPageSize;          // in points [w,h]

    /**
     * @param string       $orientation 'P'|'L'
     * @param string       $unit        'mm'|'pt'|'cm'|'in'
     * @param string|array $size        'A4'|'A5'|'Letter'|'Legal' or [w,h] in $unit
     */
    public function __construct(string $orientation = 'P', string $unit = 'mm', $size = 'A4')
    {
        // Scale factor (points per unit).
        switch ($unit) {
            case 'pt': $this->k = 1.0; break;
            case 'mm': $this->k = 72 / 25.4; break;
            case 'cm': $this->k = 72 / 2.54; break;
            case 'in': $this->k = 72.0; break;
            default:   $this->k = 72 / 25.4;
        }

        $size = $this->getPageSize($size);
        $this->defPageSize = $size;
        $this->orientationDefault = strtoupper($orientation[0] ?? 'P');
        $this->setDimensions($this->orientationDefault, $size);

        $margin = 28.35 / $this->k; // ~10mm
        $this->setMarginsInternal($margin, $margin);
        $this->cMargin = $margin / 10;
        $this->lineWidth = 0.567 / $this->k; // 0.2mm
        $this->fontWidths = $this->buildCoreWidths();
    }

    /** Resolve a named page size (or pass-through [w,h] in units) to points. */
    protected function getPageSize($size): array
    {
        if (is_string($size)) {
            $std = [
                'a3'     => [841.89, 1190.55],
                'a4'     => [595.28, 841.89],
                'a5'     => [420.94, 595.28],
                'letter' => [612.0, 792.0],
                'legal'  => [612.0, 1008.0],
            ];
            $key = strtolower($size);
            if (!isset($std[$key])) { throw new \Exception('Unknown page size: ' . $size); }
            return $std[$key]; // already in points
        }
        // Custom [w,h] given in user units -> convert to points.
        return [$size[0] * $this->k, $size[1] * $this->k];
    }

    protected function setDimensions(string $orientation, array $sizePt): void
    {
        [$w, $h] = $sizePt;
        if ($orientation === 'L') { [$w, $h] = [$h, $w]; }
        $this->wPt = $w; $this->hPt = $h;
        $this->w = $w / $this->k; $this->h = $h / $this->k;
        $this->pageBreakTrigger = $this->h; // set precisely in setAutoPageBreak
    }

    protected function setMarginsInternal(float $left, float $top): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $left;
        $this->bMargin = $top;
        $this->pageBreakTrigger = $this->h - $this->bMargin;
    }

    public function SetMargins(float $left, float $top, ?float $right = null): void
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        $this->rMargin = $right ?? $left;
    }

    public function SetAutoPageBreak(bool $auto, float $margin = 0): void
    {
        $this->autoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->pageBreakTrigger = $this->h - $margin;
    }

    public function AliasNbPages(string $alias = '{nb}'): void
    {
        $this->aliasNbPages = $alias;
    }

    // ---- Page lifecycle ----

    public function AddPage(string $orientation = '', $size = ''): void
    {
        if ($this->state === 3) { throw new \Exception('Document already closed.'); }
        $family = $this->fontFamily; $style = $this->fontStyle; $fontSize = $this->fontSizePt;
        $dc = $this->drawColor; $fc = $this->fillColor; $tc = $this->textColor; $cf = $this->colorFlag;
        $lw = $this->lineWidth;

        if ($this->page > 0) { $this->endPage(); }

        // Start a new page.
        $orientation = $orientation !== '' ? strtoupper($orientation[0]) : $this->orientationDefault;
        $sz = $size === '' ? $this->defPageSize : $this->getPageSize($size);
        $this->setDimensions($orientation, $sz);
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;

        // Re-apply graphics state on the new page.
        if ($lw != $this->lineWidth) { $this->lineWidth = $lw; }
        $this->out(sprintf('%.2F w', $this->lineWidth * $this->k));
        if ($family) { $this->SetFont($family, $style, $fontSize); }
        $this->drawColor = $dc; if ($dc !== '0 G') { $this->out($dc); }
        $this->fillColor = $fc; if ($fc !== '0 g') { $this->out($fc); }
        $this->textColor = $tc; $this->colorFlag = $cf;
    }

    protected function endPage(): void
    {
        $this->state = 1;
    }

    // ---- Colors ----

    protected function colorString($r, $g, $b, bool $upper): string
    {
        if (($r === 0 && $g === 0 && $b === 0) || $g === null) {
            $val = $g === null ? $r : $r;
            return sprintf('%.3F %s', $val / 255, $upper ? 'G' : 'g');
        }
        return sprintf('%.3F %.3F %.3F %s', $r / 255, $g / 255, $b / 255, $upper ? 'RG' : 'rg');
    }

    public function SetDrawColor($r, $g = null, $b = null): void
    {
        $this->drawColor = $this->colorString($r, $g, $b, true);
        if ($this->page > 0) { $this->out($this->drawColor); }
    }

    public function SetFillColor($r, $g = null, $b = null): void
    {
        $this->fillColor = $this->colorString($r, $g, $b, false);
        $this->colorFlag = ($this->fillColor !== $this->textColor);
        if ($this->page > 0) { $this->out($this->fillColor); }
    }

    public function SetTextColor($r, $g = null, $b = null): void
    {
        $this->textColor = $this->colorString($r, $g, $b, false);
        $this->colorFlag = ($this->fillColor !== $this->textColor);
    }

    public function SetLineWidth(float $width): void
    {
        $this->lineWidth = $width;
        if ($this->page > 0) { $this->out(sprintf('%.2F w', $width * $this->k)); }
    }

    // ---- Fonts ----

    public function SetFont(string $family, string $style = '', float $size = 0): void
    {
        $family = strtolower($family);
        if ($family === 'arial') { $family = 'helvetica'; }
        if ($family === 'times') { $family = 'helvetica'; } // no serif metrics bundled
        if (!in_array($family, ['helvetica', 'courier'], true)) { $family = 'helvetica'; }

        $style = strtoupper($style);
        if (strpos($style, 'U') !== false) { $style = str_replace('U', '', $style); }
        if ($style === 'IB') { $style = 'BI'; }

        if ($size == 0) { $size = $this->fontSizePt; }

        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->k;

        $key = $family . $style;
        if (!isset($this->fonts[$key])) {
            $this->fonts[$key] = [
                'i'    => count($this->fonts) + 1,
                'name' => $this->pdfFontName($family, $style),
                'key'  => $key,
            ];
        }
        $this->currentFontKey = $key;
        if ($this->page > 0) {
            $this->out(sprintf('BT /F%d %.2F Tf ET', $this->fonts[$key]['i'], $this->fontSizePt));
        }
    }

    public function SetFontSize(float $size): void
    {
        if ($this->fontSizePt == $size) { return; }
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->k;
        if ($this->page > 0 && $this->currentFontKey !== '') {
            $this->out(sprintf('BT /F%d %.2F Tf ET', $this->fonts[$this->currentFontKey]['i'], $size));
        }
    }

    protected function pdfFontName(string $family, string $style): string
    {
        $base = $family === 'courier' ? 'Courier' : 'Helvetica';
        if ($style === 'B') { return $base . '-Bold'; }
        if ($style === 'I') { return $base . ($base === 'Courier' ? '-Oblique' : '-Oblique'); }
        if ($style === 'BI') { return $base . '-BoldOblique'; }
        return $base;
    }

    /** Width of a string in the current font, in user units. */
    public function GetStringWidth(string $s): float
    {
        $style = $this->fontStyle;
        $bold = (strpos($style, 'B') !== false);
        $table = $bold ? $this->fontWidths['B'] : $this->fontWidths['R'];
        $w = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $w += $table[ord($s[$i])] ?? 600;
        }
        return $w * $this->fontSizePt / 1000 / $this->k;
    }

    // ---- Position helpers ----

    public function GetX(): float { return $this->x; }
    public function GetY(): float { return $this->y; }
    public function SetX(float $x): void { $this->x = $x >= 0 ? $x : $this->w + $x; }
    public function SetY(float $y, bool $resetX = true): void
    {
        $this->y = $y >= 0 ? $y : $this->h + $y;
        if ($resetX) { $this->x = $this->lMargin; }
    }
    public function SetXY(float $x, float $y): void { $this->SetX($x); $this->SetY($y, false); }

    public function Ln($h = null): void
    {
        $this->x = $this->lMargin;
        $this->y += ($h === null) ? $this->lasth : (float)$h;
    }

    // ---- Drawing primitives ----

    public function Line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->out(sprintf('%.2F %.2F m %.2F %.2F l S',
            $x1 * $this->k, ($this->h - $y1) * $this->k,
            $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    public function Rect(float $x, float $y, float $w, float $h, string $style = ''): void
    {
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $this->out(sprintf('%.2F %.2F %.2F %.2F re %s',
            $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
    }

    // ---- Cell / MultiCell ----

    public function Cell(float $w, float $h = 0, string $txt = '', $border = 0, int $ln = 0, string $align = '', bool $fill = false): void
    {
        $k = $this->k;
        if ($w == 0) { $w = $this->w - $this->rMargin - $this->x; }
        $s = '';

        if ($fill || $border === 1) {
            $op = $fill ? (($border === 1) ? 'B' : 'f') : 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ',
                $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $xk = $this->x * $k; $yk = ($this->h - $this->y) * $k;
            if (strpos($border, 'L') !== false) { $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $xk, $yk, $xk, ($this->h - ($this->y + $h)) * $k); }
            if (strpos($border, 'T') !== false) { $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $xk, $yk, ($this->x + $w) * $k, $yk); }
            if (strpos($border, 'R') !== false) { $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($this->x + $w) * $k, $yk, ($this->x + $w) * $k, ($this->h - ($this->y + $h)) * $k); }
            if (strpos($border, 'B') !== false) { $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $xk, ($this->h - ($this->y + $h)) * $k, ($this->x + $w) * $k, ($this->h - ($this->y + $h)) * $k); }
        }

        if ($txt !== '') {
            if ($align === 'R') { $dx = $w - $this->cMargin - $this->GetStringWidth($txt); }
            elseif ($align === 'C') { $dx = ($w - $this->GetStringWidth($txt)) / 2; }
            else { $dx = $this->cMargin; }

            if ($this->colorFlag) { $s .= 'q ' . $this->textColor . ' '; }
            $txt2 = $this->escape($txt);
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',
                ($this->x + $dx) * $k,
                ($this->h - ($this->y + 0.5 * $h + 0.3 * $this->fontSize)) * $k,
                $txt2);
            if ($this->colorFlag) { $s .= ' Q'; }
        }

        if ($s) { $this->out($s); }
        $this->lasth = $h;

        if ($ln > 0) {
            $this->y += $h;
            if ($ln === 1) { $this->x = $this->lMargin; }
        } else {
            $this->x += $w;
        }
    }

    public function MultiCell(float $w, float $h, string $txt, $border = 0, string $align = 'J', bool $fill = false): void
    {
        if ($w == 0) { $w = $this->w - $this->rMargin - $this->x; }
        $maxWidth = $w - 2 * $this->cMargin;
        $txt = str_replace("\r", '', $txt);
        $paragraphs = explode("\n", $txt);
        $lines = [];
        foreach ($paragraphs as $para) {
            if ($para === '') { $lines[] = ''; continue; }
            $words = explode(' ', $para);
            $line = '';
            foreach ($words as $word) {
                $try = ($line === '') ? $word : $line . ' ' . $word;
                if ($this->GetStringWidth($try) > $maxWidth && $line !== '') {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            $lines[] = $line;
        }
        foreach ($lines as $ln) {
            $b = is_string($border) ? $border : ($border === 1 ? 'LRTB' : 0);
            $this->Cell($w, $h, $ln, $b, 2, $align === 'J' ? 'L' : $align, $fill);
        }
        $this->x = $this->lMargin;
    }

    // ---- Images (decoded via GD, embedded as FlateDecode RGB) ----

    public function Image(string $file, ?float $x = null, ?float $y = null, float $w = 0, float $h = 0, string $type = ''): void
    {
        if (!isset($this->images[$file])) {
            $info = $this->parseImage($file);
            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        }
        $info = $this->images[$file];

        // Default placement dimensions (assume 96 dpi if unspecified).
        if ($w == 0 && $h == 0) {
            $w = -96; $h = -96;
        }
        if ($w < 0) { $w = -$info['w'] * 72 / $w / $this->k; }
        if ($h < 0) { $h = -$info['h'] * 72 / $h / $this->k; }
        if ($w == 0) { $w = $h * $info['w'] / $info['h']; }
        if ($h == 0) { $h = $w * $info['h'] / $info['w']; }

        if ($y === null) { $y = $this->y; }
        if ($x === null) { $x = $this->x; }

        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $w * $this->k, $h * $this->k,
            $x * $this->k, ($this->h - ($y + $h)) * $this->k,
            $info['i']));
    }

    /** Decode any GD-readable image into a raw RGB FlateDecode payload. */
    protected function parseImage(string $file): array
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \Exception('GD extension required for Image().');
        }
        $raw = @file_get_contents($file);
        if ($raw === false) { throw new \Exception('Cannot read image: ' . $file); }
        $im = @imagecreatefromstring($raw);
        if ($im === false) { throw new \Exception('Unsupported image: ' . $file); }

        $iw = imagesx($im); $ih = imagesy($im);
        // Flatten onto white to drop any alpha channel.
        $canvas = imagecreatetruecolor($iw, $ih);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $iw, $ih, $white);
        imagecopy($canvas, $im, 0, 0, 0, 0, $iw, $ih);
        imagedestroy($im);

        $rgb = '';
        for ($yy = 0; $yy < $ih; $yy++) {
            $row = '';
            for ($xx = 0; $xx < $iw; $xx++) {
                $c = imagecolorat($canvas, $xx, $yy);
                $row .= chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
            }
            $rgb .= $row;
        }
        imagedestroy($canvas);

        return [
            'w'    => $iw,
            'h'    => $ih,
            'cs'   => 'DeviceRGB',
            'bpc'  => 8,
            'f'    => 'FlateDecode',
            'data' => gzcompress($rgb, 6),
        ];
    }

    // ---- Output / document assembly ----

    public function Output(string $dest = '', string $name = '', bool $isUTF8 = false): string
    {
        if ($this->state < 3) { $this->close(); }
        $dest = strtoupper($dest);
        if ($dest === '') { $dest = 'I'; }
        if ($name === '') { $name = 'doc.pdf'; }

        switch ($dest) {
            case 'S':
                return $this->buffer;
            case 'F':
                if (file_put_contents($name, $this->buffer) === false) {
                    throw new \Exception('Unable to write file: ' . $name);
                }
                return '';
            case 'D':
            case 'I':
                if (!headers_sent()) {
                    header('Content-Type: application/pdf');
                    header('Content-Length: ' . strlen($this->buffer));
                    $disp = ($dest === 'D') ? 'attachment' : 'inline';
                    header('Content-Disposition: ' . $disp . '; filename="' . $name . '"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                }
                echo $this->buffer;
                return '';
            default:
                throw new \Exception('Unknown output destination: ' . $dest);
        }
    }

    protected function close(): void
    {
        if ($this->state === 3) { return; }
        if ($this->page === 0) { $this->AddPage(); }
        $this->endPage();
        $this->state = 3;
        $this->enddoc();
    }

    protected function out(string $s): void
    {
        if ($this->state !== 2) { throw new \Exception('No page open; call AddPage() first.'); }
        $this->pages[$this->page] .= $s . "\n";
    }

    protected function put(string $s): void { $this->buffer .= $s . "\n"; }

    protected function newobj(?int $n = null): void
    {
        if ($n === null) { $n = ++$this->n; }
        $this->offsets[$n] = strlen($this->buffer);
        $this->put($n . ' 0 obj');
    }

    protected function putStream(string $data): void
    {
        $this->put('stream');
        $this->put($data);
        $this->put('endstream');
    }

    protected function enddoc(): void
    {
        $this->buffer = '';
        $this->put('%PDF-1.3');
        $this->put('%' . chr(0xE2) . chr(0xE3) . chr(0xCF) . chr(0xD3)); // binary marker

        $this->putPages();
        $this->putResources();

        // Info
        $this->newobj();
        $this->put('<< /Producer (AK Menu System FPDF) /CreationDate (D:' . date('YmdHis') . ') >>');
        $this->put('endobj');
        $infoN = $this->n;

        // Catalog
        $this->newobj();
        $this->put('<< /Type /Catalog /Pages 1 0 R >>');
        $this->put('endobj');
        $catN = $this->n;

        // Cross-reference table
        $xref = strlen($this->buffer);
        $count = $this->n + 1;
        $this->put('xref');
        $this->put('0 ' . $count);
        $this->put('0000000000 65535 f ');
        for ($i = 1; $i <= $this->n; $i++) {
            $this->put(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }
        $this->put('trailer');
        $this->put('<< /Size ' . $count . ' /Root ' . $catN . ' 0 R /Info ' . $infoN . ' 0 R >>');
        $this->put('startxref');
        $this->put((string)$xref);
        $this->put('%%EOF');
    }

    protected function putPages(): void
    {
        $nb = $this->page;
        if ($this->aliasNbPages !== '') {
            for ($n = 1; $n <= $nb; $n++) {
                $this->pages[$n] = str_replace($this->aliasNbPages, (string)$nb, $this->pages[$n]);
            }
        }

        // Object 1 = Pages tree; each page = obj, each content = obj.
        // Reserve object numbers: pages tree is obj 1.
        $this->offsets[1] = null; // placeholder, filled below in order

        // We assign: obj1 = Pages, then per page: page obj + content obj.
        $kids = [];
        $pageObjStart = 2;
        // First emit content + page objects (numbers start at 2).
        $this->n = 1;
        for ($n = 1; $n <= $nb; $n++) {
            // Page object
            $this->newobj();
            $pageN = $this->n;
            $kids[] = $pageN . ' 0 R';
            $this->put('<< /Type /Page /Parent 1 0 R');
            $this->put('/MediaBox [0 0 ' . sprintf('%.2F %.2F', $this->wPt, $this->hPt) . ']');
            $this->put('/Resources 2 0 R'); // placeholder replaced? we set resources obj below via fixed ref
            $this->put('/Contents ' . ($this->n + 1) . ' 0 R >>');
            $this->put('endobj');

            // Content stream object
            $content = $this->pages[$n];
            $this->newobj();
            $this->put('<< /Length ' . strlen($content) . ' >>');
            $this->putStream($content);
            $this->put('endobj');
        }

        // Pages tree (object 1).
        $this->offsets[1] = strlen($this->buffer);
        $this->put('1 0 obj');
        $this->put('<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nb
            . ' /MediaBox [0 0 ' . sprintf('%.2F %.2F', $this->wPt, $this->hPt) . '] >>');
        $this->put('endobj');

        // Fix each page's /Resources reference to point at the resource dict,
        // which we assign as the object right after all page/content objects.
        $this->resourceObjNum = $this->n + 1;
        $fixed = str_replace('/Resources 2 0 R', '/Resources ' . $this->resourceObjNum . ' 0 R', $this->buffer);
        $this->buffer = $fixed;
    }

    protected int $resourceObjNum = 0;

    protected function putResources(): void
    {
        // Fonts
        $fontRefs = [];
        foreach ($this->fonts as $key => $font) {
            $this->newobj();
            $fontRefs[$font['i']] = $this->n;
            $this->put('<< /Type /Font /Subtype /Type1 /BaseFont /' . $font['name']
                . ' /Encoding /WinAnsiEncoding >>');
            $this->put('endobj');
        }

        // Images
        $imgRefs = [];
        foreach ($this->images as $file => $img) {
            $this->newobj();
            $imgRefs[$img['i']] = $this->n;
            $this->put('<< /Type /XObject /Subtype /Image /Width ' . $img['w']
                . ' /Height ' . $img['h']
                . ' /ColorSpace /' . $img['cs']
                . ' /BitsPerComponent ' . $img['bpc']
                . ' /Filter /' . $img['f']
                . ' /Length ' . strlen($img['data']) . ' >>');
            $this->putStream($img['data']);
            $this->put('endobj');
        }

        // Resource dictionary (its object number was reserved as resourceObjNum).
        $this->newobj($this->resourceObjNum);
        $s = '<< /ProcSet [/PDF /Text /ImageB /ImageC /ImageI]';
        $s .= ' /Font <<';
        foreach ($this->fonts as $font) {
            $s .= ' /F' . $font['i'] . ' ' . $fontRefs[$font['i']] . ' 0 R';
        }
        $s .= ' >>';
        if ($imgRefs) {
            $s .= ' /XObject <<';
            foreach ($this->images as $img) {
                $s .= ' /I' . $img['i'] . ' ' . $imgRefs[$img['i']] . ' 0 R';
            }
            $s .= ' >>';
        }
        $s .= ' >>';
        $this->put($s);
        $this->put('endobj');
    }

    protected function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
    }

    /** Build Helvetica / Helvetica-Bold AFM width tables (1000-em units). */
    protected function buildCoreWidths(): array
    {
        // ASCII 32..126 authentic Helvetica AFM advance widths.
        $reg = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
                556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
                1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
                667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
                333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
                556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
        $bold = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
                 556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,
                 975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,
                 667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,
                 333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,
                 611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
        $tR = array_fill(0, 256, 600);
        $tB = array_fill(0, 256, 600);
        for ($i = 0; $i < count($reg); $i++) { $tR[32 + $i] = $reg[$i]; }
        for ($i = 0; $i < count($bold); $i++) { $tB[32 + $i] = $bold[$i]; }
        $tR[32] = 278; $tB[32] = 278;
        return ['R' => $tR, 'B' => $tB];
    }
}

} // class_exists guard
