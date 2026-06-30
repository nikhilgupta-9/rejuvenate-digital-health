<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
session_start();
include_once("../../util/function.php");
$favicon = get_favicon();


// Rate limit configuration
$MAX_ATTEMPTS = 4;           // Maximum 4 attempts
$COOLDOWN_PERIOD = 1200;      // 20 minutes in seconds (20 * 60)

if (!isset($_SESSION['login_attempts'])) {
  $_SESSION['login_attempts'] = 0;
  $_SESSION['last_login_attempt'] = 0;
}

// Check if rate limit exceeded (4 or more attempts within cooldown period)
if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS && (time() - $_SESSION['last_login_attempt']) < $COOLDOWN_PERIOD) {
  $remaining_time = $COOLDOWN_PERIOD - (time() - $_SESSION['last_login_attempt']);
  $minutes = ceil($remaining_time / 60);
  $error = "Too many login attempts (" . $_SESSION['login_attempts'] . "/$MAX_ATTEMPTS). Please try again in $minutes minutes.";
  $_POST = [];
  
  // Clear POST data to prevent processing
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SERVER["REQUEST_METHOD"] = "GET";
  }
}

require __DIR__ . '/../db-conn.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
  header("Location: ../index.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $error = "Invalid request";
  } else {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
      $error = "Username and password are required";
    } elseif (strlen($username) > 50 || strlen($password) > 255) {
      $error = "Invalid input length";
    } else {
      $stmt = $conn->prepare("SELECT id, username, password, status, locked_until, failed_attempts FROM admin_user WHERE username = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();
      $user_data = $result->fetch_assoc();
      
      if ($user_data) {
        // Check if account is locked
        if ($user_data['status'] == 'locked' || ($user_data['locked_until'] && strtotime($user_data['locked_until']) > time())) {
          usleep(rand(200000, 500000));
          $error = "Account is temporarily locked. Please try again later.";
        } elseif (password_verify($password, $user_data['password'])) {
          // Successful login - reset all counters
          session_regenerate_id(true);
          $_SESSION['admin_logged_in'] = true;
          $_SESSION['admin_id'] = $user_data['id'];
          $_SESSION['admin_user'] = $user_data['username'];
          $_SESSION['login_time'] = time();
          $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
          $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
          $_SESSION['login_attempts'] = 0;
          
          // Update database - reset failed attempts and last login
          $update_stmt = $conn->prepare("UPDATE admin_user SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_login_ip = ?, password_changed_at = COALESCE(password_changed_at, NOW()) WHERE id = ?");
          $update_stmt->bind_param("si", $_SERVER['REMOTE_ADDR'], $user_data['id']);
          $update_stmt->execute();
          $update_stmt->close();
          
          if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60);
            $expiry_date = date('Y-m-d H:i:s', $expiry);
            $token_stmt = $conn->prepare("UPDATE admin_user SET remember_token = ?, token_expiry = ? WHERE id = ?");
            $token_stmt->bind_param("ssi", $token, $expiry_date, $user_data['id']);
            $token_stmt->execute();
            $token_stmt->close();
            setcookie('admin_remember', $token, $expiry, '/', '', true, true);
          }
          
          error_log("Admin login successful: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
          $stmt->close();
          $conn->close();
          header("Location: ../index.php");
          exit();
        } else {
          // Failed password - increment attempts
          $_SESSION['login_attempts']++;
          $_SESSION['last_login_attempt'] = time();
          
          $new_attempts = ($user_data['failed_attempts'] + 1);
          $locked_until = null;
          
          // Lock account if reached max attempts
          if ($new_attempts >= $MAX_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', time() + $COOLDOWN_PERIOD);
            $error = "Too many failed attempts. Account locked for 20 minutes.";
          } else {
            $error = "Invalid username or password";
          }
          
          // Update database failed attempts
          $update_stmt = $conn->prepare("UPDATE admin_user SET failed_attempts = ?, locked_until = ? WHERE id = ?");
          $update_stmt->bind_param("isi", $new_attempts, $locked_until, $user_data['id']);
          $update_stmt->execute();
          $update_stmt->close();
          
          usleep(rand(200000, 500000));
          error_log("Failed admin login attempt for username: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR'] . " (Attempt " . $_SESSION['login_attempts'] . "/$MAX_ATTEMPTS)");
        }
      } else {
        // Username not found - increment session counter only
        $_SESSION['login_attempts']++;
        $_SESSION['last_login_attempt'] = time();
        usleep(rand(200000, 500000));
        $error = "Invalid username or password";
        error_log("Failed admin login attempt for non-existent username: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
      }
      $stmt->close();
    }
  }
}

// Generate new CSRF token for each page load
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Calculate remaining cooldown time for display
$remaining_cooldown = 0;
if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS && (time() - $_SESSION['last_login_attempt']) < $COOLDOWN_PERIOD) {
  $remaining_cooldown = $COOLDOWN_PERIOD - (time() - $_SESSION['last_login_attempt']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Login | REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL.$favicon ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>admin/assets/css/login.css">
</head>

<body>
  <div class="login-wrap">

    <!-- Left Panel -->
    <div class="login-left">
      <div class="brand-icon"><i class="fas fa-heartbeat"></i></div>
      <h2>REJUVENATE</h2>
      <p>Digital Health Management System — Super Admin Portal</p>
      <div class="accent-bar"></div>
      <div class="left-features">
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-school"></i></div><span>Manage Schools & Approvals</span>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-users"></i></div><span>Doctor & Patient Management</span>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-chart-bar"></i></div><span>Analytics & Reports</span>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-id-card"></i></div><span>ABHA Integrations</span>
        </div>
      </div>
    </div>

    <!-- Right Panel -->
    <div class="login-right">
      <div class="logo-wrap">
        <div class="site-logo"><i class="fas fa-user-shield"></i></div>
        <h3>Admin Login</h3>
        <div class="accent-bar"></div>
        <p class="sub mt-2">Sign in with your super admin credentials</p>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger w-100 mb-3 p-3">
          <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" action="" class="w-100">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
            <input type="text" class="form-control" id="username" name="username"
              required placeholder="Enter admin username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password"
              required placeholder="Enter your password">
            <button type="button" class="toggle-pass" onclick="togglePassword()">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="remember_me" id="remember">
            <label class="form-check-label" for="remember" style="font-size:.82rem;color:#6b7280;">Remember me</label>
          </div>
          <a href="<?= BASE_URL ?>admin/auth/forgot-password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
        </button>
      </form>

      <div class="mt-4 text-center" style="font-size:.75rem;color:#9ca3af;">
        <i class="fas fa-shield-alt me-1"></i> Secure connection &nbsp;|&nbsp;
        <a href="../../school-login.php" style="color:#0C74C5;text-decoration:none;">School Login</a>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePassword() {
      const p = document.getElementById('password');
      const icon = document.getElementById('toggleIcon');
      p.type = p.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }
  </script>
</body>

</html>