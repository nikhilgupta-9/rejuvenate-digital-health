<?php
include_once "config/connect.php";
include_once "util/function.php";

// session_start();

$contact = contact_us();
$logo = get_header_logo();
$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        // Check if doctor exists and is approved
        $sql = "SELECT * FROM doctors WHERE email = ? AND is_verified = 1 AND status = 'Active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $doctor = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $doctor['password'])) {
                // Reset login attempts on successful login
                $reset_sql = "UPDATE doctors SET login_attempts = 0, is_locked = 0, locked_until = NULL, last_login = NOW() WHERE id = ?";
                $reset_stmt = $conn->prepare($reset_sql);
                $reset_stmt->bind_param('i', $doctor['id']);
                $reset_stmt->execute();
                
                // Create session
                $_SESSION['doctor_id'] = $doctor['id'];
                $_SESSION['doctor_email'] = $doctor['email'];
                $_SESSION['doctor_name'] = $doctor['name'];
                $_SESSION['doctor_logged_in'] = true;
                
                // Create session token
                $session_token = bin2hex(random_bytes(32));
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                
                $session_sql = "INSERT INTO doctor_sessions (doctor_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)";
                $session_stmt = $conn->prepare($session_sql);
                $session_stmt->bind_param('isss', $doctor['id'], $session_token, $ip_address, $user_agent);
                $session_stmt->execute();
                
                $_SESSION['session_token'] = $session_token;
                
                // Redirect to doctor dashboard
                header("Location: ".$site."doctor/doctor-dashboard.php");
                exit();
                
            } else {
                // Increment login attempts
                $attempts_sql = "UPDATE doctors SET login_attempts = login_attempts + 1 WHERE email = ?";
                $attempts_stmt = $conn->prepare($attempts_sql);
                $attempts_stmt->bind_param('s', $email);
                $attempts_stmt->execute();
                
                // Check if account should be locked
                $check_sql = "SELECT login_attempts FROM doctors WHERE email = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param('s', $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $attempt_data = $check_result->fetch_assoc();
                
                if ($attempt_data['login_attempts'] >= 5) {
                    $lock_sql = "UPDATE doctors SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE email = ?";
                    $lock_stmt = $conn->prepare($lock_sql);
                    $lock_stmt->bind_param('s', $email);
                    $lock_stmt->execute();
                    $error_message = "Account temporarily locked due to multiple failed login attempts. Try again in 30 minutes.";
                } else {
                    $error_message = "Invalid email or password. Attempts remaining: " . (5 - $attempt_data['login_attempts']);
                }
            }
        } else {
            $error_message = "Invalid email or account not approved yet.";
        }
    } catch (Exception $e) {
        $error_message = "Login failed. Please try again.";
    }
}

// Check if already logged in
if (isset($_SESSION['doctor_logged_in']) && $_SESSION['doctor_logged_in'] === true) {
    header("Location: doctor-dashboard.php");
    exit();
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
  <title>REJUVENATE Digital Health - Doctor Login</title>
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
   
    .alert {
      border-radius: 8px;
      border: none;
    }
  </style>
</head>
<body>
  <?php include("header.php") ?>
  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="contact-appointment-wrapper-5 mb-5">
        <div class="row align-items-center">
          <div class="col-md-6 col-sm-6 col-12">
            <div class="doctor-login text-center">
              <img src="<?= $site ?>assets/img/doctor-login.png" class="img-fluid" style="max-height: 500px;">
              <h4 class="mt-4">Welcome Back, Doctor!</h4>
              <p class="text-muted">Access your personalized dashboard and continue providing excellent healthcare services.</p>
            </div>
          </div>
          <div class="col-md-6 col-sm-6 col-12">
            <div class="login-card">
              <div class="login-logo">
                <img src="<?= $site . $logo ?>" class="img-fluid">
              </div>
              <h3 class="text-center mb-4">Login as a Doctor</h3>
              
              <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?= $error_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <?= $success_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              
              <form method="POST" action="">
                <div class="mb-3">
                  <label for="email" class="form-label">Email address</label>
                  <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required value="<?= $_POST['email'] ?? '' ?>">
                </div>
                <div class="mb-3">
                  <label for="password" class="form-label">Password</label>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                  </div>
                  <small class="form-text"><a href="<?= $site ?>forgot-password/">Forgot Password?</a></small>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
              </form>
              <p class="text-center mt-3 mb-0">Don't have an account? <a href="<?= $site ?>doctor-signup/">Sign Up</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php include("footer.php") ?>
</body>
</html>