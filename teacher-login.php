<?php
/* Teacher Login — redirects to unified login page */
if (session_status() === PHP_SESSION_NONE) session_start();
include_once "config/connect.php";

if (!empty($_SESSION['teacher_logged_in'])) {
    header("Location: " . BASE_URL . "school/teacher/dashboard.php"); exit();
}
header("Location: " . BASE_URL . "login.php"); exit();
