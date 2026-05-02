<?php
require_once 'config/constants.php';
require_once 'config/https.php';
require_once 'config/database.php';
require_once 'core/SecurityService.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $postRole = $_POST['role'] ?? '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'Username and password are required';
        echo json_encode($response);
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();
    $sec = new SecurityService();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && $sec->verifyPassword($password, $user['passwordHash'])) {
        if ($postRole && $postRole !== $user['role']) {
            $response['message'] = 'Role mismatch';
        } else {
            $_SESSION['userId'] = $user['userId'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            $redirect = '';
            if ($user['role'] == 'ADMIN') $redirect = 'modules/admin/dashboard.php';
            elseif ($user['role'] == 'DONOR') $redirect = 'modules/donor/dashboard.php';
            else $redirect = 'modules/recipient/dashboard.php';

            $response['success'] = true;
            $response['redirect'] = $redirect;
        }
    } else {
        $response['message'] = 'Invalid credentials';
    }
}

echo json_encode($response);
?>

