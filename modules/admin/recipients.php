<?php
// Recipients management page for Admin
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';
if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'ADMIN') {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../../core/AuditLog.php';

$sec = new SecurityService();
$audit = new AuditLog();
$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle recipient status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['recipientId'])) {
    $action = $_POST['action'];
    $recipientId = $_POST['recipientId'];

    try {
        if ($action === 'activate') {
            $update = $conn->prepare("UPDATE recipients SET status = 'ACTIVE' WHERE recipientId = ?");
            $update->execute([$recipientId]);
            $audit->log($_SESSION['userId'], 'RECIPIENT_ACTIVATED', $recipientId);
            $message = 'Recipient activated successfully.';
            $messageType = 'success';
        } elseif ($action === 'deactivate') {
            $update = $conn->prepare("UPDATE recipients SET status = 'INACTIVE' WHERE recipientId = ?");
            $update->execute([$recipientId]);
            $audit->log($_SESSION['userId'], 'RECIPIENT_DEACTIVATED', $recipientId);
            $message = 'Recipient deactivated successfully.';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        error_log('Failed to update recipient status: ' . $e->getMessage());
        $message = 'An error occurred while updating recipient status: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get recipients list with search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'ALL';
$requestStatus = isset($_GET['request_status']) ? $_GET['request_status'] : 'ALL';

$where = '';
$params = [];

if ($statusFilter !== 'ALL') {
    $where = ' AND r.status = ?';
    $params[] = $statusFilter;
}

if ($requestStatus !== 'ALL') {
    $where .= ' AND EXISTS (SELECT 1 FROM blood_requests br WHERE br.recipientId = r.recipientId AND br.status = ?)';
    $params[] = $requestStatus;
}

if ($searchTerm) {
    $searchParam = '%' . $searchTerm . '%';
    $where .= ' AND (u.username LIKE ? OR r.hospitalName LIKE ?)';
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql = "SELECT r.recipientId, r.encryptedName, r.encryptedPhone, r.hospitalName, r.status, u.created_at, u.username
        FROM recipients r
        JOIN users u ON r.userId = u.userId
        WHERE 1=1 $where
        ORDER BY u.created_at DESC";

if ($searchTerm || $statusFilter !== 'ALL' || $requestStatus !== 'ALL') {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
} else {
    $stmt = $conn->query($sql);
}

$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recipient statistics
$statsStmt = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'INACTIVE' THEN 1 ELSE 0 END) as inactive
    FROM recipients
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$audit->log($_SESSION['userId'], 'ADMIN_RECIPIENTS_ACCESS', $_SESSION['userId']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipients Management - KNBTS Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card recipients">
                    <h3>Total Recipients</h3>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="stat-card donors">
                    <h3>Active Recipients</h3>
                    <div class="value"><?php echo $stats['active']; ?></div>
                </div>
                <div class="stat-card requests">
                    <h3>Inactive Recipients</h3>
                    <div class="value"><?php echo $stats['inactive']; ?></div>
                </div>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px;">
                    <h2 style="margin: 0;">🏥 All Recipients</h2>
                    <form method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 500px;">
                        <input type="text" name="search" placeholder="Search by username or hospital..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <select name="status" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="ALL" <?php echo $statusFilter === 'ALL' ? 'selected' : ''; ?>>All Status</option>
                            <option value="ACTIVE" <?php echo $statusFilter === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                            <option value="INACTIVE" <?php echo $statusFilter === 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <select name="request_status" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="ALL" <?php echo $requestStatus === 'ALL' ? 'selected' : ''; ?>>All Requests</option>
                            <option value="PENDING" <?php echo $requestStatus === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                            <option value="APPROVED" <?php echo $requestStatus === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                            <option value="REJECTED" <?php echo $requestStatus === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">🔍 Search</button>
                        <?php if ($searchTerm || $statusFilter !== 'ALL' || $requestStatus !== 'ALL'): ?>
                            <a href="recipients.php" class="btn" style="padding: 8px 16px; background: #95a5a6; color: white; text-decoration: none; border-radius: 4px;">✕ Clear</a>
                        <?php endif; ?>
                    </form>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_recipients.php?format=csv&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&request_status=<?php echo urlencode($requestStatus); ?>" class="btn btn-success" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📥 CSV</a>
                        <a href="export_recipients.php?format=pdf&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&request_status=<?php echo urlencode($requestStatus); ?>" class="btn btn-info" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📄 PDF</a>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Recipient ID</th>
                            <th>Hospital</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recipients)): ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px; color: #7f8c8d;">No recipients found</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recipients as $recipient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($recipient['username']); ?></td>
                            <td><?php echo htmlspecialchars($recipient['recipientId']); ?></td>
                            <td><?php echo htmlspecialchars($recipient['hospitalName']); ?></td>
                            <td><span class="status-badge status-<?php echo strtolower($recipient['status']); ?>"><?php echo htmlspecialchars($recipient['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($recipient['created_at'])); ?></td>
                            <td>
                                <?php if ($recipient['status'] === 'ACTIVE'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="recipientId" value="<?php echo htmlspecialchars($recipient['recipientId']); ?>">
                                        <button type="submit" name="action" value="deactivate" class="btn btn-reject" style="padding: 4px 8px; font-size: 11px;">Deactivate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="recipientId" value="<?php echo htmlspecialchars($recipient['recipientId']); ?>">
                                        <button type="submit" name="action" value="activate" class="btn btn-approve" style="padding: 4px 8px; font-size: 11px;">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="../../assets/js/app.js"></script>
</body>
</html>