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

// Get active donors list
$donorsStmt = $conn->prepare('SELECT d.donorId, d.encryptedName, d.bloodGroup, d.rhFactor, d.lastDonationDate, d.status, u.username
    FROM donors d
    JOIN users u ON d.userId = u.userId
    WHERE d.status = ?
    ORDER BY d.lastDonationDate DESC');
$donorsStmt->execute(['ACTIVE']);
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .header {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            color: white;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-nav a { color: white; margin-right: 12px; text-decoration: none; font-weight: 600; }
        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h2 { margin-bottom: 15px; }
        .message { margin-bottom: 20px; padding: 15px; border-radius: 4px; }
        .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; }
        .status-available { color: #155724; background: #d4edda; padding: 3px 8px; border-radius: 12px; }
        .status-used { color: #0c5460; background: #d1ecf1; padding: 3px 8px; border-radius: 12px; }
        .status-collected { color: #856404; background: #fff3cd; padding: 3px 8px; border-radius: 12px; }
        .status-inactive { color: #721c24; background: #f8d7da; padding: 3px 8px; border-radius: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🩸 Active Donors / Inventory</h1>
            <div style="margin-top: 5px; font-size: 0.95rem;">Admin view: add donation records and update inventory at once.</div>
        </div>
        <div>
            <a class="logout-btn" href="../../logout.php">🚪 Logout</a>
        </div>
    </div>
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>📌 Active Donors</h2>
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
                    <?php foreach ($activeDonors as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['username']); ?></td>
                        <td><?php echo htmlspecialchars($d['donorId']); ?></td>
                        <td><?php echo htmlspecialchars($d['bloodGroup'] . $d['rhFactor']); ?></td>
                        <td><?php echo $d['lastDonationDate'] ? date('M d, Y', strtotime($d['lastDonationDate'])) : 'Never'; ?></td>
                        <td><span class="status-available"><?php echo htmlspecialchars($d['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>➕ Add Donation for Active Donor</h2>
            <form method="POST">
                <input type="hidden" name="donorId" value="<?php echo count($activeDonors) ? htmlspecialchars($activeDonors[0]['donorId']) : ''; ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;align-items:end;">
                    <div>
                        <label for="donorId">Select Donor</label><br>
                        <select id="donorId" name="donorId" style="width:100%;padding:8px;border:1px solid #ccc;" required>
                            <option value="">Choose donor</option>
                            <?php foreach ($activeDonors as $d): ?>
                                <option value="<?php echo htmlspecialchars($d['donorId']); ?>"><?php echo htmlspecialchars($d['username'] . ' (' . $d['bloodGroup'] . $d['rhFactor'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="bloodGroup">Blood Type</label><br>
                        <select id="bloodGroup" name="bloodGroup" style="width:100%;padding:8px;border:1px solid #ccc;" required>
                            <option value="">Select</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div>
                        <label for="collectionDate">Collection Date</label><br>
                        <input type="date" id="collectionDate" name="collectionDate" value="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:8px;border:1px solid #ccc;" required>
                    </div>
                    <div>
                        <label for="units">Units</label><br>
                        <input type="number" id="units" name="units" min="1" value="1" style="width:100%;padding:8px;border:1px solid #ccc;" required>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-success" style="width:100%; margin-top: 5px;">Add Donation + Update Inventory</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>📦 Inventory Snapshot</h2>
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
            <h2>🧬 Recent Donation Records</h2>
            <table>
                <thead><tr><th>Unit ID</th><th>Donor</th><th>Blood</th><th>Collected</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentDonations as $rec): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rec['unitId']); ?></td>
                    <td><?php echo htmlspecialchars($rec['username'] ?: $rec['donorId']); ?></td>
                    <td><?php echo htmlspecialchars($rec['bloodGroup'] . $rec['rhFactor']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($rec['collectionDate'])); ?></td>
                    <td><span class="status-<?php echo strtolower($rec['status']); ?>"><?php echo htmlspecialchars($rec['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>
