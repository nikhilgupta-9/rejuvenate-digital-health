<?php
session_start();
include_once "config/connect.php";
include_once "util/function.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Sanitize and validate input data
    $name = trim($_POST['name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $terms = isset($_POST['terms']) ? 1 : 0;
    
    // Validate terms acceptance
    if (!$terms) {
        $errors['terms'] = "You must agree to the Terms & Conditions";
    }
    
    // ... rest of your validation code ...
    // Validation
    if (empty($name)) {
        $errors['name'] = "First name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Valid email is required";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = "Email already registered";
        }
        $stmt->close();
    }
    
    if (empty($mobile)) {
        $errors['mobile'] = "Mobile number is required";
    } else {
        // Check if mobile already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['mobile'] = "Mobile number already registered";
        }
        $stmt->close();
    }
    
    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = "Passwords do not match";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $otp_code = rand(100000, 999999);
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $conn->prepare("INSERT INTO users (name, last_name, email, mobile, password, gender, dob, address, city, state, zip_code, otp_code, otp_expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
        
        $stmt->bind_param("sssssssssssss", $name, $last_name, $email, $mobile, $hashed_password, $gender, $dob, $address, $city, $state, $zip_code, $otp_code, $otp_expiry);
        
        if ($stmt->execute()) {
            // Send OTP via Email
            $email_sent = send_otp_email($email, $otp_code);
            
            // Send OTP via SMS (Uncomment when you have SMS service setup)
            // $sms_sent = send_otp_sms($mobile, $otp_code);
            // OR for Indian numbers:
            // $sms_sent = send_otp_sms_textlocal($mobile, $otp_code);
            
            $_SESSION['signup_success'] = true;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_mobile'] = $mobile;
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['otp_sent_time'] = time();
            
            // Store OTP info in session for verification
            $_SESSION['otp_code'] = $otp_code;
            $_SESSION['otp_expiry'] = $otp_expiry;
            
            header("Location: verify-otp.php");
            exit();
        } else {
            $errors['general'] = "Registration failed. Please try again.";
            $_SESSION['signup_errors'] = $errors;
            $_SESSION['old_data'] = $_POST;
            header("Location: signup.php");
            exit();
        }
    } else {
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['old_data'] = $_POST;
        header("Location: signup.php");
        exit();
    }
} else {
    header("Location: signup.php");
    exit();
}
?>