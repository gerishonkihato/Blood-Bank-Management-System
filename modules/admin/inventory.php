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
$inventoryStmt = $conn->query("SELECT * FROM inventory ORDER BY bloodType");
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 1.8rem; }
        .header-nav {
            display: flex;
            gap: 15px;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }
        .header-nav a:hover {
            background: rgba(255,255,255,0.4);
        }
        .user-info { text-align: right; }
        .user-info span { display: block; }
        .logout-btn {
            display: inline-block;
            margin-top: 5px;
            padding: 8px 20px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
        }
        .logout-btn:hover { background: #c0392b; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 30px; }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-success:hover {
            background: #229954;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .inventory-card {
            background: linear-gradient(135deg, #f5f7fa, #ecf0f1);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            text-align: center;
        }
        .inventory-card.low-stock {
            border-left-color: #e74c3c;
            background: linear-gradient(135deg, #fadbd8, #f5b7b1);
        }
        .inventory-card h3 {
            color: #2c3e50;
            font-size: 1.4rem;
            margin-bottom: 10px;
        }
        .inventory-card .units {
            font-size: 2rem;
            font-weight: bold;
            color: #27ae60;
        }
        .inventory-card.low-stock .units {
            color: #e74c3c;
        }
        .inventory-card .label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-available { background: #d4edda; color: #155724; }
        .status-used { background: #d1ecf1; color: #0c5460; }
        .status-expired { background: #f8d7da; color: #721c24; }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🩸 Inventory Management</h1>
            <small>Blood Bank Inventory Control</small>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="header-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="inventory.php">🩸 Inventory</a>
                <a href="active_donors.php">👥 Active Donors</a>
            </div>
            <div class="user-info">
                <span><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                <a href="../../logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Update Inventory Card -->
        <div class="card">
            <h2>📦 Update Inventory</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_inventory">
                <div class="form-grid">
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
                    <button type="submit" name="operation" value="set" class="btn btn-primary">🔄 Set Total</button>
                </div>
            </form>
        </div>
        
        <!-- Current Inventory Status -->
        <div class="card">
            <h2>📊 Current Blood Inventory</h2>
            <div class="inventory-grid">
                <?php foreach ($inventory as $item): ?>
                <div class="inventory-card <?php echo ($item['unitsAvailable'] < 10) ? 'low-stock' : ''; ?>">
                    <h3><?php echo htmlspecialchars($item['bloodType']); ?></h3>
                    <div class="units"><?php echo $item['unitsAvailable']; ?></div>
                    <div class="label">Units Available</div>
                    <?php if ($item['unitsAvailable'] < 10): ?>
                    <div class="label" style="color: #e74c3c; margin-top: 10px;">⚠️ Low Stock</div>
                    <?php endif; ?>
                    <div class="label" style="margin-top: 10px; font-size: 0.8rem;">
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
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">No blood units recorded</p>
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
                    <p style="color: #7f8c8d; text-align: center; padding: 20px;">No inventory changes yet</p>
                <?php else: ?>
                    <?php foreach ($history as $entry): ?>
                    <div style="padding: 10px; border-bottom: 1px solid #ecf0f1;">
                        <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($entry['action']); ?></div>
                        <div style="color: #7f8c8d; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($entry['username'] ?? 'SYSTEM'); ?>
                        </div>
                        <div style="color: #7f8c8d; font-size: 0.8rem; margin-top: 5px;">
                            <?php echo date('M d, H:i', strtotime($entry['timestamp'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

