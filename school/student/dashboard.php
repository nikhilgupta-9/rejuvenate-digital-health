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

require_once __DIR__ . '/../../lib/SchoolMembership.php';
$active_membership = school_active_membership($conn, $student_id);

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
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($student_school) ?> | Student Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    .sec-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
    .sec-card-head { padding:14px 20px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
    .sec-card-head .hd-left { display:flex; align-items:center; gap:10px; }
    .sec-card-head .icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
    .sec-card-head h6 { margin:0; font-size:.9rem; font-weight:700; color:#1f2937; }
    .sec-card-head p  { margin:0; font-size:.73rem; color:#9ca3af; }
    .sec-card-body { padding:18px 20px; }

    /* Student hero banner */
    .student-hero {
      background: var(--primary);
      border-radius: 14px;
      padding: 22px 24px;
      color: #fff;
      margin-bottom: 22px;
      display: flex;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
      position: relative;
      overflow: hidden;
    }
    .student-hero::after {
      content: '';
      position: absolute;
      right: -40px; top: -40px;
      width: 180px; height: 180px;
      background: rgba(255,255,255,.06);
      border-radius: 50%;
    }
    .student-hero .t-avatar {
      width: 56px; height: 56px; border-radius: 14px;
      background: rgba(255,255,255,.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .student-hero .t-name { font-size: 1.05rem; font-weight: 700; }
    .student-hero .t-sub  { font-size: .8rem; opacity: .8; margin-top: 2px; }
    .student-hero .t-uid  { font-size: .72rem; opacity: .6; font-family: monospace; margin-top: 3px; }

    .vital-pill {
      background: rgba(255,255,255,.15);
      border-radius: 8px; padding: 8px 14px; text-align: center; min-width: 64px;
    }
    .vital-pill .v { font-size: 1.05rem; font-weight: 700; }
    .vital-pill .l { font-size: .63rem; opacity: .8; }

    /* ABHA / membership chip inside hero */
    .abha-chip {
      background: rgba(255,255,255,.15);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 20px;
      padding: 4px 12px;
      font-size: .72rem;
      font-weight: 700;
      white-space: nowrap;
      text-decoration: none;
      color: #fff;
      display: inline-flex;
      align-items: center;
    }
    .abha-chip.linked { background: rgba(0,135,90,.35); border-color: rgba(0,135,90,.5); }
    .abha-chip.pending { background: rgba(217,119,6,.35); border-color: rgba(217,119,6,.5); }

    /* Info Grid (for health profile sections) */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .info-box { background: #f9fafb; border-radius: 8px; padding: 10px 12px; }
    .info-box .lbl { font-size: .67rem; color: #9ca3af; margin-bottom: 2px; }
    .info-box .val { font-size: .86rem; font-weight: 600; color: #1f2937; }

    /* BMI Bar */
    .bmi-track { height: 8px; border-radius: 4px; background: #e5e7eb; overflow: hidden; margin: 8px 0 4px; }
    .bmi-fill  { height: 100%; border-radius: 4px; }
    .bmi-scale { display: flex; justify-content: space-between; font-size: .6rem; color: #9ca3af; }
  </style>
</head>
<body>
<?php $active_page = 'dashboard'; $base_path = ''; include '../inc/sidebar-student.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;">Welcome, <?= htmlspecialchars($student_name) ?>!</div>
      <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($student_school) ?> &middot; <?= date('l, d F Y') ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="profile.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
      <i class="fas fa-user"></i><span>Profile</span>
    </a>
    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
         style="width:34px;height:34px;font-size:.85rem;flex-shrink:0;">
      <?= strtoupper(substr($student_name, 0, 1)) ?>
    </div>
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <!-- Student Hero Banner -->
  <div class="student-hero">
    <div class="t-avatar"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
    <div style="flex:1;min-width:0;">
      <div class="t-name"><?= htmlspecialchars($student_name) ?></div>
      <div class="t-sub">
        <?php if ($student['class']): ?>Class <?= htmlspecialchars($student['class'] . ($student['section'] ? ' ' . $student['section'] : '')) ?><?php endif; ?>
        <?php if ($student['roll_number']): ?> &middot; Roll: <?= htmlspecialchars($student['roll_number']) ?><?php endif; ?>
      </div>
      <div class="t-uid"><?= htmlspecialchars($student_uid) ?> &middot; <?= htmlspecialchars($student_school) ?></div>
    </div>
    <?php if ($hp): ?>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($hp['height_cm']): ?>
        <div class="vital-pill"><div class="v"><?= $hp['height_cm'] ?><span style="font-size:.7rem;font-weight:400;opacity:.8;"> cm</span></div><div class="l">Height</div></div>
      <?php endif; ?>
      <?php if ($hp['weight_kg']): ?>
        <div class="vital-pill"><div class="v"><?= $hp['weight_kg'] ?><span style="font-size:.7rem;font-weight:400;opacity:.8;"> kg</span></div><div class="l">Weight</div></div>
      <?php endif; ?>
      <?php if ($bmi): ?>
        <div class="vital-pill"><div class="v" style="color:<?= $bmi_col === '#198754' ? '#4ade80' : '#fcd34d' ?>;"><?= $bmi ?></div><div class="l">BMI</div></div>
      <?php endif; ?>
      <?php if (!empty($student['blood_group'])): ?>
        <div class="vital-pill"><div class="v" style="color:#fca5a5;"><?= htmlspecialchars($student['blood_group']) ?></div><div class="l">Blood</div></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Status Row: ABHA + Membership -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <?php if ($student['abha_linked'] && $student['abha_id']): ?>
        <a href="abha.php" class="d-block text-decoration-none h-100">
          <div style="background:var(--ab-green, #00875a);border-radius:14px;padding:16px 20px;color:#fff;display:flex;align-items:center;gap:14px;height:100%;position:relative;overflow:hidden;">
            <div style="position:absolute;right:-20px;top:-20px;width:100px;height:100px;background:rgba(255,255,255,.06);border-radius:50%;"></div>
            <div style="width:42px;height:42px;background:rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;position:relative;z-index:1;">
              <i class="fas fa-id-card"></i>
            </div>
            <div style="flex:1;position:relative;z-index:1;">
              <div style="font-size:.62rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;">Ayushman Bharat Health Account</div>
              <div style="font-family:monospace;font-size:.95rem;font-weight:700;letter-spacing:.06em;"><?= htmlspecialchars($student['abha_id']) ?></div>
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
        <a href="abha.php" class="d-block text-decoration-none h-100">
          <div style="background:#fff;border:2px dashed <?= $has_pending_abha ? '#d97706' : '#86efac' ?>;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;height:100%;">
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
    </div>
    <div class="col-md-6">
      <?php if ($active_membership): ?>
        <a href="membership.php" class="d-block text-decoration-none h-100">
          <div style="background:#0C74C5;border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;color:#fff;height:100%;">
            <div style="width:42px;height:42px;background:rgba(255,255,255,.18);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
              <i class="fas fa-id-card"></i>
            </div>
            <div style="flex:1;">
              <div style="font-size:.83rem;font-weight:700;"><?= htmlspecialchars($active_membership['plan_name'] ?: 'Health Membership') ?> — Active</div>
              <div style="font-size:.71rem;opacity:.85;">Valid till <?= date('d M Y', strtotime($active_membership['end_date'])) ?></div>
            </div>
            <i class="fas fa-chevron-right" style="opacity:.7;"></i>
          </div>
        </a>
      <?php else: ?>
        <a href="membership.php" class="d-block text-decoration-none h-100">
          <div style="background:#fff;border:2px dashed #93c5fd;border-radius:14px;padding:14px 18px;display:flex;align-items:center;gap:12px;height:100%;">
            <div style="width:38px;height:38px;background:#eff6ff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;color:#0C74C5;">
              <i class="fas fa-id-card"></i>
            </div>
            <div style="flex:1;">
              <div style="font-size:.83rem;font-weight:700;color:#374151;">Get Your Health Membership</div>
              <div style="font-size:.71rem;color:#6b7280;">12-month plan — unlock discounted doctor consultations</div>
            </div>
            <i class="fas fa-chevron-right" style="color:#9ca3af;"></i>
          </div>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <a href="health.php" class="quick-action">
        <i class="fas fa-heartbeat text-danger"></i>
        <div class="fw-semibold" style="font-size:.85rem;">Health Profile</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Your medical info</div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="records.php" class="quick-action">
        <i class="fas fa-file-medical text-primary"></i>
        <div class="fw-semibold" style="font-size:.85rem;">Health Records</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Reports &amp; history</div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="book-appointment.php" class="quick-action">
        <i class="fas fa-stethoscope" style="color:#16a34a;"></i>
        <div class="fw-semibold" style="font-size:.85rem;">Book Appointment</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">See a doctor</div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="profile.php" class="quick-action">
        <i class="fas fa-user-circle" style="color:#6f42c1;"></i>
        <div class="fw-semibold" style="font-size:.85rem;">My Profile</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Account &amp; password</div>
      </a>
    </div>
  </div>

  <?php if ($hp): ?>

    <!-- BMI -->
    <?php if ($bmi): ?>
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="hd-left">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-weight"></i></div>
          <div><h6>Body Mass Index</h6><p>Calculated from your height &amp; weight</p></div>
        </div>
      </div>
      <div class="sec-card-body">
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
    </div>
    <?php endif; ?>

    <!-- Physical Health -->
    <?php if ($hp['vision'] || $hp['hearing'] || $hp['dental']): ?>
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="hd-left">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-stethoscope"></i></div>
          <div><h6>Physical Health</h6><p>Vision, hearing &amp; dental</p></div>
        </div>
      </div>
      <div class="sec-card-body">
        <div class="info-grid">
          <?php if ($hp['vision']):  ?><div class="info-box"><div class="lbl">Vision</div><div class="val"><?= htmlspecialchars($hp['vision']) ?></div></div><?php endif; ?>
          <?php if ($hp['hearing']): ?><div class="info-box"><div class="lbl">Hearing</div><div class="val"><?= htmlspecialchars($hp['hearing']) ?></div></div><?php endif; ?>
          <?php if ($hp['dental']):  ?><div class="info-box"><div class="lbl">Dental</div><div class="val"><?= htmlspecialchars($hp['dental']) ?></div></div><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Medical History -->
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="hd-left">
          <div class="icon" style="background:#fdeaea;color:#dc3545;"><i class="fas fa-notes-medical"></i></div>
          <div><h6>Medical History</h6><p>Allergies, conditions &amp; medications</p></div>
        </div>
      </div>
      <div class="sec-card-body">
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
    </div>

    <!-- Vaccinations -->
    <?php if (!empty($hp['vaccination_status'])): ?>
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="hd-left">
          <div class="icon" style="background:#eafaf1;color:#198754;"><i class="fas fa-syringe"></i></div>
          <div><h6>Vaccination Status</h6><p>On record with your school</p></div>
        </div>
      </div>
      <div class="sec-card-body">
        <div style="font-size:.86rem;"><?= nl2br(htmlspecialchars($hp['vaccination_status'])) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Emergency Contact -->
    <?php if (!empty($hp['emergency_contact']) || !empty($hp['emergency_phone'])): ?>
    <div class="sec-card">
      <div class="sec-card-head">
        <div class="hd-left">
          <div class="icon" style="background:#fff4e6;color:#e07e18;"><i class="fas fa-phone-alt"></i></div>
          <div><h6>Emergency Contact</h6><p>Who to call in an emergency</p></div>
        </div>
      </div>
      <div class="sec-card-body">
        <div class="info-grid">
          <?php if ($hp['emergency_contact']): ?><div class="info-box"><div class="lbl">Name</div><div class="val"><?= htmlspecialchars($hp['emergency_contact']) ?></div></div><?php endif; ?>
          <?php if ($hp['emergency_phone']): ?><div class="info-box"><div class="lbl">Phone</div><div class="val"><?= htmlspecialchars($hp['emergency_phone']) ?></div></div><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  <?php else: ?>
  <div class="sec-card">
    <div class="sec-card-body text-center" style="padding: 40px 20px;">
      <i class="fas fa-heartbeat fa-2x mb-3 d-block" style="color:#0C74C5;opacity:.25;"></i>
      <div class="fw-semibold" style="color:#374151;margin-bottom:6px;">Health Profile Not Set Up</div>
      <p style="font-size:.82rem;color:#9ca3af;margin-bottom:0;">Your school admin or teacher hasn't created your health profile yet. Please contact them.</p>
    </div>
  </div>
  <?php endif; ?>

  <div style="text-align:center;font-size:.7rem;color:#9ca3af;padding:6px 0;">
    <i class="fas fa-lock me-1"></i>Your health data is secure &amp; private.
  </div>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
