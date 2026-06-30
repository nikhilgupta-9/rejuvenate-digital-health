<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$keys = ['student_logged_in','student_id','student_name','student_email','student_school_id','student_school','student_uid','student_class'];
foreach ($keys as $k) unset($_SESSION[$k]);
session_destroy();
header("Location: ../../student-login.php"); exit();
