<?php
// Enable error reporting for debugging (Remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';

// Check if user is logged in AND is a Donor
if (!isset($_SESSION['userId']) || $_SESSION['role'] != 'DONOR') { 
    // Redirect to login if not authorized
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
        
        // 3. Generate unique donor ID (only for new records)
        $userId = $_SESSION['userId'];
        
        // 4. Insert or update (upsert) the donor record
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
            $message = "✅ Information updated successfully!";
        } else {
            $donorId = uniqid('DNR-', true);
            $stmt = $conn->prepare(
                "INSERT INTO donors (donorId, userId, encryptedName, encryptedPhone, bloodGroup, rhFactor, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE')"
            );
            $stmt->execute([$donorId, $userId, $encryptedName, $encryptedPhone, $bloodGroup, $rhFactor]);
            $audit->log($userId, 'DONOR_REGISTERED', $donorId);
            $message = "✅ Data encrypted & stored successfully!";
        }
        
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
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #28a745; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background: #218838; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .back-link { display: inline-block; margin-top: 20px; color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <h2>🩸 Donor Registration</h2>
    
    <!-- Display Messages -->
    <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Encryption Info Box (For Research Demonstration) -->
    <div class="message info">
        <strong>🔐 Security Notice:</strong> Your personal data (Name, Phone) will be encrypted using AES-256 before storage.
    </div>
    
    <!-- Registration Form -->
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
            <label for="bloodGroup">Blood Group *</label>
            <select id="bloodGroup" name="bloodGroup" required>
                <option value="">-- Select --</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="AB">AB</option>
                <option value="O">O</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="rhFactor">Rh Factor</label>
            <select id="rhFactor" name="rhFactor">
                <option value="+">Positive (+)</option>
                <option value="-">Negative (-)</option>
            </select>
        </div>
        
        <button type="submit">Submit Encrypted Registration</button>
    </form>
    
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    <br>
    <a href="../../logout.php" class="back-link" style="color: #dc3545;">Logout</a>
</body>
</html>