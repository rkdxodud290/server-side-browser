<?php
require_once __DIR__ . '/../config.php';

// Set secure session cookie parameters
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1); // Prevent client-side script access
ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF attacks

if (ENVIRONMENT === 'production') {
    ini_set('session.cookie_secure', 1); // Only send cookies over HTTPS in production
}

session_start();

// Regenerates the session ID on login to prevent session fixation attacks
function regenerate_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
?>
