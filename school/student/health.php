<?php
include_once "../../config/connect.php";
include_once "auth.php";

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
$hp_stmt->bind_param('i', $student_id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $height_cm           = !empty($_POST['height_cm'])           ? (float)$_POST['height_cm']           : null;
    $weight_kg           = !empty($_POST['weight_kg'])           ? (float)$_POST['weight_kg']           : null;
    $blood_group         = trim($_POST['blood_group']         ?? '');
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

    /* Validate emergency phone if filled */
    if ($emergency_phone && !preg_match('/^[6-9]\d{9}$/', $emergency_phone)) {
        $error = "Emergency phone must be a valid 10-digit mobile number.";
    } else {
        if ($hp) {
            $upd = $conn->prepare("
                UPDATE member_health_profiles
                SET height_cm=?, weight_kg=?, vision=?, hearing=?, dental=?,
                    known_allergies=?, chronic_conditions=?, current_medications=?,
                    vaccination_status=?, emergency_contact=?, emergency_phone=?,
                    notes=?, last_updated_by=?, last_updated_role='student', updated_at=NOW()
                WHERE member_id=? AND school_id=?");
            $upd->bind_param('ddssssssssssiii',
                $height_cm, $weight_kg, $vision, $hearing, $dental,
                $known_allergies, $chronic_conditions, $current_medications,
                $vaccination_status, $emergency_contact, $emergency_phone,
                $notes, $student_id, $student_id, $student_school_id);
            $ok = $upd->execute();
        } else {
            $ins = $conn->prepare("
                INSERT INTO member_health_profiles
                    (member_id, school_id, height_cm, weight_kg, vision, hearing, dental,
                     known_allergies, chronic_conditions, current_medications,
                     vaccination_status, emergency_contact, emergency_phone,
                     notes, last_updated_by, last_updated_role)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'student')");
            $ins->bind_param('iiddssssssssssi',
                $student_id, $student_school_id,
                $height_cm, $weight_kg, $vision, $hearing, $dental,
                $known_allergies, $chronic_conditions, $current_medications,
                $vaccination_status, $emergency_contact, $emergency_phone,
                $notes, $student_id);
            $ok = $ins->execute();
        }

        if ($ok) {
            $success = "Health profile saved successfully!";
            $hp_stmt->execute();
            $hp = $hp_stmt->get_result()->fetch_assoc();
        } else {
            $error = "Could not save: {$conn->error}";
        }

        /* Also update blood_group on school_members */
        if ($blood_group) {
            $bg = $conn->prepare("UPDATE school_members SET blood_group=? WHERE id=?");
            $bg->bind_param('si', $blood_group, $student_id);
            $bg->execute();
            $student['blood_group'] = $blood_group;
        }
    }
}

/* BMI */
$bmi = null; $bmi_lbl = ''; $bmi_col = '#6b7280'; $bmi_pct = 0;
if (!empty($hp['height_cm']) && !empty($hp['weight_kg'])) {
    $bmi = round($hp['weight_kg'] / (($hp['height_cm']/100)**2), 1);
    $bmi_pct = min(100, max(5, ($bmi/40)*100));
    if      ($bmi < 18.5) { $bmi_lbl = 'Underweight'; $bmi_col = '#d97706'; }
    elseif  ($bmi < 25)   { $bmi_lbl = 'Normal';       $bmi_col = '#16a34a'; }
    elseif  ($bmi < 30)   { $bmi_lbl = 'Overweight';   $bmi_col = '#ea580c'; }
    else                  { $bmi_lbl = 'Obese';         $bmi_col = '#dc2626'; }
}

$blood_groups = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Health | <?= htmlspecialchars($student_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    :root { --primary: #0C74C5; --accent: #02c9b8; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }

    .s-topnav {
      background: var(--primary); color: #fff; padding: 0 16px; height: 58px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 10px rgba(12,116,197,.3);
    }
    .s-topnav .brand { font-size: .9rem; font-weight: 700; }
    .s-topnav .sub   { font-size: .68rem; opacity: .75; }

    .s-body { max-width: 700px; margin: 0 auto; padding: 18px 14px 90px; }

    /* Summary tiles */
    .health-summary {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-bottom: 18px;
    }
    .h-tile {
      background: #fff; border-radius: 12px; padding: 12px 10px;
      text-align: center; box-shadow: 0 1px 6px rgba(0,0,0,.07);
    }
    .h-tile .h-icon { font-size: 1.1rem; margin-bottom: 4px; }
    .h-tile .h-val  { font-size: .98rem; font-weight: 700; color: #1f2937; }
    .h-tile .h-lbl  { font-size: .6rem;  color: #9ca3af; margin-top: 2px; }

    /* BMI strip */
    .bmi-strip {
      background: #fff; border-radius: 12px; padding: 14px 16px;
      margin-bottom: 18px; box-shadow: 0 1px 6px rgba(0,0,0,.07);
    }
    .bmi-bar-wrap { height: 8px; border-radius: 4px; background: #e5e7eb; overflow: hidden; margin: 6px 0 3px; }
    .bmi-bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #16a34a, #eab308, #ea580c, #dc2626); }

    /* Section cards */
    .s-card { background: #fff; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
    .s-card-title {
      font-size: .7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .8px; color: #6b7280;
      display: flex; align-items: center; gap: 7px;
      margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6;
    }

    /* Input tweaks */
    .form-control, .form-select { font-size: .85rem; }
    .form-label { font-size: .8rem; font-weight: 600; margin-bottom: 4px; }

    /* Blood group chips */
    .blood-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .blood-chip {
      padding: 6px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600;
      border: 1.5px solid #e5e7eb; cursor: pointer; transition: .15s;
      background: #fff; color: #374151; user-select: none;
    }
    .blood-chip:hover  { border-color: #dc2626; color: #dc2626; }
    .blood-chip.sel    { background: #dc2626; color: #fff; border-color: #dc2626; }

    /* Bottom nav */
    .s-bottomnav {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #fff; border-top: 1px solid #e5e7eb;
      display: flex; z-index: 99;
      box-shadow: 0 -2px 10px rgba(0,0,0,.06);
    }
    .s-bottomnav a {
      flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 9px 4px; text-decoration: none; color: #9ca3af;
      font-size: .58rem; font-weight: 600; gap: 3px; transition: color .15s;
    }
    .s-bottomnav a i { font-size: 1.05rem; }
    .s-bottomnav a.active { color: var(--primary); }

    @media (max-width: 420px) {
      .health-summary { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<nav class="s-topnav">
  <div>
    <div class="brand"><i class="fas fa-heartbeat me-2" style="color:var(--accent);"></i>My Health Record</div>
    <div class="sub"><?= htmlspecialchars($student_school) ?></div>
  </div>
  <a href="logout.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:none;font-size:.76rem;">
    <i class="fas fa-sign-out-alt me-1"></i><span class="d-none d-sm-inline">Logout</span>
  </a>
</nav>

<div class="s-body">

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" style="border-radius:12px;font-size:.83rem;">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" style="border-radius:12px;font-size:.83rem;">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Health summary tiles -->
  <?php if ($hp || !empty($student['blood_group'])): ?>
  <div class="health-summary">
    <div class="h-tile">
      <div class="h-icon" style="color:#0C74C5;"><i class="fas fa-ruler-vertical"></i></div>
      <div class="h-val"><?= $hp['height_cm'] ? $hp['height_cm'].'<span style="font-size:.65rem;font-weight:400;"> cm</span>' : '—' ?></div>
      <div class="h-lbl">Height</div>
    </div>
    <div class="h-tile">
      <div class="h-icon" style="color:#7c3aed;"><i class="fas fa-weight"></i></div>
      <div class="h-val"><?= $hp['weight_kg'] ? $hp['weight_kg'].'<span style="font-size:.65rem;font-weight:400;"> kg</span>' : '—' ?></div>
      <div class="h-lbl">Weight</div>
    </div>
    <div class="h-tile">
      <div class="h-icon" style="color:<?= $bmi_col ?>;"><i class="fas fa-chart-bar"></i></div>
      <div class="h-val" style="color:<?= $bmi_col ?>;"><?= $bmi ?? '—' ?></div>
      <div class="h-lbl">BMI<?= $bmi_lbl ? ' · '.$bmi_lbl : '' ?></div>
    </div>
    <div class="h-tile">
      <div class="h-icon" style="color:#dc2626;"><i class="fas fa-tint"></i></div>
      <div class="h-val"><?= !empty($student['blood_group']) ? htmlspecialchars($student['blood_group']) : '—' ?></div>
      <div class="h-lbl">Blood Group</div>
    </div>
  </div>

  <?php if ($bmi): ?>
  <div class="bmi-strip">
    <div class="d-flex justify-content-between align-items-center">
      <span style="font-size:.82rem;font-weight:600;color:#374151;">Body Mass Index</span>
      <span style="font-size:.82rem;font-weight:700;color:<?= $bmi_col ?>;"><?= $bmi ?> · <?= $bmi_lbl ?></span>
    </div>
    <div class="bmi-bar-wrap"><div class="bmi-bar" style="width:<?= $bmi_pct ?>%"></div></div>
    <div class="d-flex justify-content-between" style="font-size:.6rem;color:#9ca3af;">
      <span>Underweight</span><span>Normal</span><span>Overweight</span><span>Obese</span>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($hp && $hp['updated_at']): ?>
    <div style="font-size:.71rem;color:#9ca3af;margin-bottom:14px;text-align:right;">
      <i class="fas fa-clock me-1"></i>Last updated <?= date('d M Y, h:i A', strtotime($hp['updated_at'])) ?>
      <?= $hp['last_updated_role'] ? ' by ' . ucfirst($hp['last_updated_role']) : '' ?>
    </div>
  <?php endif; ?>

  <!-- FORM -->
  <form method="POST" id="healthForm">

    <!-- Physical Measurements -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-ruler" style="color:#0C74C5;"></i>Physical Measurements</div>
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Height (cm)</label>
          <input type="number" step="0.1" min="50" max="250" class="form-control" name="height_cm"
            id="inp_height" value="<?= htmlspecialchars($hp['height_cm'] ?? '') ?>"
            placeholder="e.g. 165" oninput="calcBmi()">
        </div>
        <div class="col-6">
          <label class="form-label">Weight (kg)</label>
          <input type="number" step="0.1" min="10" max="300" class="form-control" name="weight_kg"
            id="inp_weight" value="<?= htmlspecialchars($hp['weight_kg'] ?? '') ?>"
            placeholder="e.g. 60" oninput="calcBmi()">
        </div>
        <div class="col-6">
          <label class="form-label">Vision</label>
          <input type="text" class="form-control" name="vision"
            value="<?= htmlspecialchars($hp['vision'] ?? '') ?>" placeholder="e.g. 6/6">
        </div>
        <div class="col-6">
          <label class="form-label">Hearing</label>
          <input type="text" class="form-control" name="hearing"
            value="<?= htmlspecialchars($hp['hearing'] ?? '') ?>" placeholder="Normal">
        </div>
        <div class="col-12">
          <label class="form-label">Dental Health</label>
          <input type="text" class="form-control" name="dental"
            value="<?= htmlspecialchars($hp['dental'] ?? '') ?>" placeholder="e.g. Good, cavity on lower right">
        </div>
      </div>

      <!-- Live BMI preview -->
      <div id="bmiPreview" style="display:none;margin-top:14px;padding:10px 14px;background:#f9fafb;border-radius:10px;">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span style="font-size:.8rem;font-weight:600;color:#374151;">BMI Preview</span>
          <span id="bmiVal" style="font-size:.82rem;font-weight:700;"></span>
        </div>
        <div style="height:6px;border-radius:3px;background:#e5e7eb;overflow:hidden;">
          <div id="bmiBar" style="height:100%;border-radius:3px;background:linear-gradient(90deg,#16a34a,#eab308,#ea580c,#dc2626);transition:width .3s;width:0%"></div>
        </div>
      </div>
    </div>

    <!-- Blood Group -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-tint" style="color:#dc2626;"></i>Blood Group</div>
      <input type="hidden" name="blood_group" id="blood_group_val" value="<?= htmlspecialchars($student['blood_group'] ?? '') ?>">
      <div class="blood-chips">
        <?php foreach ($blood_groups as $bg): ?>
          <div class="blood-chip <?= ($student['blood_group'] ?? '') === $bg ? 'sel' : '' ?>"
               onclick="selectBlood('<?= $bg ?>')">
            <?= $bg ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Medical History -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-notes-medical" style="color:#dc2626;"></i>Medical History</div>
      <div class="mb-3">
        <label class="form-label">Known Allergies</label>
        <textarea class="form-control" name="known_allergies" rows="2"
          placeholder="e.g. Penicillin, dust, peanuts — or leave blank if none"><?= htmlspecialchars($hp['known_allergies'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Chronic Conditions</label>
        <textarea class="form-control" name="chronic_conditions" rows="2"
          placeholder="e.g. Asthma, Diabetes — or leave blank"><?= htmlspecialchars($hp['chronic_conditions'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="form-label">Current Medications</label>
        <textarea class="form-control" name="current_medications" rows="2"
          placeholder="Name, dosage, frequency — or leave blank"><?= htmlspecialchars($hp['current_medications'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Vaccination -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-syringe" style="color:#16a34a;"></i>Vaccination Status</div>
      <textarea class="form-control" name="vaccination_status" rows="3"
        placeholder="List vaccines received e.g. COVID-19: ✓ (Jan 2022), Hepatitis B: ✓, BCG: ✓ …"><?= htmlspecialchars($hp['vaccination_status'] ?? '') ?></textarea>
    </div>

    <!-- Emergency Contact -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-phone-alt" style="color:#d97706;"></i>Emergency Contact</div>
      <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Guardian / Parent Name</label>
          <input type="text" class="form-control" name="emergency_contact"
            value="<?= htmlspecialchars($hp['emergency_contact'] ?? '') ?>" placeholder="Full name">
        </div>
        <div class="col-6">
          <label class="form-label">Phone Number</label>
          <input type="text" class="form-control" name="emergency_phone" inputmode="numeric" maxlength="10"
            value="<?= htmlspecialchars($hp['emergency_phone'] ?? '') ?>" placeholder="10-digit mobile">
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-sticky-note" style="color:#6b7280;"></i>Additional Notes</div>
      <textarea class="form-control" name="notes" rows="3"
        placeholder="Anything else your school should know about your health…"><?= htmlspecialchars($hp['notes'] ?? '') ?></textarea>
    </div>

    <div class="d-grid mb-3">
      <button type="submit" class="btn btn-primary fw-semibold py-3" style="border-radius:12px;font-size:.95rem;">
        <i class="fas fa-save me-2"></i>Save Health Record
      </button>
    </div>

    <p style="font-size:.7rem;color:#9ca3af;text-align:center;">
      <i class="fas fa-info-circle me-1"></i>Your school admin or teacher may also update your health record.
    </p>
  </form>

</div>

<!-- Bottom Navigation -->
<nav class="s-bottomnav">
  <a href="dashboard.php"><i class="fas fa-home"></i>Home</a>
  <a href="health.php" class="active"><i class="fas fa-heartbeat"></i>Health</a>
  <a href="abha.php"><i class="fas fa-id-card"></i>ABHA</a>
  <a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectBlood(bg) {
  document.querySelectorAll('.blood-chip').forEach(c => c.classList.remove('sel'));
  event.currentTarget.classList.add('sel');
  document.getElementById('blood_group_val').value = bg;
}

function calcBmi() {
  const h = parseFloat(document.getElementById('inp_height').value);
  const w = parseFloat(document.getElementById('inp_weight').value);
  const prev = document.getElementById('bmiPreview');
  if (!h || !w || h < 50 || w < 10) { prev.style.display = 'none'; return; }
  const bmi = (w / ((h/100)**2)).toFixed(1);
  let lbl = '', col = '';
  if (bmi < 18.5)      { lbl = 'Underweight'; col = '#d97706'; }
  else if (bmi < 25)   { lbl = 'Normal';       col = '#16a34a'; }
  else if (bmi < 30)   { lbl = 'Overweight';   col = '#ea580c'; }
  else                 { lbl = 'Obese';         col = '#dc2626'; }
  const pct = Math.min(100, Math.max(5, (bmi/40)*100));
  document.getElementById('bmiVal').textContent = `${bmi} — ${lbl}`;
  document.getElementById('bmiVal').style.color  = col;
  document.getElementById('bmiBar').style.width  = pct + '%';
  prev.style.display = 'block';
}

/* Init live BMI on load if values already exist */
calcBmi();
</script>
</body>
</html>
