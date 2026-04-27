<?php
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/https.php';
session_destroy();
header("Location: " . (defined('BASE_URL') ? BASE_URL : 'index.php'));
exit;
?>