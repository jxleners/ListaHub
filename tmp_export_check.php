<?php
require 'utils/fpdf_export.php';
$pdf = new FPDF();
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(95, 70, 50);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(50, 8, 'Header', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(50, 8, 'Body', 0, 1, 'L');
file_put_contents('tmp-test.pdf', $pdf->Output('S'));
echo "generated\n";
