<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
include_once "config/connect.php";
include_once "util/function.php";

$logo = get_header_logo();
$error_message   = '';
$success_message = '';

// Redirect if already logged in
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    header("Location: " . BASE_URL . "school/student/dashboard.php"); exit();
}

// Load active schools for dropdown
$schools_res = $conn->query("SELECT id, school_name, city FROM schools WHERE status='Active' ORDER BY school_name ASC");
$schools = [];
while ($row = $schools_res->fetch_assoc()) $schools[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $school_id   = (int)($_POST['school_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $class       = trim($_POST['class'] ?? '');
        $roll_number = trim($_POST['roll_number'] ?? '');
        $password    = $_POST['password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (!$school_id)           throw new Exception("Please select your school.");
        if (empty($name))          throw new Exception("Full name is required.");
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Valid email is required.");
        if (strlen($password) < 6) throw new Exception("Password must be at least 6 characters.");
        if ($password !== $confirm) throw new Exception("Passwords do not match.");

        // Verify school exists & is active
        $school_check = $conn->prepare("SELECT id, school_name FROM schools WHERE id=? AND status='Active'");
        $school_check->bind_param('i', $school_id);
        $school_check->execute();
        $school_row = $school_check->get_result()->fetch_assoc();
        if (!$school_row) throw new Exception("Selected school is not valid.");

        // Check duplicate email in this school
        $dup = $conn->prepare("SELECT id FROM school_members WHERE email=? AND school_id=?");
        $dup->bind_param('si', $email, $school_id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) throw new Exception("This email is already registered with that school.");

        // Check duplicate roll number (if provided)
        if (!empty($roll_number)) {
            $dup_roll = $conn->prepare("SELECT id FROM school_members WHERE roll_number=? AND school_id=?");
            $dup_roll->bind_param('si', $roll_number, $school_id);
            $dup_roll->execute();
            if ($dup_roll->get_result()->num_rows > 0) throw new Exception("This roll number is already registered.");
        }

        $hashed    = password_hash($password, PASSWORD_DEFAULT);
        $roll_val  = $roll_number ?: null;
        $phone_val = $phone ?: null;
        $class_val = $class ?: null;

        $ins = $conn->prepare("INSERT INTO school_members
            (school_id, type, name, email, phone, class, roll_number, password, status, added_by)
            VALUES (?, 'Student', ?, ?, ?, ?, ?, ?, 'Pending', NULL)");
        $ins->bind_param('issssss', $school_id, $name, $email, $phone_val, $class_val, $roll_val, $hashed);
        if (!$ins->execute()) throw new Exception("Registration failed. Please try again.");

        $success_message = "Registration submitted! Your account is pending approval by <strong>" . htmlspecialchars($school_row['school_name']) . "</strong>. You will be able to login once approved.";
        $_POST = []; // clear form

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Registration | REJUVENATE Digital Health</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/nice-select.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
    .reg-card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(12,116,197,.1); padding:32px 36px; }
    .reg-card label { font-size:.84rem; font-weight:600; color:#374151; }
    .form-control:focus { border-color:#0C74C5; box-shadow:0 0 0 3px rgba(12,116,197,.12); }
    .req-note { font-size:.75rem; color:#9ca3af; }
    @media (max-width:767px) { .reg-card { padding:22px 16px; } }
  </style>
</head>
<body>
  <?php include("header.php") ?>

  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="contact-appointment-wrapper-5 mb-5">
        <div class="row align-items-start g-4">

          <!-- Left info panel -->
          <div class="col-md-5 col-12">
            <div class="doctor-login text-center">
              <img src="<?= BASE_URL ?>assets/img/doctor-login.png" class="img-fluid" style="max-height:320px;">
              <h4 class="mt-4">Student Registration</h4>
              <p class="text-muted">Create your student account to access your school health portal.</p>
              <div class="benefits mt-3 text-start d-inline-block">
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> View your health profile</div>
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> Check health records & BMI</div>
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> ABHA Health ID details</div>
                <div class="benefit-item mb-2"><i class="fa fa-check text-success me-2"></i> Emergency contact info</div>
              </div>
              <div class="mt-4 p-3 rounded" style="background:#eaf4fd;font-size:.82rem;color:#0a5fa0;">
                <i class="fa fa-info-circle me-1"></i>
                After registering, your school admin must approve your account before you can login.
              </div>
            </div>
          </div>

          <!-- Right form -->
          <div class="col-md-7 col-12">
            <div class="reg-card">
              <div class="text-center mb-4">
                <img src="<?= BASE_URL . $logo ?>" class="img-fluid" style="max-height:52px;">
                <h4 class="mt-3 mb-0 fw-bold">Create Student Account</h4>
                <p class="text-muted" style="font-size:.83rem;">Fill in your details — your school admin will approve your account</p>
              </div>

              <?php if ($success_message): ?>
                <div class="alert alert-success">
                  <i class="fa fa-check-circle me-2"></i><?= $success_message ?>
                  <div class="mt-2">
                    <a href="<?= BASE_URL ?>student-login.php" class="btn btn-success btn-sm"><i class="fa fa-sign-in-alt me-1"></i>Go to Login</a>
                  </div>
                </div>
              <?php else: ?>

              <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                  <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>

              <form method="POST" novalidate>

                <!-- School Selection -->
                <div class="mb-3">
                  <label class="form-label">School <span class="text-danger">*</span></label>
                  <select class="form-select" name="school_id" required>
                    <option value="">— Select your school —</option>
                    <?php foreach ($schools as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= (($_POST['school_id'] ?? '') == $s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['school_name']) ?> (<?= htmlspecialchars($s['city']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Personal Info -->
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" placeholder="Your full name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" placeholder="your@email.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" placeholder="10-digit mobile"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <input type="text" class="form-control" name="class" placeholder="e.g. 10, 12"
                           value="<?= htmlspecialchars($_POST['class'] ?? '') ?>">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Roll Number</label>
                    <input type="text" class="form-control" name="roll_number" placeholder="School roll no."
                           value="<?= htmlspecialchars($_POST['roll_number'] ?? '') ?>">
                  </div>
                </div>

                <!-- Password -->
                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="position-relative">
                      <input type="password" class="form-control" id="pw1" name="password" placeholder="Min 6 characters" required>
                      <button type="button" class="btn btn-link position-absolute end-0 top-0 mt-1 me-1 p-0 px-2"
                              onclick="togglePasswordById('pw1','pw1Icon')">
                        <i id="pw1Icon" class="fas fa-eye-slash text-muted"></i>
                      </button>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="position-relative">
                      <input type="password" class="form-control" id="pw2" name="confirm_password" placeholder="Re-enter password" required>
                      <button type="button" class="btn btn-link position-absolute end-0 top-0 mt-1 me-1 p-0 px-2"
                              onclick="togglePasswordById('pw2','pw2Icon')">
                        <i id="pw2Icon" class="fas fa-eye-slash text-muted"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="mt-4">
                  <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fa fa-user-plus me-2"></i>Register as Student
                  </button>
                </div>

                <p class="req-note mt-2 text-center"><span class="text-danger">*</span> Required fields</p>
              </form>
              <?php endif; ?>

              <hr class="my-3">
              <div class="text-center" style="font-size:.82rem;">
                Already have an account? <a href="<?= BASE_URL ?>student-login.php" class="text-primary fw-semibold">Login here</a>
                &nbsp;|&nbsp;
                <a href="<?= BASE_URL ?>teacher-register.php" class="text-secondary">Teacher Registration</a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <?php include("footer.php") ?>
  <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
  <script>
  function togglePasswordById(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (inp.type === 'password') {
      inp.type = 'text';
      ico.classList.replace('fa-eye-slash', 'fa-eye');
    } else {
      inp.type = 'password';
      ico.classList.replace('fa-eye', 'fa-eye-slash');
    }
  }
  </script>
</body>
</html>
