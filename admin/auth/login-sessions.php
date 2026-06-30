<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "db-conn.php";

// Session hijacking prevention
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Check IP address
    if (!isset($_SESSION['ip_address']) || $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        header("Location: auth/login.php");
        exit();
    }
    
    // Check User Agent
    if (!isset($_SESSION['user_agent']) || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header("Location: auth/login.php");
        exit();
    }
    
    // Session timeout (optional - 2 hours)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
        session_destroy();
        header("Location: auth/login.php?timeout=1");
        exit();
    }
} else {
    // ✅ CORRECT - Redirect to login if NOT logged in
    header("Location: auth/login.php");
    exit();
}

// Rest of your code...
?>