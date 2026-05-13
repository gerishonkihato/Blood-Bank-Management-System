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

// Log inventory access
$audit->log($_SESSION['userId'], 'INVENTORY_ACCESS', $_SESSION['userId']);

// Handle inventory updates
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'update_inventory') {
            $bloodType = $_POST['bloodType'];
            $quantity = intval($_POST['quantity']);
            $expiryDate = $_POST['expiryDate'] ?? null;
            $operation = $_POST['operation']; // 'add' or 'set'
            
            // Validate inputs
            if (empty($bloodType) || $quantity < 0) {
                throw new Exception("Invalid input data");
            }
            
            // Check if blood type exists in inventory
            $checkStmt = $conn->prepare("SELECT unitsAvailable FROM inventory WHERE bloodType = ?");
            $checkStmt->execute([$bloodType]);
            $existing = $checkStmt->fetch();
            
            if ($operation === 'add') {
                if ($existing) {
                    // Update existing entry
                    $updateStmt = $conn->prepare("UPDATE inventory SET unitsAvailable = unitsAvailable + ?, lastUpdated = NOW() WHERE bloodType = ?");
                    $updateStmt->execute([$quantity, $bloodType]);
                    $audit->log($_SESSION['userId'], 'INVENTORY_ADDED', $bloodType . ' - ' . $quantity . ' units');
                } else {
                    // Insert new entry
                    $insertStmt = $conn->prepare("INSERT INTO inventory (bloodType, unitsAvailable, lastUpdated) VALUES (?, ?, NOW())");
                    $insertStmt->execute([$bloodType, $quantity]);
                    $audit->log($_SESSION['userId'], 'INVENTORY_CREATED', $bloodType . ' - ' . $quantity . ' units');
                }
                $message = "Added $quantity units of $bloodType successfully!";
                $messageType = 'success';
            } elseif ($operation === 'set') {
                if ($existing) {
                    $oldQuantity = $existing['unitsAvailable'];
                    $updateStmt = $conn->prepare("UPDATE inventory SET unitsAvailable = ?, lastUpdated = NOW() WHERE bloodType = ?");
                    $updateStmt->execute([$quantity, $bloodType]);
                    $audit->log($_SESSION['userId'], 'INVENTORY_UPDATED', $bloodType . ' - Changed from ' . $oldQuantity . ' to ' . $quantity . ' units');
                    $message = "Updated $bloodType inventory to $quantity units successfully!";
                    $messageType = 'success';
                } else {
                    throw new Exception("Blood type not found. Use 'Add' to create new entries.");
                }
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_unit') {
            $unitId = $_POST['unitId'];
            $deleteStmt = $conn->prepare("DELETE FROM blood_units WHERE unitId = ?");
            $deleteStmt->execute([$unitId]);
            $audit->log($_SESSION['userId'], 'BLOOD_UNIT_DELETED', $unitId);
            $message = "Blood unit deleted successfully!";
            $messageType = 'success';
        }
    } catch (Exception $e) {
        error_log("Inventory update failed: " . $e->getMessage());
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch current inventory
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) {
    $searchParam = '%' . $searchTerm . '%';
    $inventoryStmt = $conn->prepare("SELECT * FROM inventory WHERE bloodType LIKE ? ORDER BY bloodType");
    $inventoryStmt->execute([$searchParam]);
} else {
    $inventoryStmt = $conn->query("SELECT * FROM inventory ORDER BY bloodType");
}
$inventory = $inventoryStmt->fetchAll();

// Fetch blood units for tracking
$unitsStmt = $conn->query("
    SELECT bu.*, d.bloodGroup, u.username as donorName
    FROM blood_units bu
    LEFT JOIN donors d ON bu.donorId = d.donorId
    LEFT JOIN users u ON d.userId = u.userId
    ORDER BY bu.collectionDate DESC
    LIMIT 20
");
$bloodUnits = $unitsStmt->fetchAll();

// Fetch inventory change history
$historyStmt = $conn->query("
    SELECT a.*, u.username
    FROM audit_log a
    LEFT JOIN users u ON a.userId = u.userId
    WHERE a.action IN ('INVENTORY_ADDED', 'INVENTORY_UPDATED', 'INVENTORY_CREATED', 'BLOOD_UNIT_DELETED')
    ORDER BY a.timestamp DESC
    LIMIT 15
");
$history = $historyStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Update Inventory Form -->
            <div class="form-card">
                <h2>📦 Update Inventory</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_inventory">
                    <div class="inventory-form-grid">
                        <div class="form-group">
                            <label for="bloodType">Blood Type</label>
                            <select id="bloodType" name="bloodType" required>
                                <option value="">Select Blood Type</option>
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
                        <div class="form-group">
                            <label for="quantity">Quantity (Units)</label>
                            <input type="number" id="quantity" name="quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="expiryDate">Expiry Date (Optional)</label>
                            <input type="date" id="expiryDate" name="expiryDate">
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="submit" name="operation" value="add" class="btn btn-success">➕ Add Units</button>
                    </div>
                </form>
            </div>
            
            <!-- Current Inventory Status -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px;">
                    <h2 style="margin: 0;">📊 Current Blood Inventory</h2>
                    <form method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 400px;">
                        <select name="search" style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Blood Types</option>
                            <option value="A+" <?php echo $searchTerm === 'A+' ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo $searchTerm === 'A-' ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo $searchTerm === 'B+' ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo $searchTerm === 'B-' ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo $searchTerm === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo $searchTerm === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                            <option value="O+" <?php echo $searchTerm === 'O+' ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo $searchTerm === 'O-' ? 'selected' : ''; ?>>O-</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">🔍 Filter</button>
                    </form>
                    <div style="display: flex; gap: 10px;">
                        <a href="export_inventory.php?format=csv&search=<?php echo urlencode($searchTerm); ?>" class="btn btn-success" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📥 CSV</a>
                        <a href="export_inventory.php?format=pdf&search=<?php echo urlencode($searchTerm); ?>" class="btn btn-info" style="padding: 8px 14px; text-decoration: none; font-size: 12px;">📄 PDF</a>
                    </div>
                </div>
                <div class="inventory-grid">
                    <?php foreach ($inventory as $item): ?>
                    <div class="inventory-card <?php echo ($item['unitsAvailable'] < 10) ? 'low-stock' : ''; ?>">
                        <h3><?php echo htmlspecialchars($item['bloodType']); ?></h3>
                        <div class="units"><?php echo $item['unitsAvailable']; ?></div>
                        <div class="label">Units Available</div>
                        <?php if ($item['unitsAvailable'] < 10): ?>
                        <div class="low-stock-label">⚠️ Low Stock</div>
                        <?php endif; ?>
                        <div class="updated-time">
                            Updated: <?php echo date('M d, H:i', strtotime($item['lastUpdated'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Blood Units -->
                <div class="card">
                    <h2>🧬 Blood Units</h2>
                    <?php if (empty($bloodUnits)): ?>
                        <p style="text-align: center; padding: 20px;">No blood units recorded</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Unit ID</th>
                                    <th>Blood Group</th>
                                    <th>Donor</th>
                                    <th>Collection Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bloodUnits as $unit): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($unit['unitId']); ?></td>
                                    <td><?php echo htmlspecialchars($unit['bloodGroup']); ?></td>
                                    <td><?php echo htmlspecialchars($unit['donorName'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($unit['collectionDate'])); ?></td>
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
                
                <!-- Inventory Change History -->
                <div class="card">
                    <h2>📋 Change History</h2>
                    <?php if (empty($history)): ?>
                        <p style="text-align: center; padding: 20px;">No inventory changes yet</p>
                    <?php else: ?>
                        <?php foreach ($history as $entry): ?>
                        <div class="audit-item">
                            <div class="audit-action"><?php echo htmlspecialchars($entry['action']); ?></div>
                            <div class="audit-time">
                                <?php echo htmlspecialchars($entry['username'] ?? 'SYSTEM'); ?> | 
                                <?php echo date('M d, H:i', strtotime($entry['timestamp'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

