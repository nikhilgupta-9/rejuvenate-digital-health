<?php
// Student Auth Guard
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '../..') . "/student-login.php");
    exit();
}

$student_id        = $_SESSION['student_id']        ?? null;
$student_name      = $_SESSION['student_name']       ?? '';
$student_email     = $_SESSION['student_email']      ?? '';
$student_school_id = $_SESSION['student_school_id']  ?? null;
$student_school    = $_SESSION['student_school']     ?? '';
$student_uid       = $_SESSION['student_uid']        ?? '';
$student_class     = $_SESSION['student_class']      ?? '';
