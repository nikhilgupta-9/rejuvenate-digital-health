<?php
include_once "../../config/connect.php";
include_once "auth.php";

$stmt = $conn->prepare("SELECT sm.*, s.school_name, s.city FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$hp_stmt = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
$hp_stmt->bind_param('i', $student_id);
$hp_stmt->execute();
$hp = $hp_stmt->get_result()->fetch_assoc();

/* Pending ABHA request */
$pq = $conn->prepare("SELECT id FROM abha_link_requests WHERE member_id=? AND status='Pending' LIMIT 1");
$pq->bind_param('i', $student_id);
$pq->execute();
$has_pending_abha = (bool)$pq->get_result()->fetch_assoc();

$bmi = null; $bmi_lbl = ''; $bmi_col = '#6b7280';
if (!empty($hp['height_cm']) && !empty($hp['weight_kg'])) {
    $bmi = round($hp['weight_kg'] / (($hp['height_cm']/100)**2), 1);
    if      ($bmi < 18.5) { $bmi_lbl = 'Underweight'; $bmi_col = '#e07e18'; }
    elseif  ($bmi < 25)   { $bmi_lbl = 'Normal';       $bmi_col = '#198754'; }
    elseif  ($bmi < 30)   { $bmi_lbl = 'Overweight';   $bmi_col = '#e07e18'; }
    else                  { $bmi_lbl = 'Obese';         $bmi_col = '#dc3545'; }
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
    :root { --primary: #0C74C5; --accent: #02c9b8; --ab-green: #00875a; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }

    /* ── Top Nav ── */
    .s-topnav {
      background: var(--primary);
      color: #fff;
      padding: 0 16px;
      height: 58px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 10px rgba(12,116,197,.3);
    }
    .s-topnav .brand { font-size: .9rem; font-weight: 700; }
    .s-topnav .sub   { font-size: .68rem; opacity: .75; }

    /* ── Body — extra bottom padding so content clears the fixed nav ── */
    .s-body { max-width: 800px; margin: 0 auto; padding: 18px 14px 90px; }

    /* ── Profile Card ── */
    .profile-card {
      background: var(--primary);
      border-radius: 16px;
      padding: 20px;
      color: #fff;
      margin-bottom: 16px;
      position: relative;
      overflow: hidden;
    }
    .profile-card::after {
      content: '';
      position: absolute;
      right: -30px; top: -30px;
      width: 150px; height: 150px;
      background: rgba(255,255,255,.06);
      border-radius: 50%;
    }
    .profile-avatar {
      width: 52px; height: 52px; border-radius: 14px;
      background: rgba(255,255,255,.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; font-weight: 700; flex-shrink: 0;
    }
    .profile-card .meta { font-size: .78rem; opacity: .82; margin-top: 2px; }
    .stat-pill {
      background: rgba(255,255,255,.15);
      border-radius: 8px; padding: 8px 14px; text-align: center;
    }
    .stat-pill .v { font-size: 1.05rem; font-weight: 700; }
    .stat-pill .l { font-size: .63rem; opacity: .8; }

    /* ── Section Cards ── */
    .s-card {
      background: #fff;
      border-radius: 12px;
      padding: 16px 18px;
      margin-bottom: 14px;
      box-shadow: 0 1px 6px rgba(0,0,0,.07);
    }
    .s-card-title {
      font-size: .7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .8px; color: #6b7280;
      display: flex; align-items: center; gap: 7px;
      margin-bottom: 12px; padding-bottom: 10px;
      border-bottom: 1px solid #f3f4f6;
    }
    .s-card-title i { font-size: .82rem; }

    /* ── Info Grid ── */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .info-box { background: #f9fafb; border-radius: 8px; padding: 10px 12px; }
    .info-box .lbl { font-size: .67rem; color: #9ca3af; margin-bottom: 2px; }
    .info-box .val { font-size: .86rem; font-weight: 600; color: #1f2937; }

    /* ── BMI Bar ── */
    .bmi-track { height: 8px; border-radius: 4px; background: #e5e7eb; overflow: hidden; margin: 8px 0 4px; }
    .bmi-fill  { height: 100%; border-radius: 4px; }
    .bmi-scale { display: flex; justify-content: space-between; font-size: .6rem; color: #9ca3af; }

    /* ── Bottom Navigation ── */
    .s-bottomnav {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #fff;
      border-top: 1px solid #e5e7eb;
      display: flex;
      z-index: 99;
      box-shadow: 0 -2px 10px rgba(0,0,0,.06);
    }
    .s-bottomnav a {
      flex: 1;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 9px 4px;
      text-decoration: none;
      color: #9ca3af;
      font-size: .58rem;
      font-weight: 600;
      gap: 3px;
      transition: color .15s;
    }
    .s-bottomnav a i { font-size: 1.05rem; }
    .s-bottomnav a.active { color: var(--primary); }
    .s-bottomnav a.active i { color: var(--primary); }

    @media (max-width: 480px) {
      .info-grid { grid-template-columns: 1fr 1fr; }
      .profile-card { padding: 16px; }
    }
  </style>
</head>
<body>

<!-- Top Nav -->
<nav class="s-topnav">
  <div>
    <div class="brand"><i class="fas fa-heartbeat me-2" style="color:var(--accent);"></i>My Health Dashboard</div>
    <div class="sub"><?= htmlspecialchars($student_school) ?> &middot; <?= date('d M Y') ?></div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
         style="width:32px;height:32px;background:rgba(255,255,255,.2);font-size:.82rem;flex-shrink:0;">
      <?= strtoupper(substr($student_name,0,1)) ?>
    </div>
    <a href="logout.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:none;font-size:.76rem;">
      <i class="fas fa-sign-out-alt me-1"></i><span class="d-none d-sm-inline">Logout</span>
    </a>
  </div>
</nav>

<div class="s-body">

  <!-- Profile Card -->
  <div class="profile-card">
    <div class="d-flex align-items-center gap-3 mb-3" style="position:relative;z-index:1;">
      <div class="profile-avatar"><?= strtoupper(substr($student_name,0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="fw-bold" style="font-size:1.02rem;"><?= htmlspecialchars($student_name) ?></div>
        <div class="meta">
          <?php if ($student['class']): ?>Class <?= htmlspecialchars($student['class'] . ($student['section'] ? ' '.$student['section'] : '')) ?><?php endif; ?>
          <?php if ($student['roll_number']): ?> &middot; Roll: <?= htmlspecialchars($student['roll_number']) ?><?php endif; ?>
        </div>
        <div class="meta" style="font-family:monospace;font-size:.7rem;"><?= htmlspecialchars($student_uid) ?></div>
      </div>
    </div>
    <?php if ($hp): ?>
    <div class="d-flex gap-2 flex-wrap" style="position:relative;z-index:1;">
      <?php if ($hp['height_cm']): ?>
        <div class="stat-pill"><div class="v"><?= $hp['height_cm'] ?><span style="font-size:.7rem;font-weight:400;opacity:.8;"> cm</span></div><div class="l">Height</div></div>
      <?php endif; ?>
      <?php if ($hp['weight_kg']): ?>
        <div class="stat-pill"><div class="v"><?= $hp['weight_kg'] ?><span style="font-size:.7rem;font-weight:400;opacity:.8;"> kg</span></div><div class="l">Weight</div></div>
      <?php endif; ?>
      <?php if ($bmi): ?>
        <div class="stat-pill"><div class="v" style="color:<?= $bmi_col === '#198754' ? '#4ade80' : '#fcd34d' ?>;"><?= $bmi ?></div><div class="l">BMI</div></div>
      <?php endif; ?>
      <?php if (!empty($student['blood_group'])): ?>
        <div class="stat-pill"><div class="v" style="color:#fca5a5;"><?= htmlspecialchars($student['blood_group']) ?></div><div class="l">Blood</div></div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="opacity:.75;font-size:.82rem;position:relative;z-index:1;">
      <i class="fas fa-info-circle me-1"></i>Health profile not set up yet. Contact your school.
    </div>
    <?php endif; ?>
  </div>

  <!-- ABHA Status -->
  <?php if ($student['abha_linked'] && $student['abha_id']): ?>
  <a href="abha.php" class="d-block text-decoration-none mb-3">
    <div style="background:var(--ab-green);border-radius:14px;padding:16px 20px;color:#fff;display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;">
      <div style="position:absolute;right:-20px;top:-20px;width:100px;height:100px;background:rgba(255,255,255,.06);border-radius:50%;"></div>
      <div style="width:42px;height:42px;background:rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;position:relative;z-index:1;">
        <i class="fas fa-id-card"></i>
      </div>
      <div style="flex:1;position:relative;z-index:1;">
        <div style="font-size:.62rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;">Ayushman Bharat Health Account</div>
        <div style="font-family:monospace;font-size:.98rem;font-weight:700;letter-spacing:.06em;"><?= htmlspecialchars($student['abha_id']) ?></div>
        <?php if ($student['abha_address']): ?>
          <div style="font-size:.72rem;opacity:.8;"><?= htmlspecialchars($student['abha_address']) ?></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($student['abha_verified'])): ?>
        <span style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:3px 9px;font-size:.66rem;font-weight:700;white-space:nowrap;position:relative;z-index:1;">
          <i class="fas fa-shield-alt me-1"></i>Verified
        </span>
      <?php else: ?>
        <i class="fas fa-chevron-right" style="opacity:.6;position:relative;z-index:1;"></i>
      <?php endif; ?>
    </div>
  </a>
  <?php else: ?>
  <a href="abha.php" class="d-block text-decoration-none mb-3">
    <div style="background:#fff;border:2px dashed <?= $has_pending_abha ? '#d97706' : '#86efac' ?>;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;">
      <div style="width:38px;height:38px;background:<?= $has_pending_abha ? '#fffbeb' : '#f0fdf4' ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;color:<?= $has_pending_abha ? '#d97706' : '#00875a' ?>;">
        <i class="fas fa-<?= $has_pending_abha ? 'hourglass-half' : 'id-card' ?>"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:.83rem;font-weight:700;color:#374151;">
          <?= $has_pending_abha ? 'ABHA Request Pending' : 'Link Your ABHA' ?>
        </div>
        <div style="font-size:.71rem;color:#6b7280;">
          <?= $has_pending_abha ? 'Waiting for school admin approval' : 'Tap to link your Ayushman Bharat Health ID' ?>
        </div>
      </div>
      <i class="fas fa-chevron-right" style="color:#9ca3af;"></i>
    </div>
  </a>
  <?php endif; ?>

  <?php if ($hp): ?>

    <!-- BMI -->
    <?php if ($bmi): ?>
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-weight text-primary"></i>Body Mass Index</div>
      <div class="d-flex justify-content-between align-items-center mb-1">
        <span style="font-size:.85rem;">BMI Score</span>
        <span class="fw-bold" style="color:<?= $bmi_col ?>;"><?= $bmi ?> — <?= $bmi_lbl ?></span>
      </div>
      <div class="bmi-track">
        <div class="bmi-fill" style="width:<?= min(100, max(5, ($bmi/40)*100)) ?>%;background:<?= $bmi_col ?>;"></div>
      </div>
      <div class="bmi-scale">
        <span>Underweight<br>&lt;18.5</span>
        <span class="text-center">Normal<br>18.5–25</span>
        <span class="text-center">Overweight<br>25–30</span>
        <span class="text-end">Obese<br>≥30</span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Physical Health -->
    <?php if ($hp['vision'] || $hp['hearing'] || $hp['dental']): ?>
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-stethoscope" style="color:#0C74C5;"></i>Physical Health</div>
      <div class="info-grid">
        <?php if ($hp['vision']):  ?><div class="info-box"><div class="lbl">Vision</div><div class="val"><?= htmlspecialchars($hp['vision']) ?></div></div><?php endif; ?>
        <?php if ($hp['hearing']): ?><div class="info-box"><div class="lbl">Hearing</div><div class="val"><?= htmlspecialchars($hp['hearing']) ?></div></div><?php endif; ?>
        <?php if ($hp['dental']):  ?><div class="info-box"><div class="lbl">Dental</div><div class="val"><?= htmlspecialchars($hp['dental']) ?></div></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Medical History -->
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-notes-medical" style="color:#dc3545;"></i>Medical History</div>
      <?php if (!$hp['known_allergies'] && !$hp['chronic_conditions'] && !$hp['current_medications']): ?>
        <p class="mb-0" style="font-size:.84rem;color:#6b7280;"><i class="fas fa-check-circle text-success me-1"></i>No medical history on record.</p>
      <?php else: ?>
        <?php if ($hp['known_allergies']): ?>
          <div class="mb-3">
            <div style="font-size:.68rem;color:#9ca3af;margin-bottom:3px;">Known Allergies</div>
            <div style="font-size:.86rem;"><?= nl2br(htmlspecialchars($hp['known_allergies'])) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($hp['chronic_conditions']): ?>
          <div class="mb-3">
            <div style="font-size:.68rem;color:#9ca3af;margin-bottom:3px;">Chronic Conditions</div>
            <div style="font-size:.86rem;"><?= nl2br(htmlspecialchars($hp['chronic_conditions'])) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($hp['current_medications']): ?>
          <div>
            <div style="font-size:.68rem;color:#9ca3af;margin-bottom:3px;">Current Medications</div>
            <div style="font-size:.86rem;"><?= nl2br(htmlspecialchars($hp['current_medications'])) ?></div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Vaccinations -->
    <?php if (!empty($hp['vaccination_status'])): ?>
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-syringe" style="color:#198754;"></i>Vaccination Status</div>
      <div style="font-size:.86rem;"><?= nl2br(htmlspecialchars($hp['vaccination_status'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Emergency Contact -->
    <?php if (!empty($hp['emergency_contact']) || !empty($hp['emergency_phone'])): ?>
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-phone-alt" style="color:#e07e18;"></i>Emergency Contact</div>
      <div class="info-grid">
        <?php if ($hp['emergency_contact']): ?><div class="info-box"><div class="lbl">Name</div><div class="val"><?= htmlspecialchars($hp['emergency_contact']) ?></div></div><?php endif; ?>
        <?php if ($hp['emergency_phone']): ?><div class="info-box"><div class="lbl">Phone</div><div class="val"><?= htmlspecialchars($hp['emergency_phone']) ?></div></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  <?php else: ?>
  <div class="s-card text-center" style="padding: 40px 20px;">
    <i class="fas fa-heartbeat fa-2x mb-3 d-block" style="color:#0C74C5;opacity:.25;"></i>
    <div class="fw-semibold" style="color:#374151;margin-bottom:6px;">Health Profile Not Set Up</div>
    <p style="font-size:.82rem;color:#9ca3af;margin-bottom:0;">Your school admin or teacher hasn't created your health profile yet. Please contact them.</p>
  </div>
  <?php endif; ?>

  <div style="text-align:center;font-size:.7rem;color:#9ca3af;padding:6px 0;">
    <i class="fas fa-lock me-1"></i>Your health data is secure &amp; private.
  </div>
</div>

<!-- Bottom Navigation -->
<nav class="s-bottomnav">
  <a href="dashboard.php" class="active"><i class="fas fa-home"></i>Home</a>
  <a href="health.php"><i class="fas fa-heartbeat"></i>Health</a>
  <a href="abha.php"><i class="fas fa-id-card"></i>ABHA</a>
  <a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
