<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';

// Role-based access control: ADMIN only
if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'ADMIN') { 
    header("Location: ../../index.php"); 
    exit; 
}

// Include required files using __DIR__ pattern
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../../core/AuditLog.php';

// Initialize services
$sec = new SecurityService();
$audit = new AuditLog();
$db = new Database();
$conn = $db->getConnection();

// handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['requestId'])) {
    $action = $_POST['action'];
    $reqId = $_POST['requestId'];
    try {
        if ($action === 'approve') {
            // update request status and record approver
            $update = $conn->prepare("UPDATE blood_requests SET status='APPROVED', approvedBy=? WHERE requestId=?");
            $update->execute([$_SESSION['userId'], $reqId]);
            // deduct inventory if available
            $typeStmt = $conn->prepare("SELECT bloodGroup, quantity FROM blood_requests WHERE requestId = ?");
            $typeStmt->execute([$reqId]);
            $row = $typeStmt->fetch();
            if ($row) {
                $invUpdate = $conn->prepare("UPDATE inventory SET unitsAvailable = unitsAvailable - ? WHERE bloodType = ?");
                $invUpdate->execute([$row['quantity'], $row['bloodGroup']]);
            }
            $audit->log($_SESSION['userId'], 'BLOOD_REQUEST_APPROVED', $reqId);
        } elseif ($action === 'reject') {
            $update = $conn->prepare("UPDATE blood_requests SET status='REJECTED', approvedBy=? WHERE requestId=?");
            $update->execute([$_SESSION['userId'], $reqId]);
            $audit->log($_SESSION['userId'], 'BLOOD_REQUEST_REJECTED', $reqId);
        }
    } catch (PDOException $e) {
        error_log("Admin action failed: " . $e->getMessage());
    }
}

// Log admin dashboard access
$audit->log($_SESSION['userId'], 'ADMIN_DASHBOARD_ACCESS', $_SESSION['userId']);

// Fetch statistics
$stats = [];

// Helper to execute a count query safely
function safeCount($conn, $sql) {
    try {
        $stmt = $conn->query($sql);
        $row = $stmt->fetch();
        return $row['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Admin stats query failed: " . $e->getMessage());
        return 0;
    }
}

// Total donors
$stats['totalDonors'] = safeCount($conn, "SELECT COUNT(*) as count FROM donors");

// Total recipients
$stats['totalRecipients'] = safeCount($conn, "SELECT COUNT(*) as count FROM recipients");

// Pending requests
$stats['pendingRequests'] = safeCount($conn, "SELECT COUNT(*) as count FROM blood_requests WHERE status = 'PENDING'");

// Total inventory units
try {
    $stmt = $conn->query("SELECT SUM(unitsAvailable) as total FROM inventory");
    $stats['totalBloodUnits'] = $stmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Inventory sum query failed: " . $e->getMessage());
    $stats['totalBloodUnits'] = 0;
}

// Fetch recent audit logs
$auditStmt = $conn->query("
    SELECT a.*, u.username 
    FROM audit_log a 
    LEFT JOIN users u ON a.userId = u.userId 
    ORDER BY a.timestamp DESC 
    LIMIT 10
");
$auditLogs = $auditStmt->fetchAll();

// Fetch pending blood requests
$requestsStmt = $conn->query("
    SELECT r.*, u.username as recipientName, rec.hospitalName
    FROM blood_requests r
    JOIN recipients rec ON r.recipientId = rec.recipientId
    JOIN users u ON rec.userId = u.userId
    WHERE r.status = 'PENDING'
    ORDER BY r.requestTimestamp DESC
    LIMIT 5
");
$pendingRequests = $requestsStmt->fetchAll();

// Fetch inventory status
$inventoryStmt = $conn->query("SELECT * FROM inventory ORDER BY bloodType");
$inventory = $inventoryStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
    <div class="dashboard-container">
        <div class="container">
        <!-- Security Notice -->
        <div class="security-notice">
            🔐 <strong>Security Notice:</strong> All sensitive data is encrypted using AES-256. 
            Decryption occurs only for authorized administrators. All actions are logged for audit compliance.
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card donors">
                <h3>Total Donors</h3>
                <div class="value"><?php echo $stats['totalDonors']; ?></div>
            </div>
            <div class="stat-card recipients">
                <h3>Total Recipients</h3>
                <div class="value"><?php echo $stats['totalRecipients']; ?></div>
            </div>
            <div class="stat-card requests">
                <h3>Pending Requests</h3>
                <div class="value"><?php echo $stats['pendingRequests']; ?></div>
            </div>
            <div class="stat-card inventory">
                <h3>Blood Units Available</h3>
                <div class="value"><?php echo $stats['totalBloodUnits']; ?></div>
            </div>
        </div>
        
        <!-- Quick Action: Manage Inventory -->
        <div class="cta-banner">
            <div>
                <h2>🩸 Blood Inventory Management</h2>
                <p>Add, update, or adjust blood unit quantities and track inventory changes</p>
            </div>
            <a href="inventory.php" class="btn-cta">📦 Manage Inventory</a>
        </div>
        
        <!-- Main Content -->
        <div class="content-grid">
            <!-- Pending Requests -->
            <div class="card">
                <h2>⏳ Pending Blood Requests</h2>
                <?php if (empty($pendingRequests)): ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">No pending requests</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Recipient</th>
                                <th>Blood Type</th>
                                <th>Quantity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['requestId']); ?></td>
                                <td><?php echo htmlspecialchars($req['recipientName']); ?></td>
                                <td><?php echo htmlspecialchars($req['bloodGroup']); ?></td>
                                <td><?php echo $req['quantity']; ?> units</td>
                                <td>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="requestId" value="<?php echo htmlspecialchars($req['requestId']); ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-approve">✅ Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="requestId" value="<?php echo htmlspecialchars($req['requestId']); ?>">
                                        <button type="submit" name="action" value="reject" class="btn btn-reject">❌ Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Audit Logs -->
            <div class="card">
                <h2>📋 Recent Activity</h2>
                <?php foreach ($auditLogs as $log): ?>
                <div class="audit-item">
                    <div class="audit-action"><?php echo htmlspecialchars($log['action']); ?></div>
                    <div class="audit-time">
                        <?php echo htmlspecialchars($log['username'] ?? 'SYSTEM'); ?> | 
                        <?php echo date('M d, H:i', strtotime($log['timestamp'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
</body>
</html>
