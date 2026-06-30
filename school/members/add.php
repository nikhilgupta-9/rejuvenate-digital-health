<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $type = $_POST['type'] ?? '';
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $dob = $_POST['dob'] ?? null;
  $gender = $_POST['gender'] ?? '';
  $blood_group = $_POST['blood_group'] ?? '';
  $aadhar = trim($_POST['aadhar'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $status = $_POST['status'] ?? 'Active';

  // Student fields
  $class = trim($_POST['class'] ?? '');
  $section = trim($_POST['section'] ?? '');
  $roll_number = trim($_POST['roll_number'] ?? '');
  $admission_number = trim($_POST['admission_number'] ?? '');

  // Teacher / Staff fields
  $employee_id = trim($_POST['employee_id'] ?? '');
  $designation = trim($_POST['designation'] ?? '');
  $assigned_class = trim($_POST['assigned_class'] ?? '');

  // Password
  $set_password = $_POST['set_password'] ?? '';
  $confirm_pw = $_POST['confirm_password'] ?? '';
  $password_hash = null;

  // Validate
  if (!$type || !$name) {
    $error = "Member type and full name are required.";
  } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email address.";
  } elseif ($aadhar && !preg_match('/^\d{12}$/', $aadhar)) {
    $error = "Aadhar number must be exactly 12 digits.";
  } elseif ($set_password && strlen($set_password) < 6) {
    $error = "Password must be at least 6 characters.";
  } elseif ($set_password && $set_password !== $confirm_pw) {
    $error = "Passwords do not match.";
  } else {
    if ($set_password)
      $password_hash = password_hash($set_password, PASSWORD_DEFAULT);

    // Duplicate email check
    if ($email) {
      $chk = $conn->prepare("SELECT id FROM school_members WHERE email=? AND school_id=?");
      $chk->bind_param('si', $email, $school_id);
      $chk->execute();
      if ($chk->get_result()->num_rows > 0)
        $error = "A member with this email already exists in your school.";
    }
    // Duplicate roll number check
    if (!$error && $type === 'Student' && $roll_number) {
      $chk2 = $conn->prepare("SELECT id FROM school_members WHERE roll_number=? AND school_id=?");
      $chk2->bind_param('si', $roll_number, $school_id);
      $chk2->execute();
      if ($chk2->get_result()->num_rows > 0)
        $error = "This roll number already exists in your school.";
    }
    // Duplicate employee ID check
    if (!$error && in_array($type, ['Teacher', 'Staff']) && $employee_id) {
      $chk3 = $conn->prepare("SELECT id FROM school_members WHERE employee_id=? AND school_id=?");
      $chk3->bind_param('si', $employee_id, $school_id);
      $chk3->execute();
      if ($chk3->get_result()->num_rows > 0)
        $error = "This Employee ID already exists in your school.";
    }
  }

  // Profile picture upload
  $profile_pic = null;
  if (!$error && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
      $error = "Photo must be JPG, PNG or WebP.";
    } elseif ($_FILES['profile_pic']['size'] > 2 * 1024 * 1024) {
      $error = "Photo must be under 2 MB.";
    } else {
      $upload_dir = '../../uploads/members/';
      if (!is_dir($upload_dir))
        mkdir($upload_dir, 0755, true);
      $filename = 'member_' . $school_id . '_' . time() . '.' . $ext;
      if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $filename)) {
        $profile_pic = 'uploads/members/' . $filename;
      }
    }
  }

  if (!$error) {
    $dob_val = $dob ?: null;
    $aadhar_val = $aadhar ?: null;
    $stmt = $conn->prepare("INSERT INTO school_members
            (school_id, type, name, email, phone, dob, gender, blood_group, aadhar_number, address, status,
             class, section, roll_number, admission_number,
             employee_id, designation, assigned_class, password, profile_pic, added_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param(
      'isssssssssssssssssssi',
      $school_id,
      $type,
      $name,
      $email,
      $phone,
      $dob_val,
      $gender,
      $blood_group,
      $aadhar_val,
      $address,
      $status,
      $class,
      $section,
      $roll_number,
      $admission_number,
      $employee_id,
      $designation,
      $assigned_class,
      $password_hash,
      $profile_pic,
      $school_user_id
    );
    if ($stmt->execute()) {
      $new_id = $stmt->insert_id;
      $redirect = $_POST['after_save'] ?? 'list';
      if ($redirect === 'view') {
        header("Location: view.php?id=$new_id");
        exit();
      }
      if ($redirect === 'another') {
        header("Location: add.php?added=1");
        exit();
      }
      header("Location: list.php?added=1");
      exit();
    } else {
      $error = "Database error: " . $conn->error;
    }
  }
}

$just_added = isset($_GET['added']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Add Member</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    /* ── Type selector cards ── */
    .type-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    .type-card {
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      padding: 18px 14px;
      text-align: center;
      cursor: pointer;
      transition: all .2s;
      background: #fff;
      position: relative;
      user-select: none;
    }

    .type-card:hover {
      border-color: var(--primary);
      background: #f8fbff;
    }

    .type-card.selected {
      border-color: var(--primary);
      background: #eaf4fd;
      box-shadow: 0 0 0 3px rgba(12, 116, 197, .15);
    }

    .type-card .tc-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      margin: 0 auto 10px;
    }

    .type-card .tc-name {
      font-weight: 700;
      font-size: .92rem;
      color: #1f2937;
    }

    .type-card .tc-desc {
      font-size: .72rem;
      color: #9ca3af;
      margin-top: 3px;
    }

    .type-card .check-mark {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--primary);
      color: #fff;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: .65rem;
    }

    .type-card.selected .check-mark {
      display: flex;
    }

    /* Colored type icons */
    .type-card.student .tc-icon {
      background: #eaf4fd;
      color: #0C74C5;
    }

    .type-card.teacher .tc-icon {
      background: #f0fdf4;
      color: #16a34a;
    }

    .type-card.staff .tc-icon {
      background: #f5f3ff;
      color: #7c3aed;
    }

    .type-card.student.selected .tc-icon {
      background: #0C74C5;
      color: #fff;
    }

    .type-card.teacher.selected .tc-icon {
      background: #16a34a;
      color: #fff;
    }

    .type-card.staff.selected .tc-icon {
      background: #7c3aed;
      color: #fff;
    }

    /* ── Section card ── */
    .sec-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
      margin-bottom: 20px;
      overflow: hidden;
    }

    .sec-card-head {
      padding: 14px 20px;
      border-bottom: 1px solid #f3f4f6;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .sec-card-head .icon {
      width: 34px;
      height: 34px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .88rem;
      flex-shrink: 0;
    }

    .sec-card-head h6 {
      margin: 0;
      font-size: .9rem;
      font-weight: 700;
      color: #1f2937;
    }

    .sec-card-head p {
      margin: 0;
      font-size: .73rem;
      color: #9ca3af;
    }

    .sec-card-body {
      padding: 20px;
    }

    /* ── Live preview card ── */
    .preview-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
      position: sticky;
      top: 80px;
    }

    .preview-avatar {
      width: 60px;
      height: 60px;
      border-radius: 14px;
      background: var(--primary);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0 auto 10px;
      overflow: hidden;
    }

    .preview-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* ── Photo upload ── */
    .photo-upload {
      width: 88px;
      height: 88px;
      border-radius: 14px;
      border: 2px dashed #d1d5db;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      overflow: hidden;
      background: #f9fafb;
      transition: .2s;
    }

    .photo-upload:hover {
      border-color: var(--primary);
      background: #eaf4fd;
    }

    .photo-upload img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* ── Password match ── */
    #matchMsg {
      font-size: .72rem;
      margin-top: 4px;
    }

    @media (max-width: 767px) {
      .type-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
      }

      .type-card {
        padding: 12px 8px;
      }

      .type-card .tc-icon {
        width: 38px;
        height: 38px;
        font-size: 1rem;
      }

      .type-card .tc-desc {
        display: none;
      }
    }
  </style>
</head>

<body>
  <?php $active_page = 'add';
  $base_path = '../';
  include '../inc/sidebar-school.php'; ?>

  <div class="school-topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;">
        <i class="fas fa-user-plus me-2 text-primary"></i>Add Member
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><span
          class="d-none d-sm-inline">Back</span></a>
      <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>

  <main class="school-content">

    <?php if ($just_added): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>Member added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="addForm" novalidate>

      <div class="row g-4">
        <!-- ── Left: Main Form ── -->
        <div class="col-lg-8">

          <!-- 1. Member Type -->
          <div class="sec-card">
            <div class="sec-card-head">
              <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-tag"></i></div>
              <div>
                <h6>Member Type <span class="text-danger">*</span></h6>
                <p>Select the type of member you are adding</p>
              </div>
            </div>
            <div class="sec-card-body">
              <div class="type-grid">
                <div class="type-card student <?= (($_POST['type'] ?? '') === 'Student') ? 'selected' : '' ?>"
                  onclick="selectType('Student', this)">
                  <div class="check-mark"><i class="fas fa-check"></i></div>
                  <div class="tc-icon"><i class="fas fa-user-graduate"></i></div>
                  <div class="tc-name">Student</div>
                  <div class="tc-desc">Class, roll number</div>
                </div>
                <div class="type-card teacher <?= (($_POST['type'] ?? '') === 'Teacher') ? 'selected' : '' ?>"
                  onclick="selectType('Teacher', this)">
                  <div class="check-mark"><i class="fas fa-check"></i></div>
                  <div class="tc-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                  <div class="tc-name">Teacher</div>
                  <div class="tc-desc">Designation, class</div>
                </div>
                <div class="type-card staff <?= (($_POST['type'] ?? '') === 'Staff') ? 'selected' : '' ?>"
                  onclick="selectType('Staff', this)">
                  <div class="check-mark"><i class="fas fa-check"></i></div>
                  <div class="tc-icon"><i class="fas fa-user-tie"></i></div>
                  <div class="tc-name">Staff</div>
                  <div class="tc-desc">Admin & support</div>
                </div>
              </div>
              <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($_POST['type'] ?? '') ?>">
              <div id="typeError" class="text-danger mt-2" style="font-size:.8rem;display:none;"><i
                  class="fas fa-exclamation-circle me-1"></i>Please select a member type.</div>
            </div>
          </div>

          <!-- 2. Basic Information -->
          <div class="sec-card">
            <div class="sec-card-head">
              <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-user"></i></div>
              <div>
                <h6>Basic Information</h6>
                <p>Personal details of the member</p>
              </div>
            </div>
            <div class="sec-card-body">
              <div class="row g-3">
                <!-- Photo -->
                <div class="col-12 d-flex align-items-center gap-3 mb-1">
                  <label for="photoInput" class="photo-upload" id="photoBox" title="Upload photo">
                    <span id="photoPlaceholder">
                      <i class="fas fa-camera text-muted fa-lg"></i>
                      <div style="font-size:.64rem;color:#9ca3af;margin-top:4px;">Photo</div>
                    </span>
                  </label>
                  <input type="file" id="photoInput" name="profile_pic" accept=".jpg,.jpeg,.png,.webp" class="d-none"
                    onchange="previewPhoto(this)">
                  <div>
                    <label for="photoInput" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-upload me-1"></i>Upload Photo
                    </label>
                    <div style="font-size:.72rem;color:#9ca3af;margin-top:4px;">JPG/PNG/WebP · Max 2 MB · Optional</div>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Full Name <span
                      class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="name" id="nameInput"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Enter full name" required
                    oninput="updatePreview()">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Email Address</label>
                  <input type="email" class="form-control" name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="member@email.com">
                  <div class="form-text">Required for login access</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Phone</label>
                  <input type="tel" class="form-control" name="phone"
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="10-digit mobile">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Date of Birth</label>
                  <input type="date" class="form-control" name="dob" id="dobInput"
                    value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>" onchange="calcAge(this.value)">
                  <div id="ageDisplay" style="font-size:.72rem;color:var(--primary);margin-top:3px;"></div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Gender</label>
                  <select class="form-select" name="gender">
                    <option value="">— Select —</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                      <option value="<?= $g ?>" <?= (($_POST['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Blood Group</label>
                  <select class="form-select" name="blood_group">
                    <option value="">— Select —</option>
                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                      <option value="<?= $bg ?>" <?= (($_POST['blood_group'] ?? '') === $bg) ? 'selected' : '' ?>><?= $bg ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Aadhar Number</label>
                  <input type="text" class="form-control" name="aadhar"
                    value="<?= htmlspecialchars($_POST['aadhar'] ?? '') ?>" placeholder="12-digit Aadhar" maxlength="12"
                    pattern="\d{12}" oninput="this.value=this.value.replace(/\D/,'').slice(0,12)">
                  <div class="form-text">Optional · digits only</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Status</label>
                  <select class="form-select" name="status">
                    <option value="Active" <?= (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active
                    </option>
                    <option value="Inactive" <?= (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive
                    </option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Address</label>
                  <input type="text" class="form-control" name="address"
                    value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" placeholder="Home address (optional)">
                </div>
              </div>
            </div>
          </div>

          <!-- 3a. Student Details -->
          <div class="sec-card" id="studentFields" style="display:none;">
            <div class="sec-card-head">
              <div class="icon" style="background:#eaf4fd;color:#0C74C5;"><i class="fas fa-user-graduate"></i></div>
              <div>
                <h6>Student Details</h6>
                <p>Class, roll number and admission info</p>
              </div>
            </div>
            <div class="sec-card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Class</label>
                  <input type="text" class="form-control" name="class" id="classInput"
                    value="<?= htmlspecialchars($_POST['class'] ?? '') ?>" placeholder="e.g. 10, IX"
                    oninput="updatePreview()">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Section</label>
                  <input type="text" class="form-control" name="section"
                    value="<?= htmlspecialchars($_POST['section'] ?? '') ?>" placeholder="e.g. A, B">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Roll Number</label>
                  <input type="text" class="form-control" name="roll_number"
                    value="<?= htmlspecialchars($_POST['roll_number'] ?? '') ?>" placeholder="Roll No.">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Admission No.</label>
                  <input type="text" class="form-control" name="admission_number"
                    value="<?= htmlspecialchars($_POST['admission_number'] ?? '') ?>" placeholder="ADM-001">
                </div>
              </div>
            </div>
          </div>

          <!-- 3b. Teacher / Staff Details -->
          <div class="sec-card" id="teacherFields" style="display:none;">
            <div class="sec-card-head" id="teacherFieldsHead">
              <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-id-badge"></i></div>
              <div>
                <h6 id="empSectionTitle">Employment Details</h6>
                <p>Designation and employee information</p>
              </div>
            </div>
            <div class="sec-card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Employee ID</label>
                  <input type="text" class="form-control" name="employee_id"
                    value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>" placeholder="EMP-001">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Designation <span
                      class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="designation" id="designationInput"
                    value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>" placeholder="Math Teacher / Librarian"
                    oninput="updatePreview()">
                </div>
                <div class="col-md-4" id="assignedClassWrap">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Assigned Class</label>
                  <input type="text" class="form-control" name="assigned_class"
                    value="<?= htmlspecialchars($_POST['assigned_class'] ?? '') ?>" placeholder="Class 9-A">
                </div>
              </div>
            </div>
          </div>

          <!-- 4. Login Credentials -->
          <div class="sec-card">
            <div class="sec-card-head">
              <div class="icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-key"></i></div>
              <div>
                <h6>Login Credentials <span class="badge bg-secondary fw-normal ms-1"
                    style="font-size:.68rem;">Optional</span></h6>
                <p>Can be set now or later from the members list</p>
              </div>
            </div>
            <div class="sec-card-body">
              <div class="row g-3">
                <div class="col-md-5">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="set_password" name="set_password"
                      placeholder="Leave blank to set later" autocomplete="new-password" oninput="checkPwMatch()">
                    <button type="button" class="btn btn-outline-secondary"
                      onclick="togglePw('set_password','eyeIcon1')">
                      <i id="eyeIcon1" class="fas fa-eye"></i>
                    </button>
                  </div>
                  <div class="form-text">Min 6 characters</div>
                </div>
                <div class="col-md-5">
                  <label class="form-label fw-semibold" style="font-size:.84rem;">Confirm Password</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                      placeholder="Re-enter password" oninput="checkPwMatch()">
                    <button type="button" class="btn btn-outline-secondary"
                      onclick="togglePw('confirm_password','eyeIcon2')">
                      <i id="eyeIcon2" class="fas fa-eye"></i>
                    </button>
                  </div>
                  <div id="matchMsg"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 5. Save Actions -->
          <div class="sec-card">
            <div class="sec-card-body">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" name="after_save" value="list" class="btn btn-primary px-4">
                  <i class="fas fa-user-plus me-2"></i>Add Member
                </button>
                <button type="submit" name="after_save" value="view" class="btn btn-outline-primary px-3">
                  <i class="fas fa-eye me-2"></i>Add &amp; View
                </button>
                <button type="submit" name="after_save" value="another" class="btn btn-outline-success px-3">
                  <i class="fas fa-plus me-2"></i>Add Another
                </button>
                <a href="list.php" class="btn btn-outline-secondary px-3 ms-auto">
                  <i class="fas fa-times me-1"></i>Cancel
                </a>
              </div>
            </div>
          </div>

        </div><!-- /col-lg-8 -->

        <!-- ── Right: Live Preview ── -->
        <div class="col-lg-4 d-none d-lg-block">
          <div class="preview-card">
            <div style="padding:16px 18px;border-bottom:1px solid #f3f4f6;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9ca3af;">
                <i class="fas fa-eye me-1"></i>Live Preview
              </div>
            </div>
            <div style="padding:24px 18px;text-align:center;">
              <div class="preview-avatar" id="previewAvatar" style="width:64px;height:64px;margin:0 auto 12px;">
                <span id="previewInitial">?</span>
              </div>
              <div id="previewName" style="font-weight:700;font-size:1rem;color:#1f2937;">—</div>
              <div id="previewSub" style="font-size:.78rem;color:#9ca3af;margin-top:3px;">Select type above</div>
              <div id="previewBadge" class="mt-2"></div>
            </div>
            <div style="padding:0 18px 18px;">
              <div style="background:#f9fafb;border-radius:10px;padding:12px 14px;">
                <div class="d-flex justify-content-between align-items-center mb-2"
                  style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;">
                  <span>Details</span>
                </div>
                <div id="previewDetails" style="font-size:.8rem;color:#4b5563;">
                  <div class="d-flex align-items-center gap-2 mb-1" style="color:#d1d5db;">
                    <i class="fas fa-school" style="width:14px;"></i>
                    <span><?= htmlspecialchars($school_name) ?></span>
                  </div>
                  <div class="d-flex align-items-center gap-2 mb-1" id="prev-type-row" style="color:#d1d5db;">
                    <i class="fas fa-tag" style="width:14px;"></i><span>No type selected</span>
                  </div>
                  <div class="d-flex align-items-center gap-2" id="prev-extra-row" style="color:#d1d5db;">
                    <i class="fas fa-info-circle" style="width:14px;"></i><span>—</span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Checklist -->
            <div style="padding:0 18px 18px;">
              <div
                style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin-bottom:8px;">
                Checklist</div>
              <div id="chk-type" class="chk-item text-danger"><i class="fas fa-times me-2"></i>Type selected</div>
              <div id="chk-name" class="chk-item text-danger"><i class="fas fa-times me-2"></i>Name entered</div>
              <div id="chk-email" class="chk-item" style="color:#d1d5db;"><i class="fas fa-minus me-2"></i>Email
                (optional)</div>
            </div>
            <style>
              .chk-item {
                font-size: .78rem;
                margin-bottom: 5px;
                transition: .2s;
              }

              .chk-item.ok {
                color: #16a34a !important;
              }
            </style>
          </div>
        </div>

      </div><!-- /row -->
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ── Type selector ─────────────────────────────────────────────────────────
    const TYPE_COLORS = { Student: '#0C74C5', Teacher: '#16a34a', Staff: '#7c3aed' };
    const TYPE_ICONS = { Student: 'fa-user-graduate', Teacher: 'fa-chalkboard-teacher', Staff: 'fa-user-tie' };

    function selectType(t, el) {
      document.getElementById('typeInput').value = t;
      document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
      el.classList.add('selected');
      document.getElementById('typeError').style.display = 'none';

      const showStudent = (t === 'Student');
      const showTeacher = (t === 'Teacher' || t === 'Staff');
      document.getElementById('studentFields').style.display = showStudent ? '' : 'none';
      document.getElementById('teacherFields').style.display = showTeacher ? '' : 'none';

      if (t === 'Staff') {
        document.getElementById('assignedClassWrap').style.display = 'none';
        document.getElementById('empSectionTitle').textContent = 'Staff Details';
      } else if (t === 'Teacher') {
        document.getElementById('assignedClassWrap').style.display = '';
        document.getElementById('empSectionTitle').textContent = 'Employment Details';
      }
      updatePreview();
    }

    // ── Live preview ──────────────────────────────────────────────────────────
    function updatePreview() {
      const name = (document.getElementById('nameInput').value || '').trim();
      const type = document.getElementById('typeInput').value;
      const cls = document.getElementById('classInput') ? document.getElementById('classInput').value.trim() : '';
      const desg = document.getElementById('designationInput') ? document.getElementById('designationInput').value.trim() : '';

      // Avatar
      const av = document.getElementById('previewAvatar');
      const initEl = document.getElementById('previewInitial');
      if (!av.querySelector('img')) {
        initEl.textContent = name ? name[0].toUpperCase() : '?';
        av.style.background = type ? TYPE_COLORS[type] : '#9ca3af';
      }

      // Name
      document.getElementById('previewName').textContent = name || '—';

      // Sub label
      let sub = 'Select type above';
      if (type === 'Student') sub = cls ? 'Class ' + cls : 'Student';
      else if (type === 'Teacher') sub = desg || 'Teacher';
      else if (type === 'Staff') sub = desg || 'Staff Member';
      document.getElementById('previewSub').textContent = sub;

      // Badge
      const bdg = document.getElementById('previewBadge');
      if (type) {
        bdg.innerHTML = `<span class="badge" style="background:${TYPE_COLORS[type]};font-size:.72rem;">
      <i class="fas ${TYPE_ICONS[type]} me-1"></i>${type}</span>`;
      } else { bdg.innerHTML = ''; }

      // Details rows
      const typeRow = document.getElementById('prev-type-row');
      const extraRow = document.getElementById('prev-extra-row');
      if (type) {
        typeRow.style.color = '#4b5563';
        typeRow.innerHTML = `<i class="fas fa-tag" style="width:14px;color:${TYPE_COLORS[type]}"></i><span>${type}</span>`;
      } else {
        typeRow.style.color = '#d1d5db';
        typeRow.innerHTML = `<i class="fas fa-tag" style="width:14px;"></i><span>No type selected</span>`;
      }
      if (type === 'Student' && cls) {
        extraRow.style.color = '#4b5563';
        extraRow.innerHTML = `<i class="fas fa-chalkboard" style="width:14px;color:#0C74C5"></i><span>Class ${cls}</span>`;
      } else if ((type === 'Teacher' || type === 'Staff') && desg) {
        extraRow.style.color = '#4b5563';
        extraRow.innerHTML = `<i class="fas fa-id-badge" style="width:14px;color:${TYPE_COLORS[type]}"></i><span>${desg}</span>`;
      } else {
        extraRow.style.color = '#d1d5db';
        extraRow.innerHTML = `<i class="fas fa-info-circle" style="width:14px;"></i><span>—</span>`;
      }

      // Checklist
      setChk('chk-type', !!type);
      setChk('chk-name', !!name);
      const email = document.querySelector('[name="email"]').value.trim();
      const emailEl = document.getElementById('chk-email');
      if (email) {
        emailEl.className = 'chk-item ok';
        emailEl.innerHTML = '<i class="fas fa-check me-2"></i>Email provided';
      } else {
        emailEl.className = 'chk-item';
        emailEl.style.color = '#d1d5db';
        emailEl.innerHTML = '<i class="fas fa-minus me-2"></i>Email (optional)';
      }
    }

    function setChk(id, ok) {
      const el = document.getElementById(id);
      const label = { 'chk-type': 'Type selected', 'chk-name': 'Name entered' }[id];
      el.className = 'chk-item ' + (ok ? 'ok' : 'text-danger');
      el.innerHTML = `<i class="fas fa-${ok ? 'check' : 'times'} me-2"></i>${label}`;
    }

    // ── Age calculation ────────────────────────────────────────────────────────
    function calcAge(dob) {
      if (!dob) { document.getElementById('ageDisplay').textContent = ''; return; }
      const today = new Date(), birth = new Date(dob);
      let age = today.getFullYear() - birth.getFullYear();
      const m = today.getMonth() - birth.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
      document.getElementById('ageDisplay').textContent = age >= 0 ? `Age: ${age} years` : '';
    }

    // ── Photo preview ─────────────────────────────────────────────────────────
    function previewPhoto(input) {
      if (!input.files || !input.files[0]) return;
      const reader = new FileReader();
      reader.onload = (e) => {
        const box = document.getElementById('photoBox');
        box.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
        // Also update preview avatar
        const av = document.getElementById('previewAvatar');
        av.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:14px;">`;
      };
      reader.readAsDataURL(input.files[0]);
    }

    // ── Password helpers ──────────────────────────────────────────────────────
    function togglePw(id, iconId) {
      const el = document.getElementById(id);
      const ic = document.getElementById(iconId);
      el.type = el.type === 'password' ? 'text' : 'password';
      ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash');
    }
    function checkPwMatch() {
      const p = document.getElementById('set_password').value;
      const c = document.getElementById('confirm_password').value;
      const m = document.getElementById('matchMsg');
      if (!p || !c) { m.innerHTML = ''; return; }
      if (p === c) {
        m.innerHTML = '<span style="color:#16a34a;font-size:.72rem;"><i class="fas fa-check me-1"></i>Passwords match</span>';
      } else {
        m.innerHTML = '<span style="color:#dc2626;font-size:.72rem;"><i class="fas fa-times me-1"></i>Passwords do not match</span>';
      }
    }

    // ── Form submit validation ────────────────────────────────────────────────
    document.getElementById('addForm').addEventListener('submit', function (e) {
      if (!document.getElementById('typeInput').value) {
        e.preventDefault();
        document.getElementById('typeError').style.display = '';
        document.getElementById('typeError').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      const p = document.getElementById('set_password').value;
      const c = document.getElementById('confirm_password').value;
      if (p && p !== c) {
        e.preventDefault();
        document.getElementById('confirm_password').focus();
        checkPwMatch();
      }
    });

    // ── Init on page load (after validation error) ────────────────────────────
    window.addEventListener('DOMContentLoaded', function () {
      const t = document.getElementById('typeInput').value;
      if (t) {
        const card = document.querySelector(`.type-card.${t.toLowerCase()}`);
        if (card) card.classList.add('selected');
        document.getElementById('studentFields').style.display = (t === 'Student') ? '' : 'none';
        document.getElementById('teacherFields').style.display = (t === 'Teacher' || t === 'Staff') ? '' : 'none';
        if (t === 'Staff') {
          document.getElementById('assignedClassWrap').style.display = 'none';
          document.getElementById('empSectionTitle').textContent = 'Staff Details';
        }
      }
      updatePreview();
      // Attach email input to preview update
      document.querySelector('[name="email"]').addEventListener('input', updatePreview);
      // Calc age if dob already filled
      const dob = document.getElementById('dobInput').value;
      if (dob) calcAge(dob);
    });
  </script>
</body>

</html>