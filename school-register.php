<?php
include_once "config/connect.php";
include_once "util/function.php";
include_once "util/otp-service.php";
require_once "util/otp-widget.php";

$logo = get_header_logo();
$error = '';
$success = '';

if (isset($_SESSION['school_logged_in']) && $_SESSION['school_logged_in'] === true) {
  header("Location: " . BASE_URL . "school/dashboard.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $school_name = trim($_POST['school_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $admin_name = trim($_POST['admin_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    /* ── Validate ── */
    if (!$school_name)
      throw new Exception("School name is required.");
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
      throw new Exception("Enter a valid email address.");
    if (!preg_match('/^[6-9]\d{9}$/', $phone))
      throw new Exception("Enter a valid 10-digit mobile number.");
    if (!$admin_name)
      throw new Exception("Admin name is required.");
    if (strlen($password) < 8)
      throw new Exception("Password must be at least 8 characters.");
    if ($password !== $confirm)
      throw new Exception("Passwords do not match.");

    $phone = preg_replace('/\D/', '', $phone);
    if (!otp_consume_token('school_admin', $phone, $_POST['mobile_verify_token'] ?? ''))
      throw new Exception("Please verify your mobile number with the OTP before submitting.");

    /* ── Duplicate check ── */
    $chk = $conn->prepare("SELECT id FROM schools WHERE email=?");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0)
      throw new Exception("This email is already registered.");

    $chk2 = $conn->prepare("SELECT id FROM school_users WHERE email=?");
    $chk2->bind_param('s', $email);
    $chk2->execute();
    if ($chk2->get_result()->num_rows > 0)
      throw new Exception("This email is already in use.");

    /* ── Insert school (optional fields are NULL for now) ── */
    $ins = $conn->prepare("INSERT INTO schools
            (school_name, email, phone, city, state, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')");
    $ins->bind_param('sssss', $school_name, $email, $phone, $city, $state);
    if (!$ins->execute())
      throw new Exception("Registration failed. Please try again.");
    $school_id = $ins->insert_id;

    /* ── Insert admin account ── */
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins2 = $conn->prepare("INSERT INTO school_users
            (school_id, name, email, phone, password, role, mobile_verified, mobile_verified_at)
            VALUES (?, ?, ?, ?, ?, 'school_admin', 1, NOW())");
    $ins2->bind_param('issss', $school_id, $admin_name, $email, $phone, $hash);
    if (!$ins2->execute())
      throw new Exception("Admin account creation failed.");

    $success = "Registration submitted! Your school is under review. We'll notify you once approved.";
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

$states = [
  'Andhra Pradesh',
  'Arunachal Pradesh',
  'Assam',
  'Bihar',
  'Chhattisgarh',
  'Goa',
  'Gujarat',
  'Haryana',
  'Himachal Pradesh',
  'Jharkhand',
  'Karnataka',
  'Kerala',
  'Madhya Pradesh',
  'Maharashtra',
  'Manipur',
  'Meghalaya',
  'Mizoram',
  'Nagaland',
  'Odisha',
  'Punjab',
  'Rajasthan',
  'Sikkim',
  'Tamil Nadu',
  'Telangana',
  'Tripura',
  'Uttar Pradesh',
  'Uttarakhand',
  'West Bengal',
  'Delhi (NCT)',
  'Jammu & Kashmir',
  'Ladakh',
  'Puducherry',
  'Chandigarh',
  'Other'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>School Registration | Rejuvenate Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
</head>

<body>
  <?php include("header.php") ?>

  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="row justify-content-center align-items-start g-5">

        <!-- Left panel -->
        <div class="col-lg-4 col-md-5 d-none d-md-block">
          <div class="text-center mb-4">
            <img src="<?= BASE_URL ?>assets/img/doctor-login.png" class="img-fluid" style="max-height:260px;">
          </div>
          <h5 class="fw-bold mb-3">Why join Rejuvenate?</h5>
          <div class="benefit-row"><i class="fa fa-check-circle"></i> Centralised student health profiles</div>
          <div class="benefit-row"><i class="fa fa-check-circle"></i> ABHA / Ayushman Bharat integration</div>
          <div class="benefit-row"><i class="fa fa-check-circle"></i> Teacher & staff management</div>
          <div class="benefit-row"><i class="fa fa-check-circle"></i> Health records with BMI tracking</div>
          <div class="benefit-row"><i class="fa fa-check-circle"></i> Secure, admin-supervised platform</div>
          <hr class="my-4">
          <p class="text-muted" style="font-size:.83rem;">Already registered?</p>
          <a href="<?= BASE_URL ?>school-login.php" class="btn btn-outline-primary btn-sm w-100">Login to School
            Dashboard</a>
        </div>

        <!-- Right: Form -->
        <div class="col-lg-5 col-md-7">
          <div class="reg-card">

            <div class="text-center mb-4">
              <img src="<?= BASE_URL . $logo ?>" class="img-fluid mb-3" style="max-height:48px;">
              <h4 class="fw-bold mb-1">Register Your School</h4>
              <p class="text-muted" style="font-size:.83rem;">Takes under 2 minutes. Reviewed within 24 hours.</p>
            </div>

            <?php if ($error): ?>
              <div class="alert alert-danger alert-dismissible fade show" style="border-radius:10px;font-size:.85rem;">
                <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>

            <?php if ($success): ?>
              <div class="alert alert-success" style="border-radius:10px;font-size:.85rem;">
                <i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <div class="mt-2">
                  <a href="school-login.php" class="btn btn-success btn-sm">Go to Login</a>
                </div>
              </div>
            <?php else: ?>

              <div class="later-note">
                <i class="fa fa-info-circle me-1"></i>
                <strong>Quick sign-up:</strong> Fill in the essentials now. Logo, board, address & other details can be
                added from your school dashboard after approval.
              </div>

              <form method="POST" autocomplete="off" id="schoolRegForm">

                <!-- School name -->
                <div class="field-group">
                  <label>School Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="school_name"
                    value="<?= htmlspecialchars($_POST['school_name'] ?? '') ?>"
                    placeholder="e.g. Delhi Public School, Sector 12" required>
                </div>

                <!-- City + State -->
                <div class="row g-3 mb-0">
                  <div class="col-6 field-group">
                    <label>City</label>
                    <input type="text" class="form-control" name="city"
                      value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="New Delhi">
                  </div>
                  <div class="col-6 field-group">
                    <label>State</label>
                    <select class="form-select" name="state">
                      <option value="">Select</option>
                      <?php foreach ($states as $s): ?>
                        <option value="<?= $s ?>" <?= ($_POST['state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- Email -->
                <div class="field-group">
                  <label>Official Email <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text" style="border-radius:10px 0 0 10px;"><i
                        class="fa fa-envelope"></i></span>
                    <input type="email" class="form-control" name="email"
                      value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="principal@yourschool.edu.in"
                      required style="border-radius:0 10px 10px 0;">
                  </div>
                  <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Used to log in and receive approval
                    notification.</div>
                </div>

                <!-- Phone -->
                <div class="field-group">
                  <label>Phone Number <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text" style="border-radius:10px 0 0 10px;">+91</span>
                    <input type="text" class="form-control" name="phone" inputmode="numeric" maxlength="10"
                      value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="10-digit mobile" required
                      style="border-radius:0 10px 10px 0;">
                  </div>
                  <?php render_otp_widget([
                    'role'            => 'school_admin',
                    'mobile_field'    => 'phone',
                    'email_field'     => 'email',
                    'name_field'      => 'admin_name',
                    'submit_selector' => '#schoolRegForm button[type="submit"]',
                  ]); ?>
                </div>

                <div class="reg-divider">─── Admin Account ───</div>

                <!-- Admin name -->
                <div class="field-group">
                  <label>Your Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="admin_name"
                    value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"
                    placeholder="Name of the person managing this account" required>
                </div>

                <!-- Password -->
                <div class="field-group">
                  <label>Password <span class="text-danger">*</span></label>
                  <div class="pass-wrap">
                    <input type="password" class="form-control" name="password" id="pw1"
                      placeholder="At least 8 characters" required oninput="pwStrength(this)">
                    <button type="button" class="toggle" onclick="togglePw('pw1',this)"><i
                        class="fas fa-eye"></i></button>
                  </div>
                  <div class="pw-bar">
                    <div class="pw-bar-fill" id="pwBar"></div>
                  </div>
                  <div id="pwStrengthTxt" style="font-size:.7rem;color:#9ca3af;margin-top:2px;"></div>
                </div>

                <!-- Confirm password -->
                <div class="field-group">
                  <label>Confirm Password <span class="text-danger">*</span></label>
                  <div class="pass-wrap">
                    <input type="password" class="form-control" name="confirm_password" id="pw2"
                      placeholder="Re-enter password" required oninput="checkMatch()">
                    <button type="button" class="toggle" onclick="togglePw('pw2',this)"><i
                        class="fas fa-eye"></i></button>
                  </div>
                  <div id="matchTxt" style="font-size:.73rem;margin-top:3px;"></div>
                </div>

                <!-- Terms -->
                <div class="form-check mb-4" style="font-size:.82rem;">
                  <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                  <label class="form-check-label" for="terms">
                    I agree to the <a href="<?= BASE_URL ?>terms-and-condition.php" target="_blank">Terms of Service</a>
                    and <a href="<?= BASE_URL ?>privacy-policy.php" target="_blank">Privacy Policy</a>
                  </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"
                  style="border-radius:10px;font-size:.95rem;">
                  <i class="fa fa-paper-plane me-2"></i>Submit Registration
                </button>

                <p class="text-center mt-3 mb-0 d-md-none" style="font-size:.82rem;">
                  Already registered? <a href="school-login.php">Login here</a>
                </p>

              </form>
            <?php endif; ?>

          </div>
        </div>

      </div>
    </div>
  </section>

  <?php include("footer.php") ?>

  <script>
    function togglePw(id, btn) {
      const inp = document.getElementById(id);
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function pwStrength(inp) {
      const p = inp.value;
      const bar = document.getElementById('pwBar');
      const txt = document.getElementById('pwStrengthTxt');
      let s = 0;
      if (p.length >= 8) s++;
      if (p.match(/[a-z]/)) s++;
      if (p.match(/[A-Z]/)) s++;
      if (p.match(/[0-9]/)) s++;
      if (p.match(/[^a-zA-Z0-9]/)) s++;
      const levels = [
        { w: '20%', c: '#dc2626', l: 'Very weak' },
        { w: '40%', c: '#ea580c', l: 'Weak' },
        { w: '60%', c: '#d97706', l: 'Fair' },
        { w: '80%', c: '#16a34a', l: 'Good' },
        { w: '100%', c: '#0C74C5', l: 'Strong' },
      ];
      const lv = levels[Math.max(0, s - 1)];
      bar.style.width = lv.w;
      bar.style.background = lv.c;
      txt.textContent = p.length ? lv.l : '';
      txt.style.color = lv.c;
      checkMatch();
    }

    function checkMatch() {
      const p1 = document.getElementById('pw1').value;
      const p2 = document.getElementById('pw2').value;
      const el = document.getElementById('matchTxt');
      if (!p2) { el.textContent = ''; return; }
      if (p1 === p2) {
        el.innerHTML = '<span style="color:#16a34a"><i class="fas fa-check me-1"></i>Passwords match</span>';
      } else {
        el.innerHTML = '<span style="color:#dc2626"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
      }
    }
  </script>
</body>

</html>