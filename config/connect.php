<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');
require_once dirname(__DIR__) . '/vendor/autoload.php'; // go up from config/ to project root

use Dotenv\Dotenv;

// Load .env from project root
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

/* ─────────────────────────────────────────────────────────────
   Environment / error handling
   Production: errors go to the log, never to the browser.
   Dev: APP_ENV=development|local in .env (or a localhost SITE) turns
        on-screen errors back on.
   ───────────────────────────────────────────────────────────── */
if (!defined('APP_ENV')) {
    $appEnv = strtolower(trim((string)($_ENV['APP_ENV'] ?? '')));
    if ($appEnv === '') {
        $site   = (string)($_ENV['SITE'] ?? '');
        $appEnv = (stripos($site, 'localhost') !== false || stripos($site, '127.0.0.1') !== false)
            ? 'development' : 'production';
    }
    define('APP_ENV', $appEnv);
    define('APP_DEBUG', $appEnv !== 'production');
}

ini_set('log_errors', '1');
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Read env variables
$host = $_ENV['DB_HOST'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$dbName = $_ENV['DB_NAME'];
if (!defined('BASE_URL')) {
    define('BASE_URL', $_ENV['SITE']);
}
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
}

// Make `$site` global
global $site;

// Create Database Connection
try {
    $conn = new mysqli($host, $username, $password, $dbName);
} catch (\Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die(APP_DEBUG ? ('Database Connection Failed: ' . $e->getMessage()) : 'Service temporarily unavailable. Please try again shortly.');
}

// Check Connection (defensive — mysqli may return an object without throwing)
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die(APP_DEBUG ? ('Database Connection Failed: ' . $conn->connect_error) : 'Service temporarily unavailable. Please try again shortly.');
}

// Every table + column in the DB is utf8mb4; the connection must match or
// 4-byte characters (emoji, some Indic text) are truncated/mangled in transit.
$conn->set_charset("utf8mb4");
