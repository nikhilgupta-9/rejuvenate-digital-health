<?php
include_once "config/connect.php";
include_once "util/function.php";
include "util/mail_config.php";

$mailer = new Mailer();

$contact = contact_us();
$logo = get_header_logo();
$error_message = '';
$success_message = '';

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    try {
        // Check if doctor exists and is verified
        $sql = "SELECT id, name, email, is_verified, status, reset_attempts, last_reset_request FROM doctors WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $doctor = $result->fetch_assoc();
            
            // Check if account is active and verified
            if ($doctor['is_verified'] != 1) {
                $error_message = "Your account is not verified. Please contact administration.";
            } elseif ($doctor['status'] != 'Active') {
                $error_message = "Your account is not active. Please contact administration.";
            } else {
                // Rate limiting: Check if too many reset attempts
                $now = date('Y-m-d H:i:s');
                $last_request = $doctor['last_reset_request'];
                $reset_attempts = $doctor['reset_attempts'];
                
                if ($last_request && $reset_attempts >= 3) {
                    $time_diff = strtotime($now) - strtotime($last_request);
                    if ($time_diff < 3600) { // 1 hour cooldown
                        $remaining = ceil((3600 - $time_diff) / 60);
                        $error_message = "Too many reset attempts. Please try again in $remaining minutes.";
                    } else {
                        // Reset attempt count after 1 hour
                        $reset_attempts = 0;
                    }
                }
                
                if (empty($error_message)) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store token in database
                    $update_sql = "UPDATE doctors SET 
                                   reset_token = ?, 
                                   reset_token_expiry = ?, 
                                   reset_attempts = reset_attempts + 1,
                                   last_reset_request = NOW()
                                   WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param('ssi', $token_hash, $expiry, $doctor['id']);
                    
                    if ($update_stmt->execute()) {
                        // Send reset email using PHPMailer
                        $reset_link = BASE_URL . "forgot-password/reset-password.php?token=" . $token . "&email=" . urlencode($email)
                        
                        try {
                             $mailer->sendPasswordReset();
                            $success_message = "Password reset link has been sent to your email. Please check your inbox (and spam folder).";
                            
                        } catch (Exception $e) {
                            error_log("Password Reset Mail Error: " . $mail->ErrorInfo);
                            $error_message = "Failed to send email. Please try again later.";
                        }
                        
                    } else {
                        $error_message = "Failed to process request. Please try again.";
                    }
                }
            }
        } else {
            // For security, show same message whether email exists or not
            $success_message = "If your email is registered, you will receive a password reset link shortly.";
        }
    } catch (Exception $e) {
        $error_message = "An error occurred. Please try again later.";
        error_log("Forgot Password Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - Forgot Password</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        .forgot-password-card {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            background: white;
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        .login-logo img {
            max-height: 60px;
        }
        .form-text {
            font-size: 0.875em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include("header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="forgot-password-card">
                        <div class="login-logo text-center mb-4">
                            <img src="<?= BASE_URL . $logo ?>" class="img-fluid">
                        </div>
                        <h3 class="text-center mb-4">Forgot Your Password?</h3>
                        <p class="text-center text-muted mb-4">Enter your email address and we'll send you a link to reset your password.</p>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($error_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($success_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Enter your registered email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <div class="form-text">Make sure this is the email you used to register your doctor account.</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                        </form>
                        
                        <div class="back-to-login">
                            <p class="mb-0">Remember your password? <a href="<?= BASE_URL ?>doctor-login/">Back to Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <?php include("footer.php") ?>
</body>
</html>