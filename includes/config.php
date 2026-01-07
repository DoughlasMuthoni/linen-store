<?php
// /linen-closet/includes/config.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'linen_closet');
define('DB_USER', 'root');
define('DB_PASS', 'mwalatvc');

// Site configuration
define('SITE_NAME', 'Linen Closet');
define('SITE_URL', 'http://localhost/linen-closet/');
define('SITE_PATH', __DIR__ . '/../');

// Security
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour
define('SESSION_TIMEOUT', 7200); // 2 hours

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Email Configuration
define('SITE_EMAIL', 'githuiddoughlas8@gmail.com');
// define('SITE_NAME', 'Linen Closet');
define('ADMIN_EMAIL', 'githuiddoughlas8@gmail.com');
define('SUPPORT_EMAIL', 'githuiddoughlas8@gmail.com');

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'githuiddoughlas8@gmail.com');  
define('SMTP_PASSWORD', 'lcwuukirsttwxbce');            
define('SMTP_SECURE', 'tls');

// Order Email Settings
define('SEND_ORDER_CONFIRMATION', true);
define('SEND_SHIPPING_CONFIRMATION', true);
define('SEND_STATUS_UPDATES', true);

?>