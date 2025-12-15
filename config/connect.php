<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Database Configuration
$local = true; // Set to false for live server

if ($local) {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $dbName = 'global_doctor';
    $site = "http://localhost/global-doctor/";
} else {
    $host = 'localhost';
    $username = 'u799879276_1soft_tech_db';
    $password = 'y;E4>l#O]M';
    $dbName = 'u799879276_1soft_tech_db';
    $site = 'https://softechtechno.com/';
}

// Make `$site` global
global $site;

// Create Database Connection
$conn = new mysqli($host, $username, $password, $dbName);

// Check Connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Optional: Set Character Encoding to UTF-8
$conn->set_charset("utf8");

?>
