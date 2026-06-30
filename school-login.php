<?php
/* Redirect to unified login page */
header("Location: login.php"); exit();
include_once "config/connect.php";
include_once "util/function.php";

$logo = get_header_logo();
$error_message = '';
$success_message = '';

// Already logged in
if (isset($_SESSION['school_logged_in']) && $_SESSION['school_logged_in'] === true) {
  header("Location: " . BASE_URL . "school/dashboard.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $email    = trim($_POST['email']);
  $password = $_POST['password'];

  try {
    // Get school user
    $sql = "SELECT su.*, s.school_name, s.status as school_status, s.id as school_id
                FROM school_users su
                JOIN schools s ON su.school_id = s.id
                WHERE su.email = ? AND su.status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();

      // Check account lock
      if ($user['is_locked'] && strtotime($user['locked_until']) > time()) {
        throw new Exception("Account locked. Try again after " . date('h:i A', strtotime($user['locked_until'])));
      }

      if (password_verify($password, $user['password'])) {
        // Check school approval
        if ($user['school_status'] === 'Pending') {
          throw new Exception("Your school registration is under review. Please wait for admin approval.");
        }
        if ($user['school_status'] === 'Rejected') {
          throw new Exception("Your school registration was rejected. Please contact support.");
        }
        if ($user['school_status'] === 'Inactive') {
          throw new Exception("Your school account is inactive. Please contact support.");
        }

        // Reset attempts
        // $conn->prepare("UPDATE school_users SET login_attempts=0, is_locked=0, locked_until=NULL, last_login=NOW() WHERE id=?")->execute() || null;
        $upd = $conn->prepare("UPDATE school_users SET login_attempts=0, is_locked=0, locked_until=NULL, last_login=NOW() WHERE id=?");
        $upd->bind_param('i', $user['id']);
        $upd->execute();

        $upd = $conn->prepare("UPDATE school_users SET login_attempts=0, is_locked=0, locked_until=NULL, last_login=NOW() WHERE id=?");
        $upd->bind_param('i', $user['id']);
        $upd->execute();

        // Set session
        $_SESSION['school_logged_in']   = true;
        $_SESSION['school_user_id']      = $user['id'];
        $_SESSION['school_id']           = $user['school_id'];
        $_SESSION['school_name']         = $user['school_name'];
        $_SESSION['school_user_name']    = $user['name'];
        $_SESSION['school_user_email']   = $user['email'];
        $_SESSION['school_user_role']    = $user['role'];

        header("Location: " . BASE_URL . "school/dashboard.php");
        exit();
      } else {
        // Increment attempts
        $att = $conn->prepare("UPDATE school_users SET login_attempts = login_attempts + 1 WHERE id = ?");
        $att->bind_param('i', $user['id']);
        $att->execute();

        $attempts = $user['login_attempts'] + 1;
        if ($attempts >= 5) {
          $lock = $conn->prepare("UPDATE school_users SET is_locked=1, locked_until=DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id=?");
          $lock->bind_param('i', $user['id']);
          $lock->execute();
          throw new Exception("Account locked for 30 minutes due to multiple failed attempts.");
        }
        throw new Exception("Invalid email or password. " . (5 - $attempts) . " attempts remaining.");
      }
    } else {
      throw new Exception("No account found with this email or account is inactive.");
    }
  } catch (Exception $e) {
    $error_message = $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>REJUVENATE Digital Health - School Login</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>

<body>
  <?php include("header.php") ?>

  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="contact-appointment-wrapper-5 mb-5">
        <div class="row align-items-center">

          <!-- Left Info -->
          <div class="col-md-6 col-sm-6 col-12">
            <div class="doctor-login text-center">
              <img src="<?= BASE_URL ?>assets/img/doctor-login.png" class="img-fluid" style="max-height:500px;">
              <h4 class="mt-4">Welcome, School Admin!</h4>
              <p class="text-muted">Manage health profiles of your students, teachers and staff through your secure school dashboard.</p>
              <div class="benefits mt-4 text-start d-inline-block">
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> Manage student health records</div>
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> Teacher &amp; staff profiles</div>
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> ABHA ID linkage</div>
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> Supervised by Super Admin</div>
              </div>
            </div>
          </div>

          <!-- Right Login Form -->
          <div class="col-md-6 col-sm-6 col-12">
            <div class="login-card">
              <div class="login-logo text-center">
                <img src="<?= BASE_URL . $logo ?>" class="img-fluid" style="max-height:60px;">
              </div>
              <h3 class="text-center mb-4">School Login</h3>

              <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>

              <form method="POST" action="">
                <div class="mb-3">
                  <label class="form-label">Email Address</label>
                  <input type="email" class="form-control" name="email" placeholder="Enter school email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">Password</label>
                  <div class="position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="btn btn-link position-absolute end-0 top-0 mt-1 me-2"
                          onclick="togglePasswordById('password', 'passwordIcon')">
                          <i id="passwordIcon" class="fas fa-eye-slash"></i>
                        </button>
                  </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                  </div>
                  <small><a href="<?= BASE_URL ?>forgot-password.php">Forgot Password?</a></small>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login to Dashboard</button>
              </form>

              <hr class="my-4">
              <p class="text-center mb-0">Don't have an account? <a href="<?= BASE_URL ?>school-register.php" class="imp-link">Register Your School</a></p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
</body>

</html>