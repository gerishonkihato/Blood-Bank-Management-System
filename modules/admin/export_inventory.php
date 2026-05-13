<?php
// Export Inventory as CSV or PDF
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'ADMIN') {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/AuditLog.php';

$db = new Database();
$conn = $db->getConnection();
$audit = new AuditLog();

// Get filter parameter
$format = $_GET['format'] ?? 'csv'; // csv or pdf
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '';

// Fetch inventory
if ($search) {
    $stmt = $conn->prepare('SELECT * FROM inventory WHERE bloodType LIKE ? ORDER BY bloodType');
    $stmt->execute([$search]);
} else {
    $stmt = $conn->query('SELECT * FROM inventory ORDER BY bloodType');
}

$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
$audit->log($_SESSION['userId'], 'INVENTORY_EXPORT_' . strtoupper($format), 'Count:' . count($inventory));

function pdfEscape($text) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function generatePdfDocument($title, $lines) {
    $content = [
        'BT',
        '/F1 12 Tf',
        '14 TL',
        '50 780 Td',
        '(' . pdfEscape($title) . ') Tj',
        'T*'
    ];

    foreach ($lines as $line) {
        $content[] = '(' . pdfEscape($line) . ') Tj';
        $content[] = 'T*';
    }

    $content[] = 'ET';
    $stream = implode("\n", $content);
    $length = strlen($stream);

    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj",
        "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj",
        "5 0 obj\n<< /Length $length >>\nstream\n$stream\nendstream\nendobj"
    ];

    $pdf = "%PDF-1.4\n" . implode("\n", $objects) . "\n";
    $offset = strlen("%PDF-1.4\n");
    $xref = "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

    foreach ($objects as $obj) {
        $xref .= sprintf("%010d 00000 n \n", $offset);
        $offset += strlen($obj) + 1;
    }

    $pdf .= $xref;
    $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . strlen("%PDF-1.4\n" . implode("\n", $objects) . "\n") . "\n%%EOF";

    return $pdf;
}

if ($format === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="blood_inventory_' . date('Y-m-d_H-i-s') . '.pdf"');

    $lines = [
        sprintf("%-15s %-20s %-20s", 'BLOOD TYPE', 'UNITS AVAILABLE', 'LAST UPDATED'),
        str_repeat('=', 60)
    ];

    $totalUnits = 0;
    foreach ($inventory as $inv) {
        $totalUnits += $inv['unitsAvailable'];
        $lines[] = sprintf("%-15s %-20s %-20s",
            $inv['bloodType'],
            $inv['unitsAvailable'],
            date('M d, Y H:i', strtotime($inv['lastUpdated']))
        );
    }

    $lines[] = str_repeat('=', 60);
    $lines[] = 'Total Units Available: ' . $totalUnits;
    echo generatePdfDocument('BLOOD INVENTORY REPORT', $lines);
    exit;
} else {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blood_inventory_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, ['Blood Type', 'Units Available', 'Last Updated']);
    
    // CSV Data
    $totalUnits = 0;
    foreach ($inventory as $inv) {
        $totalUnits += $inv['unitsAvailable'];
        fputcsv($output, [
            $inv['bloodType'],
            $inv['unitsAvailable'],
            date('Y-m-d H:i:s', strtotime($inv['lastUpdated']))
        ]);
    }
    
    // Add total row
    fputcsv($output, ['TOTAL', $totalUnits, '']);
    
    fclose($output);
}
exit;
