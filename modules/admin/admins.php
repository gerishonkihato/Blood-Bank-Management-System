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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/SecurityService.php';
require_once __DIR__ . '/../../core/AuditLog.php';

$db = new Database();
$conn = $db->getConnection();
$sec = new SecurityService();
$audit = new AuditLog();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        if (!$username || !$password || !$confirm) {
            $error = 'All fields are required.';
        } elseif (!preg_match('/^(?=.*[A-Za-z])[A-Za-z0-9]+$/', $username)) {
            $error = 'Invalid username. Use letters or letters+numbers; cannot be numeric-only.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $check = $conn->prepare("SELECT userId FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error = 'Username already exists.';
                } else {
                    $hash = $sec->hashPassword($password);
                    $newUserId = uniqid('admin-', true);
                    $insert = $conn->prepare("INSERT INTO users (userId, username, passwordHash, role) VALUES (?, ?, ?, 'ADMIN')");
                    $insert->execute([$newUserId, $username, $hash]);
                    $message = 'New admin account created successfully.';
                    $audit->log($_SESSION['userId'], 'ADMIN_CREATED_ADMIN', $newUserId);
                }
            } catch (PDOException $e) {
                error_log('Admin creation failed: ' . $e->getMessage());
                $error = 'Unable to create admin account. Please try again.';
            }
        }
    } elseif ($action === 'delete_admin') {
        $targetId = $_POST['targetId'] ?? '';
        if ($_SESSION['userId'] !== 'admin-001') {
            $error = 'Only the main admin can delete admin accounts.';
        } elseif (!$targetId) {
            $error = 'Invalid admin selected for deletion.';
        } elseif ($targetId === $_SESSION['userId']) {
            $error = 'You cannot delete your own account while logged in.';
        } else {
            try {
                $delete = $conn->prepare("DELETE FROM users WHERE userId = ? AND role = 'ADMIN'");
                $delete->execute([$targetId]);
                if ($delete->rowCount()) {
                    $message = 'Admin account deleted successfully.';
                    $audit->log($_SESSION['userId'], 'ADMIN_DELETED_ADMIN', $targetId);
                } else {
                    $error = 'Admin account not found or cannot be deleted.';
                }
            } catch (PDOException $e) {
                error_log('Admin deletion failed: ' . $e->getMessage());
                $error = 'Unable to delete admin account. Please try again.';
            }
        }
    } elseif ($action === 'reset_admin_password') {
        $targetId = $_POST['targetId'] ?? '';
        if ($_SESSION['userId'] !== 'admin-001') {
            $error = 'Only the main admin can reset admin passwords.';
        } elseif (!$targetId) {
            $error = 'Invalid admin selected for password reset.';
        } else {
            try {
                $newPassword = bin2hex(random_bytes(4));
                $hash = $sec->hashPassword($newPassword);
                $update = $conn->prepare("UPDATE users SET passwordHash = ? WHERE userId = ? AND role = 'ADMIN'");
                $update->execute([$hash, $targetId]);
                if ($update->rowCount()) {
                    $message = 'Password reset successfully. New password: ' . htmlspecialchars($newPassword);
                    $audit->log($_SESSION['userId'], 'ADMIN_RESET_ADMIN_PASSWORD', $targetId);
                } else {
                    $error = 'Admin account not found or password could not be reset.';
                }
            } catch (Exception $e) {
                error_log('Admin password reset failed: ' . $e->getMessage());
                $error = 'Unable to reset password. Please try again.';
            }
        }
    }

}

// Fetch all admin users
try {
    $adminStmt = $conn->query("SELECT userId, username, created_at FROM users WHERE role = 'ADMIN' ORDER BY created_at DESC");
    $adminUsers = $adminStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Fetch admin users failed: ' . $e->getMessage());
    $adminUsers = [];
}

$audit->log($_SESSION['userId'], 'ADMIN_MANAGE_ADMINS_ACCESS', $_SESSION['userId']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - KNBTS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="dashboard-container">
            <div class="container">
                <div class="security-notice">
                    🔐 <strong>Admin User Management:</strong> Create additional administrator accounts and manage who can access the full admin dashboard.
                    <?php if ($_SESSION['userId'] !== 'admin-001'): ?>
                        <br><strong>Note:</strong> Only the main admin can delete accounts or reset passwords.
                    <?php endif; ?>
                </div>

                <div class="content-grid" style="grid-template-columns: 1fr; gap: 24px;">
                    <div class="card">
                        <h2>➕ Create New Admin</h2>
                        <?php if ($message): ?>
                            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST" style="max-width: 520px;">
                            <input type="hidden" name="action" value="create_admin">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" placeholder="Enter admin username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="Enter a secure password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm">Confirm Password</label>
                                <input type="password" id="confirm" name="confirm" placeholder="Confirm the password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Admin Account</button>
                        </form>
                    </div>

                    <div class="card">
                        <h2>👥 Existing Admin Accounts</h2>
                        <?php if (empty($adminUsers)): ?>
                            <p style="color: #7f8c8d;">No admin accounts found.</p>
                        <?php else: ?>
                            <table style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding: 10px; border-bottom: 1px solid #ddd;">Username</th>
                                        <th style="text-align:left; padding: 10px; border-bottom: 1px solid #ddd;">User ID</th>
                                        <th style="text-align:left; padding: 10px; border-bottom: 1px solid #ddd;">Created</th>
                                        <th style="text-align:left; padding: 10px; border-bottom: 1px solid #ddd;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adminUsers as $admin): ?>
                                        <tr>
                                            <td style="padding: 10px; border-bottom: 1px solid #f0f0f0;"><?php echo htmlspecialchars($admin['username']); ?></td>
                                            <td style="padding: 10px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #555;"><?php echo htmlspecialchars($admin['userId']); ?></td>
                                            <td style="padding: 10px; border-bottom: 1px solid #f0f0f0; color: #555;">
                                                <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($admin['created_at']))); ?>
                                            </td>
                                            <td style="padding: 10px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                                <?php if ($_SESSION['userId'] === 'admin-001'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Reset password for <?php echo htmlspecialchars($admin['username']); ?>?');">
                                                        <input type="hidden" name="action" value="reset_admin_password">
                                                        <input type="hidden" name="targetId" value="<?php echo htmlspecialchars($admin['userId']); ?>">
                                                        <button type="submit" class="btn btn-info" style="padding: 8px 14px; font-size: 12px;">Reset Password</button>
                                                    </form>
                                                    <?php if ($admin['userId'] !== $_SESSION['userId']): ?>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete admin <?php echo htmlspecialchars($admin['username']); ?>? This cannot be undone.');">
                                                            <input type="hidden" name="action" value="delete_admin">
                                                            <input type="hidden" name="targetId" value="<?php echo htmlspecialchars($admin['userId']); ?>">
                                                            <button type="submit" class="btn btn-reject" style="padding: 8px 14px; font-size: 12px;">Delete</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span style="font-size: 12px; color: #7f8c8d;">Current</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="font-size: 12px; color: #7f8c8d;">Only main admin can manage</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
