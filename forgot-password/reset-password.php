<?php
session_start();
include_once "config/connect.php";

// Check if token and email are provided
if (!isset($_GET['token']) || !isset($_GET['email'])) {
    header("Location: forgot-password.php?error=Invalid reset link");
    exit();
}

$token = $_GET['token'];
$email = urldecode($_GET['email']);

$error_message = '';
$success_message = '';
$token_valid = false;
$doctor_id = null;

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
            
            // Update password and clear reset token
            $update_sql = "UPDATE doctors SET 
                          password = ?,
                          reset_token = NULL,
                          reset_token_expiry = NULL,
                          reset_attempts = 0,
                          last_reset_request = NULL,
                          last_password_change = NOW()
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $password_hash, $doctor_id);
            
            if ($update_stmt->execute()) {
                // Record password change in audit log (optional)
                $audit_sql = "INSERT INTO audit_logs (doctor_id, action, ip_address, user_agent) 
                             VALUES (?, 'password_reset', ?, ?)";
                $audit_stmt = $conn->prepare($audit_sql);
                $audit_stmt->bind_param('iss', $doctor_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                $audit_stmt->execute();
                
                // Send confirmation email (optional)
                send_password_reset_confirmation($email, $doctor_name);
                
                $success_message = "Your password has been reset successfully! You can now login with your new password.";
                $token_valid = false; // Prevent form from showing again
                
                // Auto-redirect to login page after 5 seconds
                header("refresh:5;url=" . $site . "doctor-login/");
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
        } catch (Exception $e) {
            $error_message = "An error occurred while resetting your password.";
            error_log("Password Reset Error: " . $e->getMessage());
        }
    }
}

// Function to send password reset confirmation email
function send_password_reset_confirmation($email, $name) {
    // Only proceed if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return;
    }
    
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
    } catch (Exception $e) {
        error_log("Password Reset Confirmation Email Error: " . $mail->ErrorInfo);
        // Don't show error to user, just log it
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="REJUVENATE Digital Health">
    <meta name="description" content="Reset your REJUVENATE Digital Health password">
    <title>Reset Password - REJUVENATE Digital Health</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --success-color: #28a745;
            --danger-color: #dc3545;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .reset-password-card {
            max-width: 500px;
            margin: 30px auto;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            max-height: 70px;
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.25);
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .password-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
        }
        
        .requirement {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .requirement i {
            margin-right: 8px;
            font-size: 0.8rem;
        }
        
        .requirement.met {
            color: var(--success-color);
        }
        
        .requirement.unmet {
            color: #dc3545;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #3a6bc5 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .security-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .security-info i {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        @media (max-width: 576px) {
            .reset-password-card {
                padding: 25px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="reset-password-card">
                    <div class="logo-container">
                        <!-- Add your logo here -->
                        <h2 class="mb-3" style="color: var(--primary-color);">REJUVENATE Digital Health</h2>
                        <h4 class="mb-4">Reset Your Password</h4>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success_message) ?>
                            <p class="mt-2 mb-0"><small>You will be redirected to the login page in 5 seconds...</small></p>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($token_valid): ?>
                        <p class="text-center text-muted mb-4">
                            <i class="fas fa-user-md me-2"></i>Dr. <?= htmlspecialchars($doctor_name) ?>
                        </p>
                        
                        <form method="POST" action="" id="resetPasswordForm">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter new password" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Re-enter new password" required minlength="8">
                                <div id="passwordMatch" class="mt-1"></div>
                            </div>
                            
                            <!-- Password Requirements -->
                            <div class="password-requirements mb-4">
                                <small class="d-block mb-2"><strong>Password must contain:</strong></small>
                                <div class="requirement" id="reqLength">
                                    <i class="fas fa-circle"></i>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="requirement" id="reqUppercase">
                                    <i class="fas fa-circle"></i>
                                    <span>One uppercase letter</span>
                                </div>
                                <div class="requirement" id="reqLowercase">
                                    <i class="fas fa-circle"></i>
                                    <span>One lowercase letter</span>
                                </div>
                                <div class="requirement" id="reqNumber">
                                    <i class="fas fa-circle"></i>
                                    <span>One number</span>
                                </div>
                                <div class="requirement" id="reqSpecial">
                                    <i class="fas fa-circle"></i>
                                    <span>One special character</span>
                                </div>
                            </div>
                            
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                                <i class="fas fa-key me-2"></i>Reset Password
                            </button>
                            
                            <div class="security-info">
                                <p><i class="fas fa-shield-alt"></i> Your password must be strong and secure. Avoid using common words or personal information.</p>
                            </div>
                        </form>
                        
                        <div class="login-link">
                            <p>Remember your password? <a href="<?= $site ?>doctor-login/">Login here</a></p>
                        </div>
                    <?php elseif (empty($success_message)): ?>
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5 class="mb-3">Invalid Reset Link</h5>
                            <p class="text-muted mb-4">This password reset link is invalid or has expired.</p>
                            <a href="<?= $site ?>forgot-password/" class="btn btn-primary">
                                <i class="fas fa-redo me-2"></i>Request New Reset Link
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordStrength = document.getElementById('passwordStrength');
            const submitBtn = document.getElementById('submitBtn');
            
            // Password visibility toggle
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Password strength checker
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Check requirements
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[\W_]/.test(password);
                
                // Update requirement indicators
                updateRequirement('reqLength', hasLength);
                updateRequirement('reqUppercase', hasUppercase);
                updateRequirement('reqLowercase', hasLowercase);
                updateRequirement('reqNumber', hasNumber);
                updateRequirement('reqSpecial', hasSpecial);
                
                // Calculate strength score
                let strength = 0;
                if (hasLength) strength += 20;
                if (hasUppercase) strength += 20;
                if (hasLowercase) strength += 20;
                if (hasNumber) strength += 20;
                if (hasSpecial) strength += 20;
                
                // Update strength meter
                updateStrengthMeter(strength);
                
                // Check password match
                checkPasswordMatch();
            });
            
            // Confirm password checker
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            function updateRequirement(elementId, met) {
                const element = document.getElementById(elementId);
                const icon = element.querySelector('i');
                
                if (met) {
                    element.classList.add('met');
                    element.classList.remove('unmet');
                    icon.className = 'fas fa-check-circle';
                    icon.style.color = '#28a745';
                } else {
                    element.classList.add('unmet');
                    element.classList.remove('met');
                    icon.className = 'fas fa-times-circle';
                    icon.style.color = '#dc3545';
                }
            }
            
            function updateStrengthMeter(strength) {
                let color, width;
                
                if (strength < 40) {
                    color = '#dc3545'; // Weak
                } else if (strength < 80) {
                    color = '#ffc107'; // Medium
                } else {
                    color = '#28a745'; // Strong
                }
                
                passwordStrength.style.width = strength + '%';
                passwordStrength.style.backgroundColor = color;
                
                // Update submit button state
                const password = passwordInput.value;
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[\W_]/.test(password);
                
                submitBtn.disabled = !(hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial);
            }
            
            function checkPasswordMatch() {
                const matchElement = document.getElementById('passwordMatch');
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword === '') {
                    matchElement.innerHTML = '';
                    return;
                }
                
                if (password === confirmPassword) {
                    matchElement.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Passwords match</small>';
                } else {
                    matchElement.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</small>';
                }
            }
            
            // Form validation
            document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
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
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>