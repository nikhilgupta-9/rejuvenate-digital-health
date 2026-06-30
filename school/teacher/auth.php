<?php
// Teacher Auth Guard
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['teacher_logged_in']) || $_SESSION['teacher_logged_in'] !== true) {
    header("Location: " . (defined('BASE_URL') ? BASE_URL : '../..') . "/teacher-login.php");
    exit();
}

$teacher_id        = $_SESSION['teacher_id']        ?? null;
$teacher_name      = $_SESSION['teacher_name']       ?? '';
$teacher_email     = $_SESSION['teacher_email']      ?? '';
$teacher_school_id = $_SESSION['teacher_school_id']  ?? null;
$teacher_school    = $_SESSION['teacher_school']     ?? '';
$teacher_uid       = $_SESSION['teacher_uid']        ?? '';
