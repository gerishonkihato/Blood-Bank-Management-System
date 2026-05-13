<?php
// Use __DIR__ to reliably include files from the project root regardless
// of the current working directory when this script is invoked.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/SecurityService.php';

// Create Security Service
$sec = new SecurityService();

// Generate NEW hash for 'admin123'
$newPassword = 'admin123';
$hash = $sec->hashPassword($newPassword);

// Connect to DB
$db = new Database();
$conn = $db->getConnection();

// Check if admin exists
$stmt = $conn->prepare("SELECT userId FROM users WHERE username = ?");
$stmt->execute(['admin']);
$user = $stmt->fetch();

if ($user) {
    // Update existing admin
    $update = $conn->prepare("UPDATE users SET passwordHash = ? WHERE username = ?");
    $update->execute([$hash, 'admin']);
    echo "Admin password updated successfully! <br>Hash: " . $hash;
} else {
    // Create new admin
    $insert = $conn->prepare("INSERT INTO users (userId, username, passwordHash, role) VALUES (?, ?, ?, 'ADMIN')");
    $insert->execute([uniqid('admin'), 'admin', $hash]);
    echo "Admin user created successfully! <br>Hash: " . $hash;
}
?>