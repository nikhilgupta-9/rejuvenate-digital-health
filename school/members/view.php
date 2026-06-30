<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=? AND sm.school_id=?");
$stmt->bind_param('ii', $id, $school_id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) { header("Location: list.php"); exit(); }

$hp_res = $conn->prepare("SELECT * FROM member_health_profiles WHERE member_id=?");
$hp_res->bind_param('i', $id);
$hp_res->execute();
$hp = $hp_res->get_result()->fetch_assoc();

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
  <title><?= htmlspecialchars($school_name) ?> | Member Details</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    .sec-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0,0,0,.06);
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
      width: 34px; height: 34px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: .88rem; flex-shrink: 0;
    }
    .sec-card-head h6 { margin: 0; font-size: .9rem; font-weight: 700; color: #1f2937; }
    .sec-card-head p  { margin: 0; font-size: .73rem; color: #9ca3af; }
    .sec-card-body { padding: 20px; }

    /* Profile avatar */
    .member-avatar {
      width: 80px; height: 80px; border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem; font-weight: 700; color: #fff;
      margin: 0 auto 14px;
      overflow: hidden;
    }
    .member-avatar img { width: 100%; height: 100%; object-fit: cover; }

    /* Info rows inside sec-card */
    .detail-row {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: .84rem;
    }
    .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
    .detail-icon { width: 28px; height: 28px; border-radius: 7px; display: flex;
      align-items: center; justify-content: center; font-size: .75rem; flex-shrink: 0; }
    .detail-label { font-size: .72rem; color: #9ca3af; margin-bottom: 1px; }
    .detail-val { font-weight: 500; color: #1f2937; font-size: .84rem; }

    /* Status pill */
    .status-pill {
      display: inline-flex; align-items: center; gap: 5px;
      border-radius: 20px; padding: 3px 10px; font-size: .72rem; font-weight: 700;
    }
    .pill-active   { background: #f0fdf4; color: #16a34a; }
    .pill-inactive { background: #f9fafb; color: #6b7280; }
    .pill-yes      { background: #eaf4fd; color: #0C74C5; }
    .pill-no       { background: #f9fafb; color: #9ca3af; }

    /* Quick action button row */
    .qbtn {
      display: flex; align-items: center; gap: 8px;
      padding: 9px 14px; border-radius: 8px; border: 1.5px solid #e5e7eb;
      font-size: .8rem; font-weight: 600; color: #374151;
      text-decoration: none; background: #fff; transition: .18s; margin-bottom: 8px;
    }
    .qbtn:last-child { margin-bottom: 0; }
    .qbtn:hover { border-color: var(--primary); color: var(--primary); background: #f0f8ff; }
    .qbtn i { width: 18px; text-align: center; }
  </style>
</head>
<body>
<?php $active_page = 'members'; $base_path = '../'; include '../inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div style="font-size:1rem;font-weight:600;color:#1f2937;">
      <i class="fas fa-user me-2 text-primary"></i>Member Details
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-warning text-white"><i class="fas fa-edit me-1"></i><span class="d-none d-sm-inline">Edit</span></a>
    <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i><span class="d-none d-sm-inline">Back</span></a>
    <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">
  <div class="row g-4">

    <!-- ── Left: Profile sidebar ── -->
    <div class="col-lg-4">

      <!-- Profile card -->
      <div class="sec-card">
        <div class="sec-card-body text-center">
          <div class="member-avatar" style="background:<?= $tc ?>">
            <?php if (!empty($m['profile_pic']) && file_exists('../../' . $m['profile_pic'])): ?>
              <img src="../../<?= htmlspecialchars($m['profile_pic']) ?>" alt="photo">
            <?php else: ?>
              <?= strtoupper(substr($m['name'], 0, 1)) ?>
            <?php endif; ?>
          </div>

          <h5 class="fw-bold mb-1" style="font-size:1rem;"><?= htmlspecialchars($m['name']) ?></h5>

          <span style="background:<?= $tb ?>;color:<?= $tc ?>;border-radius:20px;padding:3px 12px;font-size:.73rem;font-weight:700;">
            <i class="fas <?= $ti ?> me-1"></i><?= $m['type'] ?>
          </span>

          <div class="mt-2" style="font-size:.75rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($m['member_uid']) ?></div>

          <?php if ($m['school_name']): ?>
            <div class="mt-1" style="font-size:.75rem;color:#6b7280;"><i class="fas fa-school me-1"></i><?= htmlspecialchars($m['school_name']) ?></div>
          <?php endif; ?>

          <hr style="margin:14px 0;">

          <a href="edit.php?id=<?= $id ?>" class="qbtn">
            <i class="fas fa-edit" style="color:#f59e0b;"></i>Edit Member Info
          </a>
          <a href="health-profile.php?id=<?= $id ?>" class="qbtn">
            <i class="fas fa-heartbeat" style="color:#ef4444;"></i>Health Profile
          </a>
          <a href="set-credentials.php?id=<?= $id ?>" class="qbtn">
            <i class="fas fa-key" style="color:var(--primary);"></i><?= $m['password'] ? 'Change Password' : 'Set Login Credentials' ?>
          </a>
          <a href="../health/abha.php?member_id=<?= $id ?>" class="qbtn">
            <i class="fas fa-id-card" style="color:#00875a;"></i><?= $m['abha_linked'] ? 'View ABHA' : 'Link ABHA' ?>
          </a>
        </div>
      </div>

      <!-- Account status card -->
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#f0f9ff;color:var(--primary);"><i class="fas fa-shield-alt"></i></div>
          <div>
            <h6>Account Status</h6>
            <p>Flags &amp; access summary</p>
          </div>
        </div>
        <div class="sec-card-body" style="padding:14px 20px;">
          <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f4f6;">
            <span style="font-size:.8rem;color:#6b7280;">Status</span>
            <span class="status-pill <?= $m['status'] === 'Active' ? 'pill-active' : 'pill-inactive' ?>">
              <i class="fas fa-circle" style="font-size:.4rem;"></i><?= $m['status'] ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f4f6;">
            <span style="font-size:.8rem;color:#6b7280;">Login Access</span>
            <span class="status-pill <?= $m['password'] ? 'pill-yes' : 'pill-no' ?>">
              <i class="fas fa-<?= $m['password'] ? 'check' : 'times' ?>"></i><?= $m['password'] ? 'Enabled' : 'Not Set' ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f4f6;">
            <span style="font-size:.8rem;color:#6b7280;">ABHA Linked</span>
            <span class="status-pill <?= $m['abha_linked'] ? 'pill-yes' : 'pill-no' ?>">
              <i class="fas fa-<?= $m['abha_linked'] ? 'check' : 'times' ?>"></i><?= $m['abha_linked'] ? 'Yes' : 'No' ?>
            </span>
          </div>
          <div class="d-flex justify-content-between align-items-center pt-2">
            <span style="font-size:.8rem;color:#6b7280;">Member Since</span>
            <span style="font-size:.78rem;font-weight:600;color:#374151;"><?= date('d M Y', strtotime($m['created_at'])) ?></span>
          </div>
        </div>
      </div>

    </div><!-- /col-lg-4 -->

    <!-- ── Right: Detail sections ── -->
    <div class="col-lg-8">

      <!-- Personal Information -->
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-user"></i></div>
          <div>
            <h6>Personal Information</h6>
            <p>Contact and identity details</p>
          </div>
        </div>
        <div class="sec-card-body">
          <div class="detail-row">
            <div class="detail-icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-envelope"></i></div>
            <div>
              <div class="detail-label">Email Address</div>
              <div class="detail-val"><?= $m['email'] ? htmlspecialchars($m['email']) : '<span style="color:#9ca3af;">Not provided</span>' ?></div>
            </div>
          </div>
          <div class="detail-row">
            <div class="detail-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-phone"></i></div>
            <div>
              <div class="detail-label">Phone</div>
              <div class="detail-val"><?= $m['phone'] ? htmlspecialchars($m['phone']) : '<span style="color:#9ca3af;">Not provided</span>' ?></div>
            </div>
          </div>
          <div class="detail-row">
            <div class="detail-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-calendar"></i></div>
            <div>
              <div class="detail-label">Date of Birth</div>
              <div class="detail-val">
                <?php if ($m['dob']): ?>
                  <?= date('d M Y', strtotime($m['dob'])) ?>
                  <?php
                    $age = date_diff(date_create($m['dob']), date_create('today'))->y;
                  ?>
                  <span style="font-size:.75rem;color:#9ca3af;margin-left:6px;">(<?= $age ?> years)</span>
                <?php else: ?>
                  <span style="color:#9ca3af;">Not provided</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="detail-row">
            <div class="detail-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-venus-mars"></i></div>
            <div>
              <div class="detail-label">Gender</div>
              <div class="detail-val"><?= $m['gender'] ?: '<span style="color:#9ca3af;">Not provided</span>' ?></div>
            </div>
          </div>
          <div class="detail-row">
            <div class="detail-icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-tint"></i></div>
            <div>
              <div class="detail-label">Blood Group</div>
              <div class="detail-val"><?= $m['blood_group'] ?: '<span style="color:#9ca3af;">Not provided</span>' ?></div>
            </div>
          </div>
          <?php if (!empty($m['aadhar_number'])): ?>
          <div class="detail-row">
            <div class="detail-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-id-card"></i></div>
            <div>
              <div class="detail-label">Aadhar Number</div>
              <div class="detail-val" style="font-family:monospace;"><?= htmlspecialchars($m['aadhar_number']) ?></div>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($m['address'])): ?>
          <div class="detail-row">
            <div class="detail-icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-map-marker-alt"></i></div>
            <div>
              <div class="detail-label">Address</div>
              <div class="detail-val"><?= htmlspecialchars($m['address']) ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Type-specific Details -->
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
            <div class="col-sm-4">
              <div style="background:#f9fafb;border-radius:10px;padding:14px;text-align:center;">
                <div style="font-size:1.2rem;font-weight:700;color:#0C74C5;"><?= htmlspecialchars($m['class'] ?: '—') ?></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Class</div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background:#f9fafb;border-radius:10px;padding:14px;text-align:center;">
                <div style="font-size:1.2rem;font-weight:700;color:#0C74C5;"><?= htmlspecialchars($m['section'] ?: '—') ?></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Section</div>
              </div>
            </div>
            <div class="col-sm-4">
              <div style="background:#f9fafb;border-radius:10px;padding:14px;text-align:center;">
                <div style="font-size:1.2rem;font-weight:700;color:#0C74C5;"><?= htmlspecialchars($m['roll_number'] ?: '—') ?></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Roll Number</div>
              </div>
            </div>
            <?php if (!empty($m['admission_number'])): ?>
            <div class="col-12">
              <div class="detail-row" style="padding-top:0;">
                <div class="detail-icon" style="background:#eaf4fd;color:#0C74C5;"><i class="fas fa-file-alt"></i></div>
                <div>
                  <div class="detail-label">Admission Number</div>
                  <div class="detail-val"><?= htmlspecialchars($m['admission_number']) ?></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

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
          <div class="detail-row">
            <div class="detail-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-hashtag"></i></div>
            <div>
              <div class="detail-label">Employee ID</div>
              <div class="detail-val"><?= $m['employee_id'] ? htmlspecialchars($m['employee_id']) : '<span style="color:#9ca3af;">Not assigned</span>' ?></div>
            </div>
          </div>
          <div class="detail-row">
            <div class="detail-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-briefcase"></i></div>
            <div>
              <div class="detail-label">Designation</div>
              <div class="detail-val"><?= $m['designation'] ? htmlspecialchars($m['designation']) : '<span style="color:#9ca3af;">Not set</span>' ?></div>
            </div>
          </div>
          <?php if ($m['type'] === 'Teacher' && !empty($m['assigned_class'])): ?>
          <div class="detail-row">
            <div class="detail-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-chalkboard"></i></div>
            <div>
              <div class="detail-label">Assigned Class</div>
              <div class="detail-val"><?= htmlspecialchars($m['assigned_class']) ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ABHA Section -->
      <div class="sec-card">
        <div class="sec-card-head" style="border-bottom:1px solid #f3f4f6;">
          <div class="icon" style="background:#e6f7f2;color:#00875a;"><i class="fas fa-id-card"></i></div>
          <div style="flex:1;">
            <h6 style="color:#00875a;">ABHA — Digital Health ID</h6>
            <p>Ayushman Bharat Health Account</p>
          </div>
          <a href="../health/abha.php?tab=overview" class="btn btn-sm" style="background:#00875a;color:#fff;font-size:.73rem;">
            <i class="fas fa-cog me-1"></i>Manage
          </a>
        </div>
        <div class="sec-card-body">
          <?php if ($m['abha_linked'] && $m['abha_id']): ?>
            <div style="background:#00875a;border-radius:12px;padding:16px 18px;color:#fff;margin-bottom:14px;">
              <div style="font-size:.6rem;opacity:.7;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Ayushman Bharat Health Account</div>
              <div style="font-family:monospace;font-size:1.05rem;font-weight:700;letter-spacing:.08em;"><?= htmlspecialchars($m['abha_id']) ?></div>
              <?php if ($m['abha_address']): ?>
                <div style="font-size:.75rem;opacity:.85;margin-top:3px;"><?= htmlspecialchars($m['abha_address']) ?></div>
              <?php endif; ?>
              <div class="d-flex align-items-center justify-content-between mt-2">
                <?php if ($m['abha_linked_at']): ?>
                  <span style="font-size:.65rem;opacity:.65;"><i class="fas fa-calendar me-1"></i>Linked <?= date('d M Y', strtotime($m['abha_linked_at'])) ?></span>
                <?php endif; ?>
                <span style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:2px 8px;font-size:.67rem;font-weight:700;">
                  <i class="fas fa-<?= $m['abha_verified'] ? 'shield-alt' : 'link' ?> me-1"></i><?= $m['abha_verified'] ? 'Verified' : 'Linked' ?>
                </span>
              </div>
            </div>
            <a href="../health/abha.php?tab=overview&link=<?= $m['id'] ?>" class="btn btn-sm btn-outline-success" style="font-size:.75rem;">
              <i class="fas fa-pen me-1"></i>Update ABHA
            </a>
          <?php else: ?>
            <?php
              $pend_q = $conn->prepare("SELECT id, abha_id, requested_at FROM abha_link_requests WHERE member_id=? AND status='Pending' LIMIT 1");
              $pend_q->bind_param('i', $id);
              $pend_q->execute();
              $pend_abha = $pend_q->get_result()->fetch_assoc();
            ?>
            <?php if ($pend_abha): ?>
              <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:12px;">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="fas fa-hourglass-half text-warning"></i>
                  <span style="font-size:.82rem;font-weight:700;color:#92400e;">Link Request Pending</span>
                </div>
                <div style="font-size:.78rem;color:#374151;">ABHA: <span style="font-family:monospace;font-weight:700;color:#00875a;"><?= htmlspecialchars($pend_abha['abha_id']) ?></span></div>
                <div style="font-size:.7rem;color:#9ca3af;margin-top:3px;">Submitted <?= date('d M Y', strtotime($pend_abha['requested_at'])) ?></div>
              </div>
              <a href="../health/abha.php?tab=requests" class="btn btn-sm btn-warning text-white" style="font-size:.75rem;">
                <i class="fas fa-check me-1"></i>Review Request
              </a>
            <?php else: ?>
              <div class="text-center py-3">
                <div style="width:52px;height:52px;border-radius:14px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
                  <i class="fas fa-id-card fa-lg" style="color:#d1d5db;"></i>
                </div>
                <div style="font-size:.82rem;color:#6b7280;margin-bottom:12px;">ABHA not linked for this member.</div>
                <a href="../health/abha.php?tab=overview" class="btn btn-sm" style="background:#00875a;color:#fff;font-size:.75rem;">
                  <i class="fas fa-link me-1"></i>Link ABHA Now
                </a>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Health Summary -->
      <?php if ($hp): ?>
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-heartbeat"></i></div>
          <div style="flex:1;">
            <h6>Health Summary</h6>
            <p>Last recorded health data</p>
          </div>
          <a href="health-profile.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success" style="font-size:.75rem;">
            <i class="fas fa-edit me-1"></i>Edit
          </a>
        </div>
        <div class="sec-card-body">
          <div class="row g-3">
            <?php if ($hp['height_cm']): ?>
            <div class="col-6 col-sm-3">
              <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:700;color:#0C74C5;"><?= $hp['height_cm'] ?> <span style="font-size:.7rem;">cm</span></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">Height</div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($hp['weight_kg']): ?>
            <div class="col-6 col-sm-3">
              <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:700;color:#16a34a;"><?= $hp['weight_kg'] ?> <span style="font-size:.7rem;">kg</span></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">Weight</div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($hp['bmi']): ?>
            <div class="col-6 col-sm-3">
              <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:700;color:#f59e0b;"><?= $hp['bmi'] ?></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">BMI</div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($hp['blood_pressure']): ?>
            <div class="col-6 col-sm-3">
              <div style="background:#f9fafb;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:700;color:#ef4444;"><?= htmlspecialchars($hp['blood_pressure']) ?></div>
                <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;">BP</div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($hp['known_allergies']): ?>
            <div class="col-12">
              <div class="detail-row" style="padding-top:0;">
                <div class="detail-icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                  <div class="detail-label">Known Allergies</div>
                  <div class="detail-val"><?= htmlspecialchars($hp['known_allergies']) ?></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($hp['chronic_conditions']): ?>
            <div class="col-12">
              <div class="detail-row">
                <div class="detail-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-notes-medical"></i></div>
                <div>
                  <div class="detail-label">Chronic Conditions</div>
                  <div class="detail-val"><?= htmlspecialchars($hp['chronic_conditions']) ?></div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="sec-card">
        <div class="sec-card-head">
          <div class="icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-heartbeat"></i></div>
          <div>
            <h6>Health Profile</h6>
            <p>No health data recorded yet</p>
          </div>
        </div>
        <div class="sec-card-body text-center py-2">
          <div style="width:52px;height:52px;border-radius:14px;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
            <i class="fas fa-heartbeat fa-lg" style="color:#fca5a5;"></i>
          </div>
          <div style="font-size:.84rem;color:#6b7280;margin-bottom:14px;">No health profile created for this member yet.</div>
          <a href="health-profile.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success">
            <i class="fas fa-plus me-1"></i>Create Health Profile
          </a>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /col-lg-8 -->
  </div><!-- /row -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const toggler = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.school-sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  if (toggler && sidebar) {
    toggler.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay && overlay.classList.toggle('open');
    });
    overlay && overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }
</script>
</body>
</html>
