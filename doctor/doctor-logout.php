<?php
include_once(__DIR__ . "/../config/connect.php");
session_start();

// Destroy all session data
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Check if redirected from password change
$password_changed = isset($_GET['password_changed']) ? $_GET['password_changed'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <style>
        .logout-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logout-container">
            <?php if ($password_changed): ?>
                <div class="success-icon">
                    <i class="fa fa-check-circle"></i>
                </div>
                <h3 class="mb-3">Password Changed Successfully!</h3>
                <p class="text-muted mb-4">
                    Your password has been updated. For security reasons, you have been logged out.
                    Please login again with your new password.
                </p>
            <?php else: ?>
                <div class="success-icon" style="color: #007bff;">
                    <i class="fa fa-sign-out-alt"></i>
                </div>
                <h3 class="mb-3">Logged Out Successfully</h3>
                <p class="text-muted mb-4">
                    You have been successfully logged out from your account.
                </p>
            <?php endif; ?>
            
            <div class="d-grid gap-2">
                <a href="<?= $site ?>doctor-login.php" class="btn btn-primary">
                    <i class="fa fa-sign-in-alt"></i> Login Again
                </a>
                <a href="<?= $site ?>" class="btn btn-outline-secondary">
                    <i class="fa fa-home"></i> Back to Home
                </a>
            </div>
            
            <div class="mt-4">
                <small class="text-muted">
                    For security, always log out when you're done with your session.
                </small>
            </div>
        </div>
    </div>
    
    <script>
        // Auto redirect after 10 seconds
        setTimeout(function() {
            window.location.href = "<?= $site ?>doctor-login.php";
        }, 10000);
    </script>
</body>
</html>