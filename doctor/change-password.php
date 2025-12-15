<?php
include_once(__DIR__ . "/../config/connect.php");
include_once(__DIR__ . "/../util/function.php");

// Start session and check doctor login
// session_start();
if (!isset($_SESSION['doctor_logged_in']) || $_SESSION['doctor_logged_in'] !== true) {
    header("Location: " . $site . "doctor-login.php");
    exit();
}

$doctor_id = $_SESSION['doctor_id'];
$success_message = '';
$error_message = '';

// Get doctor's basic info for sidebar
$doctor_sql = "SELECT name, email, profile_image, phone FROM doctors WHERE id = ?";
$doctor_stmt = $conn->prepare($doctor_sql);
$doctor_stmt->bind_param('i', $doctor_id);
$doctor_stmt->execute();
$doctor_result = $doctor_stmt->get_result();
$doctor_data = $doctor_result->fetch_assoc();

$doctor_name = $doctor_data['name'] ?? 'Doctor';
$doctor_email = $doctor_data['email'] ?? '';
$doctor_profile_image = !empty($doctor_data['profile_image']) ? $doctor_data['profile_image'] : 'assets/img/dummy.png';
$doctor_phone = $doctor_data['phone'] ?? '';

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All fields are required.");
        }
        
        // Check password strength
        if (strlen($new_password) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }
        
        if (!preg_match('/[A-Z]/', $new_password)) {
            throw new Exception("New password must contain at least one uppercase letter.");
        }
        
        if (!preg_match('/[a-z]/', $new_password)) {
            throw new Exception("New password must contain at least one lowercase letter.");
        }
        
        if (!preg_match('/[0-9]/', $new_password)) {
            throw new Exception("New password must contain at least one number.");
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            throw new Exception("New password must contain at least one special character.");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }
        
        // Check if new password is same as current password
        if ($current_password === $new_password) {
            throw new Exception("New password cannot be the same as current password.");
        }
        
        // Get current password hash from database
        $sql = "SELECT password FROM doctors WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $doctor = $result->fetch_assoc();
            
            // Verify current password
            if (password_verify($current_password, $doctor['password'])) {
                
                // Check if new password was used before (last 3 passwords)
                $password_history_sql = "
                    SELECT password FROM doctor_password_history 
                    WHERE doctor_id = ? 
                    ORDER BY changed_at DESC 
                    LIMIT 3
                ";
                $history_stmt = $conn->prepare($password_history_sql);
                $history_stmt->bind_param('i', $doctor_id);
                $history_stmt->execute();
                $history_result = $history_stmt->get_result();
                
                $password_reused = false;
                while ($old_password = $history_result->fetch_assoc()) {
                    if (password_verify($new_password, $old_password['password'])) {
                        $password_reused = true;
                        break;
                    }
                }
                
                if ($password_reused) {
                    throw new Exception("You cannot reuse any of your last 3 passwords.");
                }
                
                // Hash new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Update current password
                    $update_sql = "UPDATE doctors SET password = ?, last_password_reset = NOW() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param('si', $new_password_hash, $doctor_id);
                    
                    if (!$update_stmt->execute()) {
                        throw new Exception("Failed to update password.");
                    }
                    
                    // Save to password history
                    $history_sql = "INSERT INTO doctor_password_history (doctor_id, password) VALUES (?, ?)";
                    $history_stmt = $conn->prepare($history_sql);
                    $history_stmt->bind_param('is', $doctor_id, $new_password_hash);
                    
                    if (!$history_stmt->execute()) {
                        throw new Exception("Failed to save password history.");
                    }
                    
                    // Log password change
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    
                    $log_sql = "
                        INSERT INTO doctor_password_logs (doctor_id, ip_address, user_agent, action) 
                        VALUES (?, ?, ?, 'password_changed')
                    ";
                    $log_stmt = $conn->prepare($log_sql);
                    $log_stmt->bind_param('iss', $doctor_id, $ip_address, $user_agent);
                    $log_stmt->execute();
                    
                    // Clear any reset tokens
                    $clear_token_sql = "UPDATE doctors SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ?";
                    $clear_stmt = $conn->prepare($clear_token_sql);
                    $clear_stmt->bind_param('i', $doctor_id);
                    $clear_stmt->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Send email notification
                    sendPasswordChangeEmail($doctor_email, $doctor_name, $site);
                    
                    $success_message = "Password changed successfully! You will be redirected to login in 5 seconds.";
                    
                    // Auto logout after 5 seconds
                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "logout.php?password_changed=1";
                        }, 5000);
                    </script>';
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                
            } else {
                throw new Exception("Current password is incorrect.");
            }
        } else {
            throw new Exception("Doctor account not found.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        
        // Log failed attempt
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $log_sql = "
            INSERT INTO doctor_password_logs (doctor_id, ip_address, user_agent, action) 
            VALUES (?, ?, ?, 'password_change_failed')
        ";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param('iss', $doctor_id, $ip_address, $user_agent);
        $log_stmt->execute();
    }
}

// Function to send password change email
function sendPasswordChangeEmail($email, $name, $site) {
    // You can use your existing email function
    // For example: send_otp_email($email, $otp) modified for password change
    $subject = "Password Changed - REJUVENATE Digital Health";
    $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: auto; padding: 20px; }
                .header { background: #2c5aa0; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Password Changed Successfully</h2>
                </div>
                <div class='content'>
                    <p>Hello Dr. $name,</p>
                    <p>Your password has been successfully changed.</p>
                    <div class='warning'>
                        <strong>Security Alert:</strong> If you did not make this change, please contact our support team immediately.
                    </div>
                    <p>For security reasons, you have been logged out from all devices.</p>
                    <p>You can now login with your new password.</p>
                    <p>Best regards,<br>REJUVENATE Digital Health Team</p>
                </div>
            </div>
        </body>
        </html>
    ";
    
    // Use your existing email function
    // send_doctor_verification_email($email, $name, $subject, $message);
    
    // For now, just log it
    error_log("Password change email should be sent to: $email");
    return true;
}

// Create required tables if they don't exist
$create_tables_sql = array(
    "CREATE TABLE IF NOT EXISTS doctor_password_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        password VARCHAR(255) NOT NULL,
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS doctor_password_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        action VARCHAR(50),
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )"
);

foreach ($create_tables_sql as $sql) {
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        error_log("Table creation error: " . $e->getMessage());
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
    <title>REJUVENATE Digital Health - Change Password</title>
    <link rel="stylesheet" href="<?= $site ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= $site ?>assets/css/main.css">
    <style>
        .sidebar {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            height: fit-content;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #02c9b8;
            color: white;
        }
        .userd-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #02c9b8;
        }
        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
            max-width: 600px;
            margin: 0 auto;
        }
        .menu-btn {
            display: none;
            background: #02c9b8;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
        }
        .strength-0 { background: #dc3545; width: 20%; }
        .strength-1 { background: #ffc107; width: 40%; }
        .strength-2 { background: #ffc107; width: 60%; }
        .strength-3 { background: #28a745; width: 80%; }
        .strength-4 { background: #28a745; width: 100%; }
        .requirement-list {
            font-size: 13px;
            margin-top: 5px;
        }
        .requirement-met {
            color: #28a745;
        }
        .requirement-unmet {
            color: #dc3545;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        .input-group-password {
            position: relative;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .sidebar.show { display: block; }
            .menu-btn { display: block; }
        }
    </style>
</head>
<body>
    <?php include("../header.php") ?>
    
    <section class="contact-appointment-section section-padding fix">
        <div class="container">
            <div class="row mb-5">
                <!-- Sidebar -->
                <div class="col-md-3">
                    <div class="sidebar" id="sidebarMenu">
                        <div class="text-center info-content">
                            <img src="<?= $site . $doctor_profile_image ?>" class="userd-image">
                            <h5>Dr. <?= htmlspecialchars($doctor_name) ?></h5>
                            <p><?= htmlspecialchars($doctor_email) ?></p>
                            <p>Phone: <?= htmlspecialchars($doctor_phone) ?></p>
                            <a href="my-contact.php" class="btn btn-info btn-sm mb-3 mt-2">Edit Profile</a>
                        </div>

                        <a href="<?= $site ?>doctor/doctor-dashboard.php">Dashboard</a>
                        <a href="<?= $site ?>doctor/my-patients.php">My Patients</a>
                        <a href="<?= $site ?>doctor/appointments.php">Appointments</a>
                        <a href="<?= $site ?>doctor/patient-form.pdf">Patient Form</a>
                        <a href="<?= $site ?>doctor/my-contact.php">Contact Us</a>
                        <a href="<?= $site ?>doctor/doctor-about.php">About Us</a>
                        <a href="<?= $site ?>doctor/change-password.php" class="active">Change Password</a>
                        <a href="<?= $site ?>doctor/doctor-logout.php">Logout</a>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Mobile Toggle Button -->
                    <span class="menu-btn d-lg-none mb-3" onclick="toggleMenu()">☰ Menu</span>
                    
                    <!-- Password Change Form -->
                    <div class="profile-card shadow">
                        <h4 class="mb-4">Change Password</h4>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= $success_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error_message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="passwordChangeForm">
                            <!-- Current Password -->
                            <div class="mb-3">
                                <label for="current_password" class="form-label">
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group-password">
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required 
                                           placeholder="Enter current password">
                                    <span class="password-toggle" onclick="togglePassword('current_password')">
                                        <i class="fa fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group-password">
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required 
                                           placeholder="Enter new password"
                                           oninput="checkPasswordStrength(this.value)">
                                    <span class="password-toggle" onclick="togglePassword('new_password')">
                                        <i class="fa fa-eye"></i>
                                    </span>
                                </div>
                                
                                <!-- Password Strength Indicator -->
                                <div class="password-strength" id="passwordStrength"></div>
                                
                                <!-- Password Requirements -->
                                <div class="requirement-list">
                                    <div id="reqLength" class="requirement-unmet">
                                        <i class="fa fa-circle"></i> At least 8 characters
                                    </div>
                                    <div id="reqUppercase" class="requirement-unmet">
                                        <i class="fa fa-circle"></i> At least one uppercase letter
                                    </div>
                                    <div id="reqLowercase" class="requirement-unmet">
                                        <i class="fa fa-circle"></i> At least one lowercase letter
                                    </div>
                                    <div id="reqNumber" class="requirement-unmet">
                                        <i class="fa fa-circle"></i> At least one number
                                    </div>
                                    <div id="reqSpecial" class="requirement-unmet">
                                        <i class="fa fa-circle"></i> At least one special character
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group-password">
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required 
                                           placeholder="Confirm new password"
                                           oninput="checkPasswordMatch()">
                                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fa fa-eye"></i>
                                    </span>
                                </div>
                                <div id="passwordMatch" class="form-text"></div>
                            </div>
                            
                            <!-- Security Tips -->
                            <div class="alert alert-info">
                                <h6><i class="fa fa-shield-alt"></i> Security Tips:</h6>
                                <ul class="mb-0" style="font-size: 13px;">
                                    <li>Use a unique password not used elsewhere</li>
                                    <li>Don't share your password with anyone</li>
                                    <li>Change your password regularly</li>
                                    <li>Log out after each session</li>
                                    <li>You cannot reuse your last 3 passwords</li>
                                </ul>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="submit" name="change_password" class="btn btn-success px-4">
                                    <i class="fa fa-key"></i> Change Password
                                </button>
                                <a href="<?= $site ?>doctor/doctor-dashboard.php" class="btn btn-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Recent Password Changes -->
                    <?php
                    $recent_changes_sql = "
                        SELECT changed_at FROM doctor_password_history 
                        WHERE doctor_id = ? 
                        ORDER BY changed_at DESC 
                        LIMIT 5
                    ";
                    $recent_stmt = $conn->prepare($recent_changes_sql);
                    $recent_stmt->bind_param('i', $doctor_id);
                    $recent_stmt->execute();
                    $recent_result = $recent_stmt->get_result();
                    
                    if ($recent_result->num_rows > 0):
                    ?>
                    <div class="profile-card shadow mt-4">
                        <h5 class="mb-3">Recent Password Changes</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date & Time</th>
                                        <th>Days Ago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    while ($change = $recent_result->fetch_assoc()): 
                                        $change_date = new DateTime($change['changed_at']);
                                        $current_date = new DateTime();
                                        $interval = $current_date->diff($change_date);
                                    ?>
                                    <tr>
                                        <td><?= $counter ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($change['changed_at'])) ?></td>
                                        <td>
                                            <?php 
                                            if ($interval->y > 0) {
                                                echo $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
                                            } elseif ($interval->m > 0) {
                                                echo $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
                                            } elseif ($interval->d > 0) {
                                                echo $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
                                            } elseif ($interval->h > 0) {
                                                echo $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
                                            } else {
                                                echo 'Just now';
                                            }
                                            ?> ago
                                        </td>
                                    </tr>
                                    <?php 
                                    $counter++;
                                    endwhile; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <?php include("../footer.php") ?>
    
    <script>
        function toggleMenu() {
            document.getElementById("sidebarMenu").classList.toggle("show");
        }
        
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.fa-eye');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Check password strength
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Length check
            if (password.length >= 8) {
                strength++;
                document.getElementById('reqLength').className = 'requirement-met';
                document.getElementById('reqLength').querySelector('i').className = 'fa fa-check-circle';
            } else {
                document.getElementById('reqLength').className = 'requirement-unmet';
                document.getElementById('reqLength').querySelector('i').className = 'fa fa-circle';
            }
            
            // Uppercase check
            if (/[A-Z]/.test(password)) {
                strength++;
                document.getElementById('reqUppercase').className = 'requirement-met';
                document.getElementById('reqUppercase').querySelector('i').className = 'fa fa-check-circle';
            } else {
                document.getElementById('reqUppercase').className = 'requirement-unmet';
                document.getElementById('reqUppercase').querySelector('i').className = 'fa fa-circle';
            }
            
            // Lowercase check
            if (/[a-z]/.test(password)) {
                strength++;
                document.getElementById('reqLowercase').className = 'requirement-met';
                document.getElementById('reqLowercase').querySelector('i').className = 'fa fa-check-circle';
            } else {
                document.getElementById('reqLowercase').className = 'requirement-unmet';
                document.getElementById('reqLowercase').querySelector('i').className = 'fa fa-circle';
            }
            
            // Number check
            if (/[0-9]/.test(password)) {
                strength++;
                document.getElementById('reqNumber').className = 'requirement-met';
                document.getElementById('reqNumber').querySelector('i').className = 'fa fa-check-circle';
            } else {
                document.getElementById('reqNumber').className = 'requirement-unmet';
                document.getElementById('reqNumber').querySelector('i').className = 'fa fa-circle';
            }
            
            // Special character check
            if (/[^A-Za-z0-9]/.test(password)) {
                strength++;
                document.getElementById('reqSpecial').className = 'requirement-met';
                document.getElementById('reqSpecial').querySelector('i').className = 'fa fa-check-circle';
            } else {
                document.getElementById('reqSpecial').className = 'requirement-unmet';
                document.getElementById('reqSpecial').querySelector('i').className = 'fa fa-circle';
            }
            
            // Update strength bar
            const strengthBar = document.getElementById('passwordStrength');
            strengthBar.className = 'password-strength strength-' + strength;
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchText.innerHTML = '';
                matchText.className = 'form-text';
                return;
            }
            
            if (newPassword === confirmPassword) {
                matchText.innerHTML = '<span style="color: green;">✓ Passwords match</span>';
                matchText.className = 'form-text';
            } else {
                matchText.innerHTML = '<span style="color: red;">✗ Passwords do not match</span>';
                matchText.className = 'form-text';
            }
        }
        
        // Form validation
        document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check if new password meets all requirements
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            if (!/[A-Z]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter.');
                return false;
            }
            
            if (!/[a-z]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one lowercase letter.');
                return false;
            }
            
            if (!/[0-9]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one number.');
                return false;
            }
            
            if (!/[^A-Za-z0-9]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one special character.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return false;
            }
            
            if (currentPassword === newPassword) {
                e.preventDefault();
                alert('New password cannot be the same as current password.');
                return false;
            }
            
            return true;
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check initial password strength if any
            const newPassword = document.getElementById('new_password').value;
            if (newPassword) {
                checkPasswordStrength(newPassword);
                checkPasswordMatch();
            }
        });
    </script>
</body>
</html>