<?php
// Export Recipients as CSV or PDF
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
$status = $_GET['status'] ?? 'ALL'; // ALL, ACTIVE, INACTIVE
$requestStatus = $_GET['request_status'] ?? 'ALL'; // ALL, PENDING, APPROVED, REJECTED
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '';

// Fetch recipients
$where = '';
$params = [];

if ($status !== 'ALL') {
    $where = ' AND r.status = ?';
    $params[] = $status;
}

if ($requestStatus !== 'ALL') {
    $where .= ' AND EXISTS (SELECT 1 FROM blood_requests br WHERE br.recipientId = r.recipientId AND br.status = ?)';
    $params[] = $requestStatus;
}

if ($search) {
    $where .= ' AND (u.username LIKE ? OR r.hospitalName LIKE ?)';
    $params[] = $search;
    $params[] = $search;
}

$sql = "SELECT r.recipientId, r.encryptedName, r.encryptedPhone, r.hospitalName, r.status, u.created_at, u.username
        FROM recipients r
        JOIN users u ON r.userId = u.userId
        WHERE 1=1 $where
        ORDER BY u.created_at DESC";

if ($search || $status !== 'ALL' || $requestStatus !== 'ALL') {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $conn->query($sql);
}

$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
$audit->log($_SESSION['userId'], 'RECIPIENT_EXPORT_' . strtoupper($format), 'Count:' . count($recipients) . ' Status:' . $status . ' RequestStatus:' . $requestStatus);

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
    header('Content-Disposition: attachment; filename="recipients_' . date('Y-m-d_H-i-s') . '.pdf"');

    $lines = [
        sprintf("%-20s %-15s %-25s %-10s %-15s", 'RECIPIENT ID', 'USERNAME', 'HOSPITAL', 'STATUS', 'REGISTERED'),
        str_repeat('=', 120),
        'Recipient Status Filter: ' . $status,
        'Request Status Filter: ' . $requestStatus
    ];

    foreach ($recipients as $recipient) {
        $lines[] = sprintf("%-20s %-15s %-25s %-10s %-15s",
            substr($recipient['recipientId'], 0, 20),
            substr($recipient['username'], 0, 15),
            substr($recipient['hospitalName'], 0, 25),
            $recipient['status'],
            date('M d, Y', strtotime($recipient['created_at']))
        );
    }

    $lines[] = '';
    $lines[] = 'Total Recipients: ' . count($recipients);
    echo generatePdfDocument('RECIPIENTS REPORT', $lines);
    exit;
} else {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recipients_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Header
    fputcsv($output, ['Recipient ID', 'Username', 'Hospital Name', 'Status', 'Registration Date']);

    // CSV Data
    foreach ($recipients as $recipient) {
        fputcsv($output, [
            $recipient['recipientId'],
            $recipient['username'],
            $recipient['hospitalName'],
            $recipient['status'],
            date('Y-m-d H:i:s', strtotime($recipient['created_at']))
        ]);
    }

    fclose($output);
}
exit;
