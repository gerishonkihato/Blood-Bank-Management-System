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

// Fetch paginated audit logs
$auditPage = isset($_GET['audit_page']) ? max(1, intval($_GET['audit_page'])) : 1;
$auditPageSize = 25;
$auditOffset = ($auditPage - 1) * $auditPageSize;

try {
    $countStmt = $conn->query("SELECT COUNT(*) AS total FROM audit_log");
    $totalAuditLogs = $countStmt->fetch()['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Audit log count failed: " . $e->getMessage());
    $totalAuditLogs = 0;
}

$auditPageCount = max(1, (int) ceil($totalAuditLogs / $auditPageSize));

$auditStmt = $conn->prepare("SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.userId = u.userId ORDER BY a.timestamp DESC LIMIT ? OFFSET ?");
$auditStmt->bindValue(1, $auditPageSize, PDO::PARAM_INT);
$auditStmt->bindValue(2, $auditOffset, PDO::PARAM_INT);
$auditStmt->execute();
$auditLogs = $auditStmt->fetchAll();

// Fetch pending blood requests with search capability
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'PENDING';

if ($searchTerm) {
    $searchParam = '%' . $searchTerm . '%';
    if ($statusFilter === 'ALL') {
        $requestsStmt = $conn->prepare("
            SELECT r.*, u.username as recipientName, rec.hospitalName
            FROM blood_requests r
            JOIN recipients rec ON r.recipientId = rec.recipientId
            JOIN users u ON rec.userId = u.userId
            WHERE u.username LIKE ? OR r.bloodGroup LIKE ? OR rec.hospitalName LIKE ?
            ORDER BY r.requestTimestamp DESC
        ");
        $requestsStmt->execute([$searchParam, $searchParam, $searchParam]);
    } else {
        $requestsStmt = $conn->prepare("
            SELECT r.*, u.username as recipientName, rec.hospitalName
            FROM blood_requests r
            JOIN recipients rec ON r.recipientId = rec.recipientId
            JOIN users u ON rec.userId = u.userId
            WHERE r.status = ? AND (u.username LIKE ? OR r.bloodGroup LIKE ? OR rec.hospitalName LIKE ?)
            ORDER BY r.requestTimestamp DESC
        ");
        $requestsStmt->execute([$statusFilter, $searchParam, $searchParam, $searchParam]);
    }
} else {
    if ($statusFilter === 'ALL') {
        $requestsStmt = $conn->query("
            SELECT r.*, u.username as recipientName, rec.hospitalName
            FROM blood_requests r
            JOIN recipients rec ON r.recipientId = rec.recipientId
            JOIN users u ON rec.userId = u.userId
            ORDER BY r.requestTimestamp DESC
            LIMIT 20
        ");
    } else {
        $requestsStmt = $conn->query("
            SELECT r.*, u.username as recipientName, rec.hospitalName
            FROM blood_requests r
            JOIN recipients rec ON r.recipientId = rec.recipientId
            JOIN users u ON rec.userId = u.userId
            WHERE r.status = 'PENDING'
            ORDER BY r.requestTimestamp DESC
            LIMIT 20
        ");
    }
}
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px;">
                    <h2 style="margin: 0;">⏳ Pending Blood Requests</h2>
                    <form method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 500px;">
                        <input type="text" name="search" placeholder="Search by recipient, blood type, or hospital..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <select name="status" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="PENDING" <?php echo $statusFilter === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                            <option value="APPROVED" <?php echo $statusFilter === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                            <option value="REJECTED" <?php echo $statusFilter === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="ALL" <?php echo $statusFilter === 'ALL' ? 'selected' : ''; ?>>All</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">🔍 Search</button>
                    </form>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_requests.php?format=csv&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>" class="btn btn-success" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📥 CSV</a>
                        <a href="export_requests.php?format=pdf&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>" class="btn btn-info" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📄 PDF</a>
                    </div>
                </div>
                <?php if (empty($pendingRequests)): ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">No requests found</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Recipient</th>
                                <th>Hospital</th>
                                <th>Blood Type</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['requestId']); ?></td>
                                <td><?php echo htmlspecialchars($req['recipientName']); ?></td>
                                <td><?php echo htmlspecialchars($req['hospitalName']); ?></td>
                                <td><?php echo htmlspecialchars($req['bloodGroup']); ?></td>
                                <td><?php echo $req['quantity']; ?> units</td>
                                <td><span class="status-badge status-<?php echo strtolower($req['status']); ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
                                <td>
                                    <?php if ($req['status'] === 'PENDING'): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="requestId" value="<?php echo htmlspecialchars($req['requestId']); ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-approve" style="padding: 4px 8px; font-size: 11px;">✅ Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="requestId" value="<?php echo htmlspecialchars($req['requestId']); ?>">
                                            <button type="submit" name="action" value="reject" class="btn btn-reject" style="padding: 4px 8px; font-size: 11px;">❌ Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #7f8c8d; font-size: 12px;">No Actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Audit Logs -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 16px;">
                    <h2>📋 Recent Activity</h2>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="export_audit.php?format=csv" class="btn btn-success" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📥 CSV</a>
                        <a href="export_audit.php?format=pdf" class="btn btn-info" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📄 PDF</a>
                    </div>
                </div>
                <?php if (empty($auditLogs)): ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">No audit activity has been recorded yet.</p>
                <?php else: ?>
                    <p style="margin: 0 0 10px; color: #555; font-size: 14px;">Showing page <?php echo $auditPage; ?> of <?php echo $auditPageCount; ?> — total <?php echo $totalAuditLogs; ?> records.</p>
                    <table class="audit-table" style="width:100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align:left; padding: 8px; border-bottom: 1px solid #ddd;">Timestamp</th>
                                <th style="text-align:left; padding: 8px; border-bottom: 1px solid #ddd;">User</th>
                                <th style="text-align:left; padding: 8px; border-bottom: 1px solid #ddd;">Action</th>
                                <th style="text-align:left; padding: 8px; border-bottom: 1px solid #ddd;">Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #555;"><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></td>
                                <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333;"><?php echo htmlspecialchars($log['username'] ?? 'SYSTEM'); ?></td>
                                <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #333;"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td style="padding: 8px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #777;"><?php echo htmlspecialchars($log['targetId'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($auditPageCount > 1): ?>
                        <div style="display: flex; justify-content: center; flex-wrap: wrap; gap: 8px; margin-top: 16px;">
                            <?php for ($page = 1; $page <= $auditPageCount; $page++): ?>
                                <?php if ($page === $auditPage): ?>
                                    <span style="padding: 8px 12px; background: #2c3e50; color: #fff; border-radius: 4px; font-size: 14px;"><?php echo $page; ?></span>
                                <?php else: ?>
                                    <a href="?audit_page=<?php echo $page; ?>" style="padding: 8px 12px; background: #f4f4f4; color: #333; border-radius: 4px; text-decoration: none; font-size: 14px;"><?php echo $page; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</body>
</html>
