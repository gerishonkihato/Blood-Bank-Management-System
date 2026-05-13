<?php
// Enable error reporting for debugging (Remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';

// Check if user is logged in AND is a Donor
if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'DONOR') { 
    header("Location: ../../index.php"); 
    exit; 
}

// Include required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../../core/AuditLog.php';

// Initialize services
$sec = new SecurityService();
$audit = new AuditLog();
$message = "";
$error = "";

// Pre-fill defaults
$fullNameValue = '';
$phoneValue = '';
$bloodGroupValue = '';
$rhFactorValue = '+';

// Fetch existing donor data for pre-filling
$userId = $_SESSION['userId'];
try {
    $db = new Database();
    $conn = $db->getConnection();
    $checkStmt = $conn->prepare("SELECT encryptedName, encryptedPhone, bloodGroup, rhFactor FROM donors WHERE userId = ?");
    $checkStmt->execute([$userId]);
    $existingDonor = $checkStmt->fetch();
    if ($existingDonor) {
        $fullNameValue = $sec->decrypt($existingDonor['encryptedName']);
        $phoneValue = $sec->decrypt($existingDonor['encryptedPhone']);
        $bloodGroupValue = $existingDonor['bloodGroup'];
        $rhFactorValue = $existingDonor['rhFactor'];
    }
} catch (Exception $e) {
    // Silently fail pre-fill; user can still submit
    error_log("Donor pre-fill error: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // 1. Get and sanitize input
        $fullName = trim($_POST['fullName'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bloodGroup = $_POST['bloodGroup'] ?? '';
        $rhFactor = $_POST['rhFactor'] ?? '+';
        
        // Basic validation
        if (empty($fullName) || empty($phone) || empty($bloodGroup)) {
            throw new Exception("All fields are required");
        }
        
        // 2. ENCRYPT Sensitive Data (AES-256) - Core Research Objective
        $encryptedName = $sec->encrypt($fullName);
        $encryptedPhone = $sec->encrypt($phone);
        
        // 3. Insert or update (upsert) the donor record
        $checkStmt = $conn->prepare("SELECT donorId FROM donors WHERE userId = ?");
        $checkStmt->execute([$userId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // update existing donor
            $donorId = $existing['donorId'];
            $update = $conn->prepare(
                "UPDATE donors SET encryptedName = ?, encryptedPhone = ?, bloodGroup = ?, rhFactor = ? WHERE userId = ?"
            );
            $update->execute([$encryptedName, $encryptedPhone, $bloodGroup, $rhFactor, $userId]);
            $audit->log($userId, 'DONOR_UPDATED', $donorId);
        } else {
            $donorId = uniqid('DNR-', true);
            $stmt = $conn->prepare(
                "INSERT INTO donors (donorId, userId, encryptedName, encryptedPhone, bloodGroup, rhFactor, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE')"
            );
            $stmt->execute([$donorId, $userId, $encryptedName, $encryptedPhone, $bloodGroup, $rhFactor]);
            $audit->log($userId, 'DONOR_REGISTERED', $donorId);
        }
        
        $message = "✅ Donor profile saved successfully.";
        
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
        // Log the error for admin review
        error_log("Donor Registration Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Registration - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <div class="register-box">
                <h2>🩸 Donor Registration</h2>
                
                <!-- Display Messages -->
                <?php if ($message): ?>
                    <div class="message success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="fullName">Full Name *</label>
                        <input type="text" id="fullName" name="fullName" required placeholder="Enter your full name" value="<?php echo htmlspecialchars($fullNameValue); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="e.g., 0712345678" pattern="[0-9]{10}" value="<?php echo htmlspecialchars($phoneValue); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="bloodGroup">Blood Group *</label>
                        <select id="bloodGroup" name="bloodGroup" required>
                            <option value="">-- Select --</option>
                            <option value="A" <?php if ($bloodGroupValue === 'A') echo 'selected'; ?>>A</option>
                            <option value="B" <?php if ($bloodGroupValue === 'B') echo 'selected'; ?>>B</option>
                            <option value="AB" <?php if ($bloodGroupValue === 'AB') echo 'selected'; ?>>AB</option>
                            <option value="O" <?php if ($bloodGroupValue === 'O') echo 'selected'; ?>>O</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="rhFactor">Rh Factor</label>
                        <select id="rhFactor" name="rhFactor">
                            <option value="+" <?php if ($rhFactorValue === '+') echo 'selected'; ?>>Positive (+)</option>
                            <option value="-" <?php if ($rhFactorValue === '-') echo 'selected'; ?>>Negative (-)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;">Create/Update Profile</button>
                </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var successMsg = document.querySelector('.message.success');
        if (successMsg) {
            setTimeout(function() {
                window.location.href = 'dashboard.php';
            }, 2000);
        }
    });
    </script>
</body>
</html>

