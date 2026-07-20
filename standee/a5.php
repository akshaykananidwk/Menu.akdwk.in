<?php
/**
 * Standee design: COMPACT A5 POSTER  (standee_templates.folder = 'a5').
 *
 * A tighter, card-style layout for counters and smaller frames. A bordered card
 * holds the name, QR and CTA. Reads page dimensions from FPDF so it also serves
 * the square "sticker" size. See standee/a4.php for how to add a new design.
 *
 * @param FPDF   $pdf     page already added by the caller
 * @param array  $tenant  tenant row
 * @param string $qrPath  absolute path to the QR PNG
 * @param array  $ctx     precomputed assets (see render_standee_a4)
 */
function render_standee_a5(FPDF $pdf, array $tenant, string $qrPath, array $ctx = []): void
{
    $W = $pdf->getPageWidth();
    $H = $pdf->getPageHeight();
    $pri = $ctx['primary']   ?? [230, 57, 70];
    $sec = $ctx['secondary'] ?? [29, 53, 87];
    $acc = $ctx['accent']    ?? [241, 162, 8];

    // Outer accent border card.
    $m = 6;
    $pdf->SetFillColor($pri[0], $pri[1], $pri[2]);
    $pdf->Rect($m, $m, $W - 2 * $m, $H - 2 * $m, 'F');
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($m + 3, $m + 3, $W - 2 * $m - 6, $H - 2 * $m - 6, 'F');

    $inX = $m + 3;
    $inW = $W - 2 * ($m + 3);
    $cursor = $m + 8;

    // Logo (optional).
    if (!empty($ctx['logo']) && is_file($ctx['logo'])) {
        $ls = 18;
        $pdf->Image($ctx['logo'], ($W - $ls) / 2, $cursor, $ls, $ls);
        $cursor += $ls + 2;
    }

    // Restaurant name.
    $pdf->SetTextColor($pri[0], $pri[1], $pri[2]);
    $pdf->SetFont('Arial', 'B', $W < 100 ? 16 : 22);
    $pdf->SetXY($inX, $cursor);
    $pdf->Cell($inW, 10, $tenant['restaurant_name'], 0, 1, 'C');
    $cursor += 12;

    // CTA.
    $pdf->SetTextColor($sec[0], $sec[1], $sec[2]);
    $pdf->SetFont('Arial', 'B', $W < 100 ? 12 : 16);
    $pdf->SetXY($inX, $cursor);
    $pdf->Cell($inW, 8, 'SCAN FOR MENU', 0, 1, 'C');
    $cursor += 8;

    if (!empty($ctx['gujarati']) && is_file($ctx['gujarati'])) {
        $gw = min(48, $inW * 0.7);
        $pdf->Image($ctx['gujarati'], ($W - $gw) / 2, $cursor, $gw, 0);
        $cursor += $gw * 0.30 + 2;
    } else {
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetXY($inX, $cursor);
        $pdf->Cell($inW, 6, '(Sken karo)', 0, 1, 'C');
        $cursor += 8;
    }

    // QR sized to fit remaining space above footer.
    $footReserve = 20;
    $avail = ($H - $m - 6) - $cursor - $footReserve;
    $qrSize = min($inW - 16, $avail);
    if ($qrSize < 20) { $qrSize = max(20, $inW - 20); }
    $qrX = ($W - $qrSize) / 2;
    $qrY = $cursor + 2;
    $pad = 3;
    $pdf->SetFillColor($acc[0], $acc[1], $acc[2]);
    $pdf->Rect($qrX - $pad, $qrY - $pad, $qrSize + 2 * $pad, $qrSize + 2 * $pad, 'F');
    $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);

    if (!empty($ctx['table_no'])) {
        $pdf->SetTextColor($sec[0], $sec[1], $sec[2]);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY($inX, $qrY + $qrSize + $pad + 1);
        $pdf->Cell($inW, 6, 'Table ' . $ctx['table_no'], 0, 1, 'C');
    }

    // Footer branding inside the card.
    $pdf->SetTextColor(90, 90, 90);
    if (!empty($ctx['contact'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY($inX, $H - $m - 15);
        $pdf->Cell($inW, 5, $ctx['contact'], 0, 1, 'C');
    }
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY($inX, $H - $m - 9);
    $pdf->Cell($inW, 5, $ctx['powered'] ?? POWERED_BY, 0, 0, 'C');
}
