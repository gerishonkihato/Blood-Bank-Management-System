<?php
// Enforce HTTPS if configured via config/constants.php
if (defined('FORCE_HTTPS') && FORCE_HTTPS) {
    $isSecure = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $isSecure = true;
    }
    // Behind proxies/load balancers that set X-Forwarded-Proto
    if (!$isSecure && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $isSecure = true;
        }
    }

    if (!$isSecure) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $redirect = (defined('SITE_PROTOCOL') ? SITE_PROTOCOL : 'https') . '://' . $host . $requestUri;
        header('Location: ' . $redirect, true, 301);
        exit;
    }

    // If secure, send HSTS header to enforce HTTPS in browsers
    if ($isSecure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', true);
    }
}

// Configure secure session cookies and start session centrally
if (session_status() === PHP_SESSION_NONE) {
    // Prefer array form (PHP 7.3+). Fallback to legacy call if not supported.
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (defined('FORCE_HTTPS') && FORCE_HTTPS),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    if (PHP_VERSION_ID >= 70300 && function_exists('session_set_cookie_params')) {
        session_set_cookie_params($cookieParams);
    } else {
        ini_set('session.cookie_secure', $cookieParams['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        if (isset($cookieParams['samesite'])) {
            ini_set('session.cookie_samesite', $cookieParams['samesite']);
        }
        session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }
    session_start();
}

?>
