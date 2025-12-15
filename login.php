<?php
session_start();
include_once "config/connect.php";
include_once "util/function.php";

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: ".$site."user/user-dashboard.php");
    exit();
}

// Auto-login with remember token
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
    $token = $_COOKIE['remember_token'];
    $user_id = $_COOKIE['user_id'];
    
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND remember_token = ? AND status = 'Active'");
    $stmt->bind_param("is", $user_id, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['logged_in'] = true;
        
        header("Location: ".$site."user/user-dashboard.php");
        exit();
    }
}

$contact = contact_us();
$logo = get_header_logo();

// Get errors and old data from session
$errors = $_SESSION['login_errors'] ?? [];
$old_email = $_SESSION['old_email'] ?? '';

// Clear session data after retrieving
unset($_SESSION['login_errors']);
unset($_SESSION['old_email']);

// Check for success messages
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Check for email verification requirement
$verify_message = '';
if (isset($_SESSION['verify_required'])) {
    $verify_message = "Please verify your email address to continue.";
    unset($_SESSION['verify_required']);
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
  <title>User Login | REJUVENATE Digital Health</title>
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
    .error { color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
    .is-invalid { border-color: #dc3545; }
    .alert { border-radius: 8px; }
    .login-card { 
        max-width: 600px; 
        margin: 0 auto; 
        padding: 2rem;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
        border-radius: 10px;
    }
  </style>
</head>

<body>
  <?php include("header.php") ?>
  
  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="contact-appointment-wrapper-5 mb-5">
        <div class="row">
          <div class="col-md-12 col-sm-12 col-12">
            <div class="login-card">
              <div class="login-logo text-center mb-4">
                <img src="<?= $site . $logo ?>" class="img-fluid" style="max-height: 60px;">
              </div>
              <h3 class="text-center mb-4">Login to Your Account</h3>
              
              <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <?= $success_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              
              <?php if ($verify_message): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                  <?= $verify_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              
              <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?= $errors['general'] ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form id="loginForm" method="POST" action="<?= $site ?>process-login.php" novalidate>
                <div class="mb-3">
                  <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
                  <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                         id="email" name="email" placeholder="Enter your email"
                         value="<?= htmlspecialchars($old_email) ?>" required>
                  <?php if (isset($errors['email'])): ?>
                    <div class="error"><?= $errors['email'] ?></div>
                  <?php endif; ?>
                </div>
                
                <div class="mb-3">
                  <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                         id="password" name="password" placeholder="Enter your password" required>
                  <?php if (isset($errors['password'])): ?>
                    <div class="error"><?= $errors['password'] ?></div>
                  <?php endif; ?>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember">Remember me</label>
                  </div>
                  <small class="form-text"><a href="forgot-password.php">Forgot Password?</a></small>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
              </form>
              
              <p class="text-center mt-3 mb-0">Don't have an account? <a href="<?= $site ?>user-signup/">Sign Up</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  
  <?php include("footer.php") ?>
  
  <script src="<?= $site ?>assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Client-side validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const email = document.getElementById('email').value;
      const password = document.getElementById('password').value;
      let valid = true;
      
      // Clear previous errors
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      document.querySelectorAll('.error').forEach(el => el.remove());
      
      // Email validation
      if (!email || !validateEmail(email)) {
        showError('email', 'Please enter a valid email address');
        valid = false;
      }
      
      // Password validation
      if (!password) {
        showError('password', 'Please enter your password');
        valid = false;
      }
      
      if (!valid) {
        e.preventDefault();
      }
    });
    
    function validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }
    
    function showError(fieldId, message) {
      const field = document.getElementById(fieldId);
      field.classList.add('is-invalid');
      
      const errorDiv = document.createElement('div');
      errorDiv.className = 'error';
      errorDiv.textContent = message;
      field.parentNode.appendChild(errorDiv);
    }
  </script>
</body>
</html>