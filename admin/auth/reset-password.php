<?php
session_start();
require_once __DIR__ . '/../../util/mail_config.php';
require_once __DIR__ . '/../../util/function.php';
require __DIR__ . '/../db-conn.php';
// Already logged in
if (!empty($_SESSION['admin_logged_in'])) {
  header('Location: ../index.php');
  exit();
}

$token    = trim($_GET['token'] ?? '');
$success  = $error = '';
$admin    = null;
$tokenOk  = false;

// ── Validate token ──────────────────────────────────────────────────────────
if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
  $error = 'Invalid or missing reset link. Please request a new one.';
} else {
  $stmt = $conn->prepare(
    "SELECT id, first_name, last_name, email, status, reset_token_expiry
         FROM admin_user
         WHERE reset_token = ? AND reset_token_expiry > NOW() AND status = 'active'
         LIMIT 1"
  );
  $stmt->bind_param('s', $token);
  $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$admin) {
    $error = 'This reset link is invalid or has expired. Please <a href="forgot-password.php">request a new one</a>.';
  } else {
    $tokenOk = true;
  }
}

// ── CSRF ───────────────────────────────────────────────────────────────────
if (empty($_SESSION['rp_csrf'])) {
  $_SESSION['rp_csrf'] = bin2hex(random_bytes(32));
}

// ── POST handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenOk) {

  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['rp_csrf']) {
    $error = 'Invalid request. Please try again.';
    $tokenOk = false;
  } else {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    $pwErrors = [];
    if (strlen($password) < 8) {
      $pwErrors[] = 'At least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
      $pwErrors[] = 'At least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
      $pwErrors[] = 'At least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
      $pwErrors[] = 'At least one number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
      $pwErrors[] = 'At least one special character';
    }
    if ($password !== $password2) {
      $pwErrors[] = 'Passwords do not match';
    }

    if ($pwErrors) {
      $error = 'Password requirements not met: ' . implode(', ', $pwErrors) . '.';
    } else {
      // Re-verify token is still valid (race-condition safety)
      $stmt = $conn->prepare(
        "SELECT id, email, first_name, last_name FROM admin_user
                 WHERE reset_token = ? AND reset_token_expiry > NOW() AND status = 'active'
                 LIMIT 1"
      );
      $stmt->bind_param('s', $token);
      $stmt->execute();
      $freshAdmin = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if (!$freshAdmin) {
        $error   = 'Your reset link expired. Please <a href="forgot-password.php">request a new one</a>.';
        $tokenOk = false;
      } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd  = $conn->prepare(
          "UPDATE admin_user
                     SET password = ?, reset_token = NULL, reset_token_expiry = NULL,
                         password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL
                     WHERE id = ?"
        );
        $upd->bind_param('si', $hash, $freshAdmin['id']);
        $upd->execute();
        $upd->close();

        // Invalidate all existing sessions for this admin
        session_destroy();
        session_start();

        $name = trim(($freshAdmin['first_name'] ?? '') . ' ' . ($freshAdmin['last_name'] ?? ''))
          ?: $freshAdmin['email'];
        send_password_changed_email($freshAdmin['email'], $name);

        error_log("Admin password reset completed for: {$freshAdmin['email']} from IP: " . $_SERVER['REMOTE_ADDR']);

        $success  = 'Your password has been reset successfully. You can now log in.';
        $tokenOk  = false;   // hide form
      }
    }
  }
}

$favicon = get_favicon();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Reset Password | Admin — REJUVENATE</title>
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL . $favicon ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>admin/assets/css/login.css">
  <style>
    .step-icon {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.7rem;
      margin: 0 auto 18px;
    }

    .step-icon.lock {
      background: #eff6ff;
      color: #0C74C5;
    }

    .step-icon.check {
      background: #f0fdf4;
      color: #16a34a;
    }

    .step-icon.err {
      background: #fef2f2;
      color: #dc2626;
    }

    .pw-meter {
      height: 5px;
      border-radius: 3px;
      transition: .3s;
      margin-top: 8px;
    }

    .pw-label {
      font-size: .74rem;
      margin-top: 4px;
      font-weight: 600;
    }

    .req-list {
      list-style: none;
      padding: 0;
      margin: 8px 0 0;
      font-size: .78rem;
    }

    .req-list li {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 2px 0;
      color: #9ca3af;
      transition: .2s;
    }

    .req-list li.ok {
      color: #16a34a;
    }

    .req-list li.bad {
      color: #dc2626;
    }

    .req-list li i {
      width: 14px;
    }

    .steps-visual {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: center;
      margin-bottom: 22px;
    }

    .step-dot {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .72rem;
      font-weight: 700;
    }

    .step-dot.active {
      background: #0C74C5;
      color: #fff;
    }

    .step-dot.done {
      background: #16a34a;
      color: #fff;
    }

    .step-dot.idle {
      background: #e5e7eb;
      color: #9ca3af;
    }

    .step-line {
      flex: 1;
      height: 2px;
      background: #e5e7eb;
    }

    .step-line.done {
      background: #16a34a;
    }

    .alert-danger a {
      color: #991b1b;
    }

    .expires-badge {
      background: #fff7ed;
      border: 1px solid #fed7aa;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: .78rem;
      color: #9a3412;
      margin-bottom: 16px;
      width: 100%;
    }
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
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-lock"></i></div><span>Strong Password Required</span>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-check-circle"></i></div><span>One-time Use Link</span>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-clock"></i></div><span>Link valid for 1 hour</span>
        </div>
        <div class="feat">
          <div class="feat-icon"><i class="fas fa-bell"></i></div><span>Email Notification Sent</span>
        </div>
      </div>
    </div>

    <!-- Right Panel -->
    <div class="login-right">

      <?php if ($success): ?>
        <!-- ── Password reset success ── -->
        <div class="steps-visual">
          <div class="step-dot done"><i class="fas fa-check" style="font-size:.6rem;"></i></div>
          <div class="step-line done"></div>
          <div class="step-dot done"><i class="fas fa-check" style="font-size:.6rem;"></i></div>
          <div class="step-line done"></div>
          <div class="step-dot done"><i class="fas fa-check" style="font-size:.6rem;"></i></div>
        </div>

        <div class="step-icon check"><i class="fas fa-check-circle"></i></div>
        <div class="logo-wrap">
          <h3>Password Reset!</h3>
          <div class="accent-bar"></div>
          <p class="sub mt-2">Your admin password has been updated</p>
        </div>

        <div class="alert w-100 mb-4 p-3" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;font-size:.85rem;">
          <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <a href="login.php" class="btn-login text-center text-decoration-none">
          <i class="fas fa-sign-in-alt me-2"></i>Go to Login
        </a>

      <?php elseif (!$tokenOk): ?>
        <!-- ── Invalid / expired token ── -->
        <div class="step-icon err"><i class="fas fa-link-slash"></i></div>
        <div class="logo-wrap">
          <h3>Link Invalid</h3>
          <div class="accent-bar"></div>
          <p class="sub mt-2">This reset link cannot be used</p>
        </div>

        <div class="alert alert-danger w-100 mb-4 p-3">
          <i class="fas fa-exclamation-circle me-2"></i>
          <?= $error ?: 'The reset link is invalid or has already been used.' ?>
        </div>

        <a href="forgot-password.php" class="btn-login text-center text-decoration-none">
          <i class="fas fa-redo me-2"></i>Request New Link
        </a>

        <div class="mt-3 text-center">
          <a href="login.php" style="font-size:.83rem;color:#6b7280;text-decoration:none;">
            <i class="fas fa-arrow-left me-1"></i>Back to Login
          </a>
        </div>

      <?php else: ?>
        <!-- ── Set new password form ── -->
        <div class="steps-visual">
          <div class="step-dot done"><i class="fas fa-check" style="font-size:.6rem;"></i></div>
          <div class="step-line done"></div>
          <div class="step-dot done"><i class="fas fa-check" style="font-size:.6rem;"></i></div>
          <div class="step-line"></div>
          <div class="step-dot active">3</div>
        </div>

        <div class="step-icon lock"><i class="fas fa-lock-open"></i></div>
        <div class="logo-wrap">
          <h3>Set New Password</h3>
          <div class="accent-bar"></div>
          <p class="sub mt-2">
            Resetting for
            <strong><?= htmlspecialchars(
                      trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: $admin['email'],
                      ENT_QUOTES,
                      'UTF-8'
                    ) ?></strong>
          </p>
        </div>

        <!-- Token expiry notice -->
        <?php
        $expiresAt = strtotime($admin['reset_token_expiry']);
        $minsLeft  = max(1, ceil(($expiresAt - time()) / 60));
        ?>
        <div class="expires-badge">
          <i class="fas fa-clock me-1"></i>
          This link expires in <strong><?= $minsLeft ?> minute<?= $minsLeft > 1 ? 's' : '' ?></strong>.
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger w-100 mb-3 p-3">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
          </div>
        <?php endif; ?>

        <form method="post" action="?token=<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" class="w-100" id="rpForm">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['rp_csrf'] ?>">

          <!-- New password -->
          <div class="mb-3">
            <label class="form-label" for="password">New Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" class="form-control" id="password" name="password"
                required autocomplete="new-password" placeholder="Create a strong password"
                oninput="evalPassword(this.value)">
              <button type="button" class="toggle-pass" onclick="togglePass('password','eyeIcon1')">
                <i class="fas fa-eye" id="eyeIcon1"></i>
              </button>
            </div>
            <!-- Strength meter -->
            <div class="pw-meter bg-secondary" id="pwMeter"></div>
            <div class="pw-label text-secondary" id="pwLabel">Enter a password</div>
            <!-- Requirements checklist -->
            <ul class="req-list" id="reqList">
              <li id="req-len"><i class="fas fa-circle"></i> At least 8 characters</li>
              <li id="req-upper"><i class="fas fa-circle"></i> Uppercase letter</li>
              <li id="req-lower"><i class="fas fa-circle"></i> Lowercase letter</li>
              <li id="req-num"><i class="fas fa-circle"></i> Number</li>
              <li id="req-special"><i class="fas fa-circle"></i> Special character</li>
            </ul>
          </div>

          <!-- Confirm password -->
          <div class="mb-4">
            <label class="form-label" for="password2">Confirm Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" class="form-control" id="password2" name="password2"
                required autocomplete="new-password" placeholder="Re-enter your password"
                oninput="checkMatch()">
              <button type="button" class="toggle-pass" onclick="togglePass('password2','eyeIcon2')">
                <i class="fas fa-eye" id="eyeIcon2"></i>
              </button>
            </div>
            <div id="matchMsg" class="form-text" style="font-size:.78rem;"></div>
          </div>

          <button type="submit" class="btn-login" id="submitBtn" disabled>
            <i class="fas fa-shield-alt me-2"></i>Reset Password
          </button>
        </form>

      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePass(id, iconId) {
      const el = document.getElementById(id);
      const icon = document.getElementById(iconId);
      el.type = el.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }

    const checks = {
      len: v => v.length >= 8,
      upper: v => /[A-Z]/.test(v),
      lower: v => /[a-z]/.test(v),
      num: v => /[0-9]/.test(v),
      special: v => /[^A-Za-z0-9]/.test(v),
    };

    function evalPassword(val) {
      let score = 0;
      for (const [k, fn] of Object.entries(checks)) {
        const el = document.getElementById('req-' + k);
        const ok = fn(val);
        el.className = ok ? 'ok' : (val.length ? 'bad' : '');
        el.querySelector('i').className = ok ? 'fas fa-check-circle' : (val.length ? 'fas fa-times-circle' : 'fas fa-circle');
        if (ok) score++;
      }
      const meter = document.getElementById('pwMeter');
      const label = document.getElementById('pwLabel');
      const pct = (score / 5) * 100;
      meter.style.width = pct + '%';
      const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
      const labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
      meter.style.background = colors[score - 1] || '#e5e7eb';
      label.textContent = val.length ? labels[score - 1] : 'Enter a password';
      label.style.color = colors[score - 1] || '#9ca3af';
      checkMatch();
    }

    function checkMatch() {
      const p1 = document.getElementById('password').value;
      const p2 = document.getElementById('password2').value;
      const msg = document.getElementById('matchMsg');
      const btn = document.getElementById('submitBtn');
      const allOk = Object.values(checks).every(fn => fn(p1));

      if (!p2) {
        msg.textContent = '';
        msg.style.color = '';
      } else if (p1 === p2) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#16a34a';
      } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#dc2626';
      }
      btn.disabled = !(allOk && p1 === p2 && p2.length > 0);
    }

    <?php if ($tokenOk): ?>
      // Countdown timer
      let expiresIn = <?= $expiresAt - time() ?>;
      const badge = document.querySelector('.expires-badge strong');
      const timer = setInterval(() => {
        expiresIn--;
        if (expiresIn <= 0) {
          clearInterval(timer);
          document.getElementById('rpForm').innerHTML =
            '<div class="alert alert-danger"><i class="fas fa-clock me-2"></i>This link has expired. <a href="forgot-password.php">Request a new one</a>.</div>';
        } else {
          const m = Math.floor(expiresIn / 60);
          const s = expiresIn % 60;
          badge.textContent = m + ':' + String(s).padStart(2, '0');
        }
      }, 1000);
    <?php endif; ?>
  </script>
</body>

</html>