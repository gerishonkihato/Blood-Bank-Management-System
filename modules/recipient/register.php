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
$message = "";
$error = "";

// Pre-fill defaults
$fullNameValue = '';
$phoneValue = '';
$hospitalNameValue = '';

// Fetch existing recipient data for pre-filling
$userId = $_SESSION['userId'];
try {
    $db = new Database();
    $conn = $db->getConnection();
    $checkStmt = $conn->prepare("SELECT encryptedName, encryptedPhone, hospitalName FROM recipients WHERE userId = ?");
    $checkStmt->execute([$userId]);
    $existingRecipient = $checkStmt->fetch();
    if ($existingRecipient) {
        $fullNameValue = $sec->decrypt($existingRecipient['encryptedName']);
        $phoneValue = $sec->decrypt($existingRecipient['encryptedPhone']);
        $hospitalNameValue = $existingRecipient['hospitalName'];
    }
} catch (Exception $e) {
    // Silently fail pre-fill; user can still submit
    error_log("Recipient pre-fill error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get and sanitize input
        $fullName = trim($_POST['fullName'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $hospitalName = trim($_POST['hospitalName'] ?? '');
        
        // Basic validation
        if (empty($fullName) || empty($phone) || empty($hospitalName)) {
            throw new Exception("All fields are required");
        }
        
        // Encrypt sensitive data
        $encryptedName = $sec->encrypt($fullName);
        $encryptedPhone = $sec->encrypt($phone);
        
        // Check if recipient already exists (upsert pattern)
        $checkStmt = $conn->prepare("SELECT recipientId FROM recipients WHERE userId = ?");
        $checkStmt->execute([$userId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Update existing recipient
            $recipientId = $existing['recipientId'];
            $update = $conn->prepare(
                "UPDATE recipients SET encryptedName = ?, encryptedPhone = ?, hospitalName = ? WHERE userId = ?"
            );
            $update->execute([$encryptedName, $encryptedPhone, $hospitalName, $userId]);
            $audit->log($userId, 'RECIPIENT_UPDATED', $recipientId);
        } else {
            // Create new recipient
            $recipientId = uniqid('RCP-', true);
            $stmt = $conn->prepare(
                "INSERT INTO recipients (recipientId, userId, encryptedName, encryptedPhone, hospitalName) 
                VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$recipientId, $userId, $encryptedName, $encryptedPhone, $hospitalName]);
            $audit->log($userId, 'RECIPIENT_REGISTERED', $recipientId);
        }
        
        $message = "✅ Recipient profile saved successfully.";
        
    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
        error_log("Recipient Registration Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipient Registration - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <div class="register-box">
                <h2>🏥 Recipient Profile Registration</h2>
                
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
                        <label for="hospitalName">Hospital Name *</label>
                        <input type="text" id="hospitalName" name="hospitalName" required placeholder="Enter your hospital name" value="<?php echo htmlspecialchars($hospitalNameValue); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Create/Update Profile</button>
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

