<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$keys = ['teacher_logged_in','teacher_id','teacher_name','teacher_email','teacher_school_id','teacher_school','teacher_uid'];
foreach ($keys as $k) unset($_SESSION[$k]);
session_destroy();
header("Location: ../../teacher-login.php"); exit();
