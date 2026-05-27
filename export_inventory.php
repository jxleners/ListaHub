<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once './utils/lhdb.php';
require_once './utils/fpdf_export.php';

$user_id = (int) $_SESSION['user_id'];
$search = trim((string) ($_GET['search'] ?? ''));
$category_filter = trim((string) ($_GET['category_filter'] ?? ''));

try {
    $pdo = getPDO();

    $sql = "SELECT p.product_id, p.product_name, p.sku, p.quantity, p.cost_price,
                   p.retail_price, p.expiration_date, p.status,
                   COALESCE(c.category_name, 'Uncategorized') AS category_name
            FROM Product p
            LEFT JOIN Category c ON c.category_id = p.category_id
            WHERE p.user_id = :user_id";

    $params = [':user_id' => $user_id];

    if ($search !== '') {
        $sql .= " AND (p.product_name LIKE :search OR p.sku LIKE :search2)";
        $params[':search'] = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    if ($category_filter !== '') {
        $sql .= " AND c.category_name = :cat_filter";
        $params[':cat_filter'] = $category_filter;
    }

    $sql .= " ORDER BY p.product_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Inventory export error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to export inventory at this time.');
}

function formatExpiry(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('m/d/Y', $timestamp) : '-';
}

function formatCurrency(float $value): string
{
    return 'PHP ' . number_format($value, 2);
}

function truncateText(string $value, int $limit): string
{
    if ($limit <= 0) {
        return $value;
    }

    if (function_exists('mb_strlen') && mb_strlen($value) > $limit) {
        return mb_substr($value, 0, max(0, $limit - 1)) . '...';
    }

    if (strlen($value) > $limit) {
        return substr($value, 0, max(0, $limit - 1)) . '...';
    }

    return $value;
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 12);
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(62, 44, 35);
$pdf->Cell(0, 8, 'ListaHub Inventory Export', 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 5, 'Generated on ' . date('F j, Y, g:i A'), 0, 1, 'L');

$pdf->SetFont('Helvetica', '', 7.5);
$pdf->Cell(0, 4.5, 'Filters: ' . ($search !== '' ? 'Search: ' . $search : 'All products') . ($category_filter !== '' ? ' | Category: ' . $category_filter : ''), 0, 1, 'L');
$pdf->Ln(2);

$header = [
    'Product Name' => 60,
    'SKU' => 22,
    'Category' => 28,
    'Stock' => 15,
    'Expiry Date' => 20,
    'Retail Price' => 20,
    'Status' => 22,
];

$pdf->SetFont('Helvetica', 'B', 7);
$pdf->SetTextColor(62, 44, 35);
$pdf->SetFillColor(235, 214, 101);

$startX = $pdf->leftMargin;
$pdf->SetXY($startX, $pdf->y);
foreach ($header as $label => $width) {
    $pdf->Cell($width, 5, $label, 0, 0, 'C', true);
}
$pdf->Ln(5);

$pdf->SetFont('Helvetica', '', 7);
$pdf->SetTextColor(40, 40, 40);
$pdf->SetFillColor(255, 255, 255);

if ($products === []) {
    $pdf->Cell(0, 6, 'No products match the selected filters.', 0, 1, 'C');
} else {
    foreach ($products as $product) {
        $pdf->Cell($header['Product Name'], 5, truncateText((string) ($product['product_name'] ?? ''), 28), 0, 0, 'C');
        $pdf->Cell($header['SKU'], 5, truncateText((string) ($product['sku'] ?? '-'), 14), 0, 0, 'C');
        $pdf->Cell($header['Category'], 5, truncateText((string) ($product['category_name'] ?? 'Uncategorized'), 18), 0, 0, 'C');
        $pdf->Cell($header['Stock'], 5, (string) (int) ($product['quantity'] ?? 0), 0, 0, 'C');
        $pdf->Cell($header['Expiry Date'], 5, formatExpiry((string) ($product['expiration_date'] ?? null)), 0, 0, 'C');
        $pdf->Cell($header['Retail Price'], 5, formatCurrency((float) ($product['retail_price'] ?? 0)), 0, 0, 'C');
        $pdf->Cell($header['Status'], 5, (string) ($product['status'] ?? 'In Stock'), 0, 1, 'C');
    }
}

$pdf->Output('D', 'inventory-export-' . date('Y-m-d') . '.pdf');
