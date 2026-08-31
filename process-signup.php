<?php
session_start();
include_once "config/connect.php";
include_once "util/function.php";
include_once "util/otp-service.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Sanitize and validate input data
    $name = trim($_POST['name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
    $mobile_verify_token = $_POST['mobile_verify_token'] ?? '';
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
    
    if (empty($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors['mobile'] = "Valid 10-digit mobile number is required";
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

        // Mobile must have been verified via WhatsApp/email OTP on the form
        if (!isset($errors['mobile']) && !otp_consume_token('patient', $mobile, $mobile_verify_token)) {
            $errors['mobile'] = "Please verify your mobile number with the OTP before creating your account";
        }
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

        // Mobile is already OTP-verified (WhatsApp + email) at this point, so the
        // account goes straight to Active with mobile + email marked verified —
        // no separate verify-otp.php step.
        $stmt = $conn->prepare("INSERT INTO users
            (name, last_name, email, mobile, password, gender, dob, address, city, state, zip_code,
             status, email_verified, mobile_verified, mobile_verified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 1, 1, NOW())");

        $stmt->bind_param("sssssssssss", $name, $last_name, $email, $mobile, $hashed_password, $gender, $dob, $address, $city, $state, $zip_code);

        if ($stmt->execute()) {
            if (function_exists('send_welcome_email') && $email) {
                @send_welcome_email($email, trim($name . ' ' . $last_name), 'patient');
            }

            $_SESSION['success_message'] = "Account created and mobile number verified! You can now log in.";

            header("Location: login.php");
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