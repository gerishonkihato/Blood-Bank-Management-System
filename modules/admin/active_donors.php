<?php
// Active donors + donation management page for Admin
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

// Handle donation add form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donorId'])) {
    $donorId = $_POST['donorId'];
    $bloodGroup = $_POST['bloodGroup'];
    $units = intval($_POST['units']);
    $collectionDate = $_POST['collectionDate'] ?: date('Y-m-d');

    if (!$donorId || !$bloodGroup || $units <= 0) {
        $message = 'Please provide a valid donor, blood group, and unit quantity.';
        $messageType = 'error';
    } else {
        try {
            // Create a new blood unit record
            $unitId = 'UNIT-' . bin2hex(random_bytes(8));
            $stmt = $conn->prepare('INSERT INTO blood_units (unitId, donorId, collectionDate, status) VALUES (?, ?, ?, ? )');
            $stmt->execute([$unitId, $donorId, $collectionDate, 'AVAILABLE']);

            // Update donor last donation date
            $updateDonor = $conn->prepare('UPDATE donors SET lastDonationDate = ?, status = ? WHERE donorId = ?');
            $updateDonor->execute([$collectionDate, 'ACTIVE', $donorId]);

            // Add to inventory for 1 unit per blood_unit entry
            $invStmt = $conn->prepare('SELECT unitsAvailable FROM inventory WHERE bloodType = ?');
            $invStmt->execute([$bloodGroup]);
            $inventoryRow = $invStmt->fetch(PDO::FETCH_ASSOC);
            if ($inventoryRow) {
                $updateInv = $conn->prepare('UPDATE inventory SET unitsAvailable = unitsAvailable + ?, lastUpdated = NOW() WHERE bloodType = ?');
                $updateInv->execute([$units, $bloodGroup]);
            } else {
                $insertInv = $conn->prepare('INSERT INTO inventory (bloodType, unitsAvailable, lastUpdated) VALUES (?, ?, NOW())');
                $insertInv->execute([$bloodGroup, $units]);
            }

            // If you want to store one row per donated unit, optionally add multiple rows. For now insert one entry and treat units as quantity.
            if ($units > 1) {
                for ($i = 1; $i < $units; $i++) {
                    $extraUnitId = 'UNIT-' . bin2hex(random_bytes(8));
                    $stmt2 = $conn->prepare('INSERT INTO blood_units (unitId, donorId, collectionDate, status) VALUES (?, ?, ?, ?)');
                    $stmt2->execute([$extraUnitId, $donorId, $collectionDate, 'AVAILABLE']);
                }
            }

            $audit->log($_SESSION['userId'], 'ADMIN_CREATED_DONATION', $donorId);
            $audit->log($_SESSION['userId'], 'INVENTORY_INCREMENTED', $bloodGroup . ':' . $units);

            $message = 'Donation added and inventory updated successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            error_log('Failed to add donation: ' . $e->getMessage());
            $message = 'An error occurred while adding donation: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get active donors list with search functionality
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($searchTerm)) {
    $searchParam = '%' . $searchTerm . '%';
    $donorsStmt = $conn->prepare('SELECT d.donorId, d.encryptedName, d.bloodGroup, d.rhFactor, d.lastDonationDate, d.status, u.username
        FROM donors d
        JOIN users u ON d.userId = u.userId
        WHERE d.status = ? AND (u.username LIKE ? OR CONCAT(d.bloodGroup, d.rhFactor) LIKE ?)
        ORDER BY d.lastDonationDate DESC');
    $donorsStmt->execute(['ACTIVE', $searchParam, $searchParam]);
} else {
    $donorsStmt = $conn->prepare('SELECT d.donorId, d.encryptedName, d.bloodGroup, d.rhFactor, d.lastDonationDate, d.status, u.username
        FROM donors d
        JOIN users u ON d.userId = u.userId
        WHERE d.status = ?
        ORDER BY d.lastDonationDate DESC');
    $donorsStmt->execute(['ACTIVE']);
}
$activeDonors = $donorsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent donation rows for display
$donationStmt = $conn->query('SELECT bu.unitId, bu.donorId, bu.collectionDate, bu.status, d.bloodGroup, d.rhFactor, u.username
    FROM blood_units bu
    LEFT JOIN donors d ON bu.donorId = d.donorId
    LEFT JOIN users u ON d.userId = u.userId
    ORDER BY bu.collectionDate DESC
    LIMIT 20');
$recentDonations = $donationStmt->fetchAll(PDO::FETCH_ASSOC);

// Inventory snapshot
$inventoryStmt = $conn->query('SELECT * FROM inventory ORDER BY bloodType');
$inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

$audit->log($_SESSION['userId'], 'ADMIN_ACTIVE_DONORS_ACCESS', $_SESSION['userId']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Donors & Inventory - KNBTS Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px;">
                    <h2 style="margin: 0;">🩸 Active Donors</h2>
                    <form method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 500px;">
                        <input type="text" name="search" placeholder="Search by donor name or blood type (e.g., A+, AB-, John)..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">🔍 Search</button>
                        <?php if ($searchTerm): ?>
                            <a href="active_donors.php" class="btn" style="padding: 8px 16px; background: #95a5a6; color: white; text-decoration: none; border-radius: 4px;">✕ Clear</a>
                        <?php endif; ?>
                    </form>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_donors.php?format=csv&search=<?php echo urlencode($searchTerm); ?>" class="btn btn-success" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📥 CSV</a>
                        <a href="export_donors.php?format=pdf&search=<?php echo urlencode($searchTerm); ?>" class="btn btn-info" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📄 PDF</a>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Donor</th>
                            <th>Donor ID</th>
                            <th>Blood Group</th>
                            <th>Last Donation</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activeDonors)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 20px; color: #7f8c8d;">No donors found</td></tr>
                        <?php endif; ?>
                        <?php foreach ($activeDonors as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['username']); ?></td>
                            <td><?php echo htmlspecialchars($d['donorId']); ?></td>
                            <td><?php echo htmlspecialchars($d['bloodGroup'] . $d['rhFactor']); ?></td>
                            <td><?php echo $d['lastDonationDate'] ? date('M d, Y', strtotime($d['lastDonationDate'])) : 'Never'; ?></td>
                            <td><span class="status-badge status-available"><?php echo htmlspecialchars($d['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-card">
                <h2>🩸 Add Donation for Active Donor</h2>
                <form method="POST" id="donationForm">
                    <div class="inventory-form-grid">
                        <div class="form-group autocomplete-wrapper">
                            <label for="donorSearch">Select Donor</label>
                            <input type="text" id="donorSearch" class="autocomplete-input" placeholder="Type donor name or blood type..." autocomplete="off" required>
                            <input type="hidden" id="donorId" name="donorId">
                            <div id="donorSuggestions" class="autocomplete-dropdown"></div>
                            <script>
                                window.activeDonors = <?php echo json_encode(array_map(function($d) {
                                    return [
                                        'donorId' => $d['donorId'],
                                        'username' => $d['username'],
                                        'bloodGroup' => $d['bloodGroup'],
                                        'rhFactor' => $d['rhFactor']
                                    ];
                                }, $activeDonors)); ?>;
                            </script>
                        </div>
                        <div class="form-group">
                            <label for="bloodGroup">Blood Type</label>
                            <input type="text" id="bloodGroup" name="bloodGroup" readonly required placeholder="Auto-generated on donor selection">
                        </div>
                        <div class="form-group">
                            <label for="collectionDate">Collection Date</label>
                            <input type="date" id="collectionDate" name="collectionDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="units">Units</label>
                            <input type="number" id="units" name="units" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn btn-success">Add Donation + Update Inventory</button>
                    </div>
                </form>
            </div>

            <div class="content-grid">
                <div class="card">
                    <h2>Inventory Snapshot</h2>
                    <table>
                        <thead><tr><th>Blood Type</th><th>Units Available</th><th>Last Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($inventory as $inv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['bloodType']); ?></td>
                            <td><?php echo htmlspecialchars($inv['unitsAvailable']); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($inv['lastUpdated'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <h2> Recent Donation Records</h2>
                    <table>
                        <thead><tr><th>Unit ID</th><th>Donor</th><th>Blood</th><th>Collected</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentDonations as $rec): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rec['unitId']); ?></td>
                            <td><?php echo htmlspecialchars($rec['username'] ?: $rec['donorId']); ?></td>
                            <td><?php echo htmlspecialchars($rec['bloodGroup'] . $rec['rhFactor']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($rec['collectionDate'])); ?></td>
                            <td><span class="status-badge status-<?php echo strtolower($rec['status']); ?>"><?php echo htmlspecialchars($rec['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="../../assets/js/app.js"></script>
</body>
</html>

