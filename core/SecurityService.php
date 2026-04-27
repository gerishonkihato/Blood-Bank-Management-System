<?php
// Path resolution using __DIR__ ensures the file is loaded correctly
// regardless of the current working directory when this class is included.
require_once __DIR__ . '/../config/constants.php';

class SecurityService {
    
    // Encrypt Data (AES-256)
    public function encrypt($data) {
        $ivLength = openssl_cipher_iv_length(CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($data, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    // Decrypt Data
    public function decrypt($encodedData) {
        $data = base64_decode($encodedData);
        $ivLength = openssl_cipher_iv_length(CIPHER_METHOD);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        return openssl_decrypt($encrypted, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    }

    // Hash Password
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // Verify Password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>