<?php
/**
 * Configuration Settings for Visitor Management System
 */

// Database Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'vms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email Settings (PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_USERNAME', 'omg191883@gmail.com');
define('EMAIL_PASSWORD', 'ysrxidzntkaefilm');
define('EMAIL_FROM', 'omg191883@gmail.com');
define('EMAIL_FROM_NAME', 'Visitor Management System');

// QR Code Settings
define('QR_CODE_EXPIRY_HOURS', 24);
define('QR_CODE_BASE_URL', 'http://localhost/vms/');

// Security Settings
define('HASH_ALGORITHM', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 3600); // 1 hour

// Application Settings
define('APP_NAME', 'Visitor Management System');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Kolkata');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
?>
