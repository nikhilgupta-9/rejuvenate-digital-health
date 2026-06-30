<?php
session_start();
require_once __DIR__ . '/../../util/mail_config.php';
require_once __DIR__ . '/../../util/function.php';
require_once __DIR__ . '/../db-conn.php';

// Already logged in
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ../index.php');
    exit();
}

$success = $error = '';
$step = 'form';   // 'form' | 'sent'

// ── CSRF ───────────────────────────────────────────────────────────────────
if (empty($_SESSION['fp_csrf'])) {
    $_SESSION['fp_csrf'] = bin2hex(random_bytes(32));
}

// ── Rate-limit (max 3 attempts per 15 min per session) ─────────────────────
if (!isset($_SESSION['fp_attempts'])) {
    $_SESSION['fp_attempts']    = 0;
    $_SESSION['fp_attempt_time'] = 0;
}
$RATE_LIMIT   = 3;
$RATE_WINDOW  = 900; // 15 min
$rate_locked  = (
    $_SESSION['fp_attempts'] >= $RATE_LIMIT &&
    (time() - $_SESSION['fp_attempt_time']) < $RATE_WINDOW
);

// ── POST handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    if ($rate_locked) {
        $mins  = ceil(($RATE_WINDOW - (time() - $_SESSION['fp_attempt_time'])) / 60);
        $error = "Too many requests. Please wait {$mins} minute(s) before trying again.";
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['fp_csrf']) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $_SESSION['fp_attempts']++;
            $_SESSION['fp_attempt_time'] = time();

            // Look up admin by email
            $stmt = $conn->prepare(
                "SELECT id, first_name, last_name, email, status FROM admin_user WHERE email = ? LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Always show success to prevent user enumeration
            $step = 'sent';

            if ($admin && $admin['status'] === 'active') {
                $name  = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: $admin['email'];
                $token = bin2hex(random_bytes(32));   // 64-char hex
                $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                $upd = $conn->prepare(
                    "UPDATE admin_user SET reset_token = ?, reset_token_expiry = ? WHERE id = ?"
                );
                $upd->bind_param('ssi', $token, $expiry, $admin['id']);
                $upd->execute();
                $upd->close();

                $resetLink = BASE_URL . 'admin/auth/reset-password.php?token=' . $token;
                send_password_reset_email($email, $name, $resetLink, 60);

                error_log("Admin password reset requested for: {$email} from IP: " . $_SERVER['REMOTE_ADDR']);
            }

            // Rotate CSRF after successful submission
            $_SESSION['fp_csrf'] = bin2hex(random_bytes(32));
        }
    }
}

$favicon = get_favicon();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | Admin — REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>admin/assets/css/login.css">
  <style>
    .step-icon {
      width: 64px; height: 64px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.7rem; margin: 0 auto 18px;
    }
    .step-icon.key   { background: #eff6ff; color: #0C74C5; }
    .step-icon.check { background: #f0fdf4; color: #16a34a; }
    .back-link { font-size: .83rem; color: #6b7280; text-decoration: none; }
    .back-link:hover { color: #0C74C5; }
    .info-box {
      background: #f0f9ff; border: 1px solid #bae6fd;
      border-radius: 10px; padding: 14px 16px; font-size: .83rem; color: #0369a1;
    }
    .steps-visual {
      display: flex; gap: 8px; align-items: center;
      justify-content: center; margin-bottom: 22px;
    }
    .step-dot {
      width: 28px; height: 28px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .72rem; font-weight: 700;
    }
    .step-dot.active { background: #0C74C5; color: #fff; }
    .step-dot.done   { background: #16a34a; color: #fff; }
    .step-dot.idle   { background: #e5e7eb; color: #9ca3af; }
    .step-line { flex: 1; height: 2px; background: #e5e7eb; }
    .step-line.done { background: #16a34a; }
  </style>
</head>
<body>
<div class="login-wrap">

  <!-- Left Panel -->
  <div class="login-left">
    <div class="brand-icon"><i class="fas fa-heartbeat"></i></div>
    <h2>REJUVENATE</h2>
    <p>Digital Health Management System — Super Admin Portal</p>
    <div class="accent-bar"></div>
    <div class="left-features mt-4">
      <div class="feat"><div class="feat-icon"><i class="fas fa-lock"></i></div><span>Secure Reset Process</span></div>
      <div class="feat"><div class="feat-icon"><i class="fas fa-envelope"></i></div><span>Email Verification</span></div>
      <div class="feat"><div class="feat-icon"><i class="fas fa-clock"></i></div><span>Link valid for 1 hour</span></div>
      <div class="feat"><div class="feat-icon"><i class="fas fa-shield-alt"></i></div><span>Admin-only Access</span></div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="login-right">

    <?php if ($step === 'sent'): ?>
      <!-- ── Success state ── -->
      <div class="step-icon check"><i class="fas fa-paper-plane"></i></div>
      <div class="logo-wrap">
        <h3>Check Your Email</h3>
        <div class="accent-bar"></div>
        <p class="sub mt-2">Reset instructions have been sent</p>
      </div>

      <div class="info-box w-100 mb-4">
        <i class="fas fa-info-circle me-1"></i>
        If an admin account with that email exists, a password reset link has been sent.
        The link will expire in <strong>1 hour</strong>.
        <hr class="my-2" style="border-color:#bae6fd;">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Didn't receive the email? Check your spam folder or
        <a href="forgot-password.php" style="color:#0369a1;">try again</a>.
      </div>

      <div class="w-100 d-grid gap-2">
        <a href="forgot-password.php" class="btn-login text-center text-decoration-none">
          <i class="fas fa-redo me-2"></i>Send Again
        </a>
      </div>

      <div class="mt-4 text-center">
        <a href="login.php" class="back-link">
          <i class="fas fa-arrow-left me-1"></i>Back to Login
        </a>
      </div>

    <?php else: ?>
      <!-- ── Form state ── -->

      <!-- Steps visual -->
      <div class="steps-visual">
        <div class="step-dot active">1</div>
        <div class="step-line"></div>
        <div class="step-dot idle">2</div>
        <div class="step-line"></div>
        <div class="step-dot idle">3</div>
      </div>

      <div class="step-icon key"><i class="fas fa-key"></i></div>
      <div class="logo-wrap">
        <h3>Forgot Password?</h3>
        <div class="accent-bar"></div>
        <p class="sub mt-2">Enter your admin email to receive a reset link</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger w-100 mb-3 p-3">
          <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($rate_locked): ?>
        <div class="alert alert-warning w-100 mb-3 p-3" style="font-size:.85rem;">
          <i class="fas fa-clock me-2"></i>
          Too many attempts. Please wait
          <?= ceil(($RATE_WINDOW - (time() - $_SESSION['fp_attempt_time'])) / 60) ?> minute(s).
        </div>
      <?php endif; ?>

      <form method="post" action="" class="w-100" <?= $rate_locked ? 'style="pointer-events:none;opacity:.6;"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['fp_csrf'] ?>">

        <div class="mb-4">
          <label class="form-label" for="email">Admin Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email"
                   required autofocus
                   placeholder="Enter your admin email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-text" style="font-size:.78rem;color:#9ca3af;">
            A secure reset link will be sent to this address.
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-paper-plane me-2"></i>Send Reset Link
        </button>
      </form>

      <div class="mt-4 text-center">
        <a href="login.php" class="back-link">
          <i class="fas fa-arrow-left me-1"></i>Back to Login
        </a>
      </div>

    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
