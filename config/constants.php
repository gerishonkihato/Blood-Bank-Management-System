<?php
// Encryption Key (32 bytes for AES-256)
// WARNING: In production, store this in a secure vault, not code.
define('ENCRYPTION_KEY', '0123456789abcdef0123456789abcdef'); 
define('CIPHER_METHOD', 'aes-256-cbc');

// Database Config
define('DB_HOST', 'localhost');
define('DB_NAME', 'knbts_db');
define('DB_USER', 'root');
define('DB_PASS', '');
// Force HTTPS settings
define('FORCE_HTTPS', true);
define('SITE_PROTOCOL', 'https');
// Compute a BASE_URL using the current host and script location. This will
// produce something like: https://localhost/knbts_secure_system/
if (!defined('BASE_URL')) {
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
	$basePath = rtrim(dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'), "/\\");
	define('BASE_URL', SITE_PROTOCOL . '://' . $host . ($basePath === '/' ? '' : $basePath) . '/');
}
?>