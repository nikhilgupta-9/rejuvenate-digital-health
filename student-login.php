<?php
/* Student Login — redirects to unified login page */
if (session_status() === PHP_SESSION_NONE) session_start();
include_once "config/connect.php";

if (!empty($_SESSION['student_logged_in'])) {
    header("Location: " . BASE_URL . "school/student/dashboard.php"); exit();
}
header("Location: " . BASE_URL . "login.php"); exit();
