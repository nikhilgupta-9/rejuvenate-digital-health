<?php
session_start();
include_once "config/connect.php";
include_once "util/function.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Sanitize and validate input data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? 1 : 0;
    
    // Validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Valid email is required";
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
    }
    
    // If no errors, proceed with login
    if (empty($errors)) {
        // Check if user exists and is active
        $stmt = $conn->prepare("SELECT id, name, email, password, status, email_verified, mobile_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check account status
            if ($user['status'] === 'Inactive') {
                $errors['general'] = "Your account is inactive. Please contact support.";
            } elseif ($user['status'] === 'Blocked') {
                $errors['general'] = "Your account has been blocked. Please contact support.";
            } elseif (!password_verify($password, $user['password'])) {
                $errors['general'] = "Invalid email or password";
            } else {
                // Check if email is verified
                if (!$user['email_verified']) {
                    // Generate new OTP for verification
                    $otp_code = rand(100000, 999999);
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $update_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
                    $update_stmt->bind_param("ssi", $otp_code, $otp_expiry, $user['id']);
                    $update_stmt->execute();
                    
                    // Send OTP email
                    send_otp_email($user['email'], $otp_code);
                    
                    $_SESSION['verify_email'] = true;
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['otp_code'] = $otp_code;
                    $_SESSION['otp_expiry'] = $otp_expiry;
                    $_SESSION['otp_sent_time'] = time();
                    
                    header("Location: verify-otp.php?source=login");
                    exit();
                }
                
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                
                // Update last login timestamp
                $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . $user['id']);
                
                // Set remember me cookie if requested
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    setcookie('remember_token', $token, $expiry, '/');
                    setcookie('user_id', $user['id'], $expiry, '/');
                    
                    // Store token in database
                    $conn->query("UPDATE users SET remember_token = '$token' WHERE id = " . $user['id']);
                }
                
                // Redirect to dashboard or previous page
                if (isset($_SESSION['redirect_url'])) {
                    $redirect_url = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                    header("Location: $redirect_url");
                } else {
                    header("Location: ".$site."user/user-dashboard.php");
                }
                exit();
            }
        } else {
            $errors['general'] = "Invalid email or password";
        }
    }
    
    // If there are errors, redirect back to login page
    $_SESSION['login_errors'] = $errors;
    $_SESSION['old_email'] = $email;
    header("Location: login.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>