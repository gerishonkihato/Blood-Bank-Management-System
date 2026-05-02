<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'DONOR') { 
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

// Fetch donor profile
$donorStmt = $conn->prepare("SELECT * FROM donors WHERE userId = ?");
$donorStmt->execute([$userId]);
$donor = $donorStmt->fetch();

// Fetch donation history (if table exists)
$donationHistory = [];
try {
    $historyStmt = $conn->prepare("
        SELECT bu.* FROM blood_units bu
        WHERE bu.donorId = ?
        ORDER BY bu.collectionDate DESC
        LIMIT 5
    ");
    $historyStmt->execute([$donor['donorId'] ?? '']);
    $donationHistory = $historyStmt->fetchAll();
} catch (PDOException $e) {
    // table might not exist yet; ignore and log for debugging
    error_log("Donation history query failed: " . $e->getMessage());
}

// Log access
if ($donor) {
    $audit->log($userId, 'DONOR_DASHBOARD_ACCESS', $donor['donorId']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
    <div class="dashboard-container">
        <div class="container">
            <div class="profile-card">
                <h2>Your Donor Profile</h2>
                <?php if ($donor): ?>
                    <div class="blood-type-badge">
                        <?php echo htmlspecialchars($donor['bloodGroup'] . $donor['rhFactor']); ?>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Donor ID</label>
                            <value><?php echo htmlspecialchars($donor['donorId']); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Status</label>
                            <value><?php echo htmlspecialchars($donor['status']); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Last Donation</label>
                            <value><?php echo $donor['lastDonationDate'] ? date('M d, Y', strtotime($donor['lastDonationDate'])) : 'Never'; ?></value>
                        </div>
                    
                    <div class="action-buttons">
                        <a href="register.php" class="btn btn-primary">📝 Update Information</a>
                        <a href="donation_history.php" class="btn btn-success">📋 View Full History</a>
                    </div>
                <?php else: ?>
                    <p>Your donor profile is not complete.</p>
                    <a href="register.php" class="btn btn-primary">Complete Registration</a>
                <?php endif; ?>
            </div>
            
            <div class="profile-card">
                <h2>Recent Donation History</h2>
                <?php if (empty($donationHistory)): ?>
                    <p>No donation records yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Unit ID</th>
                                <th>Collection Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donationHistory as $unit): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($unit['unitId']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($unit['collectionDate'])); ?></td>
                                <td><?php echo htmlspecialchars($unit['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
    </div>
</body>
</html>
