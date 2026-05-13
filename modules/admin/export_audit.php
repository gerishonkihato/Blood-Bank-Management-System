<?php
// Export Audit Log as CSV or PDF
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

$format = strtolower($_GET['format'] ?? 'csv');
if ($format !== 'pdf') {
    $format = 'csv';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$actionFilter = isset($_GET['action']) ? trim($_GET['action']) : '';

$where = '';
$params = [];

if ($actionFilter !== '') {
    $where .= ' AND a.action LIKE ?';
    $params[] = '%' . $actionFilter . '%';
}

if ($search !== '') {
    $where .= ' AND (u.username LIKE ? OR a.action LIKE ? OR a.targetId LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql = "SELECT a.logId, a.timestamp, a.userId, u.username, a.action, a.targetId
        FROM audit_log a
        LEFT JOIN users u ON a.userId = u.userId
        WHERE 1=1 $where
        ORDER BY a.timestamp DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $conn->query($sql);
}

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$audit->log($_SESSION['userId'], 'AUDIT_EXPORT_' . strtoupper($format), 'Count:' . count($logs));

if ($format === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.pdf"');

    $lines = [
        sprintf("%-8s %-19s %-10s %-18s %-25s %-30s", 'LOG ID', 'TIMESTAMP', 'USER ID', 'USERNAME', 'ACTION', 'TARGET ID'),
        str_repeat('=', 120)
    ];

    foreach ($logs as $log) {
        $lines[] = sprintf(
            "%-8s %-19s %-10s %-18s %-25s %-30s",
            substr($log['logId'], 0, 8),
            date('Y-m-d H:i:s', strtotime($log['timestamp'])),
            substr($log['userId'] ?? 'SYSTEM', 0, 10),
            substr($log['username'] ?? 'SYSTEM', 0, 18),
            substr($log['action'], 0, 25),
            substr($log['targetId'] ?? '-', 0, 30)
        );
    }

    $lines[] = "\nTotal records: " . count($logs);

    if (!function_exists('pdfEscape')) {
        function pdfEscape($text) {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        }

        function generatePdfDocument($lines) {
            $content = [
                'BT',
                '/F1 10 Tf',
                '14 TL',
                '40 780 Td',
                '(' . pdfEscape($lines[0]) . ') Tj',
                'T*'
            ];

            for ($i = 1; $i < count($lines); $i++) {
                $content[] = '(' . pdfEscape($lines[$i]) . ') Tj';
                $content[] = 'T*';
            }

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

    echo generatePdfDocument($lines);
    exit;
}

// CSV export
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Log ID', 'Timestamp', 'User ID', 'Username', 'Action', 'Target ID']);

foreach ($logs as $log) {
    fputcsv($output, [
        $log['logId'],
        $log['timestamp'],
        $log['userId'] ?? 'SYSTEM',
        $log['username'] ?? 'SYSTEM',
        $log['action'],
        $log['targetId'] ?? '-',
    ]);
}

fclose($output);
exit;
