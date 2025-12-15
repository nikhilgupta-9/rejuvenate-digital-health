<?php
session_start();
if (!isset($_SESSION['signup_success'])) {
    header("Location: signup.php");
    exit();
}

include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$logo = get_header_logo();

$email = $_SESSION['user_email'];
$mobile = $_SESSION['user_mobile'];
$user_id = $_SESSION['user_id'];

// Handle OTP resend
if (isset($_POST['resend_otp'])) {
    $new_otp = rand(100000, 999999);
    $new_otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Update OTP in database
    $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_otp, $new_otp_expiry, $user_id);
    
    if ($stmt->execute()) {
        // Resend OTP
        send_otp_email($email, $new_otp);
        // send_otp_sms($mobile, $new_otp); // Uncomment when SMS is setup
        
        $_SESSION['otp_code'] = $new_otp;
        $_SESSION['otp_expiry'] = $new_otp_expiry;
        $_SESSION['otp_sent_time'] = time();
        $_SESSION['success_message'] = "New OTP sent successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to resend OTP. Please try again.";
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend_otp'])) {
    $otp = $_POST['otp'] ?? '';
    
    if (empty($otp)) {
        $error = "Please enter OTP code";
    } else {
        // Verify OTP from session or database
        $current_otp = $_SESSION['otp_code'] ?? '';
        $otp_expiry = $_SESSION['otp_expiry'] ?? '';
        
        if ($otp == $current_otp && strtotime($otp_expiry) > time()) {
            // OTP verified successfully
            $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Account verified successfully! You can now login.";
                
                // Clear session data
                unset($_SESSION['signup_success']);
                unset($_SESSION['user_email']);
                unset($_SESSION['user_mobile']);
                unset($_SESSION['user_id']);
                unset($_SESSION['otp_code']);
                unset($_SESSION['otp_expiry']);
                unset($_SESSION['otp_sent_time']);
                
                header("Location: login.php");
                exit();
            } else {
                $error = "Verification failed. Please try again.";
            }
        } else {
            $error = "Invalid or expired OTP code.";
        }
    }
}

// Calculate time remaining for OTP
$otp_sent_time = $_SESSION['otp_sent_time'] ?? time();
$time_elapsed = time() - $otp_sent_time;
$time_remaining = 600 - $time_elapsed; // 10 minutes in seconds
$can_resend = $time_elapsed > 60; // Can resend after 60 seconds
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP | REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/odometer.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/nice-select.css">
  <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .countdown { color: #dc3545; font-weight: bold; }
        .resend-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>
</head>
<body>
    <?php include("header.php") ?>
    
    <section class="contact-appointment-section section-apadding fix">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="login-card p-4 my-2">
                        <div class="login-logo">
                            <img src="<?= $site . $logo ?>" class="img-fluid">
                        </div>
                        <h3>Verify Your Account</h3>
                        <p>We've sent a 6-digit OTP to:<br>
                           <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
                           <strong>Mobile:</strong> <?= htmlspecialchars($mobile) ?>
                        </p>
                        
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $_SESSION['success_message'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Enter 6-digit OTP</label>
                                <input type="text" class="form-control" name="otp" maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="000000" required style="text-align: center; font-size: 18px; letter-spacing: 5px;">
                            </div>
                            
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-2">Didn't receive OTP?</p>
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="resend_otp" class="btn btn-outline-secondary resend-btn" 
                                            <?= !$can_resend ? 'disabled' : '' ?>>
                                        Resend OTP 
                                        <?php if (!$can_resend): ?>
                                            (<span class="countdown" id="countdown"><?= ceil($time_remaining/60) ?></span>m)
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include("footer.php") ?>
    
    <script>
        // Countdown timer for OTP resend
        <?php if (!$can_resend): ?>
        let timeLeft = <?= $time_remaining ?>;
        
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            document.getElementById('countdown').textContent = 
                minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            
            if (timeLeft > 0) {
                timeLeft--;
                setTimeout(updateCountdown, 1000);
            } else {
                document.querySelector('.resend-btn').disabled = false;
                document.getElementById('countdown').parentElement.remove();
            }
        }
        
        updateCountdown();
        <?php endif; ?>
    </script>
</body>
</html>