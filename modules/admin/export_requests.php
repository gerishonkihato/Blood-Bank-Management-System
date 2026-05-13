<?php
// Export Blood Requests as CSV or PDF
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

// Get filter and status parameter
$format = $_GET['format'] ?? 'csv'; // csv or pdf
$status = $_GET['status'] ?? 'PENDING'; // PENDING, APPROVED, REJECTED, or ALL
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '';

// Fetch blood requests
$where = '';
$params = [];

if ($status !== 'ALL') {
    $where = ' AND br.status = ?';
    $params[] = $status;
}

if ($search) {
    $where .= ' AND (u.username LIKE ? OR br.bloodGroup LIKE ? OR rec.hospitalName LIKE ?)';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$sql = "SELECT br.requestId, br.bloodGroup, br.quantity, br.status, br.requestTimestamp, u.username, rec.hospitalName
        FROM blood_requests br
        JOIN recipients rec ON br.recipientId = rec.recipientId
        JOIN users u ON rec.userId = u.userId
        WHERE 1=1 $where
        ORDER BY br.requestTimestamp DESC";

if ($search || $status !== 'ALL') {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $conn->query($sql);
}

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
$audit->log($_SESSION['userId'], 'REQUEST_EXPORT_' . strtoupper($format), 'Count:' . count($requests) . ' Status:' . $status);

if ($format === 'pdf') {
    // PDF Export
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="blood_requests_' . date('Y-m-d_H-i-s') . '.pdf"');
    
    $pdf_content = "BLOOD REQUESTS REPORT\n";
    $pdf_content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $pdf_content .= "Status Filter: $status\n";
    $pdf_content .= "=" . str_repeat("=", 120) . "\n\n";
    $pdf_content .= sprintf("%-20s %-15s %-10s %-15s %-20s %-20s\n", "REQUEST ID", "RECIPIENT", "BLOOD TYPE", "QUANTITY", "STATUS", "REQUESTED DATE");
    $pdf_content .= "=" . str_repeat("=", 120) . "\n";
    
    foreach ($requests as $req) {
        $pdf_content .= sprintf("%-20s %-15s %-10s %-15s %-20s %-20s\n", 
            substr($req['requestId'], 0, 20),
            substr($req['username'], 0, 15),
            $req['bloodGroup'],
            $req['quantity'],
            $req['status'],
            date('M d, Y H:i', strtotime($req['requestTimestamp']))
        );
    }
    
    $pdf_content .= "\n" . "=" . str_repeat("=", 120) . "\n";
    $pdf_content .= "Total Requests: " . count($requests) . "\n";

    if (!function_exists('pdfEscape')) {
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
    }

    $lines = [
        sprintf("%-20s %-15s %-10s %-15s %-20s %-20s", 'REQUEST ID', 'RECIPIENT', 'BLOOD TYPE', 'QUANTITY', 'STATUS', 'REQUESTED DATE'),
        str_repeat('=', 120)
    ];

    foreach ($requests as $req) {
        $lines[] = sprintf("%-20s %-15s %-10s %-15s %-20s %-20s",
            substr($req['requestId'], 0, 20),
            substr($req['username'], 0, 15),
            $req['bloodGroup'],
            $req['quantity'],
            $req['status'],
            date('M d, Y H:i', strtotime($req['requestTimestamp']))
        );
    }

    $lines[] = '';
    $lines[] = 'Total Requests: ' . count($requests);
    echo generatePdfDocument('BLOOD REQUESTS REPORT', $lines);
    exit;
} else {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blood_requests_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, ['Request ID', 'Recipient', 'Hospital', 'Blood Type', 'Quantity', 'Status', 'Requested Date']);
    
    // CSV Data
    foreach ($requests as $req) {
        fputcsv($output, [
            $req['requestId'],
            $req['username'],
            $req['hospitalName'],
            $req['bloodGroup'],
            $req['quantity'],
            $req['status'],
            date('Y-m-d H:i:s', strtotime($req['requestTimestamp']))
        ]);
    }
    
    fclose($output);
}
exit;
