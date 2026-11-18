<?php
/** PDF receipt generation (FPDF). */
declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

/** Loads everything needed for a receipt; callers must check ownership themselves. */
function fetch_receipt_data(PDO $pdo, int $paymentId): ?array
{
    $s = $pdo->prepare('SELECT pm.*, r.receipt_number, r.generated_at, t.user_id AS tenant_user_id,
            tu.full_name AS tenant_name, tu.phone AS tenant_phone, un.unit_name, pr.property_name, pr.address,
            lu.full_name AS landlord_name, lu.phone AS landlord_phone, lu.email AS landlord_email
        FROM payments pm
        JOIN receipts r ON r.payment_id = pm.id
        JOIN tenants t ON t.id = pm.tenant_id
        JOIN users tu ON tu.id = t.user_id
        JOIN units un ON un.id = pm.unit_id
        JOIN properties pr ON pr.id = un.property_id
        JOIN users lu ON lu.id = pm.landlord_id
        WHERE pm.id = ?');
    $s->execute([$paymentId]);
    return $s->fetch() ?: null;
}

function pdf_txt(string $s): string
{
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
    }
    return (string)preg_replace('/[^ -~]/', '?', $s);
}

function pdf_money(float $n): string
{
    return CURRENCY_CODE . ' ' . number_format($n, 2);
}

/** Streams the receipt PDF (inline, or as a download) and exits. */
function render_receipt_pdf(array $d, bool $download = false): never
{
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetTitle('Receipt ' . $d['receipt_number']);
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // Header band
    $pdf->SetFillColor(27, 36, 51);
    $pdf->Rect(20, 20, 170, 28, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetXY(26, 25);
    $pdf->Cell(100, 8, 'OFFICIAL RECEIPT');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY(26, 34);
    $pdf->Cell(100, 6, pdf_txt(APP_NAME));
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetXY(120, 25);
    $pdf->Cell(64, 7, pdf_txt($d['receipt_number']), 0, 2, 'R');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(64, 6, 'Issued: ' . date('d M Y', strtotime($d['generated_at'])), 0, 0, 'R');

    // Landlord block
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetXY(20, 56);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(85, 5, 'RECEIVED BY (LANDLORD)');
    $pdf->Cell(85, 5, 'RECEIVED FROM (TENANT)', 0, 1);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(85, 7, pdf_txt($d['landlord_name']));
    $pdf->Cell(85, 7, pdf_txt($d['tenant_name']), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(85, 5, pdf_txt($d['landlord_email']));
    $pdf->Cell(85, 5, pdf_txt($d['tenant_phone']), 0, 1);
    $pdf->Cell(85, 5, pdf_txt($d['landlord_phone']), 0, 1);

    // Details table
    $pdf->Ln(8);
    $rows = [
        ['Property', $d['property_name'] . ($d['address'] ? ' (' . $d['address'] . ')' : '')],
        ['Unit', $d['unit_name']],
        ['Payment date', date('d M Y', strtotime($d['payment_date']))],
        ['Payment method', ucfirst($d['payment_method'])],
        ['Balance before payment', pdf_money((float)$d['balance_before'])],
    ];
    $pdf->SetDrawColor(220, 224, 230);
    foreach ($rows as $i => [$label, $value]) {
        $pdf->SetFillColor($i % 2 ? 255 : 246, $i % 2 ? 255 : 247, $i % 2 ? 255 : 250);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(90, 100, 115);
        $pdf->Cell(60, 9, ' ' . $label, 'B', 0, 'L', true);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(110, 9, ' ' . pdf_txt($value), 'B', 1, 'L', true);
    }

    // Amount highlight
    $pdf->Ln(6);
    $pdf->SetFillColor(230, 244, 234);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(22, 101, 52);
    $pdf->Cell(60, 14, '  AMOUNT PAID', 0, 0, 'L', true);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->Cell(110, 14, pdf_money((float)$d['amount_paid']) . '  ', 0, 1, 'R', true);

    // Balance after
    $after = (float)$d['balance_after'];
    if ($after > 0) {
        [$text, $rgb] = ['OWING ' . pdf_money($after), [185, 28, 28]];
    } elseif ($after < 0) {
        [$text, $rgb] = ['IN CREDIT ' . pdf_money(abs($after)), [29, 78, 216]];
    } else {
        [$text, $rgb] = ['CLEAR (fully paid)', [22, 101, 52]];
    }
    $pdf->Ln(3);
    $pdf->SetFillColor(246, 247, 250);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(90, 100, 115);
    $pdf->Cell(60, 11, '  Balance after payment', 0, 0, 'L', true);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->Cell(110, 11, $text . '  ', 0, 1, 'R', true);

    if (!empty($d['notes'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(90, 100, 115);
        $pdf->Cell(170, 5, 'NOTES', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(170, 5, pdf_txt($d['notes']));
    }

    // Footer
    $pdf->SetDrawColor(180, 185, 195);
    $pdf->Line(20, 255, 190, 255);
    $pdf->SetXY(20, 259);
    $pdf->SetFont('Helvetica', 'I', 10);
    $pdf->SetTextColor(90, 100, 115);
    $pdf->Cell(170, 6, 'This is an official receipt', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->Cell(170, 5, 'Generated by ' . pdf_txt(APP_NAME), 0, 0, 'C');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $pdf->Output($download ? 'D' : 'I', $d['receipt_number'] . '.pdf');
    exit;
}
