<?php
// School Auth Guard - include this at top of every school dashboard page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['school_logged_in']) || $_SESSION['school_logged_in'] !== true) {
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '../..') . "/school-login.php");
    exit();
}

// Make session vars available as short vars
$school_id        = $_SESSION['school_id']         ?? null;
$school_name      = $_SESSION['school_name']        ?? '';
$school_user_id   = $_SESSION['school_user_id']    ?? null;
$school_user_name = $_SESSION['school_user_name']  ?? '';
$school_user_role = $_SESSION['school_user_role']  ?? 'school_admin';
