<?php
/**
 * Standee design: TABLE TENT (two-sided)  (standee_templates.folder = 'tent').
 *
 * A 4x6" table tent printed as two identical panels stacked on one sheet so it
 * can be folded down the middle and stood on a table - both diners facing each
 * side see the same QR. Each panel is a compact QR card. See standee/a4.php for
 * how to register additional designs.
 *
 * @param FPDF   $pdf     page already added by the caller
 * @param array  $tenant  tenant row
 * @param string $qrPath  absolute path to the QR PNG
 * @param array  $ctx     precomputed assets (see render_standee_a4)
 */
function render_standee_tent(FPDF $pdf, array $tenant, string $qrPath, array $ctx = []): void
{
    $W = $pdf->getPageWidth();
    $H = $pdf->getPageHeight();
    $halfH = $H / 2;

    // Fold line down the middle.
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->SetLineWidth(0.2);
    $pdf->Line(6, $halfH, $W - 6, $halfH);

    // Panel 1 is drawn normally; panel 2 is the same content in the lower half.
    // Both panels are identical so either side of the folded tent is readable.
    _tent_panel($pdf, $tenant, $qrPath, $ctx, 0, $halfH, $W);
    _tent_panel($pdf, $tenant, $qrPath, $ctx, $halfH, $halfH, $W);
}

/** Draw one tent panel starting at vertical offset $oy with height $ph. */
function _tent_panel(FPDF $pdf, array $tenant, string $qrPath, array $ctx, float $oy, float $ph, float $W): void
{
    $pri = $ctx['primary']   ?? [230, 57, 70];
    $sec = $ctx['secondary'] ?? [29, 53, 87];
    $acc = $ctx['accent']    ?? [241, 162, 8];

    // Top colour strip with the name.
    $stripH = 16;
    $pdf->SetFillColor($pri[0], $pri[1], $pri[2]);
    $pdf->Rect(4, $oy + 4, $W - 8, $stripH, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 15);
    $pdf->SetXY(4, $oy + 4 + 3);
    $pdf->Cell($W - 8, 9, $tenant['restaurant_name'], 0, 0, 'C');

    // CTA.
    $pdf->SetTextColor($sec[0], $sec[1], $sec[2]);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetXY(0, $oy + 24);
    $pdf->Cell($W, 6, 'SCAN FOR MENU', 0, 0, 'C');

    $ctaBottom = $oy + 31;
    if (!empty($ctx['gujarati']) && is_file($ctx['gujarati'])) {
        $gw = min(42, $W * 0.55);
        $pdf->Image($ctx['gujarati'], ($W - $gw) / 2, $ctaBottom, $gw, 0);
        $ctaBottom += $gw * 0.30;
    } else {
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetXY(0, $ctaBottom);
        $pdf->Cell($W, 5, '(Sken karo)', 0, 0, 'C');
        $ctaBottom += 6;
    }

    // QR card.
    $footReserve = 12;
    $avail = ($oy + $ph) - $ctaBottom - $footReserve;
    $qrSize = min($W - 40, $avail);
    if ($qrSize < 18) { $qrSize = max(18, $avail); }
    $qrX = ($W - $qrSize) / 2;
    $qrY = $ctaBottom + 2;
    $pad = 2.5;
    $pdf->SetFillColor($acc[0], $acc[1], $acc[2]);
    $pdf->Rect($qrX - $pad, $qrY - $pad, $qrSize + 2 * $pad, $qrSize + 2 * $pad, 'F');
    $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize);

    if (!empty($ctx['table_no'])) {
        $pdf->SetTextColor($sec[0], $sec[1], $sec[2]);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetXY(0, $qrY + $qrSize + $pad);
        $pdf->Cell($W, 5, 'Table ' . $ctx['table_no'], 0, 0, 'C');
    }

    // Branding line.
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(0, $oy + $ph - 6);
    $pdf->Cell($W, 4, $ctx['powered'] ?? POWERED_BY, 0, 0, 'C');
}
