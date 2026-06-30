<?php
include_once "../../config/connect.php";
include_once "auth.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: students.php"); exit(); }

$stmt = $conn->prepare("SELECT sm.* FROM school_members sm WHERE sm.id=? AND sm.school_id=? AND sm.type='Student'");
$stmt->bind_param('ii', $id, $teacher_school_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) { header("Location: students.php"); exit(); }

$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
$hp_stmt->bind_param('i', $id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $height_cm           = !empty($_POST['height_cm'])           ? (float)$_POST['height_cm']           : null;
    $weight_kg           = !empty($_POST['weight_kg'])           ? (float)$_POST['weight_kg']           : null;
    $vision              = trim($_POST['vision']              ?? '');
    $hearing             = trim($_POST['hearing']             ?? '');
    $dental              = trim($_POST['dental']              ?? '');
    $known_allergies     = trim($_POST['known_allergies']     ?? '');
    $chronic_conditions  = trim($_POST['chronic_conditions']  ?? '');
    $current_medications = trim($_POST['current_medications'] ?? '');
    $vaccination_status  = trim($_POST['vaccination_status']  ?? '');
    $emergency_contact   = trim($_POST['emergency_contact']   ?? '');
    $emergency_phone     = trim($_POST['emergency_phone']     ?? '');
    $notes               = trim($_POST['notes']               ?? '');

    if ($hp) {
        $upd = $conn->prepare("UPDATE member_health_profiles SET height_cm=?,weight_kg=?,vision=?,hearing=?,dental=?,known_allergies=?,chronic_conditions=?,current_medications=?,vaccination_status=?,emergency_contact=?,emergency_phone=?,notes=?,last_updated_by=?,last_updated_role='teacher',updated_at=NOW() WHERE member_id=? AND school_id=?");
        $upd->bind_param('ddssssssssssiii', $height_cm,$weight_kg,$vision,$hearing,$dental,$known_allergies,$chronic_conditions,$current_medications,$vaccination_status,$emergency_contact,$emergency_phone,$notes,$teacher_id,$id,$teacher_school_id);
        $ok = $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO member_health_profiles (member_id,school_id,height_cm,weight_kg,vision,hearing,dental,known_allergies,chronic_conditions,current_medications,vaccination_status,emergency_contact,emergency_phone,notes,last_updated_by,last_updated_role) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'teacher')");
        $ins->bind_param('iiddssssssssssi', $id,$teacher_school_id,$height_cm,$weight_kg,$vision,$hearing,$dental,$known_allergies,$chronic_conditions,$current_medications,$vaccination_status,$emergency_contact,$emergency_phone,$notes,$teacher_id);
        $ok = $ins->execute();
    }

    if ($ok) {
        $success = "Health profile saved successfully.";
        $hp_stmt->execute();
        $hp = $hp_stmt->get_result()->fetch_assoc();
    } else { $error = "Could not save: {$conn->error}"; }
}

$bmi = null; $bmi_lbl = ''; $bmi_col = '#6b7280';
if (!empty($hp['height_cm']) && !empty($hp['weight_kg'])) {
    $bmi = round($hp['weight_kg'] / (($hp['height_cm']/100)**2), 1);
    if      ($bmi < 18.5) { $bmi_lbl = 'Underweight'; $bmi_col = '#d97706'; }
    elseif  ($bmi < 25)   { $bmi_lbl = 'Normal';       $bmi_col = '#16a34a'; }
    elseif  ($bmi < 30)   { $bmi_lbl = 'Overweight';   $bmi_col = '#ea580c'; }
    else                  { $bmi_lbl = 'Obese';         $bmi_col = '#dc2626'; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Health Profile — <?= htmlspecialchars($student['name']) ?></title>
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
    .student-hero { background:var(--primary); border-radius:14px; color:#fff; padding:18px 22px; margin-bottom:20px; display:flex; align-items:center; gap:14px; position:relative; overflow:hidden; }
    .student-hero::after { content:''; position:absolute; right:-30px; top:-30px; width:140px; height:140px; background:rgba(255,255,255,.06); border-radius:50%; }
    .s-avatar { width:48px; height:48px; border-radius:12px; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:1.2rem; font-weight:700; flex-shrink:0; position:relative; z-index:1; }
  </style>
</head>
<body>
<?php $active_page = 'students'; $base_path = ''; include '../inc/sidebar-teacher.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;"><i class="fas fa-heartbeat me-2 text-danger"></i>Health Profile</div>
      <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($student['name']) ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="students.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><span class="d-none d-sm-inline">Back</span></a>
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <!-- Student banner -->
  <div class="student-hero">
    <div class="s-avatar"><?= strtoupper(substr($student['name'],0,1)) ?></div>
    <div style="position:relative;z-index:1;flex:1;min-width:0;">
      <div style="font-size:1.02rem;font-weight:700;"><?= htmlspecialchars($student['name']) ?></div>
      <div style="font-size:.78rem;opacity:.8;margin-top:2px;">
        <?= htmlspecialchars($student['member_uid']) ?>
        <?php if ($student['class']): ?> &middot; Class <?= htmlspecialchars($student['class']) ?><?= $student['section'] ? ' '.$student['section'] : '' ?><?php endif; ?>
        <?php if ($student['roll_number']): ?> &middot; Roll <?= htmlspecialchars($student['roll_number']) ?><?php endif; ?>
      </div>
    </div>
    <?php if ($bmi): ?>
    <div style="position:relative;z-index:1;text-align:right;flex-shrink:0;">
      <div style="font-size:1.3rem;font-weight:700;"><?= $bmi ?></div>
      <div style="font-size:.65rem;opacity:.75;text-transform:uppercase;letter-spacing:.05em;">BMI &middot; <?= $bmi_lbl ?></div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" style="border-radius:12px;">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" style="border-radius:12px;">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <form method="POST">

    <!-- Physical -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#eaf4fd;color:#0C74C5;"><i class="fas fa-ruler"></i></div>
        <div><h6>Physical Measurements</h6><p>Height, weight, vision, hearing, dental</p></div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-3 col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Height (cm)</label>
            <input type="number" step="0.1" min="50" max="250" class="form-control" name="height_cm"
              value="<?= htmlspecialchars($hp['height_cm'] ?? '') ?>" placeholder="165.0">
          </div>
          <div class="col-md-3 col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Weight (kg)</label>
            <input type="number" step="0.1" min="10" max="300" class="form-control" name="weight_kg"
              value="<?= htmlspecialchars($hp['weight_kg'] ?? '') ?>" placeholder="60.0">
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Vision</label>
            <input type="text" class="form-control" name="vision"
              value="<?= htmlspecialchars($hp['vision'] ?? '') ?>" placeholder="6/6">
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Hearing</label>
            <input type="text" class="form-control" name="hearing"
              value="<?= htmlspecialchars($hp['hearing'] ?? '') ?>" placeholder="Normal">
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Dental</label>
            <input type="text" class="form-control" name="dental"
              value="<?= htmlspecialchars($hp['dental'] ?? '') ?>" placeholder="Good">
          </div>
        </div>

        <?php if ($bmi): ?>
        <div class="mt-3 p-3" style="background:#f9fafb;border-radius:10px;">
          <div class="d-flex justify-content-between mb-1">
            <span style="font-size:.8rem;font-weight:600;color:#374151;">BMI <?= $bmi ?></span>
            <span style="font-size:.78rem;font-weight:600;color:<?= $bmi_col ?>;"><?= $bmi_lbl ?></span>
          </div>
          <div style="height:7px;border-radius:4px;background:#e5e7eb;overflow:hidden;">
            <div style="height:100%;border-radius:4px;background:linear-gradient(90deg,#16a34a,#eab308,#ea580c,#dc2626);width:<?= min(100,max(5,(($bmi-10)/30)*100)) ?>%"></div>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:.62rem;color:#9ca3af;">
            <span>Underweight</span><span>Normal</span><span>Overweight</span><span>Obese</span>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Medical History -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#fff5f5;color:#dc2626;"><i class="fas fa-notes-medical"></i></div>
        <div><h6>Medical History</h6><p>Allergies, conditions, medications, vaccinations</p></div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Known Allergies</label>
            <textarea class="form-control" name="known_allergies" rows="2" placeholder="e.g. Penicillin, Dust, Peanuts"><?= htmlspecialchars($hp['known_allergies'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Chronic Conditions</label>
            <textarea class="form-control" name="chronic_conditions" rows="2" placeholder="e.g. Asthma, Diabetes"><?= htmlspecialchars($hp['chronic_conditions'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Current Medications</label>
            <textarea class="form-control" name="current_medications" rows="2" placeholder="Name, dosage, frequency"><?= htmlspecialchars($hp['current_medications'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Vaccination Status</label>
            <textarea class="form-control" name="vaccination_status" rows="2" placeholder="COVID: ✓, Hepatitis B: ✓ …"><?= htmlspecialchars($hp['vaccination_status'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Emergency Contact -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#fffbeb;color:#d97706;"><i class="fas fa-phone-alt"></i></div>
        <div><h6>Emergency Contact</h6><p>Parent or guardian contact information</p></div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Contact Name</label>
            <input type="text" class="form-control" name="emergency_contact"
              value="<?= htmlspecialchars($hp['emergency_contact'] ?? '') ?>" placeholder="Parent / Guardian name">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" style="font-size:.82rem;">Contact Phone</label>
            <input type="text" class="form-control" name="emergency_phone"
              value="<?= htmlspecialchars($hp['emergency_phone'] ?? '') ?>" placeholder="10-digit mobile">
          </div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#f3f4f6;color:#6b7280;"><i class="fas fa-sticky-note"></i></div>
        <div><h6>Additional Notes</h6><p>Teacher observations and remarks</p></div>
      </div>
      <div class="sec-card-body">
        <textarea class="form-control" name="notes" rows="3"
          placeholder="Any additional health observations or remarks…"><?= htmlspecialchars($hp['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="d-flex gap-2 mb-4">
      <button type="submit" class="btn btn-success px-5 fw-semibold">
        <i class="fas fa-save me-2"></i>Save Health Profile
      </button>
      <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>

  <?php if ($hp && $hp['updated_at']): ?>
  <div class="text-center mb-4" style="font-size:.74rem;color:#9ca3af;">
    Last updated <?= date('d M Y, h:i A', strtotime($hp['updated_at'])) ?>
    <?= $hp['last_updated_role'] ? ' by ' . ucfirst(htmlspecialchars($hp['last_updated_role'])) : '' ?>
  </div>
  <?php endif; ?>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
