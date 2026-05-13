<?php
// Export Active Donors as CSV or PDF
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
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch active donors
if (!empty($searchTerm)) {
    $searchParam = '%' . $searchTerm . '%';
    $stmt = $conn->prepare('SELECT d.donorId, d.encryptedName, d.bloodGroup, d.rhFactor, d.lastDonationDate, d.status, u.username
        FROM donors d
        JOIN users u ON d.userId = u.userId
        WHERE d.status = ? AND (u.username LIKE ? OR CONCAT(d.bloodGroup, d.rhFactor) LIKE ?)
        ORDER BY d.lastDonationDate DESC');
    $stmt->execute(['ACTIVE', $searchParam, $searchParam]);
} else {
    $stmt = $conn->prepare('SELECT d.donorId, d.encryptedName, d.bloodGroup, d.rhFactor, d.lastDonationDate, d.status, u.username
        FROM donors d
        JOIN users u ON d.userId = u.userId
        WHERE d.status = ?
        ORDER BY d.lastDonationDate DESC');
    $stmt->execute(['ACTIVE']);
}

$donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
$audit->log($_SESSION['userId'], 'DONOR_EXPORT_' . strtoupper($format), 'Count:' . count($donors));

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
    header('Content-Disposition: attachment; filename="active_donors_' . date('Y-m-d_H-i-s') . '.pdf"');

    $lines = [
        sprintf("%-15s %-15s %-10s %-20s %-10s", 'DONOR ID', 'USERNAME', 'BLOOD', 'LAST DONATION', 'STATUS'),
        str_repeat('=', 75)
    ];

    foreach ($donors as $donor) {
        $lastDonation = $donor['lastDonationDate'] ? date('M d, Y', strtotime($donor['lastDonationDate'])) : 'Never';
        $lines[] = sprintf("%-15s %-15s %-10s %-20s %-10s",
            substr($donor['donorId'], 0, 15),
            substr($donor['username'], 0, 15),
            $donor['bloodGroup'] . $donor['rhFactor'],
            $lastDonation,
            $donor['status']
        );
    }

    $lines[] = '';
    $lines[] = 'Total Donors: ' . count($donors);
    echo generatePdfDocument('ACTIVE DONORS REPORT', $lines);
    exit;
} else {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="active_donors_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, ['Donor ID', 'Username', 'Blood Group', 'Rh Factor', 'Last Donation Date', 'Status']);
    
    // CSV Data
    foreach ($donors as $donor) {
        fputcsv($output, [
            $donor['donorId'],
            $donor['username'],
            $donor['bloodGroup'],
            $donor['rhFactor'],
            $donor['lastDonationDate'] ?: 'Never',
            $donor['status']
        ]);
    }
    
    fclose($output);
}
exit;
