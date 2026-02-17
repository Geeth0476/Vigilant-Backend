<?php
// config/config.php

// Use Environment Variables for Production Readiness
// Fallback to local defaults if env vars are missing

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'vigilant_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''); // Default XAMPP empty
define('DB_CHARSET', 'utf8mb4');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', "$protocol://$host/vigilant_backend/public/");
define('API_VERSION', 'v1');

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_jwt_secret_key_change_in_production');

// Error reporting
$debug = getenv('APP_DEBUG') === 'true';
// Also valid if we are on localhost explicitly? Maybe.
// Check if explicitly local
$isLocal = ($host === 'localhost' || $host === '127.0.0.1');

if ($debug || $isLocal) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
?>
