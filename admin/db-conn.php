<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/vendor/autoload.php'; // go up from admin/ to project root

use Dotenv\Dotenv;

// Load .env from project root
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

/* ─────────────────────────────────────────────────────────────
   Environment / error handling — production keeps errors out of
   the browser (see config/connect.php for the full rationale).
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

// Create Database Connection
try {
    $conn = new mysqli($host, $username, $password, $dbName);
} catch (\Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die(APP_DEBUG ? ('Database Connection Failed: ' . $e->getMessage()) : 'Service temporarily unavailable. Please try again shortly.');
}

if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die(APP_DEBUG ? ('Database Connection Failed: ' . $conn->connect_error) : 'Service temporarily unavailable. Please try again shortly.');
}

// Match the DB — every table/column is utf8mb4 (see config/connect.php).
$conn->set_charset("utf8mb4");
