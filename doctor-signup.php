<?php
include_once "config/connect.php";
include_once "util/function.php";
include_once "util/otp-service.php";
require_once "util/otp-widget.php";

// session_start();

$contact = contact_us();
$logo = get_header_logo();
$departments = get_sub_category();
$error_message = '';
$success_message = '';

// Referral link support: doctor-signup.php?ref=<referring doctor's doctor_uid>
$ref_uid = trim($_GET['ref'] ?? $_POST['ref'] ?? '');
$referring_doctor = null;
if ($ref_uid !== '') {
    $ref_stmt = $conn->prepare("SELECT id, name FROM doctors WHERE doctor_uid = ? AND status = 'Active' LIMIT 1");
    $ref_stmt->bind_param('s', $ref_uid);
    $ref_stmt->execute();
    $referring_doctor = $ref_stmt->get_result()->fetch_assoc();
}

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $degrees = trim($_POST['designation']);
    $experience_years = intval($_POST['experience']);
    $doctor_departments = $_POST['department'] ?? [];
    $doctor_departments = array_values(array_unique(array_map('intval', $doctor_departments)));
    $doctor_departments = array_filter($doctor_departments, function ($id) { return $id > 0; });
    $password = $_POST['password'];
    $confirm_password = $_POST['confirmPassword'];
    $mobile_verify_token = $_POST['mobile_verify_token'] ?? '';
    $role = "doctor";

    try {
        // Validate passwords match
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        // Validate password strength
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }

        // Normalise + require an OTP-verified mobile number
        $phone = preg_replace('/\D/', '', $phone);
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
            throw new Exception("Enter a valid 10-digit mobile number.");
        }

        if (empty($doctor_departments)) {
            throw new Exception("Please select at least one department.");
        }

        // Check if email already exists
        $check_sql = "SELECT id FROM doctors WHERE email = ? limit 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            throw new Exception("Email already registered. Please use a different email or login.");
        }

        if (!otp_consume_token('doctor', $phone, $mobile_verify_token)) {
            throw new Exception("Please verify your mobile number with the OTP before creating your account.");
        }

        // Generate doctor UID
        $doctor_uid = 'DOC' . date('YmdHis') . rand(100, 999);
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Handle document upload
        $documents = [];
        
        if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
            $upload_dir = 'uploads/doctors/documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = 'doc-' . time() . '-' . $key . '-' . $_FILES['documents']['name'][$key];
                    $target_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $documents[] = [
                            'type' => pathinfo($_FILES['documents']['name'][$key], PATHINFO_EXTENSION),
                            'name' => $_FILES['documents']['name'][$key],
                            'path' => $target_path
                        ];
                    }
                }
            }
        }
        
        $documents_json = json_encode($documents);
        
        // Insert doctor
        $referred_by = $referring_doctor['id'] ?? null;

        $sql = "INSERT INTO doctors (
            doctor_uid, referred_by, name, email, phone, degrees, specialization,
            experience_years, password, documents, mobile_verified, mobile_verified_at, status, added_on
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), 'Active', NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sisssssiss',
            $doctor_uid, $referred_by, $name, $email, $phone, $degrees, $specialization,
            $experience_years, $hashed_password, $documents_json
        );
        
        if ($stmt->execute()) {
            $doctor_id = $stmt->insert_id;
            
            // Insert documents into separate table if needed
            if (!empty($documents)) {
                foreach ($documents as $doc) {
                    $doc_sql = "INSERT INTO doctor_documents (doctor_id, document_type, document_name, file_path) VALUES (?, ?, ?, ?)";
                    $doc_stmt = $conn->prepare($doc_sql);
                    $doc_stmt->bind_param('isss', $doctor_id, $doc['type'], $doc['name'], $doc['path']);
                    $doc_stmt->execute();
                }
            }
            
            $success_message = "Registration successful! Your account is pending approval. We'll notify you once it's approved.";
            
            // Clear form
            $_POST = [];
            
        } else {
            throw new Exception("Registration failed. Please try again.");
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
  <meta name="author" content="modinatheme">
  <meta name="description" content="">
  <title>REJUVENATE Digital Health - Doctor Signup</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/font-awesome.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/animate.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/magnific-popup.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/meanmenu.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/odometer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/swiper-bundle.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
  <style>
   
    .password-strength {
      height: 5px;
      border-radius: 5px;
      margin-top: 5px;
      transition: all 0.3s ease;
    }
    .strength-weak { background: #dc3545; width: 25%; }
    .strength-medium { background: #ffc107; width: 50%; }
    .strength-strong { background: #28a745; width: 75%; }
    .strength-very-strong { background: #20c997; width: 100%; }
  </style>
</head>
<body>
  <?php include("header.php") ?>
  <section class="contact-appointment-section section-padding fix">
    <div class="container">
      <div class="contact-appointment-wrapper-5 mb-5">
        <div class="row align-items-center">
          <div class="col-md-6 col-sm-6 col-12">
            <div class="doctor-login text-center">
              <img src="<?= BASE_URL ?>assets/img/doctor-login.png" class="img-fluid" style="max-height: 500px;">
              <h4 class="mt-4">Join Our Medical Community</h4>
              <p class="text-muted">Register as a doctor and start providing healthcare services through our digital platform.</p>
              <div class="benefits mt-4">
                <div class="benefit-item mb-2">
                  <i class="fa fa-check text-success me-2"></i> Reach more patients
                </div>
                <div class="benefit-item mb-2">
                  <i class="fa fa-check text-success me-2"></i> Flexible working hours
                </div>
                <div class="benefit-item mb-2">
                  <i class="fa fa-check text-success me-2"></i> Secure platform
                </div>
                <div class="benefit-item mb-2">
                  <i class="fa fa-check text-success me-2"></i> Professional network
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-sm-6 col-12">
            <div class="login-card">
              <div class="login-logo">
                <img src="<?= BASE_URL . $logo ?>" class="img-fluid">
              </div>
              <h3 class="text-center mb-4">Create Doctor Account</h3>
              
              <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <?= $error_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                  <?= $success_message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <?php if ($ref_uid !== '' && $referring_doctor): ?>
                <div class="alert alert-info" role="alert">
                  <i class="fa fa-user-md me-2"></i> You were referred by <strong>Dr. <?= htmlspecialchars($referring_doctor['name']) ?></strong>.
                </div>
              <?php elseif ($ref_uid !== ''): ?>
                <div class="alert alert-warning" role="alert">
                  <i class="fa fa-exclamation-triangle me-2"></i> Referral link not recognized — you can still sign up normally.
                </div>
              <?php endif; ?>

              <form method="POST" action="" enctype="multipart/form-data" id="doctorSignupForm">
                <input type="hidden" name="ref" value="<?= htmlspecialchars($ref_uid) ?>">
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Enter your full name" required value="<?= $_POST['fullname'] ?? '' ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                      <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required value="<?= $_POST['email'] ?? '' ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="phone" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter your Mobile number"
                             maxlength="10" inputmode="numeric" required value="<?= $_POST['phone'] ?? '' ?>">
                      <?php render_otp_widget([
                        'role'            => 'doctor',
                        'mobile_field'    => 'phone',
                        'email_field'     => 'email',
                        'name_field'      => 'fullname',
                        'submit_selector' => '#doctorSignupForm button[type="submit"]',
                      ]); ?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="designation" class="form-label">Your Designation <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="designation" name="designation" placeholder="MBBS, MD, etc" required value="<?= $_POST['designation'] ?? '' ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="experience" class="form-label">Experience (Years) <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" id="experience" name="experience" placeholder="Years of experience" min="0" required value="<?= $_POST['experience'] ?? '' ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="specialization" class="form-label">Specialization <span class="text-danger">*</span></label>
                      <select class="form-control" id="specialization" name="specialization" required>
                        <option value="">Please Select Department</option>
                        <option value="General Surgery" <?= ($_POST['specialization'] ?? '') == 'General Surgery' ? 'selected' : '' ?>>General Surgery</option>
                        <option value="Urology" <?= ($_POST['specialization'] ?? '') == 'Urology' ? 'selected' : '' ?>>Urology</option>
                        <option value="Neuro Surgery" <?= ($_POST['specialization'] ?? '') == 'Neuro Surgery' ? 'selected' : '' ?>>Neuro Surgery</option>
                        <option value="GI Surgery" <?= ($_POST['specialization'] ?? '') == 'GI Surgery' ? 'selected' : '' ?>>GI Surgery</option>
                        <option value="Cardiology" <?= ($_POST['specialization'] ?? '') == 'Cardiology' ? 'selected' : '' ?>>Cardiology</option>
                        <option value="Neurology" <?= ($_POST['specialization'] ?? '') == 'Neurology' ? 'selected' : '' ?>>Neurology</option>
                        <option value="Pulmonology" <?= ($_POST['specialization'] ?? '') == 'Pulmonology' ? 'selected' : '' ?>>Pulmonology</option>
                        <!-- Add more specializations as needed -->
                      </select>
                    </div>
                  </div>
                  <div class="col-md-12">
                    <div class="mb-3">
                      <label for="documents" class="form-label">Upload Your Documents</label>
                      <input type="file" class="form-control" id="documents" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                      <small class="text-dark" style="font-size: 12px;">Upload your medical certificates, degree documents, etc. (PDF, JPG, PNG, DOC)</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                      <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                      <div id="passwordStrength" class="password-strength mt-2"></div>
                      <small class="text-dark" style="font-size: 12px;">Password must be at least 8 characters long</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="confirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                      <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                      <div id="passwordMatch" class="mt-2"></div>
                    </div>
                  </div>
                  <div class="col-md-12 mt-3">
                    <div class="form-check mb-3">
                      <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                      <label class="form-check-label" for="terms">
                        I agree to the <a href="<?= BASE_URL ?>terms/">Terms of Service</a> and <a href="<?= BASE_URL ?>privacy/">Privacy Policy</a>
                      </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create Account</button>
                  </div>
                </div>
              </form>
              <p class="text-center mt-3 mb-0">Already have an account? <a href="<?= BASE_URL ?>doctor-login/">Sign In</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php include("footer.php") ?>

  <script>
    // Password strength indicator
    document.getElementById('password').addEventListener('input', function() {
      const password = this.value;
      const strengthBar = document.getElementById('passwordStrength');
      let strength = 0;
      
      if (password.length >= 8) strength++;
      if (password.match(/[a-z]+/)) strength++;
      if (password.match(/[A-Z]+/)) strength++;
      if (password.match(/[0-9]+/)) strength++;
      if (password.match(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/)) strength++;
      
      strengthBar.className = 'password-strength mt-2';
      
      if (strength <= 2) {
        strengthBar.classList.add('strength-weak');
      } else if (strength === 3) {
        strengthBar.classList.add('strength-medium');
      } else if (strength === 4) {
        strengthBar.classList.add('strength-strong');
      } else if (strength >= 5) {
        strengthBar.classList.add('strength-very-strong');
      }
    });
    
    // Password match checker
    document.getElementById('confirmPassword').addEventListener('input', function() {
      const password = document.getElementById('password').value;
      const confirmPassword = this.value;
      const matchDiv = document.getElementById('passwordMatch');
      
      if (confirmPassword === '') {
        matchDiv.innerHTML = '';
      } else if (password === confirmPassword) {
        matchDiv.innerHTML = '<small class="text-success"><i class="fa fa-check"></i> Passwords match</small>';
      } else {
        matchDiv.innerHTML = '<small class="text-danger"><i class="fa fa-times"></i> Passwords do not match</small>';
      }
    });
  </script>
</body>
</html>