<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

$stmt = $conn->prepare("SELECT id, name, type, member_uid, dob, blood_group FROM school_members WHERE id=? AND school_id=?");
$stmt->bind_param('ii', $id, $school_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) { header("Location: list.php"); exit(); }

$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
$hp_stmt->bind_param('i', $id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $height_cm          = $_POST['height_cm']           ?? null;
    $weight_kg          = $_POST['weight_kg']           ?? null;
    $vision             = trim($_POST['vision']          ?? '');
    $hearing            = trim($_POST['hearing']         ?? '');
    $dental             = trim($_POST['dental']          ?? '');
    $known_allergies    = trim($_POST['known_allergies'] ?? '');
    $chronic_conditions = trim($_POST['chronic_conditions'] ?? '');
    $current_medications= trim($_POST['current_medications'] ?? '');
    $vaccination_status = trim($_POST['vaccination_status'] ?? '');
    $emergency_contact  = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone    = trim($_POST['emergency_phone'] ?? '');
    $notes              = trim($_POST['notes'] ?? '');

    if ($hp) {
        $upd = $conn->prepare("UPDATE member_health_profiles SET height_cm=?,weight_kg=?,vision=?,hearing=?,dental=?,known_allergies=?,chronic_conditions=?,current_medications=?,vaccination_status=?,emergency_contact=?,emergency_phone=?,notes=?,updated_at=NOW() WHERE member_id=? AND school_id=?");
        $upd->bind_param('ddssssssssssii', $height_cm,$weight_kg,$vision,$hearing,$dental,$known_allergies,$chronic_conditions,$current_medications,$vaccination_status,$emergency_contact,$emergency_phone,$notes,$id,$school_id);
        $ok = $upd->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO member_health_profiles (member_id,school_id,height_cm,weight_kg,vision,hearing,dental,known_allergies,chronic_conditions,current_medications,vaccination_status,emergency_contact,emergency_phone,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->bind_param('iiddssssssssss', $id,$school_id,$height_cm,$weight_kg,$vision,$hearing,$dental,$known_allergies,$chronic_conditions,$current_medications,$vaccination_status,$emergency_contact,$emergency_phone,$notes);
        $ok = $ins->execute();
    }

    if ($ok) {
        $success = "Health profile saved successfully.";
        $hp_s2 = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
        $hp_s2->bind_param('i', $id);
        $hp_s2->execute();
        $hp = $hp_s2->get_result()->fetch_assoc();
    } else { $error = "Error saving: " . $conn->error; }
}

$bmi = null;
$h = $hp['height_cm'] ?? null;
$w = $hp['weight_kg'] ?? null;
if ($h && $w) { $bmi = round($w / (($h / 100) * ($h / 100)), 1); }

$type_color = ['Student' => '#0C74C5', 'Teacher' => '#16a34a', 'Staff' => '#7c3aed'];
$type_bg    = ['Student' => '#eaf4fd', 'Teacher' => '#f0fdf4', 'Staff' => '#f5f3ff'];
$type_icon  = ['Student' => 'fa-user-graduate', 'Teacher' => 'fa-chalkboard-teacher', 'Staff' => 'fa-user-tie'];
$tc = $type_color[$member['type']] ?? '#0C74C5';
$tb = $type_bg[$member['type']]    ?? '#eaf4fd';
$ti = $type_icon[$member['type']]  ?? 'fa-user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Health Profile</title>
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
    .bmi-stat { background:#f9fafb; border-radius:10px; padding:14px; text-align:center; }
    .bmi-stat .bmi-val { font-size:1.4rem; font-weight:700; }
    .bmi-stat .bmi-lbl { font-size:.72rem; color:#9ca3af; margin-top:3px; }
  </style>
</head>
<body>
<?php $active_page = 'health'; $base_path = '../'; include '../inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div style="font-size:1rem;font-weight:600;color:#1f2937;">
      <i class="fas fa-heartbeat me-2 text-danger"></i>Health Profile
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-user me-1"></i><span class="d-none d-sm-inline">Member</span></a>
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
          <?= strtoupper(substr($member['name'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-weight:700;font-size:.95rem;color:#1f2937;"><?= htmlspecialchars($member['name']) ?></div>
          <div class="d-flex align-items-center gap-2 mt-1">
            <span style="background:<?= $tb ?>;color:<?= $tc ?>;border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:700;">
              <i class="fas <?= $ti ?> me-1"></i><?= $member['type'] ?>
            </span>
            <span style="font-size:.75rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($member['member_uid']) ?></span>
            <?php if ($member['blood_group']): ?>
              <span style="background:#fef2f2;color:#ef4444;border-radius:20px;padding:2px 8px;font-size:.7rem;font-weight:700;">
                <i class="fas fa-tint me-1"></i><?= htmlspecialchars($member['blood_group']) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($bmi): ?>
        <div class="ms-auto text-end d-none d-sm-block">
          <?php
            if ($bmi < 18.5)     { $bmi_color = '#f59e0b'; $bmi_label = 'Underweight'; }
            elseif ($bmi < 25)   { $bmi_color = '#16a34a'; $bmi_label = 'Normal'; }
            elseif ($bmi < 30)   { $bmi_color = '#f59e0b'; $bmi_label = 'Overweight'; }
            else                 { $bmi_color = '#ef4444'; $bmi_label = 'Obese'; }
          ?>
          <div style="font-size:1.1rem;font-weight:700;color:<?= $bmi_color ?>;"><?= $bmi ?></div>
          <div style="font-size:.68rem;color:#9ca3af;">BMI · <?= $bmi_label ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <form method="POST" id="healthForm">

    <!-- Physical Measurements -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-ruler-combined"></i></div>
        <div>
          <h6>Physical Measurements</h6>
          <p>Height, weight and sensory checks</p>
        </div>
      </div>
      <div class="sec-card-body">
        <?php if ($bmi): ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-sm-3">
            <div class="bmi-stat">
              <div class="bmi-val" style="color:var(--primary);"><?= $hp['height_cm'] ?> <span style="font-size:.7rem;">cm</span></div>
              <div class="bmi-lbl">Height</div>
            </div>
          </div>
          <div class="col-6 col-sm-3">
            <div class="bmi-stat">
              <div class="bmi-val" style="color:#16a34a;"><?= $hp['weight_kg'] ?> <span style="font-size:.7rem;">kg</span></div>
              <div class="bmi-lbl">Weight</div>
            </div>
          </div>
          <div class="col-6 col-sm-3">
            <div class="bmi-stat">
              <div class="bmi-val" style="color:<?= $bmi_color ?? '#374151' ?>;"><?= $bmi ?></div>
              <div class="bmi-lbl">BMI · <?= $bmi_label ?? '' ?></div>
            </div>
          </div>
          <?php if ($hp['blood_pressure']): ?>
          <div class="col-6 col-sm-3">
            <div class="bmi-stat">
              <div class="bmi-val" style="color:#ef4444;"><?= htmlspecialchars($hp['blood_pressure']) ?></div>
              <div class="bmi-lbl">Blood Pressure</div>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Height (cm)</label>
            <input type="number" step="0.1" class="form-control" name="height_cm" id="heightInput"
              value="<?= $hp['height_cm'] ?? '' ?>" placeholder="e.g. 165.5" oninput="calcBMI()">
          </div>
          <div class="col-md-3">
            <label class="form-label">Weight (kg)</label>
            <input type="number" step="0.1" class="form-control" name="weight_kg" id="weightInput"
              value="<?= $hp['weight_kg'] ?? '' ?>" placeholder="e.g. 60.0" oninput="calcBMI()">
            <div id="bmiLive" style="font-size:.72rem;margin-top:4px;"></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Vision</label>
            <input type="text" class="form-control" name="vision" value="<?= htmlspecialchars($hp['vision'] ?? '') ?>" placeholder="e.g. 6/6 Normal">
          </div>
          <div class="col-md-3">
            <label class="form-label">Hearing</label>
            <input type="text" class="form-control" name="hearing" value="<?= htmlspecialchars($hp['hearing'] ?? '') ?>" placeholder="e.g. Normal">
          </div>
          <div class="col-md-3">
            <label class="form-label">Dental</label>
            <input type="text" class="form-control" name="dental" value="<?= htmlspecialchars($hp['dental'] ?? '') ?>" placeholder="e.g. Good">
          </div>
        </div>
      </div>
    </div>

    <!-- Medical History -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-notes-medical"></i></div>
        <div>
          <h6>Medical History</h6>
          <p>Allergies, conditions and medications</p>
        </div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Known Allergies</label>
            <textarea class="form-control" name="known_allergies" rows="2" placeholder="e.g. Peanuts, Dust mites, Penicillin"><?= htmlspecialchars($hp['known_allergies'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Chronic Conditions</label>
            <textarea class="form-control" name="chronic_conditions" rows="2" placeholder="e.g. Asthma, Diabetes, Hypertension"><?= htmlspecialchars($hp['chronic_conditions'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Current Medications</label>
            <textarea class="form-control" name="current_medications" rows="2" placeholder="e.g. Salbutamol inhaler, Metformin"><?= htmlspecialchars($hp['current_medications'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Vaccination Status</label>
            <textarea class="form-control" name="vaccination_status" rows="2" placeholder="e.g. COVID-19 fully vaccinated, BCG, MMR"><?= htmlspecialchars($hp['vaccination_status'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Emergency Contact -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-phone-alt"></i></div>
        <div>
          <h6>Emergency Contact</h6>
          <p>Parent or guardian reachable in emergencies</p>
        </div>
      </div>
      <div class="sec-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Contact Name</label>
            <input type="text" class="form-control" name="emergency_contact" value="<?= htmlspecialchars($hp['emergency_contact'] ?? '') ?>" placeholder="Parent / Guardian name">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Phone</label>
            <input type="tel" class="form-control" name="emergency_phone" value="<?= htmlspecialchars($hp['emergency_phone'] ?? '') ?>" placeholder="+91 XXXXX XXXXX">
          </div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-sticky-note"></i></div>
        <div>
          <h6>Additional Notes</h6>
          <p>Any other health observations</p>
        </div>
      </div>
      <div class="sec-card-body">
        <textarea class="form-control" name="notes" rows="3" placeholder="Any additional health notes or observations..."><?= htmlspecialchars($hp['notes'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Actions -->
    <div class="sec-card">
      <div class="sec-card-body">
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-success px-4">
            <i class="fas fa-save me-2"></i>Save Health Profile
          </button>
          <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary px-4">
            <i class="fas fa-times me-1"></i>Cancel
          </a>
        </div>
      </div>
    </div>

  </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function calcBMI() {
    const h = parseFloat(document.getElementById('heightInput').value);
    const w = parseFloat(document.getElementById('weightInput').value);
    const el = document.getElementById('bmiLive');
    if (h > 0 && w > 0) {
      const bmi = (w / ((h / 100) * (h / 100))).toFixed(1);
      let label, color;
      if (bmi < 18.5)      { label = 'Underweight'; color = '#f59e0b'; }
      else if (bmi < 25)   { label = 'Normal';       color = '#16a34a'; }
      else if (bmi < 30)   { label = 'Overweight';   color = '#f59e0b'; }
      else                 { label = 'Obese';         color = '#ef4444'; }
      el.innerHTML = `<span style="color:${color};font-weight:700;">BMI: ${bmi}</span> <span style="color:#9ca3af;">— ${label}</span>`;
    } else {
      el.innerHTML = '';
    }
  }
  calcBMI();
</script>
</body>
</html>
