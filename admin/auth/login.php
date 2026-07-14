<?php
session_start();
include_once("../../util/function.php");
$favicon = get_favicon();

require __DIR__ . '/../db-conn.php';
require_once __DIR__ . '/../../lib/JWT.php';
require_once __DIR__ . '/../../lib/AuditLogger.php';
if (!defined('JWT_SECRET')) {
  define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// ── If already holding a valid JWT, skip straight to the dashboard ──
if (JWT_SECRET && !empty($_COOKIE['rdh_admin_token'])) {
  try {
    $p = JWT::verify($_COOKIE['rdh_admin_token'], JWT_SECRET);
    if (($p['role'] ?? '') === 'admin') {
      header("Location: ../index.php");
      exit();
    }
  } catch (RuntimeException $e) { /* expired/invalid — fall through to login form */ }
}

// ── IP-based rate limit — survives cleared cookies/sessions ──
// Independent of the per-account lock below: this throttles an IP hammering
// many different usernames, which a per-account lock alone can't catch.
$IP_MAX_ATTEMPTS  = 10;
$IP_WINDOW_SECS   = 900; // 15 minutes

$rl = $conn->prepare("SELECT COUNT(*) as c FROM login_rate_limits
    WHERE entity_type='admin' AND ip_address=? AND success=0 AND attempted_at > (NOW() - INTERVAL ? SECOND)");
$rl->bind_param('si', $ip, $IP_WINDOW_SECS);
$rl->execute();
$ip_failures = (int) $rl->get_result()->fetch_assoc()['c'];
$ip_blocked  = $ip_failures >= $IP_MAX_ATTEMPTS;

$error = '';
if ($ip_blocked) {
  $error = "Too many failed login attempts from this network. Please try again in a few minutes.";
}

// Per-account lock configuration (mirrors the DB columns already on admin_user)
$MAX_ATTEMPTS    = 4;
$COOLDOWN_PERIOD = 1200; // 20 minutes

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$ip_blocked) {
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
      $stmt = $conn->prepare("SELECT id, username, password, status, role, locked_until, failed_attempts FROM admin_user WHERE username = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();
      $user_data = $result->fetch_assoc();

      $logger = new AuditLogger($conn);

      if ($user_data) {
        // Check if account is locked
        if ($user_data['status'] !== 'active' || ($user_data['locked_until'] && strtotime($user_data['locked_until']) > time())) {
          usleep(rand(200000, 500000));
          $error = ($user_data['status'] !== 'active')
            ? "This account is not active. Contact a super admin."
            : "Account is temporarily locked. Please try again later.";
          _record_attempt($conn, $ip, $username, false);
          $logger->logAuthAttempt($username, 'password', false, $user_data['id'], 'admin');
        } elseif (password_verify($password, $user_data['password'])) {
          // ── Successful login — issue JWT ──
          session_regenerate_id(true);

          $update_stmt = $conn->prepare("UPDATE admin_user SET failed_attempts = 0, locked_until = NULL, last_login = NOW(), last_login_ip = ?, password_changed_at = COALESCE(password_changed_at, NOW()) WHERE id = ?");
          $update_stmt->bind_param("si", $ip, $user_data['id']);
          $update_stmt->execute();
          $update_stmt->close();

          $payload = [
            'sub'        => (int) $user_data['id'],
            'role'       => 'admin',
            'username'   => $user_data['username'],
            'admin_role' => $user_data['role'],
          ];
          $accessToken  = JWT::issue($payload, JWT_SECRET, 900);      // 15 min
          $refreshToken = bin2hex(random_bytes(32));
          $refreshHash  = hash('sha256', $refreshToken);
          $refreshExp   = date('Y-m-d H:i:s', strtotime('+7 days'));

          // Revoke any previous refresh tokens for this admin
          $conn->query("UPDATE jwt_refresh_tokens SET revoked=1, revoked_at=NOW() WHERE entity_type='admin' AND entity_id=" . (int) $user_data['id']);

          $ins = $conn->prepare("INSERT INTO jwt_refresh_tokens (entity_type,entity_id,token_hash,expires_at,ip_address,user_agent) VALUES ('admin',?,?,?,?,?)");
          $ins->bind_param('issss', $user_data['id'], $refreshHash, $refreshExp, $ip, $ua);
          $ins->execute();

          // "Remember me" unchecked → refresh cookie dies with the browser session
          // instead of persisting the full 7 days.
          $remember = isset($_POST['remember_me']);
          $refresh_cookie_expiry = $remember ? (time() + 604800) : 0;

          $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
          setcookie('rdh_admin_token', $accessToken, [
            'expires' => time() + 900, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict',
          ]);
          setcookie('rdh_admin_refresh', $refreshToken, [
            'expires' => $refresh_cookie_expiry, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict',
          ]);

          // Populate session too, so this same request's redirect target loads instantly
          $_SESSION['admin_logged_in'] = true;
          $_SESSION['admin_id']        = $user_data['id'];
          $_SESSION['admin_user']      = $user_data['username'];
          $_SESSION['admin_role']      = $user_data['role'];

          _record_attempt($conn, $ip, $username, true);
          $logger->logAuthAttempt($username, 'password', true, $user_data['id'], 'admin');

          $stmt->close();
          $conn->close();
          header("Location: ../index.php");
          exit();
        } else {
          // Failed password — increment per-account attempts
          $new_attempts = ($user_data['failed_attempts'] + 1);
          $locked_until = null;

          if ($new_attempts >= $MAX_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', time() + $COOLDOWN_PERIOD);
            $error = "Too many failed attempts. Account locked for 20 minutes.";
          } else {
            $error = "Invalid username or password";
          }

          $update_stmt = $conn->prepare("UPDATE admin_user SET failed_attempts = ?, locked_until = ? WHERE id = ?");
          $update_stmt->bind_param("isi", $new_attempts, $locked_until, $user_data['id']);
          $update_stmt->execute();
          $update_stmt->close();

          _record_attempt($conn, $ip, $username, false);
          $logger->logAuthAttempt($username, 'password', false, $user_data['id'], 'admin');
          usleep(rand(200000, 500000));
        }
      } else {
        // Username not found
        _record_attempt($conn, $ip, $username, false);
        $logger->logAuthAttempt($username, 'password', false, 0, 'admin');
        usleep(rand(200000, 500000));
        $error = "Invalid username or password";
      }
      $stmt->close();
    }
  }
}

function _record_attempt(mysqli $conn, string $ip, string $identifier, bool $success): void
{
  $stmt = $conn->prepare("INSERT INTO login_rate_limits (entity_type, ip_address, identifier, success) VALUES ('admin', ?, ?, ?)");
  $s = $success ? 1 : 0;
  $stmt->bind_param('ssi', $ip, $identifier, $s);
  $stmt->execute();
}

// Generate new CSRF token for each page load
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Login | REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
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

      <?php if ($error): ?>
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
            <input type="text" class="form-control" id="username" name="username" required
              placeholder="Enter admin username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              <?= $ip_blocked ? 'disabled' : '' ?>>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <input type="password" class="form-control" id="password" name="password" required
              placeholder="Enter your password" <?= $ip_blocked ? 'disabled' : '' ?>>
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

        <button type="submit" class="btn-login" <?= $ip_blocked ? 'disabled' : '' ?>>
          <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
        </button>
      </form>

      <div class="mt-4 text-center" style="font-size:.75rem;color:#9ca3af;">
        <i class="fas fa-shield-alt me-1"></i> Secured with JWT · ABDM compliant &nbsp;|&nbsp;
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
