<?php
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

$sec = new SecurityService();
$audit = new AuditLog();
$db = new Database();
$conn = $db->getConnection();

$userId = $_SESSION['userId'];
$username = $_SESSION['username'];

// Fetch recipient profile
$recipientStmt = $conn->prepare("SELECT * FROM recipients WHERE userId = ?");
$recipientStmt->execute([$userId]);
$recipient = $recipientStmt->fetch();

// Fetch blood requests
$requests = [];
try {
    $requestsStmt = $conn->prepare("
        SELECT * FROM blood_requests 
        WHERE recipientId = ?
        ORDER BY requestTimestamp DESC
        LIMIT 10
    ");
    $requestsStmt->execute([$recipient['recipientId'] ?? '']);
    $requests = $requestsStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Recipient requests query failed: " . $e->getMessage());
}

// Fetch available inventory
$inventory = [];
try {
    $inventoryStmt = $conn->query("SELECT * FROM inventory WHERE unitsAvailable > 0");
    $inventory = $inventoryStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Inventory query failed: " . $e->getMessage());
}

// Log access
if ($recipient) {
    $audit->log($userId, 'RECIPIENT_DASHBOARD_ACCESS', $recipient['recipientId']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Dashboard - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
        <div class="main-content">
    <div class="dashboard-container">
        <div class="container">
            <div class="card">
                <h2>My Hospital Information</h2>
                <?php if ($recipient): ?>
                    <p><strong>Hospital:</strong> <?php echo htmlspecialchars($recipient['hospitalName']); ?></p>
                    <p><strong>Recipient ID:</strong> <?php echo htmlspecialchars($recipient['recipientId']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($recipient['status']); ?></p>
                <?php else: ?>
                    <p>⚠️ Recipient profile not found. You must set up your profile before you can request blood.</p>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if (!$recipient): ?>
                        <a href="register.php" class="btn btn-primary">⚙️ Setup Profile</a>
                    <?php else: ?>
                        <a href="request.php" class="btn btn-primary">🩸 Request Blood</a>
                        <a href="track_request.php" class="btn btn-success">📊 Track Requests</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <h2>My Blood Requests</h2>
                <?php if (empty($requests)): ?>
                    <p>No blood requests submitted yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Blood Type</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Requested On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['requestId']); ?></td>
                                <td><?php echo htmlspecialchars($req['bloodGroup']); ?></td>
                                <td><?php echo $req['quantity']; ?> units</td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($req['status']); ?>">
                                        <?php echo $req['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($req['requestTimestamp'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Available Blood Inventory</h2>
                <?php if (empty($inventory)): ?>
                    <p>No blood units currently available.</p>
                <?php else: ?>
                    <div class="inventory-grid">
                        <?php foreach ($inventory as $item): ?>
                        <div class="inventory-item">
                            <div class="blood-type"><?php echo htmlspecialchars($item['bloodType']); ?></div>
                            <div class="units"><?php echo $item['unitsAvailable']; ?> units available</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</body>
</html>