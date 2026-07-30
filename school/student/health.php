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

/* Read-only record — students cannot edit their medical info.
   Only school admin/teacher (school/members/health-profile.php) or a doctor can update it. */

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

    /* Read-only info grid */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .info-box { background: #f9fafb; border-radius: 8px; padding: 10px 12px; }
    .info-box .lbl { font-size: .67rem; color: #9ca3af; margin-bottom: 2px; }
    .info-box .val { font-size: .86rem; font-weight: 600; color: #1f2937; }
    .info-box .val.empty { font-weight: 400; color: #c1c7d0; }

    .text-block { font-size: .85rem; color: #374151; background: #f9fafb; border-radius: 8px; padding: 10px 12px; white-space: pre-wrap; }
    .text-block.empty { color: #c1c7d0; font-style: italic; }

    /* Blood group display chip */
    .blood-chip-ro {
      display: inline-flex; align-items: center; padding: 6px 16px; border-radius: 20px;
      font-size: .85rem; font-weight: 700; background: #dc2626; color: #fff;
    }

    .locked-note {
      display: flex; align-items: flex-start; gap: 8px;
      background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
      padding: 10px 14px; font-size: .74rem; color: #92400e; margin-bottom: 16px;
    }
    .locked-note i { margin-top: 2px; }

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

  <div class="locked-note">
    <i class="fas fa-lock"></i>
    <div>This is a <strong>read-only</strong> record. Only your school nurse, teacher or doctor can update it. If something looks wrong, please let them know.</div>
  </div>

  <!-- Health summary tiles -->
  <?php if ($hp || !empty($student['blood_group'])): ?>
  <div class="health-summary">
    <div class="h-tile">
      <div class="h-icon" style="color:#0C74C5;"><i class="fas fa-ruler-vertical"></i></div>
      <div class="h-val"><?= !empty($hp['height_cm']) ? $hp['height_cm'].'<span style="font-size:.65rem;font-weight:400;"> cm</span>' : '—' ?></div>
      <div class="h-lbl">Height</div>
    </div>
    <div class="h-tile">
      <div class="h-icon" style="color:#7c3aed;"><i class="fas fa-weight"></i></div>
      <div class="h-val"><?= !empty($hp['weight_kg']) ? $hp['weight_kg'].'<span style="font-size:.65rem;font-weight:400;"> kg</span>' : '—' ?></div>
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

  <?php if (!$hp): ?>

    <div class="s-card text-center" style="padding: 40px 20px;">
      <i class="fas fa-heartbeat fa-2x mb-3 d-block" style="color:#0C74C5;opacity:.25;"></i>
      <div class="fw-semibold" style="color:#374151;margin-bottom:6px;">Health Profile Not Set Up</div>
      <p style="font-size:.82rem;color:#9ca3af;margin-bottom:0;">Your school admin, teacher or doctor hasn't created your health profile yet.</p>
    </div>

  <?php else: ?>

    <!-- Physical Measurements -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-ruler" style="color:#0C74C5;"></i>Physical Measurements</div>
      <div class="info-grid">
        <div class="info-box"><div class="lbl">Vision</div><div class="val <?= $hp['vision'] ? '' : 'empty' ?>"><?= $hp['vision'] ? htmlspecialchars($hp['vision']) : 'Not recorded' ?></div></div>
        <div class="info-box"><div class="lbl">Hearing</div><div class="val <?= $hp['hearing'] ? '' : 'empty' ?>"><?= $hp['hearing'] ? htmlspecialchars($hp['hearing']) : 'Not recorded' ?></div></div>
        <div class="info-box"><div class="lbl">Dental Health</div><div class="val <?= $hp['dental'] ? '' : 'empty' ?>"><?= $hp['dental'] ? htmlspecialchars($hp['dental']) : 'Not recorded' ?></div></div>
      </div>
    </div>

    <!-- Blood Group -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-tint" style="color:#dc2626;"></i>Blood Group</div>
      <?php if (!empty($student['blood_group'])): ?>
        <span class="blood-chip-ro"><?= htmlspecialchars($student['blood_group']) ?></span>
      <?php else: ?>
        <span style="font-size:.85rem;color:#c1c7d0;font-style:italic;">Not recorded</span>
      <?php endif; ?>
    </div>

    <!-- Medical History -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-notes-medical" style="color:#dc2626;"></i>Medical History</div>
      <div class="mb-3">
        <div class="lbl" style="font-size:.72rem;color:#9ca3af;margin-bottom:4px;">Known Allergies</div>
        <div class="text-block <?= $hp['known_allergies'] ? '' : 'empty' ?>"><?= $hp['known_allergies'] ? nl2br(htmlspecialchars($hp['known_allergies'])) : 'None recorded' ?></div>
      </div>
      <div class="mb-3">
        <div class="lbl" style="font-size:.72rem;color:#9ca3af;margin-bottom:4px;">Chronic Conditions</div>
        <div class="text-block <?= $hp['chronic_conditions'] ? '' : 'empty' ?>"><?= $hp['chronic_conditions'] ? nl2br(htmlspecialchars($hp['chronic_conditions'])) : 'None recorded' ?></div>
      </div>
      <div>
        <div class="lbl" style="font-size:.72rem;color:#9ca3af;margin-bottom:4px;">Current Medications</div>
        <div class="text-block <?= $hp['current_medications'] ? '' : 'empty' ?>"><?= $hp['current_medications'] ? nl2br(htmlspecialchars($hp['current_medications'])) : 'None recorded' ?></div>
      </div>
    </div>

    <!-- Vaccination -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-syringe" style="color:#16a34a;"></i>Vaccination Status</div>
      <div class="text-block <?= $hp['vaccination_status'] ? '' : 'empty' ?>"><?= $hp['vaccination_status'] ? nl2br(htmlspecialchars($hp['vaccination_status'])) : 'Not recorded' ?></div>
    </div>

    <!-- Emergency Contact -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-phone-alt" style="color:#d97706;"></i>Emergency Contact</div>
      <div class="info-grid">
        <div class="info-box"><div class="lbl">Guardian / Parent</div><div class="val <?= $hp['emergency_contact'] ? '' : 'empty' ?>"><?= $hp['emergency_contact'] ? htmlspecialchars($hp['emergency_contact']) : 'Not recorded' ?></div></div>
        <div class="info-box"><div class="lbl">Phone</div><div class="val <?= $hp['emergency_phone'] ? '' : 'empty' ?>"><?= $hp['emergency_phone'] ? htmlspecialchars($hp['emergency_phone']) : 'Not recorded' ?></div></div>
      </div>
    </div>

    <!-- Notes -->
    <?php if ($hp['notes']): ?>
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-sticky-note" style="color:#6b7280;"></i>Additional Notes</div>
      <div class="text-block"><?= nl2br(htmlspecialchars($hp['notes'])) ?></div>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<!-- Bottom Navigation -->
<nav class="s-bottomnav">
  <a href="dashboard.php"><i class="fas fa-home"></i>Home</a>
  <a href="health.php" class="active"><i class="fas fa-heartbeat"></i>Health</a>
  <a href="records.php"><i class="fas fa-file-medical"></i>Records</a>
  <a href="abha.php"><i class="fas fa-id-card"></i>ABHA</a>
  <a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
