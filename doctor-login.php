<?php
require_once "config/connect.php";
require_once "util/function.php";
require_once "lib/JWT.php";

// If already logged in via JWT, redirect directly
$secret = defined('JWT_SECRET') ? JWT_SECRET : '';
if ($secret && !empty($_COOKIE['rdh_doctor_token'])) {
    try {
        $p = JWT::verify($_COOKIE['rdh_doctor_token'], $secret);
        if (($p['role'] ?? '') === 'doctor') {
            header("Location: " . BASE_URL . "doctor/doctor-dashboard.php");
            exit();
        }
    } catch (RuntimeException $e) { /* expired/invalid — show login */ }
}

$contact = contact_us();
$logo    = get_header_logo();

// Error message from redirect
$err_map = [
    'session_expired' => 'Your session has expired. Please log in again.',
    'unauthorized'    => 'Access denied. Please log in as a doctor.',
    'config'          => 'Server configuration error. Please contact support.',
];
$err_key = $_GET['err'] ?? '';
$flash   = $err_map[$err_key] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Doctor Login — REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    .login-wrap { max-width: 480px; margin: 60px auto; }
    .login-card { background:#fff; border-radius:16px; box-shadow:0 4px 32px rgba(0,0,0,.10); padding:40px 36px; }
    .login-logo  { text-align:center; margin-bottom:24px; }
    .login-logo img { max-height:60px; }
    .login-tabs .nav-link          { color:#555; border-radius:8px 8px 0 0; }
    .login-tabs .nav-link.active   { color:#0c74c5; font-weight:600; border-bottom:2px solid #0c74c5; }
    .hpr-badge { background:#e8f4fd; border:1px solid #b6d9f5; border-radius:8px; padding:10px 14px; font-size:13px; color:#1565c0; }
    .btn-doctor { background:#0c74c5; border-color:#0c74c5; font-weight:600; letter-spacing:.3px; }
    .btn-doctor:hover { background:#0a5fa8; border-color:#0a5fa8; }
  </style>
</head>
<body>
  <?php include("header.php") ?>

  <section class="section-padding fix">
    <div class="container">
      <div class="login-wrap">
        <div class="login-card">
          <div class="login-logo">
            <img src="<?= BASE_URL . htmlspecialchars($logo) ?>" alt="Rejuvenate Digital Health">
          </div>
          <h4 class="text-center mb-1 fw-bold">Doctor Login</h4>
          <p class="text-center text-muted mb-4" style="font-size:13px;">Access your ABHA-compliant doctor panel</p>

          <?php if ($flash): ?>
          <div class="alert alert-warning py-2 mb-3" role="alert">
            <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($flash) ?>
          </div>
          <?php endif; ?>

          <!-- Login Tabs -->
          <ul class="nav login-tabs mb-3 border-bottom" role="tablist">
            <li class="nav-item">
              <button class="nav-link active px-3 py-2" data-bs-toggle="tab" data-bs-target="#tab-email" type="button">
                <i class="fa fa-envelope me-1"></i> Email / Phone
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link px-3 py-2" data-bs-toggle="tab" data-bs-target="#tab-hpr" type="button">
                <i class="fa fa-id-card me-1"></i> HPR ID
              </button>
            </li>
          </ul>

          <!-- Error/Success banner -->
          <div id="login-alert" class="d-none mb-3"></div>

          <div class="tab-content">
            <!-- Email / Phone Login -->
            <div class="tab-pane fade show active" id="tab-email">
              <form id="form-email" novalidate>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Email or Mobile</label>
                  <input type="text" class="form-control" name="identifier"
                         placeholder="doctor@example.com or 9XXXXXXXXX" required
                         autocomplete="username">
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" name="password"
                           placeholder="Enter your password" required autocomplete="current-password"
                           id="pw-email">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('pw-email', this)">
                      <i class="fa fa-eye"></i>
                    </button>
                  </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember-email">
                    <label class="form-check-label" for="remember-email">Remember me</label>
                  </div>
                  <a href="<?= BASE_URL ?>forgot-password/" style="font-size:13px;">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-doctor btn-primary w-100" id="btn-email">
                  <span class="spinner-border spinner-border-sm d-none me-1" id="spin-email"></span>
                  Login
                </button>
              </form>
            </div>

            <!-- HPR ID Login -->
            <div class="tab-pane fade" id="tab-hpr">
              <div class="hpr-badge mb-3">
                <i class="fa fa-info-circle me-1"></i>
                Login with your <strong>HPR Health Professional ID</strong> (format: <code>XX-XXXX-XXXX-XXXX</code>) as registered on
                <strong>hpr.abdm.gov.in</strong>
              </div>
              <form id="form-hpr" novalidate>
                <div class="mb-3">
                  <label class="form-label fw-semibold">HPR ID</label>
                  <input type="text" class="form-control" name="identifier"
                         placeholder="27-1234-5678-9012"
                         pattern="\d{2}-\d{4}-\d{4}-\d{4}" required>
                  <div class="form-text">Your 14-digit HPR registration number</div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" name="password"
                           placeholder="Enter your password" required id="pw-hpr">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('pw-hpr', this)">
                      <i class="fa fa-eye"></i>
                    </button>
                  </div>
                </div>
                <button type="submit" class="btn btn-doctor btn-primary w-100" id="btn-hpr">
                  <span class="spinner-border spinner-border-sm d-none me-1" id="spin-hpr"></span>
                  Login with HPR ID
                </button>
              </form>
            </div>
          </div><!-- /.tab-content -->

          <hr class="my-3">
          <p class="text-center mb-0" style="font-size:13px;">
            New doctor? <a href="<?= BASE_URL ?>doctor-signup/">Register here</a>
          </p>
        </div><!-- /.login-card -->

        <p class="text-center mt-3 text-muted" style="font-size:12px;">
          This portal complies with <strong>ABDM / ABHA</strong> guidelines by NHA India.
          All sessions are secured with JWT.
        </p>
      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
  <script src="<?= BASE_URL ?>assets/js/jquery-3-6-0.min.js"></script>
  <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
  <script>
  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      inp.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  }

  function showAlert(msg, type) {
    const el = document.getElementById('login-alert');
    el.className = `alert alert-${type} py-2`;
    el.innerHTML = `<i class="fa fa-${type === 'danger' ? 'times' : 'check'}-circle me-1"></i> ${msg}`;
    el.classList.remove('d-none');
    window.scrollTo(0, 0);
  }

  function submitLogin(formEl, spinId, btnId) {
    const fd = new FormData(formEl);
    const identifier = fd.get('identifier').trim();
    const password   = fd.get('password');

    if (!identifier || !password) {
      showAlert('Please fill in all fields.', 'warning');
      return;
    }

    const spin = document.getElementById(spinId);
    const btn  = document.getElementById(btnId);
    spin.classList.remove('d-none');
    btn.disabled = true;

    fetch('<?= BASE_URL ?>doctor/auth/login-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier, password }),
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showAlert('Login successful! Redirecting…', 'success');
        setTimeout(() => { window.location.href = data.redirect; }, 600);
      } else {
        showAlert(data.error || 'Login failed. Please try again.', 'danger');
        spin.classList.add('d-none');
        btn.disabled = false;
      }
    })
    .catch(() => {
      showAlert('Network error. Please try again.', 'danger');
      spin.classList.add('d-none');
      btn.disabled = false;
    });
  }

  document.getElementById('form-email').addEventListener('submit', function(e) {
    e.preventDefault();
    submitLogin(this, 'spin-email', 'btn-email');
  });

  document.getElementById('form-hpr').addEventListener('submit', function(e) {
    e.preventDefault();
    submitLogin(this, 'spin-hpr', 'btn-hpr');
  });
  </script>
</body>
</html>
