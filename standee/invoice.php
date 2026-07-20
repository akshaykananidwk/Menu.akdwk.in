<?php
/**
 * AK Menu System - Plan / billing invoice PDF (super-admin only).
 *
 * Linked from admin/invoices.php as  standee/invoice.php?id=<invoices.id>.
 * Renders a clean A4 invoice with the white-label site header, bill-to tenant,
 * the plan line item, tax, total and paid/unpaid status. Output inline.
 *
 * NOTE: the PDF uses core fonts (Helvetica/WinAnsi), which cannot render the ₹
 * glyph, so amounts are printed as "Rs." to stay legible in every viewer.
 */

require_once dirname(__DIR__) . '/config/config.php';
requireAdmin(); // redirects to admin login if not a super admin

require_once LIB_PATH . '/fpdf/fpdf.php';

// ---- Validate input + load records ------------------------------------------
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid invoice id.'); }

$inv = db_one('SELECT * FROM ' . tbl('invoices') . ' WHERE id = :id', [':id' => $id]);
if (!$inv) { http_response_code(404); exit('Invoice not found.'); }

$tenant = db_one('SELECT * FROM ' . tbl('tenants') . ' WHERE id = :id', [':id' => (int)$inv['tenant_id']]);
$plan   = $inv['plan_id']
    ? db_one('SELECT * FROM ' . tbl('plans') . ' WHERE id = :id', [':id' => (int)$inv['plan_id']])
    : null;

$siteName = getSetting('site_name', 'AK Menu System');
$logoAbs  = invoiceSiteLogo(getSetting('logo', ''));

// ---- Build the PDF ----------------------------------------------------------
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();
$W = $pdf->GetPageWidth();

// Header band with site logo + name.
$pdf->SetFillColor(29, 53, 87);
$pdf->Rect(0, 0, $W, 34, 'F');
if ($logoAbs) {
    $pdf->Image($logoAbs, 12, 7, 20, 20);
}
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 22);
$pdf->SetXY($logoAbs ? 36 : 12, 9);
$pdf->Cell(0, 9, $siteName, 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->SetXY($logoAbs ? 36 : 12, 20);
$pdf->Cell(0, 6, getSetting('tagline', 'Digital Restaurant Menu & Ordering'), 0, 0, 'L');

// "INVOICE" title + meta.
$pdf->SetTextColor(29, 53, 87);
$pdf->SetFont('Arial', 'B', 26);
$pdf->SetXY(12, 44);
$pdf->Cell(0, 12, 'INVOICE', 0, 1, 'L');

$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('Arial', '', 11);
$metaY = 46;
$pdf->SetXY($W - 90, $metaY);
$pdf->Cell(40, 7, 'Invoice No:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(38, 7, (string)$inv['invoice_no'], 0, 1, 'R');
$pdf->SetFont('Arial', '', 11);
$pdf->SetXY($W - 90, $metaY + 7);
$pdf->Cell(40, 7, 'Date:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(38, 7, date('d M Y', strtotime($inv['created_at'])), 0, 1, 'R');
$pdf->SetFont('Arial', '', 11);
$pdf->SetXY($W - 90, $metaY + 14);
$pdf->Cell(40, 7, 'Status:', 0, 0, 'L');
$paid = ($inv['status'] === 'paid');
$pdf->SetTextColor($paid ? 25 : 200, $paid ? 135 : 40, $paid ? 60 : 40);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(38, 7, strtoupper($inv['status']), 0, 1, 'R');

// Bill-to block.
$pdf->SetTextColor(120, 120, 120);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetXY(12, 70);
$pdf->Cell(0, 6, 'BILL TO', 0, 1, 'L');
$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetXY(12, 76);
$pdf->Cell(0, 7, $tenant['restaurant_name'] ?? 'Customer', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$lines = array_filter([
    $tenant['owner_name'] ?? '',
    trim(($tenant['address'] ?? '') . (!empty($tenant['city']) ? ', ' . $tenant['city'] : '')),
    !empty($tenant['mobile']) ? 'Mobile: ' . $tenant['mobile'] : '',
    !empty($tenant['email']) ? $tenant['email'] : '',
    !empty($tenant['gst_no']) ? 'GSTIN: ' . $tenant['gst_no'] : '',
]);
$ly = 84;
foreach ($lines as $line) {
    $pdf->SetXY(12, $ly);
    $pdf->Cell(0, 5.5, asciiText($line), 0, 1, 'L');
    $ly += 5.5;
}

// Line-item table.
$tableY = max($ly + 8, 116);
$pdf->SetFillColor(29, 53, 87);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(12, $tableY);
$pdf->Cell($W - 84, 9, '  Description', 0, 0, 'L', true);
$pdf->Cell(60, 9, 'Amount  ', 0, 1, 'R', true);

$rowY = $tableY + 9;
$pdf->SetTextColor(40, 40, 40);
$pdf->SetFont('Arial', '', 11);
$planName = $plan['name'] ?? 'Subscription Plan';
$validity = $plan ? ('  (' . (int)$plan['validity_days'] . ' days validity)') : '';
$pdf->SetXY(12, $rowY);
$pdf->Cell($W - 84, 9, '  ' . asciiText($planName) . $validity, 'B', 0, 'L');
$pdf->Cell(60, 9, moneyPdf($inv['amount']) . '  ', 'B', 1, 'R');

// Totals.
$totY = $rowY + 14;
$labelX = $W - 12 - 100;
$pdf->SetFont('Arial', '', 11);
$pdf->SetXY($labelX, $totY);
$pdf->Cell(60, 7, 'Subtotal', 0, 0, 'R');
$pdf->Cell(40, 7, moneyPdf($inv['amount']), 0, 1, 'R');
$pdf->SetXY($labelX, $totY + 7);
$pdf->Cell(60, 7, 'Tax (GST)', 0, 0, 'R');
$pdf->Cell(40, 7, moneyPdf($inv['tax']), 0, 1, 'R');

$pdf->SetDrawColor(180, 180, 180);
$pdf->Line($labelX + 4, $totY + 15, $W - 12, $totY + 15);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(29, 53, 87);
$pdf->SetXY($labelX, $totY + 17);
$pdf->Cell(60, 9, 'TOTAL', 0, 0, 'R');
$pdf->Cell(40, 9, moneyPdf($inv['total']), 0, 1, 'R');

// Paid stamp / note.
if ($paid) {
    $pdf->SetTextColor(25, 135, 60);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetXY(12, $totY + 8);
    $stamp = 'PAID';
    if (!empty($inv['paid_on'])) { $stamp .= ' on ' . date('d M Y', strtotime($inv['paid_on'])); }
    $pdf->Cell(0, 7, $stamp, 0, 1, 'L');
}

// Footer.
$H = $pdf->GetPageHeight();
$pdf->SetDrawColor(210, 210, 210);
$pdf->Line(12, $H - 24, $W - 12, $H - 24);
$pdf->SetTextColor(120, 120, 120);
$pdf->SetFont('Arial', '', 9);
$pdf->SetXY(12, $H - 20);
$pdf->MultiCell(0, 5, asciiText('This is a computer-generated invoice and does not require a signature. '
    . 'Thank you for choosing ' . $siteName . '.'), 0, 'C');
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetXY(12, $H - 10);
$pdf->Cell(0, 5, POWERED_BY, 0, 0, 'C');

$pdf->Output('I', 'invoice-' . preg_replace('/[^A-Za-z0-9\-]+/', '', (string)$inv['invoice_no']) . '.pdf');
exit;

// =============================================================================
// Helpers
// =============================================================================

/** Format an amount for a core-font PDF (avoids the non-WinAnsi ₹ glyph). */
function moneyPdf($amount): string
{
    return 'Rs. ' . number_format((float)$amount, 2);
}

/** Downgrade any non-Latin1 characters so core fonts never render tofu. */
function asciiText(string $s): string
{
    $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    if ($conv === false) {
        $conv = preg_replace('/[^\x20-\x7E]/', '', $s);
    }
    return $conv;
}

/** Resolve the global site logo to a safe absolute path under /uploads. */
function invoiceSiteLogo(?string $rel): string
{
    $rel = trim((string)$rel);
    if ($rel === '' || strpos($rel, '..') !== false) { return ''; }
    $abs  = realpath(ROOT_PATH . '/' . ltrim($rel, '/'));
    $base = realpath(UPLOAD_PATH);
    if ($abs === false || $base === false) { return ''; }
    if (strncmp($abs, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) { return ''; }
    return is_file($abs) ? $abs : '';
}
