<?php
// -- Environment: 'development' or 'production' --
// In development, errors are shown. In production, they are hidden/logged.
define('ENVIRONMENT', 'development');

// -- Database Configuration --
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');

// -- SMTP Configuration for PHPMailer --
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_USER', 'your_email@example.com');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_PORT', 587);

// -- Application Settings --
define('APP_NAME', 'Interactive Browser');
define('APP_FROM_EMAIL', 'no-reply@yourdomain.com');
define('APP_BASE_URL', 'http://your_vps_ip:8080'); // Base URL for verification links

// -- Bandwidth Limits --
define('FREE_USER_BANDWIDTH_LIMIT_BYTES', 50 * 1024 * 1024); // 50 MB

// -- Security Settings --
define('PASSWORD_RESET_EXPIRY_MINUTES', 60); // Token is valid for 60 minutes

// --- Error Reporting ---
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    // For production, you would configure a logger here, e.g., to a file:
    // ini_set('log_errors', 1);
    // ini_set('error_log', '/path/to/your/php-errors.log');
}
?>
