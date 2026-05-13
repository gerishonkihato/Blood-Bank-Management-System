<?php
// Simple blood request form for recipients
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/https.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../../core/AuditLog.php';

$sec = new SecurityService();
$audit = new AuditLog();
$db = new Database();
$conn = $db->getConnection();

$userId = $_SESSION['userId'];

// fetch recipient profile
$recipientStmt = $conn->prepare("SELECT * FROM recipients WHERE userId = ?");
$recipientStmt->execute([$userId]);
$recipient = $recipientStmt->fetch();

// Redirect to register if no profile exists
if (!$recipient) {
    header("Location: register.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $bloodGroup = $_POST['bloodGroup'] ?? '';
    $rhFactor = $_POST['rhFactor'] ?? '+';
    $quantity = intval($_POST['quantity'] ?? 0);

    if (!$bloodGroup || $quantity <= 0) {
        $error = 'Please enter valid blood type and quantity.';
    } else {
        $requestId = uniqid('REQ-', true);
        // Combine blood group and RH factor
        $fullBloodType = $bloodGroup . $rhFactor;
        $stmt = $conn->prepare("INSERT INTO blood_requests (requestId, recipientId, bloodGroup, quantity) VALUES (?, ?, ?, ?)");
        $stmt->execute([$requestId, $recipient['recipientId'], $fullBloodType, $quantity]);
        $audit->log($userId, 'BLOOD_REQUESTED', $requestId);
        $message = 'Request submitted successfully! ID: ' . htmlspecialchars($requestId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Blood - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">

</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <div class="form-card">
                <h2>Request Blood</h2>
                <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="bloodGroup">Blood Group</label>
                        <select id="bloodGroup" name="bloodGroup" required>
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
                    <div class="form-group">
                        <label for="quantity">Quantity (units)</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Request</button>
                </form>
                
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
