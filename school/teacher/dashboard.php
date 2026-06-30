<?php
include_once "../../config/connect.php";
include_once "auth.php";

/* ── Teacher info ── */
$t_stmt = $conn->prepare("SELECT sm.*, s.school_name, s.city, s.state FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$t_stmt->bind_param('i', $teacher_id);
$t_stmt->execute();
$teacher = $t_stmt->get_result()->fetch_assoc();

/* ── Stats ── */
$total_students = (int)$conn->query("SELECT COUNT(*) FROM school_members WHERE school_id=$teacher_school_id AND type='Student' AND status='Active'")->fetch_row()[0];
$total_staff    = (int)$conn->query("SELECT COUNT(*) FROM school_members WHERE school_id=$teacher_school_id AND type IN('Teacher','Staff') AND status='Active'")->fetch_row()[0];

$hp_stmt = $conn->prepare("SELECT COUNT(*) FROM member_health_profiles WHERE school_id=?");
$hp_stmt->bind_param('i', $teacher_school_id); $hp_stmt->execute();
$with_hp = (int)$hp_stmt->get_result()->fetch_row()[0];

$abha_stmt = $conn->prepare("SELECT COUNT(*) FROM school_members WHERE school_id=? AND abha_linked=1 AND status='Active'");
$abha_stmt->bind_param('i', $teacher_school_id); $abha_stmt->execute();
$abha_count = (int)$abha_stmt->get_result()->fetch_row()[0];

$assigned_stmt = $conn->prepare("SELECT COUNT(*) FROM teacher_student_assignments WHERE teacher_id=?");
$assigned_stmt->bind_param('i', $teacher_id); $assigned_stmt->execute();
$assigned_count = (int)$assigned_stmt->get_result()->fetch_row()[0];

/* ── Recent students + health profile status in one query ── */
$recent_stmt = $conn->prepare("
    SELECT sm.*, IF(hp.id IS NOT NULL,1,0) AS has_hp
    FROM school_members sm
    LEFT JOIN member_health_profiles hp ON hp.member_id = sm.id
    WHERE sm.school_id=? AND sm.type='Student' AND sm.status='Active'
    ORDER BY sm.created_at DESC LIMIT 8");
$recent_stmt->bind_param('i', $teacher_school_id);
$recent_stmt->execute();
$recent_students = $recent_stmt->get_result();

/* ── Teacher ABHA status ── */
$my_abha = $teacher['abha_linked'] && $teacher['abha_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($teacher_school) ?> | Teacher Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    .sec-card { background:#fff; border-radius:12px; box-shadow:0 1px 8px rgba(0,0,0,.06); margin-bottom:20px; overflow:hidden; }
    .sec-card-head { padding:14px 20px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; }
    .sec-card-head .hd-left { display:flex; align-items:center; gap:10px; }
    .sec-card-head .icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.88rem; flex-shrink:0; }
    .sec-card-head h6 { margin:0; font-size:.9rem; font-weight:700; color:#1f2937; }
    .sec-card-head p  { margin:0; font-size:.73rem; color:#9ca3af; }

    /* Teacher hero banner */
    .teacher-hero {
      background: var(--primary);
      border-radius: 14px;
      padding: 22px 24px;
      color: #fff;
      margin-bottom: 22px;
      display: flex;
      align-items: center;
      gap: 18px;
      position: relative;
      overflow: hidden;
    }
    .teacher-hero::after {
      content: '';
      position: absolute;
      right: -40px; top: -40px;
      width: 180px; height: 180px;
      background: rgba(255,255,255,.06);
      border-radius: 50%;
    }
    .teacher-hero .t-avatar {
      width: 56px; height: 56px; border-radius: 14px;
      background: rgba(255,255,255,.2);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; font-weight: 700; color: #fff; flex-shrink: 0;
    }
    .teacher-hero .t-name { font-size: 1.05rem; font-weight: 700; }
    .teacher-hero .t-sub  { font-size: .8rem; opacity: .8; margin-top: 2px; }
    .teacher-hero .t-uid  { font-size: .72rem; opacity: .6; font-family: monospace; margin-top: 3px; }

    /* ABHA chip inside hero */
    .abha-chip {
      background: rgba(255,255,255,.15);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 20px;
      padding: 4px 12px;
      font-size: .72rem;
      font-weight: 700;
      white-space: nowrap;
    }
    .abha-chip.linked { background: rgba(0,135,90,.35); border-color: rgba(0,135,90,.5); }
  </style>
</head>
<body>
<?php $active_page = 'dashboard'; $base_path = ''; include '../inc/sidebar-teacher.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;">Welcome, <?= htmlspecialchars($teacher_name) ?>!</div>
      <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($teacher_school) ?> &middot; <?= date('l, d F Y') ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="profile.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
      <i class="fas fa-user"></i><span>Profile</span>
    </a>
    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
         style="width:34px;height:34px;font-size:.85rem;flex-shrink:0;">
      <?= strtoupper(substr($teacher_name, 0, 1)) ?>
    </div>
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <!-- Teacher Hero Banner -->
  <div class="teacher-hero">
    <div class="t-avatar"><?= strtoupper(substr($teacher_name, 0, 1)) ?></div>
    <div style="flex:1;min-width:0;">
      <div class="t-name"><?= htmlspecialchars($teacher_name) ?></div>
      <div class="t-sub">
        <?= htmlspecialchars($teacher['designation'] ?? 'Teacher') ?>
        <?= $teacher['assigned_class'] ? ' &middot; ' . htmlspecialchars($teacher['assigned_class']) : '' ?>
      </div>
      <div class="t-uid"><?= htmlspecialchars($teacher_uid) ?> &middot; <?= htmlspecialchars($teacher_school) ?></div>
    </div>
    <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
      <?php if ($my_abha): ?>
        <span class="abha-chip linked"><i class="fas fa-id-card me-1"></i>ABHA Linked</span>
      <?php else: ?>
        <a href="abha.php" class="abha-chip text-decoration-none text-white">
          <i class="fas fa-link me-1"></i>Link ABHA
        </a>
      <?php endif; ?>
      <div style="font-size:.72rem;opacity:.65;text-align:right;">
        Last login: <?= $teacher['last_login'] ? date('d M Y', strtotime($teacher['last_login'])) : 'First login' ?>
      </div>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card card-primary">
        <i class="fas fa-user-graduate bg-icon"></i>
        <div class="num"><?= $total_students ?></div>
        <div class="lbl">Students</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card card-green">
        <i class="fas fa-heartbeat bg-icon"></i>
        <div class="num"><?= $with_hp ?></div>
        <div class="lbl">Health Profiles</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card card-purple">
        <i class="fas fa-id-card bg-icon"></i>
        <div class="num"><?= $abha_count ?></div>
        <div class="lbl">ABHA Linked</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card card-orange">
        <i class="fas fa-link bg-icon"></i>
        <div class="num"><?= $assigned_count ?></div>
        <div class="lbl">Assigned to Me</div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <a href="students.php" class="quick-action">
        <i class="fas fa-users text-primary"></i>
        <div class="fw-semibold" style="font-size:.85rem;">View Students</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">All school students</div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="health-records.php" class="quick-action">
        <i class="fas fa-heartbeat text-danger"></i>
        <div class="fw-semibold" style="font-size:.85rem;">Health Records</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">View &amp; manage profiles</div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="profile.php" class="quick-action">
        <i class="fas fa-user-circle" style="color:#16a34a;"></i>
        <div class="fw-semibold" style="font-size:.85rem;">My Profile</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Account &amp; password</div>
      </a>
    </div>
    <div class="col-6 col-lg-3">
      <a href="abha.php" class="quick-action">
        <i class="fas fa-id-card" style="color:#00875a;"></i>
        <div class="fw-semibold" style="font-size:.85rem;">My ABHA</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:3px;">Health account ID</div>
      </a>
    </div>
  </div>

  <!-- Recent Students -->
  <div class="sec-card">
    <div class="sec-card-head">
      <div class="hd-left">
        <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-users"></i></div>
        <div>
          <h6>Students in Your School</h6>
          <p>Recently added — showing last 8</p>
        </div>
      </div>
      <a href="students.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:.83rem;">
        <thead style="background:#f9fafb;">
          <tr>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">#</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Student</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Class</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Roll No.</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Health Profile</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_students->num_rows === 0): ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="fas fa-users fa-2x mb-2 d-block" style="opacity:.2;"></i>
                No students found in your school.
              </td>
            </tr>
          <?php endif; ?>
          <?php $i = 1; while ($s = $recent_students->fetch_assoc()): ?>
          <tr>
            <td style="padding:10px 16px;color:#9ca3af;"><?= $i++ ?></td>
            <td style="padding:10px 16px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:30px;height:30px;border-radius:8px;background:#eaf4fd;color:#0C74C5;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">
                  <?= strtoupper(substr($s['name'], 0, 1)) ?>
                </div>
                <div>
                  <div class="fw-semibold" style="color:#1f2937;"><?= htmlspecialchars($s['name']) ?></div>
                  <div style="font-size:.7rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($s['member_uid']) ?></div>
                </div>
              </div>
            </td>
            <td style="padding:10px 16px;">
              <?php $cls = trim(($s['class'] ?? '') . ' ' . ($s['section'] ?? '')); ?>
              <?= $cls ? htmlspecialchars($cls) : '<span style="color:#9ca3af;">—</span>' ?>
            </td>
            <td style="padding:10px 16px;">
              <?= $s['roll_number'] ? htmlspecialchars($s['roll_number']) : '<span style="color:#9ca3af;">—</span>' ?>
            </td>
            <td style="padding:10px 16px;">
              <?php if ($s['has_hp']): ?>
                <span style="color:#16a34a;font-size:.78rem;font-weight:600;"><i class="fas fa-check-circle me-1"></i>Available</span>
              <?php else: ?>
                <span style="color:#9ca3af;font-size:.78rem;"><i class="fas fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i>Not created</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;">
              <a href="student-health.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-success" style="font-size:.75rem;padding:3px 10px;">
                <i class="fas fa-heartbeat me-1"></i>Health
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
