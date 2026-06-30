<?php
include_once "../../config/connect.php";
include_once "auth.php";

$search  = trim($_GET['q'] ?? '');
$class_f = trim($_GET['class'] ?? '');
$filter  = trim($_GET['filter'] ?? '');   /* 'with_hp' | 'no_hp' | '' */

$where = "sm.school_id=$teacher_school_id AND sm.type='Student' AND sm.status='Active'";
if ($search)  $where .= " AND (sm.name LIKE '%" . mysqli_real_escape_string($conn,$search) . "%' OR sm.member_uid LIKE '%" . mysqli_real_escape_string($conn,$search) . "%')";
if ($class_f) $where .= " AND sm.class='" . mysqli_real_escape_string($conn,$class_f) . "'";
if ($filter === 'with_hp') $where .= " AND hp.id IS NOT NULL";
if ($filter === 'no_hp')   $where .= " AND hp.id IS NULL";

$members = $conn->query("
    SELECT sm.*, hp.height_cm, hp.weight_kg, hp.vision, hp.known_allergies, hp.updated_at AS hp_updated, hp.id AS hp_id
    FROM school_members sm
    LEFT JOIN member_health_profiles hp ON hp.member_id = sm.id
    WHERE $where
    ORDER BY sm.class+0 ASC, sm.class ASC, sm.section ASC, sm.name ASC");

$classes = $conn->query("SELECT DISTINCT class FROM school_members WHERE school_id=$teacher_school_id AND type='Student' AND status='Active' AND class IS NOT NULL ORDER BY class+0 ASC, class ASC");

/* Summary counts */
$total  = (int)$conn->query("SELECT COUNT(*) FROM school_members WHERE school_id=$teacher_school_id AND type='Student' AND status='Active'")->fetch_row()[0];
$with_hp = (int)$conn->query("SELECT COUNT(*) FROM school_members sm JOIN member_health_profiles hp ON hp.member_id=sm.id WHERE sm.school_id=$teacher_school_id AND sm.type='Student' AND sm.status='Active'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Health Records | <?= htmlspecialchars($teacher_school) ?></title>
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
    .filter-chip { display:inline-block; padding:4px 12px; border-radius:20px; font-size:.73rem; font-weight:600; border:1.5px solid #e5e7eb; color:#374151; text-decoration:none; background:#fff; transition:.15s; }
    .filter-chip:hover, .filter-chip.active { background:var(--primary); color:#fff; border-color:var(--primary); }
  </style>
</head>
<body>
<?php $active_page = 'health'; $base_path = ''; include '../inc/sidebar-teacher.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;"><i class="fas fa-heartbeat me-2 text-danger"></i>Health Records</div>
      <div style="font-size:.75rem;color:#6b7280;"><?= htmlspecialchars($teacher_school) ?> &middot; <?= $with_hp ?> / <?= $total ?> profiles filled</div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="students.php" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex align-items-center gap-1">
      <i class="fas fa-users"></i><span>Students</span>
    </a>
    <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <!-- Progress summary -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card card-primary">
        <i class="fas fa-users bg-icon"></i>
        <div class="num"><?= $total ?></div>
        <div class="lbl">Total Students</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card card-green">
        <i class="fas fa-check-circle bg-icon"></i>
        <div class="num"><?= $with_hp ?></div>
        <div class="lbl">Profiles Filled</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card card-orange">
        <i class="fas fa-exclamation-circle bg-icon"></i>
        <div class="num"><?= $total - $with_hp ?></div>
        <div class="lbl">Profiles Pending</div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" class="d-flex flex-wrap gap-2 mb-4 align-items-center">
    <input type="text" class="form-control form-control-sm" name="q"
      value="<?= htmlspecialchars($search) ?>" placeholder="Search student name or UID…" style="max-width:240px;">
    <select class="form-select form-select-sm" name="class" style="max-width:140px;">
      <option value="">All Classes</option>
      <?php while ($cl = $classes->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($cl['class']) ?>" <?= $class_f === $cl['class'] ? 'selected' : '' ?>>
          Class <?= htmlspecialchars($cl['class']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
    <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
    <?php if ($search || $class_f || $filter): ?>
      <a href="health-records.php" class="btn btn-outline-secondary btn-sm">Clear</a>
    <?php endif; ?>
    <div class="ms-auto d-flex gap-2">
      <a href="?filter=<?= $filter==='with_hp'?'':'with_hp' ?>&q=<?= urlencode($search) ?>&class=<?= urlencode($class_f) ?>"
         class="filter-chip <?= $filter==='with_hp' ? 'active' : '' ?>">
        <i class="fas fa-check me-1"></i>Has Profile
      </a>
      <a href="?filter=<?= $filter==='no_hp'?'':'no_hp' ?>&q=<?= urlencode($search) ?>&class=<?= urlencode($class_f) ?>"
         class="filter-chip <?= $filter==='no_hp' ? 'active' : '' ?>">
        <i class="fas fa-times me-1"></i>Missing
      </a>
    </div>
  </form>

  <!-- Records table -->
  <div class="sec-card">
    <div class="sec-card-head">
      <div class="hd-left">
        <div class="icon" style="background:#fff5f5;color:#dc2626;"><i class="fas fa-heartbeat"></i></div>
        <div><h6>Student Health Records</h6><p>Showing <?= $members->num_rows ?> students</p></div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:.83rem;">
        <thead style="background:#f9fafb;">
          <tr>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">#</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Student</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Class</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Ht / Wt</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">BMI</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Vision</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Last Updated</th>
            <th style="font-size:.72rem;padding:10px 16px;color:#6b7280;font-weight:600;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($members->num_rows === 0): ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="fas fa-heartbeat fa-2x d-block mb-2" style="opacity:.2;"></i>
                No records found.
              </td>
            </tr>
          <?php endif; ?>
          <?php $i = 1; while ($r = $members->fetch_assoc()):
            $bmi = ($r['height_cm'] && $r['weight_kg']) ? round($r['weight_kg']/(($r['height_cm']/100)**2),1) : null;
            $bmi_col = '';
            if ($bmi) {
              if ($bmi < 18.5) $bmi_col = '#d97706';
              elseif ($bmi < 25) $bmi_col = '#16a34a';
              elseif ($bmi < 30) $bmi_col = '#ea580c';
              else $bmi_col = '#dc2626';
            }
          ?>
          <tr>
            <td style="padding:10px 16px;color:#9ca3af;"><?= $i++ ?></td>
            <td style="padding:10px 16px;">
              <div style="display:flex;align-items:center;gap:9px;">
                <div style="width:28px;height:28px;border-radius:7px;background:#eaf4fd;color:#0C74C5;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;"><?= strtoupper(substr($r['name'],0,1)) ?></div>
                <div>
                  <div class="fw-semibold" style="color:#1f2937;"><?= htmlspecialchars($r['name']) ?></div>
                  <div style="font-size:.68rem;color:#9ca3af;font-family:monospace;"><?= htmlspecialchars($r['member_uid']) ?></div>
                </div>
              </div>
            </td>
            <td style="padding:10px 16px;"><?= htmlspecialchars(trim(($r['class']??'—').' '.($r['section']??''))) ?></td>
            <td style="padding:10px 16px;">
              <?php if ($r['height_cm']): ?>
                <span style="color:#374151;"><?= $r['height_cm'] ?>cm / <?= $r['weight_kg'] ?>kg</span>
              <?php else: ?>
                <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;">
              <?php if ($bmi): ?>
                <span style="font-weight:700;color:<?= $bmi_col ?>;"><?= $bmi ?></span>
              <?php else: ?>
                <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;">
              <?= $r['vision'] ? htmlspecialchars($r['vision']) : '<span style="color:#d1d5db;">—</span>' ?>
            </td>
            <td style="padding:10px 16px;font-size:.78rem;">
              <?php if ($r['hp_updated']): ?>
                <span style="color:#6b7280;"><?= date('d M Y', strtotime($r['hp_updated'])) ?></span>
              <?php elseif ($r['hp_id']): ?>
                <span style="color:#9ca3af;">Created</span>
              <?php else: ?>
                <span style="color:#fca5a5;font-weight:600;">Never</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;">
              <a href="student-health.php?id=<?= $r['id'] ?>" class="btn btn-sm <?= $r['hp_id'] ? 'btn-outline-success' : 'btn-outline-primary' ?>" style="font-size:.75rem;padding:3px 10px;">
                <i class="fas fa-<?= $r['hp_id'] ? 'edit' : 'plus' ?> me-1"></i><?= $r['hp_id'] ? 'Edit' : 'Add' ?>
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
