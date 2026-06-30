<?php
include_once "../../config/connect.php";
include_once "auth.php";

$search  = trim($_GET['q'] ?? '');
$class_f = trim($_GET['class'] ?? '');

$where = "sm.school_id=$teacher_school_id AND sm.type='Student' AND sm.status='Active'";
if ($search)  $where .= " AND (sm.name LIKE '%" . mysqli_real_escape_string($conn,$search) . "%' OR sm.roll_number LIKE '%" . mysqli_real_escape_string($conn,$search) . "%' OR sm.member_uid LIKE '%" . mysqli_real_escape_string($conn,$search) . "%')";
if ($class_f) $where .= " AND sm.class='" . mysqli_real_escape_string($conn,$class_f) . "'";

$students = $conn->query("
    SELECT sm.*, IF(hp.id IS NOT NULL,1,0) AS has_hp
    FROM school_members sm
    LEFT JOIN member_health_profiles hp ON hp.member_id = sm.id
    WHERE $where
    ORDER BY sm.class+0 ASC, sm.class ASC, sm.section ASC, sm.roll_number+0 ASC, sm.name ASC");

/* Class list for filter */
$classes = $conn->query("SELECT DISTINCT class FROM school_members WHERE school_id=$teacher_school_id AND type='Student' AND status='Active' AND class IS NOT NULL ORDER BY class+0 ASC, class ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Students | <?= htmlspecialchars($teacher_school) ?></title>
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
  </style>
</head>
<body>
<?php $active_page = 'students'; $base_path = ''; include '../inc/sidebar-teacher.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;"><i class="fas fa-user-graduate me-2 text-primary"></i>Students</div>
      <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($teacher_school) ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="health-records.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
      <i class="fas fa-heartbeat"></i><span>Health Records</span>
    </a>
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <!-- Search & Filter -->
  <form method="GET" class="d-flex flex-wrap gap-2 mb-4 align-items-center">
    <input type="text" class="form-control form-control-sm" name="q"
      value="<?= htmlspecialchars($search) ?>"
      placeholder="Search name, roll no, UID…"
      style="max-width:260px;">
    <select class="form-select form-select-sm" name="class" style="max-width:140px;">
      <option value="">All Classes</option>
      <?php while ($cl = $classes->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($cl['class']) ?>" <?= $class_f === $cl['class'] ? 'selected' : '' ?>>
          Class <?= htmlspecialchars($cl['class']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
    <?php if ($search || $class_f): ?>
      <a href="students.php" class="btn btn-outline-secondary btn-sm">Clear</a>
    <?php endif; ?>
    <span class="ms-auto text-muted" style="font-size:.78rem;"><?= $students->num_rows ?> student<?= $students->num_rows != 1 ? 's' : '' ?></span>
  </form>

  <!-- Table -->
  <div class="sec-card">
    <div class="sec-card-head">
      <div class="hd-left">
        <div class="icon" style="background:#eaf4fd;color:var(--primary);"><i class="fas fa-user-graduate"></i></div>
        <div>
          <h6>Student List</h6>
          <p><?= htmlspecialchars($teacher_school) ?></p>
        </div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:.83rem;">
        <thead style="background:#f9fafb;">
          <tr>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">#</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Student</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Class / Section</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Roll No.</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Health Profile</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">ABHA</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($students->num_rows === 0): ?>
            <tr>
              <td colspan="7" class="text-center py-5 text-muted">
                <i class="fas fa-user-slash fa-2x d-block mb-2" style="opacity:.2;"></i>
                No students found.
              </td>
            </tr>
          <?php endif; ?>
          <?php $i = 1; while ($s = $students->fetch_assoc()): ?>
          <tr>
            <td style="padding:10px 16px;color:#9ca3af;"><?= $i++ ?></td>
            <td style="padding:10px 16px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:30px;height:30px;border-radius:8px;background:#eaf4fd;color:#0C74C5;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">
                  <?= strtoupper(substr($s['name'],0,1)) ?>
                </div>
                <div>
                  <div class="fw-semibold" style="color:#1f2937;"><?= htmlspecialchars($s['name']) ?></div>
                  <div style="font-size:.7rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($s['member_uid']) ?></div>
                </div>
              </div>
            </td>
            <td style="padding:10px 16px;">
              <?php $cls = trim(($s['class']??'').($s['section'] ? ' '.$s['section'] : '')); ?>
              <?= $cls ? htmlspecialchars($cls) : '<span style="color:#9ca3af;">—</span>' ?>
            </td>
            <td style="padding:10px 16px;">
              <?= $s['roll_number'] ? htmlspecialchars($s['roll_number']) : '<span style="color:#9ca3af;">—</span>' ?>
            </td>
            <td style="padding:10px 16px;">
              <?php if ($s['has_hp']): ?>
                <span style="color:#16a34a;font-size:.78rem;font-weight:600;"><i class="fas fa-check-circle me-1"></i>Available</span>
              <?php else: ?>
                <span style="color:#9ca3af;font-size:.78rem;"><i class="fas fa-minus-circle me-1"></i>Not created</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;">
              <?php if ($s['abha_linked']): ?>
                <span style="color:#00875a;font-size:.78rem;font-weight:600;"><i class="fas fa-id-card me-1"></i>Linked</span>
              <?php else: ?>
                <span style="color:#9ca3af;font-size:.78rem;"><i class="fas fa-minus-circle me-1"></i>No</span>
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
