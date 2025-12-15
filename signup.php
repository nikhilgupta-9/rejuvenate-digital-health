<?php
session_start();
include_once "config/connect.php";
include_once "util/function.php";

$contact = contact_us();
$logo = get_header_logo();

// Get errors and old data from session
$errors = $_SESSION['signup_errors'] ?? [];
$old_data = $_SESSION['old_data'] ?? [];

// Clear session data after retrieving
unset($_SESSION['signup_errors']);
unset($_SESSION['old_data']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>User Signup | REJUVENATE Digital Health</title>
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
  </style>
</head>

<body>
  <?php include("header.php") ?>
  
  <section class="contact-appointment-section section-apadding fix">
    <div class="container">
      <div class="contact-appointment-wrapper-5 mb-5">
        <div class="row">
          <div class="col-md-12 col-sm-12 col-12">
            <div class="login-card p-4 my-2">
              <div class="login-logo">
                <img src="<?= $site . $logo ?>" class="img-fluid">
              </div>
              <h3>Create an Account</h3>
              
              <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <?= $_SESSION['success_message'] ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
              <?php endif; ?>
              
              <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?= $errors['general'] ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form id="signupForm" method="POST" action="<?= $site ?>process-signup.php" novalidate>
                <div class="row">
                  <!-- First Name -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                           name="name" value="<?= htmlspecialchars($old_data['name'] ?? '') ?>" required>
                    <?php if (isset($errors['name'])): ?>
                      <div class="error"><?= $errors['name'] ?></div>
                    <?php endif; ?>
                  </div>

                  <!-- Last Name -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" 
                           value="<?= htmlspecialchars($old_data['last_name'] ?? '') ?>">
                  </div>

                  <!-- Email -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                           name="email" value="<?= htmlspecialchars($old_data['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?>
                      <div class="error"><?= $errors['email'] ?></div>
                    <?php endif; ?>
                  </div>

                  <!-- Mobile -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['mobile']) ? 'is-invalid' : '' ?>" 
                           name="mobile" value="<?= htmlspecialchars($old_data['mobile'] ?? '') ?>" required>
                    <?php if (isset($errors['mobile'])): ?>
                      <div class="error"><?= $errors['mobile'] ?></div>
                    <?php endif; ?>
                  </div>

                  <!-- Password -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                           name="password" required>
                    <?php if (isset($errors['password'])): ?>
                      <div class="error"><?= $errors['password'] ?></div>
                    <?php endif; ?>
                  </div>

                  <!-- Confirm Password -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control <?= isset($errors['confirmPassword']) ? 'is-invalid' : '' ?>" 
                           name="confirmPassword" required>
                    <?php if (isset($errors['confirmPassword'])): ?>
                      <div class="error"><?= $errors['confirmPassword'] ?></div>
                    <?php endif; ?>
                  </div>

                  <!-- Gender -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Gender</label>
                    <select class="form-control" name="gender">
                      <option value="" disabled selected>Select Gender</option>
                      <option value="Male" <?= ($old_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                      <option value="Female" <?= ($old_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                      <option value="Other" <?= ($old_data['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                  </div>

                  <!-- Date of Birth -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="dob" 
                           value="<?= htmlspecialchars($old_data['dob'] ?? '') ?>">
                  </div>

                  <!-- Address -->
                  <div class="col-12 mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" 
                           value="<?= htmlspecialchars($old_data['address'] ?? '') ?>">
                  </div>

                  <!-- City, State, Zip Code -->
                  <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="city" 
                           value="<?= htmlspecialchars($old_data['city'] ?? '') ?>">
                  </div>

                  <div class="col-md-4 mb-3">
                    <label class="form-label">State</label>
                    <input type="text" class="form-control" name="state" 
                           value="<?= htmlspecialchars($old_data['state'] ?? '') ?>">
                  </div>

                  <div class="col-md-4 mb-3">
                    <label class="form-label">Zip Code</label>
                    <input type="text" class="form-control" name="zip_code" 
                           value="<?= htmlspecialchars($old_data['zip_code'] ?? '') ?>">
                  </div>

                  <!-- Terms & Conditions -->
                  <div class="col-md-12 mb-3 form-check">
                    <input type="checkbox" class="form-check-input <?= isset($errors['terms']) ? 'is-invalid' : '' ?>" 
                           name="terms" required <?= isset($old_data['terms']) ? 'checked' : '' ?>>
                    <label class="form-check-label">I agree to Terms & Conditions <span class="text-danger">*</span></label>
                    <?php if (isset($errors['terms'])): ?>
                      <div class="error"><?= $errors['terms'] ?></div>
                    <?php endif; ?>
                  </div>

                  <button type="submit" class="btn btn-primary w-100">Create Account</button>
                </div>
              </form>

              <p class="text-center mt-3 mb-0">Already have an account? <a href="<?= $site ?>user-login/">Sign in</a></p>
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
    document.getElementById('signupForm').addEventListener('submit', function(e) {
      const password = document.querySelector('input[name="password"]').value;
      const confirmPassword = document.querySelector('input[name="confirmPassword"]').value;
      
      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }
      
      if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
      }
    });
  </script>
</body>
</html>