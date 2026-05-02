<?php
require_once 'config/constants.php';
require_once 'config/https.php';
require_once 'config/database.php';
require_once 'core/SecurityService.php';

$role = $_GET['role'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $selectedRole = $_POST['role'] ?? '';

    if (!$username || !$password || !$confirm || !$selectedRole) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        // ensure username unique
        $stmt = $conn->prepare("SELECT userId FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already exists';
        } else {
            $sec = new SecurityService();
            $hash = $sec->hashPassword($password);
            $userId = uniqid('USR-', true);
            $insert = $conn->prepare("INSERT INTO users (userId, username, passwordHash, role) VALUES (?, ?, ?, ?)");
            $insert->execute([$userId, $username, $hash, $selectedRole]);
            $message = 'Account created successfully. Redirecting to login...';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - KNBTS Secure Blood Banking System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>🩸 KNBTS</h1>
            <nav>
                <a href="index.php">← Back to Home</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="content">
            <div class="register-box">
                <h2>📝 Create Account</h2>
                
                <?php if ($message): ?>
                    <div class="message success">✅ <?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="message error">❌ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter a strong password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm">Confirm Password</label>
                        <input type="password" id="confirm" name="confirm" placeholder="Confirm your password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Select Your Role</label>
                        <select id="role" name="role" required>
                            <option value="">-- Select Role --</option>
                            <option value="DONOR" <?php if($role=='DONOR') echo 'selected'; ?>>🩸 Blood Donor</option>
                            <option value="RECIPIENT" <?php if($role=='RECIPIENT') echo 'selected'; ?>>🏥 Recipient / Hospital</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="register-button">🔐 Create Account</button>
                </form>
                
                <p>Already have an account? <a href="index.php<?php echo $role ? '?role=' . urlencode($role) : ''; ?>">Login here</a></p>
                
                <div class="back-link">
                    <a href="index.php">← Back to role selection</a>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var successMsg = document.querySelector('.message.success');
        if (successMsg) {
            setTimeout(function() {
                window.location.href = 'index.php<?php echo $role ? '?role=' . urlencode($role) : ''; ?>';
            }, 2000);
        }
    });
    </script>
</body>
</html>

