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
        
        $userId = $_SESSION['userId'];
        
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
            $message = "✅ Profile updated successfully!";
        } else {
            // Create new recipient
            $recipientId = uniqid('RCP-', true);
            $stmt = $conn->prepare(
                "INSERT INTO recipients (recipientId, userId, encryptedName, encryptedPhone, hospitalName) 
                VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$recipientId, $userId, $encryptedName, $encryptedPhone, $hospitalName]);
            $audit->log($userId, 'RECIPIENT_REGISTERED', $recipientId);
            $message = "✅ Profile created successfully!";
        }
        
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
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #9b59b6; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #8e44ad; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .back-link { display: inline-block; margin-top: 20px; color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <h2>🏥 Recipient Profile Registration</h2>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="message info">
        <strong>🔐 Security Notice:</strong> Your personal data (Name, Phone) will be encrypted using AES-256 before storage.
    </div>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="fullName">Full Name *</label>
            <input type="text" id="fullName" name="fullName" required placeholder="Enter your full name">
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone" required placeholder="e.g., 0712345678" pattern="[0-9]{10}">
        </div>
        
        <div class="form-group">
            <label for="hospitalName">Hospital Name *</label>
            <input type="text" id="hospitalName" name="hospitalName" required placeholder="Enter your hospital name">
        </div>
        
        <button type="submit">Create/Update Profile</button>
    </form>
    
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    <br>
    <a href="../../logout.php" class="back-link" style="color: #dc3545;">Logout</a>
</body>
</html>
