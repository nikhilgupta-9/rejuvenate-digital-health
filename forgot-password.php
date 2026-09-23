<?php
include_once "config/connect.php";
include_once "util/function.php";
include_once "util/mail_config.php";

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
                        // Send reset email using the shared Mailer
                        $reset_link = BASE_URL . "forgot-password/reset-password.php?token=" . $token . "&email=" . urlencode($email);

                        try {
                            if ($mailer->sendPasswordReset($doctor['email'], $doctor['name'], $reset_link, 60)) {
                                $success_message = "Password reset link has been sent to your email. Please check your inbox (and spam folder).";
                            } else {
                                $error_message = "Failed to send email. Please try again later.";
                            }
                        } catch (Exception $e) {
                            error_log("Password Reset Mail Error: " . $e->getMessage());
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
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="modinatheme">
    <meta name="description" content="">
    <title>REJUVENATE Digital Health - Forgot Password</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>
<body>
    <?php include("header.php") ?>

    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="reg-card">

                        <div class="text-center mb-4">
                            <img src="<?= BASE_URL . $logo ?>" class="img-fluid mb-3" style="max-height:48px;">
                            <h4 class="fw-bold mb-1">Forgot Your Password?</h4>
                            <p class="text-muted" style="font-size:.83rem;">Enter your registered email and we'll send you a link to reset your password.</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;">
                                <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">
                                <i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="field-group">
                                <label for="email">Email address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="Enter your registered email" required
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <div class="form-text" style="font-size:.75rem;">Make sure this is the email you used to register your doctor account.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"
                                    style="border-radius:10px;font-size:.95rem;">
                                <i class="fa fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <p class="mb-0" style="font-size:13px;">Remember your password? <a href="<?= BASE_URL ?>doctor-login/">Back to Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("footer.php") ?>
</body>
</html>