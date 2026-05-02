<?php
// Donor donation history page
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

// fetch donor record
$donorStmt = $conn->prepare("SELECT * FROM donors WHERE userId = ?");
$donorStmt->execute([$userId]);
$donor = $donorStmt->fetch();

$history = [];
$donationStats = ['total' => 0, 'totalUnits' => 0];
if ($donor && !empty($donor['donorId'])) {
    $historyStmt = $conn->prepare("SELECT * FROM blood_units WHERE donorId = ? ORDER BY collectionDate DESC");
    $historyStmt->execute([$donor['donorId']]);
    $history = $historyStmt->fetchAll();
    
    // Calculate stats
    $donationStats['total'] = count($history);
    $availableUnits = array_filter($history, function($unit) { return $unit['status'] === 'AVAILABLE'; });
    $donationStats['totalUnits'] = count($availableUnits);
    
    $audit->log($userId, 'VIEW_DONATION_HISTORY', $donor['donorId']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation History - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 30px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #27ae60;
            text-align: center;
        }
        .stat-card h3 { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 10px; }
        .stat-card .value { font-size: 2.5rem; font-weight: bold; color: #27ae60; }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        tr:hover { background: #f8f9fa; }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-available { background: #d4edda; color: #155724; }
        .status-used { background: #d1ecf1; color: #0c5460; }
        .status-expired { background: #f8d7da; color: #721c24; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .empty-state p { font-size: 1.1rem; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
    <div class="container">
        <?php if ($donor): ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Donations</h3>
                    <div class="value"><?php echo $donationStats['total']; ?></div>
                <div class="stat-card">
                    <h3>Units in Use</h3>
                    <div class="value"><?php echo $donationStats['totalUnits']; ?></div>
                <div class="stat-card">
                    <h3>Blood Type</h3>
                    <div class="value" style="font-size: 2rem;"><?php echo htmlspecialchars($donor['bloodGroup'] . $donor['rhFactor']); ?></div>
            </div>
            
            <!-- Donation History Table -->
            <div class="card">
                <h2>🩸 Complete Donation Record</h2>
                <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <p>No donation records yet.</p>
                        <p style="font-size: 0.95rem; margin-top: 10px;">Your donations will appear here once registered.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Unit ID</th>
                                <th>Collection Date</th>
                                <th>Days Stored</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $unit): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($unit['unitId']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($unit['collectionDate'])); ?></td>
                                <td>
                                    <?php 
                                        $daysStored = round((time() - strtotime($unit['collectionDate'])) / 86400);
                                        echo $daysStored . ' days';
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($unit['status']); ?>">
                                        <?php echo htmlspecialchars($unit['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <div class="icon">⚠️</div>
                    <p>Your donor profile is not complete.</p>
                    <p style="margin-top: 15px;"><a href="register.php" style="color: #27ae60; text-decoration: none; font-weight: 600;">Complete your registration →</a></p>
                </div>
        <?php endif; ?>
    </div>
</body>
</html>
