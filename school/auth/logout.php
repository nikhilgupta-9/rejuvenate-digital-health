<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Clear school session variables
$keys = ['school_logged_in','school_user_id','school_id','school_name','school_user_name','school_user_email','school_user_role'];
foreach ($keys as $key) unset($_SESSION[$key]);

session_destroy();
header("Location: ../../school-login.php");
exit();
