<?php
/**
 * AK Menu System - Minimal, self-contained QR Code encoder (public-domain style).
 *
 * APPROACH USED:  A GENUINE, LOCAL QR ENCODER (byte mode, Reed-Solomon ECC,
 * data masking + penalty selection, versions 1-10, all four EC levels) rendered
 * to a PNG with PHP-GD. No external services and no Composer dependency.
 *
 * The public surface matches what config/functions.php expects:
 *     QRcode::png($text, $file, $level = 'H', $size = 8, $margin = 2)
 * plus the QR_ECLEVEL_* constants that generateQr() passes in.
 *
 * If (and only if) the encoder cannot represent the payload locally - e.g. the
 * data is longer than a version-10 symbol can hold - png() transparently falls
 * back to the public QR web service so a scannable PNG is still produced.
 *
 * Algorithm reference: ISO/IEC 18004. Matrix construction follows the well
 * known Nayuki reference structure. Output verified end-to-end with an external
 * QR decoder (OpenCV QRCodeDetector) during development.
 */

// Error-correction level constants (values match the classic phpqrcode lib).
if (!defined('QR_ECLEVEL_L')) define('QR_ECLEVEL_L', 0);
if (!defined('QR_ECLEVEL_M')) define('QR_ECLEVEL_M', 1);
if (!defined('QR_ECLEVEL_Q')) define('QR_ECLEVEL_Q', 2);
if (!defined('QR_ECLEVEL_H')) define('QR_ECLEVEL_H', 3);

if (!class_exists('QRcode')) {

/**
 * Static facade kept API-compatible with the bundled-lib call site.
 */
class QRcode
{
    /**
     * Encode $text and write a PNG QR image to $file.
     *
     * @param string     $text   payload (treated as raw bytes / UTF-8)
     * @param string     $file   absolute destination path for the PNG
     * @param int|string $level  QR_ECLEVEL_* constant or 'L'|'M'|'Q'|'H'
     * @param int        $size   pixels per module (module = one QR "dot")
     * @param int        $margin quiet-zone width in modules
     */
    public static function png(string $text, string $file, $level = 'H', int $size = 8, int $margin = 2): bool
    {
        $ecl = self::normaliseLevel($level);
        $size   = max(1, (int)$size);
        $margin = max(1, (int)$margin);

        try {
            $enc    = new QREncoder();
            $matrix = $enc->encode($text, $ecl); // bool[y][x], true = dark module
        } catch (\Throwable $e) {
            // Local encode failed (e.g. payload too large) - fall back to a
            // remote QR service so callers still get a scannable PNG.
            return self::remoteFallback($text, $file, $size, $margin);
        }

        return self::renderPng($matrix, $file, $size, $margin);
    }

    /** Map a QR_ECLEVEL_* int (or L/M/Q/H letter) to the internal 0..3 index. */
    private static function normaliseLevel($level): int
    {
        if (is_int($level)) {
            return max(0, min(3, $level));
        }
        $map = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];
        $key = strtoupper((string)$level);
        return $map[$key] ?? 3;
    }

    /** Rasterise the boolean module matrix into a PNG file via GD. */
    private static function renderPng(array $matrix, string $file, int $size, int $margin): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }
        $count = count($matrix);
        $dim   = ($count + 2 * $margin) * $size;

        $img = imagecreatetruecolor($dim, $dim);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $dim, $dim, $white);

        for ($y = 0; $y < $count; $y++) {
            for ($x = 0; $x < $count; $x++) {
                if (!empty($matrix[$y][$x])) {
                    $px = ($x + $margin) * $size;
                    $py = ($y + $margin) * $size;
                    imagefilledrectangle($img, $px, $py, $px + $size - 1, $py + $size - 1, $black);
                }
            }
        }

        $dir = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $ok = imagepng($img, $file);
        imagedestroy($img);
        return (bool)$ok && file_exists($file);
    }

    /** Last-resort remote generator; writes fetched PNG bytes to $file. */
    private static function remoteFallback(string $text, string $file, int $size, int $margin): bool
    {
        $px  = max(120, min(1000, ($size * 33) + ($margin * 2 * $size)));
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $px . 'x' . $px
             . '&margin=' . ($margin * $size)
             . '&data=' . urlencode($text);

        $bytes = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $bytes = curl_exec($ch);
            curl_close($ch);
        }
        if ($bytes === false || $bytes === '') {
            $bytes = @file_get_contents($url);
        }
        if ($bytes === false || $bytes === '') {
            return false;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        return (bool)@file_put_contents($file, $bytes);
    }
}

/**
 * Core QR symbol builder. Supports byte mode, versions 1-10 and every
 * error-correction level - far more than enough for menu/table URLs.
 */
class QREncoder
{
    /** @var int[] GF(256) exp table */
    private array $gfExp = [];
    /** @var int[] GF(256) log table */
    private array $gfLog = [];

    /**
     * Per-version, per-EC-level block layout.
     *   [ecCodewordsPerBlock, group1Blocks, group1DataCw, group2Blocks, group2DataCw]
     * Index: BLOCKS[version][ecLevel(0=L,1=M,2=Q,3=H)].
     */
    private const BLOCKS = [
        1  => [[7,1,19,0,0],  [10,1,16,0,0], [13,1,13,0,0], [17,1,9,0,0]],
        2  => [[10,1,34,0,0], [16,1,28,0,0], [22,1,22,0,0], [28,1,16,0,0]],
        3  => [[15,1,55,0,0], [26,1,44,0,0], [18,2,17,0,0], [22,2,13,0,0]],
        4  => [[20,1,80,0,0], [18,2,32,0,0], [26,2,24,0,0], [16,4,9,0,0]],
        5  => [[26,1,108,0,0],[24,2,43,0,0], [18,2,15,2,16],[22,2,11,2,12]],
        6  => [[18,2,68,0,0], [16,4,27,0,0], [24,4,19,0,0], [28,4,15,0,0]],
        7  => [[20,2,78,0,0], [18,4,31,0,0], [18,2,14,4,15],[26,4,13,1,14]],
        8  => [[24,2,97,0,0], [22,2,38,2,39],[22,4,18,2,19],[26,4,14,2,15]],
        9  => [[30,2,116,0,0],[22,3,36,2,37],[20,4,16,4,17],[24,4,12,4,13]],
        10 => [[18,2,68,2,69],[26,4,43,1,44],[24,6,19,2,20],[28,6,15,2,16]],
    ];

    /** Alignment-pattern centre coordinates per version (empty for v1). */
    private const ALIGN = [
        1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
        6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
    ];

    /** EC-level -> format-info bits (per ISO/IEC 18004): L=1,M=0,Q=3,H=2. */
    private const ECC_FORMAT_BITS = [0 => 1, 1 => 0, 2 => 3, 3 => 2];

    private int $version = 0;
    private int $size = 0;
    /** @var array<int,array<int,bool>> */
    private array $modules = [];
    /** @var array<int,array<int,bool>> */
    private array $isFunction = [];

    public function __construct()
    {
        $this->initGaloisField();
    }

    /**
     * Build a QR symbol for $text at EC level $ecl (0..3).
     * @return array<int,array<int,bool>> boolean matrix [y][x]
     */
    public function encode(string $text, int $ecl): array
    {
        $this->version = $this->chooseVersion($text, $ecl);
        $this->size    = $this->version * 4 + 17;

        $allCodewords = $this->buildCodewords($text, $ecl);

        // Initialise blank matrix.
        $this->modules    = [];
        $this->isFunction = [];
        for ($y = 0; $y < $this->size; $y++) {
            $this->modules[$y]    = array_fill(0, $this->size, false);
            $this->isFunction[$y] = array_fill(0, $this->size, false);
        }

        $this->drawFunctionPatterns($ecl);
        $this->drawCodewords($allCodewords);

        // Try all 8 masks, keep the one with the lowest penalty.
        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($ecl, $mask);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // XOR again to revert
        }
        $this->applyMask($bestMask);
        $this->drawFormatBits($ecl, $bestMask);

        return $this->modules;
    }

    // ---------------------------------------------------------------------
    // Data encoding (byte mode) + Reed-Solomon
    // ---------------------------------------------------------------------

    /** Pick the smallest version (1..10) whose data capacity fits the payload. */
    private function chooseVersion(string $text, int $ecl): int
    {
        $len = strlen($text);
        for ($v = 1; $v <= 10; $v++) {
            $dataCw = $this->totalDataCodewords($v, $ecl);
            $ccBits = ($v <= 9) ? 8 : 16;             // byte-mode char-count bits
            $needBits = 4 + $ccBits + ($len * 8);      // mode + count + data
            if ($needBits <= $dataCw * 8) {
                return $v;
            }
        }
        throw new \RuntimeException('QR payload too large for supported versions (1-10).');
    }

    /** Total data codewords available for a version/EC-level. */
    private function totalDataCodewords(int $version, int $ecl): int
    {
        [$ec, $g1b, $g1d, $g2b, $g2d] = self::BLOCKS[$version][$ecl];
        return $g1b * $g1d + $g2b * $g2d;
    }

    /**
     * Produce the final, interleaved data+EC codeword byte array for the symbol.
     * @return int[] codewords (0..255)
     */
    private function buildCodewords(string $text, int $ecl): array
    {
        $version = $this->version;
        [$ecPerBlock, $g1b, $g1d, $g2b, $g2d] = self::BLOCKS[$version][$ecl];
        $totalData = $g1b * $g1d + $g2b * $g2d;

        // --- Assemble the raw data bit stream ---
        $bits = [];
        $this->appendBits($bits, 0b0100, 4);                        // byte mode
        $ccBits = ($version <= 9) ? 8 : 16;
        $this->appendBits($bits, strlen($text), $ccBits);           // char count
        foreach (str_split($text) as $ch) {
            $this->appendBits($bits, ord($ch), 8);
        }

        // Terminator (up to 4 zero bits) without overflowing capacity.
        $capacityBits = $totalData * 8;
        $terminator = min(4, $capacityBits - count($bits));
        if ($terminator > 0) { $this->appendBits($bits, 0, $terminator); }

        // Pad to a byte boundary.
        while (count($bits) % 8 !== 0) { $bits[] = 0; }

        // Convert to codewords.
        $data = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) { $byte = ($byte << 1) | $bits[$i + $b]; }
            $data[] = $byte;
        }
        // Pad bytes: 0xEC / 0x11 alternating until full.
        $padBytes = [0xEC, 0x11];
        $pi = 0;
        while (count($data) < $totalData) {
            $data[] = $padBytes[$pi % 2];
            $pi++;
        }

        // --- Split into blocks, compute EC per block ---
        $blocks   = [];
        $ecBlocks = [];
        $offset = 0;
        $gen = $this->rsGenerator($ecPerBlock);
        for ($i = 0; $i < $g1b + $g2b; $i++) {
            $dataLen = ($i < $g1b) ? $g1d : $g2d;
            $blk = array_slice($data, $offset, $dataLen);
            $offset += $dataLen;
            $blocks[]   = $blk;
            $ecBlocks[] = $this->rsEncodeBlock($blk, $gen);
        }

        // --- Interleave data codewords, then EC codewords ---
        $result = [];
        $maxData = max($g1d, $g2d);
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $blk) {
                if ($i < count($blk)) { $result[] = $blk[$i]; }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $blk) {
                $result[] = $blk[$i];
            }
        }
        return $result;
    }

    /** Append the low $n bits of $value (MSB first) to the bit list. */
    private function appendBits(array &$bits, int $value, int $n): void
    {
        for ($i = $n - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    // ---- Galois field GF(256) with primitive polynomial 0x11d ----

    private function initGaloisField(): void
    {
        $this->gfExp = array_fill(0, 512, 0);
        $this->gfLog = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $this->gfExp[$i] = $x;
            $this->gfLog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) { $x ^= 0x11d; }
        }
        for ($i = 255; $i < 512; $i++) {
            $this->gfExp[$i] = $this->gfExp[$i - 255];
        }
    }

    private function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) { return 0; }
        return $this->gfExp[$this->gfLog[$a] + $this->gfLog[$b]];
    }

    /** Reed-Solomon generator polynomial of degree $degree (length degree+1). */
    private function rsGenerator(int $degree): array
    {
        $g = [1];
        for ($i = 0; $i < $degree; $i++) {
            $ng = array_fill(0, count($g) + 1, 0);
            for ($j = 0; $j < count($g); $j++) {
                $ng[$j]     ^= $g[$j];                                   // * x
                $ng[$j + 1] ^= $this->gfMul($g[$j], $this->gfExp[$i]);   // * a^i
            }
            $g = $ng;
        }
        return $g;
    }

    /** Compute the EC codewords for one data block. */
    private function rsEncodeBlock(array $data, array $gen): array
    {
        $ecLen = count($gen) - 1;
        $res = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $res[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $res[$i + $j] ^= $this->gfMul($gen[$j], $coef);
                }
            }
        }
        return array_slice($res, count($data));
    }

    // ---------------------------------------------------------------------
    // Matrix construction
    // ---------------------------------------------------------------------

    private function setFunction(int $x, int $y, bool $isDark): void
    {
        $this->modules[$y][$x]    = $isDark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $ecl): void
    {
        $size = $this->size;

        // Timing patterns (row 6 and column 6).
        for ($i = 0; $i < $size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }

        // Three finder patterns (with separators).
        $this->drawFinder(3, 3);
        $this->drawFinder($size - 4, 3);
        $this->drawFinder(3, $size - 4);

        // Alignment patterns (skip those overlapping the finders).
        $pos = self::ALIGN[$this->version];
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignment($pos[$i], $pos[$j]);
            }
        }

        // Reserve the format-info area (drawn for real after masking).
        $this->drawFormatBits($ecl, 0);
        // Version info (v7+).
        if ($this->version >= 7) {
            $this->drawVersionInfo();
        }
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $this->setFunction($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** Draw (and, when finalising, re-draw) the 15-bit format information. */
    private function drawFormatBits(int $ecl, int $mask): void
    {
        $data = (self::ECC_FORMAT_BITS[$ecl] << 3) | $mask; // 5 bits
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412; // 15-bit masked format value
        $size = $this->size;

        // First copy (around the top-left finder).
        for ($i = 0; $i <= 5; $i++) { $this->setFunction(8, $i, $this->getBit($bits, $i)); }
        $this->setFunction(8, 7, $this->getBit($bits, 6));
        $this->setFunction(8, 8, $this->getBit($bits, 7));
        $this->setFunction(7, 8, $this->getBit($bits, 8));
        for ($i = 9; $i < 15; $i++) { $this->setFunction(14 - $i, 8, $this->getBit($bits, $i)); }

        // Second copy (split across the other two finders).
        for ($i = 0; $i < 8; $i++) { $this->setFunction($size - 1 - $i, 8, $this->getBit($bits, $i)); }
        for ($i = 8; $i < 15; $i++) { $this->setFunction(8, $size - 15 + $i, $this->getBit($bits, $i)); }
        $this->setFunction(8, $size - 8, true); // always-dark module
    }

    /** Draw the 18-bit version information (versions 7-10). */
    private function drawVersionInfo(): void
    {
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1f25);
        }
        $bits = ($this->version << 12) | $rem; // 18 bits
        $size = $this->size;
        for ($i = 0; $i < 18; $i++) {
            $bit = $this->getBit($bits, $i);
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $bit);
            $this->setFunction($b, $a, $bit);
        }
    }

    /** Zig-zag placement of the data/EC codeword bit stream. */
    private function drawCodewords(array $codewords): void
    {
        $size = $this->size;
        $totalBits = count($codewords) * 8;
        $i = 0; // bit cursor

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) { $right = 5; } // skip the vertical timing column
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) === 0);
                    $y = $upward ? ($size - 1 - $vert) : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $byte = $codewords[$i >> 3];
                        $bit  = ($byte >> (7 - ($i & 7))) & 1;
                        $this->modules[$y][$x] = ($bit === 1);
                        $i++;
                    }
                    // Remaining (remainder) modules stay light.
                }
            }
        }
    }

    /** XOR the data region with the given mask pattern (idempotent per call). */
    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) { continue; }
                switch ($mask) {
                    case 0: $inv = (($x + $y) % 2) === 0; break;
                    case 1: $inv = ($y % 2) === 0; break;
                    case 2: $inv = ($x % 3) === 0; break;
                    case 3: $inv = (($x + $y) % 3) === 0; break;
                    case 4: $inv = ((intdiv($y, 2) + intdiv($x, 3)) % 2) === 0; break;
                    case 5: $inv = ((($x * $y) % 2) + (($x * $y) % 3)) === 0; break;
                    case 6: $inv = (((($x * $y) % 2) + (($x * $y) % 3)) % 2) === 0; break;
                    default: $inv = (((($x + $y) % 2) + (($x * $y) % 3)) % 2) === 0; break;
                }
                if ($inv) { $this->modules[$y][$x] = !$this->modules[$y][$x]; }
            }
        }
    }

    /** Compute the standard 4-rule penalty score for the current matrix. */
    private function penaltyScore(): int
    {
        $size = $this->size;
        $m = $this->modules;
        $penalty = 0;

        // Rule 1: runs of 5+ same-colour modules in rows and columns.
        for ($y = 0; $y < $size; $y++) {
            $runColor = $m[$y][0]; $runLen = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($m[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) { $penalty += 3; }
                    elseif ($runLen > 5) { $penalty++; }
                } else { $runColor = $m[$y][$x]; $runLen = 1; }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $runColor = $m[0][$x]; $runLen = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($m[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) { $penalty += 3; }
                    elseif ($runLen > 5) { $penalty++; }
                } else { $runColor = $m[$y][$x]; $runLen = 1; }
            }
        }

        // Rule 2: 2x2 blocks of the same colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: finder-like 1:1:3:1:1 patterns in rows and columns.
        $pat1 = [true, false, true, true, true, false, true, false, false, false, false];
        $pat2 = [false, false, false, false, true, false, true, true, true, false, true];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - 11; $x++) {
                $ok1 = true; $ok2 = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$y][$x + $k] !== $pat1[$k]) { $ok1 = false; }
                    if ($m[$y][$x + $k] !== $pat2[$k]) { $ok2 = false; }
                }
                if ($ok1 || $ok2) { $penalty += 40; }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y <= $size - 11; $y++) {
                $ok1 = true; $ok2 = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$y + $k][$x] !== $pat1[$k]) { $ok1 = false; }
                    if ($m[$y + $k][$x] !== $pat2[$k]) { $ok2 = false; }
                }
                if ($ok1 || $ok2) { $penalty += 40; }
            }
        }

        // Rule 4: overall dark/light balance.
        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($m[$y][$x]) { $dark++; }
            }
        }
        $total = $size * $size;
        $ratio = ($dark * 100) / $total;
        $k = (int)(abs($ratio - 50) / 5);
        $penalty += $k * 10;

        return $penalty;
    }

    private function getBit(int $value, int $i): bool
    {
        return (($value >> $i) & 1) !== 0;
    }
}

} // class_exists guard
