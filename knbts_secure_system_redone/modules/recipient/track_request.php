<?php
// Track blood requests for recipient
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'RECIPIENT') {
    header("Location: ../../index.php");
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../../core/AuditLog.php';
$db = new Database();
$conn = $db->getConnection();
$audit = new AuditLog();

$userId = $_SESSION['userId'];

$recipientStmt = $conn->prepare("SELECT * FROM recipients WHERE userId = ?");
$recipientStmt->execute([$userId]);
$recipient = $recipientStmt->fetch();

$requests = [];
if ($recipient) {
    $stmt = $conn->prepare("SELECT * FROM blood_requests WHERE recipientId = ? ORDER BY requestTimestamp DESC");
    $stmt->execute([$recipient['recipientId']]);
    $requests = $stmt->fetchAll();
    $audit->log($userId, 'VIEW_REQUESTS', $recipient['recipientId']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Requests - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background: #f0f0f0; }
        .status-pending { color: #856404; }
        .status-approved { color: #155724; }
        .status-rejected { color: #721c24; }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">

    <h2>My Blood Requests</h2>
    <?php if (empty($requests)): ?>
        <p>No requests found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Blood Type</th><th>Qty</th><th>Status</th><th>Requested</th></tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['requestId']); ?></td>
                    <td><?php echo htmlspecialchars($r['bloodGroup']); ?></td>
                    <td><?php echo (int)$r['quantity']; ?></td>
                    <td class="status-<?php echo strtolower($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($r['requestTimestamp'])); ?></td>
                    <tr></tr>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</body>
</html>
