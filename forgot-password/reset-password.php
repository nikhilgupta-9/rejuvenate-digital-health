<?php
session_start();
include_once "../config/connect.php";
include_once "../util/function.php";

// Import PHPMailer classes if available
$phpmailer_available = false;
if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
    $phpmailer_available = true;
} elseif (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
    $phpmailer_available = true;
}

// Check if token and email are provided
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    header("Location: forgot-password.php?error=Invalid+reset+link");
    exit();
}

$token = $_GET['token'];
$email = urldecode($_GET['email']);

$error_message = '';
$success_message = '';
$token_valid = false;
$doctor_id = null;
$doctor_name = '';

// Verify token
try {
    // Hash the token for comparison
    $token_hash = hash('sha256', $token);
    
    $sql = "SELECT id, name, reset_token_expiry FROM doctors 
            WHERE email = ? AND reset_token = ? AND reset_token_expiry > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $email, $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $doctor = $result->fetch_assoc();
        $token_valid = true;
        $doctor_id = $doctor['id'];
        $doctor_name = $doctor['name'];
    } else {
        $error_message = "Invalid or expired reset link. Please request a new password reset.";
    }
} catch (Exception $e) {
    $error_message = "An error occurred. Please try again.";
    error_log("Reset Password Token Verification Error: " . $e->getMessage());
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_valid) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error_message = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error_message = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error_message = "Password must contain at least one number.";
    } elseif (!preg_match('/[\W_]/', $password)) {
        $error_message = "Password must contain at least one special character.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // Hash the new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // First, let's check what columns exist
            $check_columns_sql = "SHOW COLUMNS FROM doctors LIKE 'last_password_change'";
            $check_result = $conn->query($check_columns_sql);
            $has_last_password_change = ($check_result->num_rows > 0);
            
            // Build the update query based on available columns
            if ($has_last_password_change) {
                $update_sql = "UPDATE doctors SET 
                              password = ?,
                              reset_token = NULL,
                              reset_token_expiry = NULL,
                              reset_attempts = 0,
                              last_reset_request = NULL,
                              last_password_change = NOW()
                              WHERE id = ?";
            } else {
                $update_sql = "UPDATE doctors SET 
                              password = ?,
                              reset_token = NULL,
                              reset_token_expiry = NULL,
                              reset_attempts = 0,
                              last_reset_request = NULL
                              WHERE id = ?";
            }
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $password_hash, $doctor_id);
            
            if ($update_stmt->execute()) {
                // Check if audit_logs table exists before trying to insert
                $check_audit_sql = "SHOW TABLES LIKE 'audit_logs'";
                $audit_result = $conn->query($check_audit_sql);
                
                if ($audit_result->num_rows > 0) {
                    // Record password change in audit log
                    $audit_sql = "INSERT INTO audit_logs (doctor_id, action, ip_address, user_agent) 
                                 VALUES (?, 'password_reset', ?, ?)";
                    $audit_stmt = $conn->prepare($audit_sql);
                    $audit_stmt->bind_param('iss', $doctor_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                    $audit_stmt->execute();
                }
                
                // Send confirmation email if PHPMailer is available
                if ($phpmailer_available) {
                    send_password_reset_confirmation($email, $doctor_name);
                }
                
                $success_message = "Your password has been reset successfully! You can now login with your new password.";
                $token_valid = false; // Prevent form from showing again
                
                // Auto-redirect to login page after 5 seconds
                header("refresh:5;url=" . BASE_URL . "doctor-login/");
            } else {
                $error_message = "Failed to reset password. Please try again. Error: " . $update_stmt->error;
            }
        } catch (Exception $e) {
            $error_message = "An error occurred while resetting your password.";
            error_log("Password Reset Error: " . $e->getMessage());
            error_log("Password Reset Error Details: " . $e->getFile() . ":" . $e->getLine());
        }
    }
}

// Function to send password reset confirmation email
function send_password_reset_confirmation($email, $name) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'nik007guptadu@gmail.com'; // your email
        $mail->Password   = 'ltmnhrwacmwmcrni';        // app password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
        
        // Sender & Recipient
        $mail->setFrom('noreply@rejuvenatehealth.com', 'REJUVENATE Digital Health');
        $mail->addAddress($email, 'Dr. ' . $name);
        
        // Email Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Successful - REJUVENATE Digital Health';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background:#f4f6f8; }
                .container { max-width:600px; margin:auto; background:#ffffff; padding:20px; }
                h2 { background:#2c5aa0; color:#fff; padding:15px; text-align:center; }
                .content { padding:20px; }
                .success-box { background:#d4edda; border:1px solid #c3e6cb; border-radius:5px; padding:15px; margin:15px 0; }
                .footer { text-align:center; font-size:12px; color:#777; margin-top:20px; border-top:1px solid #eee; padding-top:20px; }
                .login-btn { background:#2c5aa0; color:#fff; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Password Reset Successful</h2>
                <div class='content'>
                    <p>Hello Dr. " . htmlspecialchars($name) . ",</p>
                    
                    <div class='success-box'>
                        <p><strong>✅ Your password has been successfully reset!</strong></p>
                        <p>You can now log in to your REJUVENATE Digital Health account with your new password.</p>
                    </div>
                    
                    <p>If you made this change, no further action is required.</p>
                    
                    <p style='text-align:center; margin:20px 0;'>
                        <a href='https://rejuvenatedigitalhealth.com/doctor-login/' class='login-btn'>Go to Login</a>
                    </p>
                    
                    <div class='warning' style='background:#fff3cd; border-left:4px solid #ffc107; padding:10px; margin:15px 0;'>
                        <p><strong>⚠️ Security Notice:</strong></p>
                        <p>If you did not make this change, please contact our support team immediately.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p><strong>REJUVENATE Digital Health</strong></p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " REJUVENATE Digital Health. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $mail->AltBody = "Password Reset Successful\n\n" .
            "Hello Dr. " . $name . ",\n\n" .
            "Your password has been successfully reset!\n\n" .
            "You can now log in to your REJUVENATE Digital Health account with your new password.\n\n" .
            "If you did not make this change, please contact our support team immediately.\n\n" .
            "REJUVENATE Digital Health Team\n" .
            "This is an automated message.";
        
        $mail->send();
        error_log("Password reset confirmation email sent to: " . $email);
    } catch (Exception $e) {
        error_log("Password Reset Confirmation Email Error: " . $e->getMessage());
        // Don't show error to user, just log it
    }
}
$logo = get_header_logo();
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="Reset your REJUVENATE Digital Health password">
    <title>Reset Password - REJUVENATE Digital Health</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>
<body>
    <?php include("../header.php") ?>

    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="reg-card">

                        <div class="text-center mb-4">
                            <img src="<?= BASE_URL . $logo ?>" class="img-fluid mb-3" style="max-height:48px;">
                            <h4 class="fw-bold mb-1">Reset Your Password</h4>
                            <?php if ($token_valid): ?>
                                <p class="text-muted" style="font-size:.83rem;">
                                    <i class="fa fa-user-md me-1"></i>Dr. <?= htmlspecialchars($doctor_name) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" style="border-radius:10px;font-size:.85rem;">
                                <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">
                                <i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                                <div class="mt-1" style="font-size:.75rem;">You will be redirected to the login page in 5 seconds...</div>
                            </div>
                        <?php endif; ?>

                        <?php if ($token_valid): ?>
                            <form method="POST" autocomplete="off" id="resetPasswordForm">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                                <div class="field-group">
                                    <label>New Password <span class="text-danger">*</span></label>
                                    <div class="pass-wrap">
                                        <input type="password" class="form-control" id="password" name="password"
                                               placeholder="At least 8 characters" required minlength="8" oninput="pwStrength(this)">
                                        <button type="button" class="toggle" onclick="togglePw('password',this)"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <div class="pw-bar">
                                        <div class="pw-bar-fill" id="pwBar"></div>
                                    </div>
                                    <div id="pwStrengthTxt" style="font-size:.7rem;color:#9ca3af;margin-top:2px;"></div>
                                </div>

                                <div class="field-group">
                                    <label>Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="pass-wrap">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                               placeholder="Re-enter password" required minlength="8" oninput="checkMatch()">
                                        <button type="button" class="toggle" onclick="togglePw('confirm_password',this)"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <div id="matchTxt" style="font-size:.73rem;margin-top:3px;"></div>
                                </div>

                                <div class="password-requirements mb-3" style="font-size:.78rem;">
                                    <span class="d-block mb-2 text-muted"><strong>Password must contain:</strong></span>
                                    <div class="requirement" id="reqLength"><i class="fas fa-circle"></i><span>At least 8 characters</span></div>
                                    <div class="requirement" id="reqUppercase"><i class="fas fa-circle"></i><span>One uppercase letter</span></div>
                                    <div class="requirement" id="reqLowercase"><i class="fas fa-circle"></i><span>One lowercase letter</span></div>
                                    <div class="requirement" id="reqNumber"><i class="fas fa-circle"></i><span>One number</span></div>
                                    <div class="requirement" id="reqSpecial"><i class="fas fa-circle"></i><span>One special character</span></div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" id="submitBtn"
                                        style="border-radius:10px;font-size:.95rem;">
                                    <i class="fa fa-key me-2"></i>Reset Password
                                </button>
                            </form>

                            <div class="text-center mt-3">
                                <p class="mb-0" style="font-size:13px;">Remember your password? <a href="<?= BASE_URL ?>doctor-login/">Login here</a></p>
                            </div>

                        <?php elseif (empty($success_message)): ?>
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <p class="text-muted mb-4" style="font-size:.85rem;">This password reset link is invalid or has expired.</p>
                                <a href="<?= BASE_URL ?>forgot-password/" class="btn btn-primary w-100 py-2 fw-semibold"
                                   style="border-radius:10px;font-size:.95rem;">
                                    <i class="fa fa-redo me-2"></i>Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include("../footer.php") ?>

    <style>
        .requirement { margin-bottom: 4px; display: flex; align-items: center; color: #9ca3af; }
        .requirement i { margin-right: 8px; font-size: .65rem; }
        .requirement.met { color: #16a34a; }
        .requirement.unmet { color: #dc2626; }
    </style>

    <script>
        function togglePw(id, btn) {
            const inp = document.getElementById(id);
            const isText = inp.type === 'text';
            inp.type = isText ? 'password' : 'text';
            btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
        }

        function updateRequirement(elementId, met) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const icon = el.querySelector('i');
            el.classList.toggle('met', met);
            el.classList.toggle('unmet', !met);
            icon.className = met ? 'fas fa-check-circle' : 'fas fa-circle';
        }

        function pwStrength(inp) {
            const p = inp.value;
            const bar = document.getElementById('pwBar');
            const txt = document.getElementById('pwStrengthTxt');

            const hasLength = p.length >= 8;
            const hasUppercase = /[A-Z]/.test(p);
            const hasLowercase = /[a-z]/.test(p);
            const hasNumber = /[0-9]/.test(p);
            const hasSpecial = /[\W_]/.test(p);

            updateRequirement('reqLength', hasLength);
            updateRequirement('reqUppercase', hasUppercase);
            updateRequirement('reqLowercase', hasLowercase);
            updateRequirement('reqNumber', hasNumber);
            updateRequirement('reqSpecial', hasSpecial);

            const s = [hasLength, hasUppercase, hasLowercase, hasNumber, hasSpecial].filter(Boolean).length;
            const levels = [
                { w: '20%', c: '#dc2626', l: 'Very weak' },
                { w: '40%', c: '#ea580c', l: 'Weak' },
                { w: '60%', c: '#d97706', l: 'Fair' },
                { w: '80%', c: '#16a34a', l: 'Good' },
                { w: '100%', c: '#0C74C5', l: 'Strong' },
            ];
            const lv = levels[Math.max(0, s - 1)];
            bar.style.width = p.length ? lv.w : '0';
            bar.style.background = lv.c;
            txt.textContent = p.length ? lv.l : '';
            txt.style.color = lv.c;

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = !(hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial);

            checkMatch();
        }

        function checkMatch() {
            const p1 = document.getElementById('password').value;
            const p2 = document.getElementById('confirm_password').value;
            const el = document.getElementById('matchTxt');
            if (!p2) { el.textContent = ''; return; }
            if (p1 === p2) {
                el.innerHTML = '<span style="color:#16a34a"><i class="fas fa-check me-1"></i>Passwords match</span>';
            } else {
                el.innerHTML = '<span style="color:#dc2626"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
            }
        }

        const resetForm = document.getElementById('resetPasswordForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return false;
                }
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }

                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Processing...';
                submitBtn.disabled = true;
            });
        }
    </script>
</body>
</html>