<?php
session_start();
include_once "config/connect.php";
include_once "config/abdm.php";
include_once "util/function.php";
require_once __DIR__ . "/lib/Security.php";

// CSRF token for the AJAX login flows (ABHA / Aadhaar / OTP → ajax/*.php)
$csrf_token = Security::csrfToken();

/* ── Already logged-in redirects ── */
if (!empty($_SESSION['logged_in'])) {
  header("Location: " . BASE_URL . "user/user-dashboard.php");
  exit();
}
if (!empty($_SESSION['school_logged_in'])) {
  header("Location: " . BASE_URL . "school/dashboard.php");
  exit();
}
if (!empty($_SESSION['student_logged_in'])) {
  header("Location: " . BASE_URL . "school/student/dashboard.php");
  exit();
}
if (!empty($_SESSION['teacher_logged_in'])) {
  header("Location: " . BASE_URL . "school/teacher/dashboard.php");
  exit();
}
if (!empty($_SESSION['doctor_logged_in'])) {
  header("Location: " . BASE_URL . "doctor/doctor-dashboard.php");
  exit();
}
if (!empty($_SESSION['admin_logged_in'])) {
  // The admin panel is JWT-based — $_SESSION['admin_logged_in'] can linger
  // after the JWT cookies are gone (logout / expiry / different browser).
  // Only bounce to the admin panel if a JWT cookie actually exists,
  // otherwise clear the stale flag so this login form is shown instead of
  // ping-ponging to admin/auth/login.php?err=session_expired.
  if (!empty($_COOKIE['rdh_admin_token']) || !empty($_COOKIE['rdh_admin_refresh'])) {
    header("Location: " . BASE_URL . "admin/index.php");
    exit();
  }
  unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_user'],
    $_SESSION['admin_role']
  );
}

/* ── Remember-me auto login (patient) ── */
if (isset($_COOKIE['remember_token'], $_COOKIE['user_id'])) {
  $s = $conn->prepare("SELECT id,name,email FROM users WHERE id=? AND remember_token=? AND status='Active'");
  $s->bind_param('is', $_COOKIE['user_id'], $_COOKIE['remember_token']);
  $s->execute();
  if ($u = $s->get_result()->fetch_assoc()) {
    $_SESSION['logged_in']  = true;
    $_SESSION['user_id']    = $u['id'];
    $_SESSION['user_name']  = $u['name'];
    $_SESSION['user_email'] = $u['email'];
    header("Location: " . BASE_URL . "user/user-dashboard.php");
    exit();
  }
}

$errors     = $_SESSION['login_errors']     ?? [];
$old_id     = $_SESSION['login_identifier'] ?? '';
$success_msg = $_SESSION['success_message']  ?? '';
unset($_SESSION['login_errors'], $_SESSION['login_identifier'], $_SESSION['success_message']);

$logo = get_header_logo();
$contact = contact_us();
$abdm_on = ABDM_CONFIGURED;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    :root {
      --primary: #0C74C5;
      --primary-dark: #0a5fa3;
      --accent: #02c9b8;
      --ab: #00875a;
      --ink: #1c1e21;
      --muted: #65676b;
      --line: #dddfe2;
      --field-bg: #f5f6f7;
    }

    /* keep the auth page from inheriting any horizontal scroll from the
       global header/footer markup on small screens */
    html,
    body {
      overflow-x: hidden;
    }

    /* ── Page shell ── */
    .rjv-auth {
      background: #f0f2f5;
      min-height: 100vh;
      width: 100%;
      overflow-x: hidden;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    .login-wrap {
      max-width: 400px;
      width: 100%;
      margin: 0 auto;
      padding: 22px 16px 24px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    /* ── Brand ── */
    .login-brand {
      text-align: center;
      margin-bottom: 14px;
    }

    .login-brand h1 {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0;
      line-height: 1.3;
    }

    .login-brand p {
      font-size: .84rem;
      color: var(--muted);
      margin: 2px 0 0;
    }

    /* ── Card ── */
    .login-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, .1), 0 2px 12px rgba(0, 0, 0, .08);
      width: 100%;
      overflow: hidden;
    }

    .login-body {
      padding: 18px 18px 20px;
    }

    /* tighten Bootstrap spacing utilities inside the card */
    .login-body .mb-3 {
      margin-bottom: .7rem !important;
    }

    .login-body .mb-4 {
      margin-bottom: .55rem !important;
    }

    /* ── Fields ── */
    .login-body .form-label {
      font-size: .82rem !important;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 4px;
    }

    .login-body .form-control,
    .login-body .form-select,
    .login-body .input-group-text {
      font-size: 1rem;
      padding: 10px 14px;
      border-radius: 8px;
      border: 1px solid var(--line);
      background: var(--field-bg);
    }

    .login-body .form-control:focus,
    .login-body .form-select:focus {
      background: #fff;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(12, 116, 197, .15);
    }

    .login-body .input-group-text {
      background: #eef0f2;
      color: var(--muted);
      font-weight: 600;
    }

    .login-body small.text-muted {
      font-size: .74rem;
      color: var(--muted) !important;
    }

    /* ── Primary buttons ── */
    .login-body .btn {
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
    }

    .login-body .btn-primary {
      background: var(--primary);
      border-color: var(--primary);
      padding: 10px 16px;
    }

    .login-body .btn-primary:hover,
    .login-body .btn-primary:focus {
      background: var(--primary-dark);
      border-color: var(--primary-dark);
    }

    /* ── Method tabs ── */
    .method-tabs {
      display: flex;
      gap: 6px;
      margin-bottom: 14px;
    }

    .method-tab {
      flex: 1;
      padding: 7px 4px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: #fff;
      font-size: .7rem;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      transition: .15s;
      text-align: center;
      line-height: 1.3;
      white-space: nowrap;
    }

    .method-tab i {
      display: block;
      font-size: 1.05rem;
      margin-bottom: 3px;
    }

    .method-tab:hover {
      background: #f0f2f5;
    }

    .method-tab.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .method-tab.ab-tab.active {
      background: var(--ab);
      border-color: var(--ab);
    }

    /* Detected role badge */
    #roleBadge {
      display: none;
      font-size: .73rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      background: #dbeafe;
      color: #1e40af;
    }

    /* OTP input */
    .otp-big {
      letter-spacing: .3em;
      font-size: 1.35rem !important;
      font-weight: 700;
      text-align: center;
      font-family: inherit;
    }

    /* Password toggle */
    .pw-wrap {
      position: relative;
    }

    .pw-toggle {
      position: absolute;
      right: 8px;
      top: 21px;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #9ca3af;
      cursor: pointer;
      padding: 6px;
      line-height: 1;
      z-index: 5;
    }

    .pw-wrap .form-control {
      padding-right: 42px;
    }

    /* Role hint chips */
    .role-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 16px;
    }

    .role-chip {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: .7rem;
      font-weight: 600;
      border: 1px solid var(--line);
      background: #f5f6f7;
      color: var(--muted);
    }

    .role-chip i {
      margin-right: 3px;
    }

    /* Alert */
    .login-body .alert {
      border-radius: 8px;
      font-size: .85rem;
      padding: 10px 12px;
    }

    /* Divider + footer links */
    .login-sep {
      border: 0;
      border-top: 1px solid var(--line);
      margin: 14px 0 12px;
    }

    .login-footer-links {
      text-align: center;
      font-size: .82rem;
      color: var(--muted);
      margin-top: 12px;
      line-height: 1.6;
    }

    .login-footer-links a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
    }

    .login-footer-links a:hover {
      text-decoration: underline;
    }

    .login-create {
      text-align: center;
    }

    .login-create a {
      display: inline-block;
      background: #42b72a;
      color: #fff;
      font-weight: 700;
      font-size: .92rem;
      padding: 9px 18px;
      border-radius: 8px;
      text-decoration: none;
    }

    .login-create a:hover {
      background: #369a20;
      color: #fff;
    }

    /* ── Standalone shell: topbar + legal footer ── */
    .rjv-auth {
      display: flex;
      flex-direction: column;
    }

    .auth-topbar {
      background: #fff;
      border-bottom: 1px solid var(--line);
      padding: 8px 16px;
      flex-shrink: 0;
    }

    .auth-topbar-inner {
      max-width: 980px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .auth-topbar img {
      height: 30px;
      object-fit: contain;
    }

    .auth-topbar a.auth-help {
      font-size: .82rem;
      font-weight: 600;
      color: var(--primary);
      text-decoration: none;
    }

    .login-wrap {
      flex: 1 0 auto;
    }

    .auth-legal {
      flex-shrink: 0;
      background: #fff;
      border-top: 1px solid var(--line);
      padding: 12px 16px 14px;
      font-size: .75rem;
      color: var(--muted);
    }

    .auth-legal-inner {
      max-width: 980px;
      margin: 0 auto;
      text-align: center;
    }

    .auth-legal a {
      color: var(--muted);
      text-decoration: none;
      margin: 0 7px;
      white-space: nowrap;
    }

    .auth-legal a:hover {
      text-decoration: underline;
    }

    .auth-legal .auth-legal-links {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 2px 0;
      margin-bottom: 5px;
    }

    @media (max-width: 480px) {
      .login-wrap {
        padding: 16px 12px 18px;
      }

      .login-body {
        padding: 16px 14px 18px;
      }

      .method-tab {
        font-size: .68rem;
      }

      .auth-topbar img {
        height: 26px;
      }
    }

    /* very short viewports: keep it inside the screen */
    @media (max-height: 720px) {
      .login-brand {
        margin-bottom: 10px;
      }

      .login-brand p {
        display: none;
      }

      .login-body .mb-3 {
        margin-bottom: .55rem !important;
      }
    }
  </style>
</head>

<body>
  <section class="rjv-auth">

    <!-- Minimal top bar -->
    <div class="auth-topbar">
      <div class="auth-topbar-inner">
        <a href="<?= BASE_URL ?>"><img src="<?= BASE_URL . $logo ?>" alt="REJUVENATE Digital Health"></a>
        <a href="<?= BASE_URL ?>contact-us.php" class="auth-help">Need help?</a>
      </div>
    </div>

    <div class="login-wrap">

      <!-- Brand -->
      <div class="login-brand">
        <h1>Log in to REJUVENATE</h1>
        <p>Access your Digital Health account</p>
      </div>

      <div class="login-card">
        <div class="login-body">

          <!-- Alerts -->
          <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show">
              <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_msg) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>
          <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
              <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>
          <div id="ajaxAlert" class="alert" style="display:none;"></div>

          <!-- Method tabs -->
          <div class="method-tabs">
            <div class="method-tab active" id="tab-pw" onclick="switchMethod('pw')">
              <i class="fas fa-lock"></i>Password
            </div>
            <div class="method-tab" id="tab-otp" onclick="switchMethod('otp')">
              <i class="fas fa-mobile-alt"></i>Mobile OTP
            </div>
            <?php if ($abdm_on): ?>
              <div class="method-tab ab-tab" id="tab-abha" onclick="switchMethod('abha')">
                <i class="fas fa-id-card"></i>ABHA / Aadhaar
              </div>
            <?php endif; ?>
          </div>

          <!-- ══ METHOD 1: PASSWORD ══ -->
          <div id="panel-pw">
            <form method="POST" action="<?= BASE_URL ?>process-login.php" id="pwForm">
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:.85rem;">
                  Email / Mobile / Roll No / Employee ID
                  <span id="roleBadge" class="ms-2"></span>
                </label>
                <input type="text" class="form-control <?= !empty($errors['identifier']) ? 'is-invalid' : '' ?>"
                  name="identifier" id="identifier" placeholder="Email, mobile number, or ID"
                  value="<?= htmlspecialchars($old_id) ?>" autocomplete="username" required>
                <?php if (!empty($errors['identifier'])): ?>
                  <div class="invalid-feedback"><?= htmlspecialchars($errors['identifier']) ?></div>
                <?php endif; ?>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between">
                  <label class="form-label fw-semibold" style="font-size:.85rem;">Password</label>
                  <a href="<?= BASE_URL ?>forgot-password.php" style="font-size:.78rem;color:var(--primary);">Forgot password?</a>
                </div>
                <div class="pw-wrap">
                  <input type="password" class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                    name="password" id="password" placeholder="Enter your password" autocomplete="current-password" required>
                  <button type="button" class="pw-toggle" onclick="togglePw()">
                    <i class="fas fa-eye-slash" id="pwIcon"></i>
                  </button>
                  <?php if (!empty($errors['password'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                  <input class="form-check-input mt-2" type="checkbox" name="remember" id="remember">
                  <label class="form-check-label" for="remember" style="font-size:.83rem;">Remember me</label>
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" style="border-radius:10px;">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
              </button>
            </form>
          </div>

          <!-- ══ METHOD 2: MOBILE OTP ══ -->
          <div id="panel-otp" style="display:none;">

            <!-- Step 1: Enter mobile -->
            <div id="otp-step1">
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:.85rem;">Mobile Number</label>
                <div class="input-group">
                  <span class="input-group-text">+91</span>
                  <input type="text" class="form-control" id="otp_mobile"
                    placeholder="10-digit mobile number" maxlength="10" inputmode="numeric">
                </div>
                <small class="text-muted">OTP will be sent to your registered email (SMS coming soon)</small>
              </div>
              <button class="btn btn-primary w-100 py-2 fw-semibold" style="border-radius:10px;" onclick="sendMobileOtp()">
                <i class="fas fa-paper-plane me-2"></i>Send OTP
              </button>
            </div>

            <!-- Step 2: Verify OTP -->
            <div id="otp-step2" style="display:none;">
              <div class="text-center mb-3">
                <div style="width:56px;height:56px;background:#eff6ff;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:1.4rem;color:var(--primary);">
                  <i class="fas fa-mobile-alt"></i>
                </div>
                <p id="otpSentMsg" style="font-size:.83rem;color:#374151;margin:0;"></p>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:.85rem;">Enter 6-digit OTP</label>
                <input type="text" class="form-control otp-big" id="otp_code"
                  placeholder="• • • • • •" maxlength="6" inputmode="numeric">
              </div>
              <button class="btn btn-primary w-100 py-2 fw-semibold mb-2" style="border-radius:10px;" onclick="verifyMobileOtp()">
                <i class="fas fa-check me-2"></i>Verify & Sign In
              </button>
              <button class="btn btn-outline-secondary w-100" style="border-radius:10px;font-size:.82rem;" onclick="resetOtpStep()">
                <i class="fas fa-arrow-left me-1"></i>Back
              </button>
              <!-- Debug OTP (dev only) -->
              <div id="debugOtpBox" style="display:none;margin-top:8px;padding:8px 12px;background:#fef3c7;border-radius:8px;font-size:.78rem;color:#92400e;text-align:center;"></div>
            </div>

          </div>

          <!-- ══ METHOD 3: ABHA / AADHAAR OTP ══ -->
          <?php if ($abdm_on): ?>
            <div id="panel-abha" style="display:none;">

              <!-- Sub-tabs: ABHA or Aadhaar -->
              <div style="display:flex;gap:6px;margin-bottom:14px;">
                <button class="btn btn-outline-success flex-fill btn-sm" id="abSubBtnAbha" onclick="switchAbSub('abha')">
                  <i class="fas fa-id-card me-1"></i>ABHA Number
                </button>
                <button class="btn btn-outline-secondary flex-fill btn-sm" id="abSubBtnAadhaar" onclick="switchAbSub('aadhaar')">
                  <i class="fas fa-fingerprint me-1"></i>Aadhaar Number
                </button>
              </div>

              <!-- ABHA sub-panel -->
              <div id="ab-sub-abha">

                <!-- Step A1 -->
                <div id="abha-step1">
                  <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem;">Your ABHA Health ID</label>
                    <input type="text" class="form-control" id="abha_num_in"
                      placeholder="XX-XXXX-XXXX-XXXX" maxlength="19"
                      oninput="fmtAbha(this)">
                    <small class="text-muted">Your 14-digit Ayushman Bharat Health Account number</small>
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem;">Auth Method</label>
                    <select class="form-select" id="abha_auth_method" style="font-size:.85rem;">
                      <option value="MOBILE_OTP">Mobile OTP (recommended)</option>
                      <option value="AADHAAR_OTP">Aadhaar OTP</option>
                    </select>
                  </div>
                  <button class="btn w-100 py-2 fw-semibold" style="background:var(--ab);color:#fff;border-radius:10px;" onclick="initAbhaLogin()">
                    <i class="fas fa-arrow-right me-2"></i>Send OTP
                  </button>
                </div>

                <!-- Step A2 -->
                <div id="abha-step2" style="display:none;">
                  <p style="font-size:.83rem;color:#374151;margin-bottom:14px;" id="abhaOtpMsg"></p>
                  <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem;">6-digit OTP</label>
                    <input type="text" class="form-control otp-big" id="abha_otp_in"
                      placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" style="border-radius:10px;" onclick="resetAbhaStep()">
                      <i class="fas fa-arrow-left me-1"></i>Back
                    </button>
                    <button class="btn flex-fill fw-semibold" style="background:var(--ab);color:#fff;border-radius:10px;" onclick="confirmAbhaLogin()">
                      <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                  </div>
                </div>

              </div>

              <!-- Aadhaar sub-panel -->
              <div id="ab-sub-aadhaar" style="display:none;">

                <!-- Step D1 -->
                <div id="aadhaar-step1">
                  <div class="alert" style="background:#f0fdf4;border:1px solid #86efac;font-size:.78rem;color:#065f46;padding:8px 12px;border-radius:8px;margin-bottom:12px;">
                    <i class="fas fa-shield-alt me-1"></i>Aadhaar is RSA-encrypted before sending to ABDM. Never stored.
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem;">Aadhaar Number</label>
                    <input type="text" class="form-control" id="aadhaar_in"
                      placeholder="XXXX XXXX XXXX" maxlength="14" inputmode="numeric"
                      oninput="this.value=this.value.replace(/\D/g,'').substring(0,12).replace(/(.{4})/g,'$1 ').trim()">
                    <small class="text-muted">Must be registered on our platform</small>
                  </div>
                  <button class="btn w-100 py-2 fw-semibold" style="background:var(--primary);color:#fff;border-radius:10px;" onclick="initAadhaarLogin()">
                    <i class="fas fa-mobile-alt me-2"></i>Send OTP to Aadhaar Mobile
                  </button>
                </div>

                <!-- Step D2 -->
                <div id="aadhaar-step2" style="display:none;">
                  <p style="font-size:.83rem;color:#374151;margin-bottom:14px;" id="aadhaarOtpMsg"></p>
                  <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.85rem;">6-digit OTP</label>
                    <input type="text" class="form-control otp-big" id="aadhaar_otp_in"
                      placeholder="• • • • • •" maxlength="6" inputmode="numeric">
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" style="border-radius:10px;" onclick="resetAadhaarStep()">
                      <i class="fas fa-arrow-left me-1"></i>Back
                    </button>
                    <button class="btn flex-fill fw-semibold" style="background:var(--primary);color:#fff;border-radius:10px;" onclick="confirmAadhaarLogin()">
                      <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                  </div>
                </div>

              </div>

              <!-- Forgot ABHA -->
              <div style="text-align:center;margin-top:12px;">
                <a href="<?= BASE_URL ?>forgot-abha.php" style="font-size:.78rem;color:var(--ab);font-weight:600;">
                  Forgot ABHA number / address?
                </a>
              </div>

            </div>
          <?php endif; ?>

          <!-- Create account -->
          <hr class="login-sep">
          <div class="login-create">
            <a href="<?= BASE_URL ?>signup.php">Create new patient account</a>
          </div>

        </div><!-- /login-body -->
      </div><!-- /login-card -->
    </div><!-- /login-wrap -->

    <!-- Minimal legal footer -->
    <footer class="auth-legal">
      <div class="auth-legal-inner">
        <div class="auth-legal-links">
          <a href="<?= BASE_URL ?>school-register.php">Register School</a>
          <a href="<?= BASE_URL ?>student-register.php">Student</a>
          <a href="<?= BASE_URL ?>teacher-register.php">Teacher</a>
          <a href="<?= BASE_URL ?>school/request-password-link.php">Student password link</a>
          <a href="<?= BASE_URL ?>doctor-login/">Doctor Login</a>
        </div>
        <div class="auth-legal-links">
          <a href="<?= BASE_URL ?>privacy-policy.php">Privacy Policy</a>
          <a href="<?= BASE_URL ?>terms-and-condition.php">Terms</a>
          <a href="<?= BASE_URL ?>legal-compliance.php">Legal &amp; Compliance</a>
          <a href="<?= BASE_URL ?>contact-us.php">Contact</a>
        </div>
        &copy; <?= date('Y') ?> REJUVENATE Digital Health. All rights reserved.
      </div>
    </footer>

  </section>
  <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
  <script>
    const BASE = '<?= BASE_URL ?>';
    const RDH_CSRF = '<?= htmlspecialchars($csrf_token, ENT_QUOTES) ?>';

    /* ── Tab switching ── */
    function switchMethod(m) {
      ['pw', 'otp', 'abha'].forEach(t => {
        const tab = document.getElementById('tab-' + t);
        const panel = document.getElementById('panel-' + t);
        if (!tab || !panel) return;
        const active = t === m;
        tab.classList.toggle('active', active);
        panel.style.display = active ? 'block' : 'none';
      });
      clearAlert();
    }

    /* ── Alert helper ── */
    function showAlert(msg, type = 'danger') {
      const b = document.getElementById('ajaxAlert');
      b.className = 'alert alert-' + type;
      b.innerHTML = '<i class="fas fa-' + (type === 'danger' ? 'exclamation-circle' : 'check-circle') + ' me-2"></i>' + msg;
      b.style.display = 'block';
    }

    function clearAlert() {
      document.getElementById('ajaxAlert').style.display = 'none';
    }

    /* ── Loading state ── */
    function setBtn(id, loading) {
      const b = document.getElementById(id);
      if (!b) return;
      b.disabled = loading;
      if (loading) b.dataset.orig = b.innerHTML, b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait…';
      else b.innerHTML = b.dataset.orig || b.innerHTML;
    }

    /* ── Password toggle ── */
    function togglePw() {
      const f = document.getElementById('password');
      const i = document.getElementById('pwIcon');
      if (f.type === 'password') {
        f.type = 'text';
        i.className = 'fas fa-eye';
      } else {
        f.type = 'password';
        i.className = 'fas fa-eye-slash';
      }
    }

    /* ── ABHA number formatter ── */
    function fmtAbha(el) {
      let v = el.value.replace(/\D/g, '').substring(0, 14);
      let o = v.length > 0 ? v.substring(0, 2) : '';
      if (v.length > 2) o += '-' + v.substring(2, 6);
      if (v.length > 6) o += '-' + v.substring(6, 10);
      if (v.length > 10) o += '-' + v.substring(10, 14);
      el.value = o;
    }

    /* ── Generic POST helper ── */
    async function apiPost(url, body) {
      const r = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(Object.assign({
          _csrf: RDH_CSRF
        }, body)),
      });
      return r.json();
    }

    async function redirect(url) {
      window.location.href = url;
    }

    /* ══ Mobile OTP flow ══ */
    async function sendMobileOtp() {
      clearAlert();
      const mobile = document.getElementById('otp_mobile').value.replace(/\D/g, '');
      if (mobile.length !== 10) {
        showAlert('Enter a valid 10-digit mobile number');
        return;
      }

      document.getElementById('otp-step1').style.display = 'none';
      document.getElementById('otp-step2').style.display = 'block';
      document.getElementById('otpSentMsg').textContent = 'Sending OTP…';

      const res = await apiPost(BASE + 'ajax/login-send-otp.php', {
        mobile
      });

      if (res.success) {
        document.getElementById('otpSentMsg').innerHTML =
          `OTP sent • <strong>${res.message}</strong>` +
          (res.role_label ? ` &nbsp;<span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:12px;font-size:.7rem;font-weight:700;">${res.role_label}</span>` : '');

        if (res.__debug_otp) {
          const d = document.getElementById('debugOtpBox');
          d.style.display = 'block';
          d.textContent = '🧪 Dev OTP: ' + res.__debug_otp;
        }
      } else {
        showAlert(res.message);
        resetOtpStep();
      }
    }

    async function verifyMobileOtp() {
      clearAlert();
      const mobile = document.getElementById('otp_mobile').value.replace(/\D/g, '');
      const otp = document.getElementById('otp_code').value.trim();
      if (otp.length < 6) {
        showAlert('Enter 6-digit OTP');
        return;
      }

      const res = await apiPost(BASE + 'ajax/login-verify-otp.php', {
        mobile,
        otp
      });
      if (res.success) {
        showAlert('Verified! Redirecting as <strong>' + res.role + '</strong>…', 'success');
        setTimeout(() => redirect(res.redirect), 800);
      } else {
        showAlert(res.message);
      }
    }

    function resetOtpStep() {
      document.getElementById('otp-step1').style.display = 'block';
      document.getElementById('otp-step2').style.display = 'none';
      document.getElementById('otp_code').value = '';
      document.getElementById('debugOtpBox').style.display = 'none';
      clearAlert();
    }

    /* ══ ABHA Login flow ══ */
    function switchAbSub(sub) {
      document.getElementById('ab-sub-abha').style.display = sub === 'abha' ? 'block' : 'none';
      document.getElementById('ab-sub-aadhaar').style.display = sub === 'aadhaar' ? 'block' : 'none';
      document.getElementById('abSubBtnAbha').className = 'btn flex-fill btn-sm ' + (sub === 'abha' ? 'btn-success' : 'btn-outline-success');
      document.getElementById('abSubBtnAadhaar').className = 'btn flex-fill btn-sm ' + (sub === 'aadhaar' ? 'btn-secondary' : 'btn-outline-secondary');
      clearAlert();
    }

    async function initAbhaLogin() {
      clearAlert();
      const abhaId = document.getElementById('abha_num_in').value;
      const authMethod = document.getElementById('abha_auth_method').value;
      if (abhaId.replace(/\D/g, '').length !== 14) {
        showAlert('Enter valid 14-digit ABHA number');
        return;
      }

      const res = await apiPost(BASE + 'ajax/login-abdm.php', {
        action: 'init_abha_login',
        abha_id: abhaId,
        auth_method: authMethod
      });
      if (res.success) {
        document.getElementById('abhaOtpMsg').textContent = 'OTP sent to your ABDM-registered mobile. Valid for 10 minutes.';
        document.getElementById('abha-step1').style.display = 'none';
        document.getElementById('abha-step2').style.display = 'block';
      } else {
        showAlert(res.message);
      }
    }

    async function confirmAbhaLogin() {
      clearAlert();
      const otp = document.getElementById('abha_otp_in').value.trim();
      if (otp.length < 6) {
        showAlert('Enter 6-digit OTP');
        return;
      }

      const res = await apiPost(BASE + 'ajax/login-abdm.php', {
        action: 'confirm_abha_login',
        otp
      });
      if (res.success) {
        showAlert('Verified! Signing in as <strong>' + res.role + '</strong>…', 'success');
        setTimeout(() => redirect(res.redirect), 800);
      } else {
        showAlert(res.message);
      }
    }

    function resetAbhaStep() {
      document.getElementById('abha-step1').style.display = 'block';
      document.getElementById('abha-step2').style.display = 'none';
      document.getElementById('abha_otp_in').value = '';
      clearAlert();
    }

    /* ══ Aadhaar Login flow ══ */
    async function initAadhaarLogin() {
      clearAlert();
      const raw = document.getElementById('aadhaar_in').value.replace(/\D/g, '');
      if (raw.length !== 12) {
        showAlert('Enter valid 12-digit Aadhaar number');
        return;
      }

      const res = await apiPost(BASE + 'ajax/login-abdm.php', {
        action: 'init_aadhaar_login',
        aadhaar: raw
      });
      if (res.success) {
        document.getElementById('aadhaarOtpMsg').textContent = 'OTP sent to ' + (res.maskedMobile || 'your Aadhaar mobile') + '. Valid 10 minutes.';
        document.getElementById('aadhaar-step1').style.display = 'none';
        document.getElementById('aadhaar-step2').style.display = 'block';
      } else {
        showAlert(res.message);
      }
    }

    async function confirmAadhaarLogin() {
      clearAlert();
      const otp = document.getElementById('aadhaar_otp_in').value.trim();
      if (otp.length < 6) {
        showAlert('Enter 6-digit OTP');
        return;
      }

      const res = await apiPost(BASE + 'ajax/login-abdm.php', {
        action: 'confirm_aadhaar_login',
        otp
      });
      if (res.success) {
        showAlert('Verified! Signing in as <strong>' + res.role + '</strong>…', 'success');
        setTimeout(() => redirect(res.redirect), 800);
      } else {
        showAlert(res.message);
      }
    }

    function resetAadhaarStep() {
      document.getElementById('aadhaar-step1').style.display = 'block';
      document.getElementById('aadhaar-step2').style.display = 'none';
      document.getElementById('aadhaar_otp_in').value = '';
      clearAlert();
    }

    /* Init: ABHA sub-tab defaults */
    <?php if ($abdm_on): ?>
      switchAbSub('abha');
    <?php endif; ?>
  </script>
</body>

</html>