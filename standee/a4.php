<?php
/**
 * Standee design: CLASSIC A4 POSTER  (standee_templates.folder = 'a4').
 *
 * Full-page poster: coloured header band with logo + restaurant name, a large
 * centred QR, bilingual "Scan for Menu" call-to-action, contact line and the
 * AK Computer branding footer. The layout reads the page width/height from the
 * FPDF instance, so the same function also serves other portrait sizes.
 *
 * HOW TO ADD A NEW STANDEE DESIGN:
 *   1. Drop a file like standee/<folder>.php exposing
 *      render_standee_<folder>(FPDF $pdf, array $tenant, string $qrPath, array $ctx).
 *   2. Insert a row into the `standee_templates` table (name, folder, size).
 *   3. It is then reachable via  standee/generate.php?slug=...&tpl=<folder>.
 *
 * @param FPDF   $pdf     a page must already be added by the caller
 * @param array  $tenant  tenant row
 * @param string $qrPath  absolute path to the QR PNG
 * @param array  $ctx     precomputed assets: logo, gujarati, contact, subtitle,
 *                        powered, primary/secondary/accent [r,g,b], table_no
 */
function render_standee_a4(FPDF $pdf, array $tenant, string $qrPath, array $ctx = []): void
{
    $W = $pdf->getPageWidth();
    $H = $pdf->getPageHeight();
    $pri = $ctx['primary']   ?? [230, 57, 70];
    $sec = $ctx['secondary'] ?? [29, 53, 87];
    $acc = $ctx['accent']    ?? [241, 162, 8];

    // ---- Header band ----
    $bandH = $H * 0.22;
    $pdf->SetFillColor($pri[0], $pri[1], $pri[2]);
    $pdf->Rect(0, 0, $W, $bandH, 'F');

    // Logo (optional) centred in the band.
    $logoY = 12;
    if (!empty($ctx['logo']) && is_file($ctx['logo'])) {
        $ls = 26;
        $pdf->Image($ctx['logo'], ($W - $ls) / 2, $logoY, $ls, $ls);
        $nameY = $logoY + $ls + 4;
    } else {
        $nameY = $bandH * 0.32;
    }

    // Restaurant name (big).
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 34);
    $pdf->SetXY(8, $nameY);
    $pdf->Cell($W - 16, 14, $tenant['restaurant_name'], 0, 1, 'C');

    if (!empty($ctx['subtitle'])) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetXY(8, $nameY + 14);
        $pdf->Cell($W - 16, 7, $ctx['subtitle'], 0, 1, 'C');
    }

    // ---- Call to action ----
    $ctaY = $bandH + 14;
    $pdf->SetTextColor($sec[0], $sec[1], $sec[2]);
    $pdf->SetFont('Arial', 'B', 26);
    $pdf->SetXY(0, $ctaY);
    $pdf->Cell($W, 12, 'SCAN FOR MENU', 0, 1, 'C');

    // Gujarati sub-label (rasterised) or transliteration fallback.
    if (!empty($ctx['gujarati']) && is_file($ctx['gujarati'])) {
        $gw = 70; $gh = $gw * 0.28;
        $pdf->Image($ctx['gujarati'], ($W - $gw) / 2, $ctaY + 13, $gw, 0);
    } else {
        $pdf->SetFont('Arial', '', 14);
        $pdf->SetXY(0, $ctaY + 13);
        $pdf->Cell($W, 7, '(Sken karo)', 0, 1, 'C');
    }

    // ---- QR code (large, centred) ----
    $qrSize = min($W - 50, $H * 0.44);
    $qrX = ($W - $qrSize) / 2;
    $qrY = $ctaY + 26;

    // Accent frame behind the QR for a finished look.
    $pad = 6;
    $pdf->SetFillColor($acc[0], $acc[1], $acc[2]);
    $pdf->Rect($qrX - $pad, $qrY - $pad, $qrSize + 2 * $pad, $qrSize + 2 * $pad, 'F');
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($qrX - $pad + 2, $qrY - $pad + 2, $qrSize + 2 * $pad - 4, $qrSize + 2 * $pad - 4, 'F');
    $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);

    // ---- Helper line under QR ----
    $pdf->SetTextColor(90, 90, 90);
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetXY(0, $qrY + $qrSize + $pad + 4);
    $pdf->Cell($W, 7, 'Point your camera at the code to view our menu', 0, 1, 'C');

    if (!empty($ctx['table_no'])) {
        $pdf->SetTextColor($sec[0], $sec[1], $sec[2]);
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetXY(0, $qrY + $qrSize + $pad + 12);
        $pdf->Cell($W, 8, 'Table ' . $ctx['table_no'], 0, 1, 'C');
    }

    // ---- Footer branding ----
    $pdf->SetFillColor($sec[0], $sec[1], $sec[2]);
    $footH = 18;
    $pdf->Rect(0, $H - $footH, $W, $footH, 'F');
    $pdf->SetTextColor(255, 255, 255);
    if (!empty($ctx['contact'])) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY(0, $H - $footH + 3);
        $pdf->Cell($W, 6, $ctx['contact'], 0, 1, 'C');
    }
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(0, $H - 7);
    $pdf->Cell($W, 5, $ctx['powered'] ?? POWERED_BY, 0, 0, 'C');
}
