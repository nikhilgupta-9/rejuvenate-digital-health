<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM school_members WHERE id=? AND school_id=?");
$stmt->bind_param('ii', $id, $school_id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) { header("Location: list.php"); exit(); }

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']   ?? '');
    $email       = trim($_POST['email']  ?? '');
    $phone       = trim($_POST['phone']  ?? '');
    $dob         = $_POST['dob']         ?? null;
    $gender      = $_POST['gender']      ?? '';
    $address     = trim($_POST['address'] ?? '');
    $blood_group = $_POST['blood_group'] ?? '';
    $status      = $_POST['status']      ?? 'Active';

    $class            = trim($_POST['class']            ?? '');
    $section          = trim($_POST['section']          ?? '');
    $roll_number      = trim($_POST['roll_number']      ?? '');
    $admission_number = trim($_POST['admission_number'] ?? '');
    $employee_id      = trim($_POST['employee_id']      ?? '');
    $designation      = trim($_POST['designation']      ?? '');
    $assigned_class   = trim($_POST['assigned_class']   ?? '');

    if (!$name) { $error = "Name is required."; }
    elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = "Invalid email."; }
    else {
        if ($email) {
            $chk = $conn->prepare("SELECT id FROM school_members WHERE email=? AND school_id=? AND id!=?");
            $chk->bind_param('sii', $email, $school_id, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) { $error = "Another member with this email already exists."; }
        }
        if (!$error && $m['type'] === 'Student' && $roll_number) {
            $chk2 = $conn->prepare("SELECT id FROM school_members WHERE roll_number=? AND school_id=? AND id!=?");
            $chk2->bind_param('sii', $roll_number, $school_id, $id);
            $chk2->execute();
            if ($chk2->get_result()->num_rows > 0) { $error = "Roll number already used by another student."; }
        }
    }

    if (!$error) {
        $dob_val = $dob ?: null;
        $upd = $conn->prepare("UPDATE school_members SET name=?,email=?,phone=?,dob=?,gender=?,address=?,blood_group=?,status=?,class=?,section=?,roll_number=?,admission_number=?,employee_id=?,designation=?,assigned_class=? WHERE id=? AND school_id=?");
        $upd->bind_param('sssssssssssssssii',
            $name,$email,$phone,$dob_val,$gender,$address,$blood_group,$status,
            $class,$section,$roll_number,$admission_number,$employee_id,$designation,$assigned_class,
            $id,$school_id
        );
        if ($upd->execute()) {
            $success = "Member updated successfully.";
            $stmt2 = $conn->prepare("SELECT * FROM school_members WHERE id=?");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $m = $stmt2->get_result()->fetch_assoc();
        } else { $error = "Database error: " . $conn->error; }
    }
}

$type_color = ['Student' => '#0C74C5', 'Teacher' => '#16a34a', 'Staff' => '#7c3aed'];
$type_bg    = ['Student' => '#eaf4fd', 'Teacher' => '#f0fdf4', 'Staff' => '#f5f3ff'];
$type_icon  = ['Student' => 'fa-user-graduate', 'Teacher' => 'fa-chalkboard-teacher', 'Staff' => 'fa-user-tie'];
$tc = $type_color[$m['type']] ?? '#0C74C5';
$tb = $type_bg[$m['type']] ?? '#eaf4fd';
$ti = $type_icon[$m['type']] ?? 'fa-user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Edit Member</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    .sec-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
    .sec-card-head { padding:14px 20px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; gap:10px; }
    .sec-card-head .icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
    .sec-card-head h6 { margin:0; font-size:.9rem; font-weight:700; color:#1f2937; }
    .sec-card-head p  { margin:0; font-size:.73rem; color:#9ca3af; }
    .sec-card-body { padding:20px; }
    .form-label { font-size:.84rem; font-weight:600; color:#374151; margin-bottom:5px; }
  </style>
</head>
<body>
<?php $active_page = 'members'; $base_path = '../'; include '../inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div style="font-size:1rem;font-weight:600;color:#1f2937;">
      <i class="fas fa-edit me-2 text-primary"></i>Edit Member
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i><span class="d-none d-sm-inline">View</span></a>
    <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><span class="d-none d-sm-inline">Back</span></a>
    <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Member identity strip -->
  <div class="sec-card mb-4">
    <div class="sec-card-body" style="padding:16px 20px;">
      <div class="d-flex align-items-center gap-3">
        <div style="width:48px;height:48px;border-radius:13px;background:<?= $tc ?>;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;color:#fff;flex-shrink:0;">
          <?= strtoupper(substr($m['name'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-weight:700;font-size:.95rem;color:#1f2937;"><?= htmlspecialchars($m['name']) ?></div>
          <div class="d-flex align-items-center gap-2 mt-1">
            <span style="background:<?= $tb ?>;color:<?= $tc ?>;border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:700;">
              <i class="fas <?= $ti ?> me-1"></i><?= $m['type'] ?>
            </span>
            <span style="font-size:.75rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($m['member_uid']) ?></span>
          </div>
        </div>
        <div class="ms-auto d-none d-sm-block">
          <span style="font-size:.72rem;color:#9ca3af;">Editing member info — type cannot be changed</span>
        </div>
      </div>
    </div>
  </div>

  <form method="POST">

    <!-- Basic Information -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-user"></i></div>
        <div>
          <h6>Basic Information</h6>
          <p>Personal and contact details</p>
        </div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($m['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($m['email'] ?? '') ?>" placeholder="member@email.com">
            <div class="form-text">Required for login access</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Phone</label>
            <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($m['phone'] ?? '') ?>" placeholder="10-digit mobile">
          </div>
          <div class="col-md-4">
            <label class="form-label">Date of Birth</label>
            <input type="date" class="form-control" name="dob" value="<?= $m['dob'] ? date('Y-m-d', strtotime($m['dob'])) : '' ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender">
              <option value="">— Select —</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($m['gender'] === $g) ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Blood Group</label>
            <select class="form-select" name="blood_group">
              <option value="">— Select —</option>
              <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                <option value="<?= $bg ?>" <?= ($m['blood_group'] === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="Active"   <?= ($m['status'] === 'Active')   ? 'selected' : '' ?>>Active</option>
              <option value="Inactive" <?= ($m['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Address</label>
            <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($m['address'] ?? '') ?>" placeholder="Home address">
          </div>
        </div>
      </div>
    </div>

    <!-- Student-specific -->
    <?php if ($m['type'] === 'Student'): ?>
    <div class="sec-card">
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
            <label class="form-label">Class</label>
            <input type="text" class="form-control" name="class" value="<?= htmlspecialchars($m['class'] ?? '') ?>" placeholder="e.g. 10, IX">
          </div>
          <div class="col-md-3">
            <label class="form-label">Section</label>
            <input type="text" class="form-control" name="section" value="<?= htmlspecialchars($m['section'] ?? '') ?>" placeholder="e.g. A, B">
          </div>
          <div class="col-md-3">
            <label class="form-label">Roll Number</label>
            <input type="text" class="form-control" name="roll_number" value="<?= htmlspecialchars($m['roll_number'] ?? '') ?>" placeholder="Roll No.">
          </div>
          <div class="col-md-3">
            <label class="form-label">Admission No.</label>
            <input type="text" class="form-control" name="admission_number" value="<?= htmlspecialchars($m['admission_number'] ?? '') ?>" placeholder="ADM-001">
          </div>
        </div>
      </div>
    </div>

    <!-- Teacher / Staff-specific -->
    <?php elseif ($m['type'] === 'Teacher' || $m['type'] === 'Staff'): ?>
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-id-badge"></i></div>
        <div>
          <h6><?= $m['type'] === 'Teacher' ? 'Employment Details' : 'Staff Details' ?></h6>
          <p>Designation and employee information</p>
        </div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Employee ID</label>
            <input type="text" class="form-control" name="employee_id" value="<?= htmlspecialchars($m['employee_id'] ?? '') ?>" placeholder="EMP-001">
          </div>
          <div class="col-md-4">
            <label class="form-label">Designation</label>
            <input type="text" class="form-control" name="designation" value="<?= htmlspecialchars($m['designation'] ?? '') ?>" placeholder="Math Teacher / Librarian">
          </div>
          <?php if ($m['type'] === 'Teacher'): ?>
          <div class="col-md-4">
            <label class="form-label">Assigned Class</label>
            <input type="text" class="form-control" name="assigned_class" value="<?= htmlspecialchars($m['assigned_class'] ?? '') ?>" placeholder="Class 9-A">
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="sec-card">
      <div class="sec-card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save me-2"></i>Save Changes
          </button>
          <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary px-4">
            <i class="fas fa-times me-1"></i>Cancel
          </a>
          <a href="health-profile.php?id=<?= $id ?>" class="btn btn-outline-success px-3 ms-auto">
            <i class="fas fa-heartbeat me-1"></i>Health Profile
          </a>
        </div>
      </div>
    </div>

  </form>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
