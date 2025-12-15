<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "db-conn.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: auth/login.php");
    exit();
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle profile updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

    $admin_id = $_SESSION['admin_id'];
    $new_username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    // Fetch current admin data
    $stmt = $conn->prepare("SELECT password, failed_attempts, locked_until FROM admin_user WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->bind_result($hashed_password, $failed_attempts, $locked_until);
    $stmt->fetch();
    $stmt->close();
    
    // Check if account is locked
    if ($locked_until && strtotime($locked_until) > time()) {
        $error_message = "Account locked. Try again after " . date('H:i', strtotime($locked_until));
    } 
    // Verify current password
    else if (password_verify($current_password, $hashed_password)) {
        // Reset failed attempts on successful verification
        $reset_attempts = $conn->prepare("UPDATE admin_user SET failed_attempts = 0 WHERE id = ?");
        $reset_attempts->bind_param("i", $admin_id);
        $reset_attempts->execute();
        $reset_attempts->close();
        
        // Update profile information
        $update_stmt = $conn->prepare("UPDATE admin_user SET 
                                     username = ?, email = ?, first_name = ?, last_name = ? 
                                     WHERE id = ?");
        $update_stmt->bind_param("ssssi", $new_username, $email, $first_name, $last_name, $admin_id);
        $update_stmt->execute();
        
        // Update password if new password provided
        if (!empty($new_password)) {
            if (strlen($new_password) < 8) {
                $error_message = "Password must be at least 8 characters";
            } else {
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pass_stmt = $conn->prepare("UPDATE admin_user SET 
                                                  password = ?, password_changed_at = NOW() 
                                                  WHERE id = ?");
                $update_pass_stmt->bind_param("si", $new_hashed_password, $admin_id);
                $update_pass_stmt->execute();
                $update_pass_stmt->close();
            }
        }
        
        $update_stmt->close();
        
        // Update session data
        $_SESSION['admin_user'] = $new_username;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_name'] = $first_name . ' ' . $last_name;
        
        $success_message = "Profile updated successfully!";
    } else {
        // Increment failed attempts
        $failed_attempts++;
        $update_attempts = $conn->prepare("UPDATE admin_user SET 
                                          failed_attempts = ?,
                                          locked_until = IF(? >= 5, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL)
                                          WHERE id = ?");
        $update_attempts->bind_param("iii", $failed_attempts, $failed_attempts, $admin_id);
        $update_attempts->execute();
        $update_attempts->close();
        
        $remaining_attempts = 5 - $failed_attempts;
        $error_message = "Current password is incorrect! " . 
                        ($remaining_attempts > 0 ? 
                         "$remaining_attempts attempts remaining" : 
                         "Account locked for 30 minutes");
    }
}

// Fetch current admin data for display
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, email, first_name, last_name, role, status, 
                       last_login, last_login_ip, created_at 
                       FROM admin_user WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($username, $email, $first_name, $last_name, $role, $status, 
                  $last_login, $last_login_ip, $created_at);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Admin Profile | Computer Electronics</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">

    <?php include "links.php"; ?>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --danger-color: #f72585;
            --success-color: #4bb543;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        }
        
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .profile-img:hover {
            transform: scale(1.05);
        }
        
        .profile-detail-card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: none;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .profile-detail-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .detail-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: #555;
        }
        
        .password-strength {
            height: 4px;
            background: #e9ecef;
            margin-top: 0.5rem;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: var(--danger-color);
            transition: all 0.3s ease;
        }
        
        .password-toggle {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .password-toggle:hover {
            color: var(--accent-color);
        }
        
        .btn-update {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            padding: 0.5rem 1.5rem;
        }
        
        .btn-update:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
        }
    </style>
</head>

<body class="crm_body_bg">

    <?php include "header.php"; ?>
    
    <section class="main_content dashboard_part large_header_bg">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="dashboard_header mb-4">
                            <h2>Admin Profile Management</h2>
                            <p>Update your account information and security settings</p>
                        </div>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Profile Header -->
                            <div class="col-12">
                                <div class="profile-header text-center">
                                    <img src="assets/img/client_img.png" alt="Admin" class="profile-img mb-3">
                                    <h3><?= htmlspecialchars($first_name . ' ' . $last_name) ?></h3>
                                    <p class="mb-0">
                                        <span class="role-badge bg-<?= $role === 'super_admin' ? 'danger' : 'primary' ?> me-2">
                                            <?= ucfirst(str_replace('_', ' ', $role)) ?>
                                        </span>
                                        <span class="status-badge bg-<?= $status === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Left Column - Profile Details -->
                            <div class="col-lg-4">
                                <div class="profile-detail-card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Account Details</h5>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Username</div>
                                            <div class="detail-value"><?= htmlspecialchars($username) ?></div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Email</div>
                                            <div class="detail-value"><?= htmlspecialchars($email) ?></div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Account Created</div>
                                            <div class="detail-value"><?= date('M j, Y', strtotime($created_at)) ?></div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Last Login</div>
                                            <div class="detail-value">
                                                <?= $last_login ? date('M j, Y H:i', strtotime($last_login)) : 'Never' ?>
                                                <?php if ($last_login_ip): ?>
                                                    <small class="text-muted d-block">IP: <?= $last_login_ip ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Account Status</div>
                                            <div class="detail-value">
                                                <span class="badge bg-<?= $status === 'active' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($status) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="profile-detail-card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Security Information</h5>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Two-Factor Authentication</div>
                                            <div class="detail-value">
                                                <span class="badge bg-secondary">Not Enabled</span>
                                                <a href="#" class="btn btn-sm btn-outline-primary ms-2">Enable</a>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Login History</div>
                                            <div class="detail-value">
                                                <a href="login-history.php" class="btn btn-sm btn-outline-primary">View History</a>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Active Sessions</div>
                                            <div class="detail-value">
                                                <span class="badge bg-success">1 Active</span>
                                                <a href="#" class="btn btn-sm btn-outline-danger ms-2">Logout Others</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column - Update Form -->
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-4">Update Profile Information</h5>
                                        
                                        <form method="post" action="">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="first_name" class="form-label">First Name</label>
                                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                                        value="<?= htmlspecialchars($first_name) ?>" required>
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label for="last_name" class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                                        value="<?= htmlspecialchars($last_name) ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <input type="text" class="form-control" id="username" name="username" 
                                                    value="<?= htmlspecialchars($username) ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                    value="<?= htmlspecialchars($email) ?>" required>
                                            </div>
                                            
                                            <hr class="my-4">
                                            
                                            <h6 class="mb-3">Password Update</h6>
                                            
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="current_password" 
                                                        name="current_password" required>
                                                    <span class="input-group-text password-toggle" 
                                                        onclick="togglePassword('current_password')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                </div>
                                                <small class="text-muted">Required to confirm any changes</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="new_password" 
                                                        name="new_password" oninput="checkPasswordStrength(this.value)">
                                                    <span class="input-group-text password-toggle" 
                                                        onclick="togglePassword('new_password')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                </div>
                                                <div class="password-strength mt-2">
                                                    <div class="password-strength-bar" id="password-strength-bar"></div>
                                                </div>
                                                <small class="text-muted">Leave blank to keep current password. Minimum 8 characters.</small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" id="confirm_password" 
                                                        name="confirm_password">
                                                    <span class="input-group-text password-toggle" 
                                                        onclick="togglePassword('confirm_password')">
                                                        <i class="fas fa-eye"></i>
                                                    </span>
                                                </div>
                                                <small id="password-match-feedback" class="text-muted"></small>
                                            </div>
                                            
                                            <div class="d-flex justify-content-end mt-4">
                                                <button type="submit" name="update_profile" class="btn btn-update">
                                                    <i class="fas fa-save me-2"></i> Update Profile
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
    </section>

    <script>
        // Toggle password visibility
        function togglePassword(id) {
            const password = document.getElementById(id);
            const icon = password.nextElementSibling.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Check password strength
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength-bar');
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
            if (password.match(/([0-9])/)) strength += 1;
            if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;
            
            switch(strength) {
                case 0:
                    strengthBar.style.width = '0%';
                    strengthBar.style.backgroundColor = 'var(--danger-color)';
                    break;
                case 1:
                    strengthBar.style.width = '25%';
                    strengthBar.style.backgroundColor = 'var(--danger-color)';
                    break;
                case 2:
                    strengthBar.style.width = '50%';
                    strengthBar.style.backgroundColor = '#ffcc00';
                    break;
                case 3:
                    strengthBar.style.width = '75%';
                    strengthBar.style.backgroundColor = '#66cc33';
                    break;
                case 4:
                    strengthBar.style.width = '100%';
                    strengthBar.style.backgroundColor = 'var(--success-color)';
                    break;
            }
        }
        
        // Check password match
        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            const feedback = document.getElementById('password-match-feedback');
            
            if (confirmPassword.value.length > 0) {
                if (this.value === confirmPassword.value) {
                    feedback.textContent = "Passwords match!";
                    feedback.style.color = "var(--success-color)";
                } else {
                    feedback.textContent = "Passwords don't match!";
                    feedback.style.color = "var(--danger-color)";
                }
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password');
            const feedback = document.getElementById('password-match-feedback');
            
            if (this.value.length > 0) {
                if (this.value === newPassword.value) {
                    feedback.textContent = "Passwords match!";
                    feedback.style.color = "var(--success-color)";
                } else {
                    feedback.textContent = "Passwords don't match!";
                    feedback.style.color = "var(--danger-color)";
                }
            } else {
                feedback.textContent = "";
            }
        });
    </script>
</body>
</html>