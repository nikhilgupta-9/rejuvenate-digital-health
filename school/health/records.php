<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all'; // all | students | teachers | staff | missing

$where = "WHERE sm.school_id=$school_id AND sm.status != 'Pending'";
if ($search) {
  $s = mysqli_real_escape_string($conn, $search);
  $where .= " AND (sm.name LIKE '%$s%' OR sm.roll_number LIKE '%$s%' OR sm.email LIKE '%$s%')";
}
if ($filter === 'students')
  $where .= " AND sm.type='Student'";
if ($filter === 'teachers')
  $where .= " AND sm.type='Teacher'";
if ($filter === 'staff')
  $where .= " AND sm.type='Staff'";
if ($filter === 'missing')
  $where .= " AND hp.id IS NULL";

$all = mysqli_query($conn, "
    SELECT sm.id, sm.name, sm.type, sm.member_uid, sm.class, sm.section, sm.roll_number,
           sm.designation, sm.employee_id, sm.email, sm.gender, sm.dob,
           hp.id as hp_id, hp.height_cm, hp.weight_kg, hp.vision,
           hp.known_allergies, hp.vaccination_status, hp.updated_at as hp_updated
    FROM school_members sm
    LEFT JOIN member_health_profiles hp ON hp.member_id = sm.id
    $where
    ORDER BY sm.type ASC, sm.class+0 ASC, sm.class ASC, sm.section ASC, sm.roll_number+0 ASC, sm.name ASC
");

// Group data in PHP
$students_by_class = [];
$teachers = [];
$staff_list = [];
$total = $with_hp = $missing_hp = 0;
$all_rows = [];

while ($r = mysqli_fetch_assoc($all)) {
  $total++;
  if ($r['hp_id'])
    $with_hp++;
  else
    $missing_hp++;
  $all_rows[] = $r;

  if ($r['type'] === 'Student') {
    $cls = trim($r['class'] ?: '');
    $key = $cls !== '' ? $cls : '__unassigned__';
    $students_by_class[$key][] = $r;
  } elseif ($r['type'] === 'Teacher') {
    $teachers[] = $r;
  } else {
    $staff_list[] = $r;
  }
}

// Natural-sort classes (so Class 2 < Class 10)
uksort($students_by_class, function ($a, $b) {
  if ($a === '__unassigned__')
    return 1;
  if ($b === '__unassigned__')
    return -1;
  return strnatcmp($a, $b);
});

// Count totals for tab badges
$count_students = array_sum(array_map('count', $students_by_class));
$count_teachers = count($teachers);
$count_staff = count($staff_list);

// Helper: BMI calculation and label
function bmiInfo($h, $w)
{
  if (!$h || !$w)
    return null;
  $bmi = round($w / (($h / 100) ** 2), 1);
  if ($bmi < 18.5)
    $label = ['Underweight', '#f59e0b'];
  elseif ($bmi < 25)
    $label = ['Normal', '#16a34a'];
  elseif ($bmi < 30)
    $label = ['Overweight', '#f59e0b'];
  else
    $label = ['Obese', '#ef4444'];
  return ['val' => $bmi, 'label' => $label[0], 'color' => $label[1]];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Health Records</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    /* ── Sec card (shared) ── */
    .sec-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
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
      width: 34px;
      height: 34px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .88rem;
      flex-shrink: 0;
    }

    .sec-card-head h6 {
      margin: 0;
      font-size: .9rem;
      font-weight: 700;
      color: #1f2937;
    }

    .sec-card-head p {
      margin: 0;
      font-size: .73rem;
      color: #9ca3af;
    }

    /* ── Stat tiles ── */
    .stat-tile {
      background: #fff;
      border-radius: 12px;
      padding: 18px 20px;
      box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
    }

    .stat-tile .num {
      font-size: 1.8rem;
      font-weight: 700;
      line-height: 1;
    }

    .stat-tile .lbl {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: #9ca3af;
      margin-top: 4px;
    }

    /* ── Tab bar ── */
    .hr-tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .hr-tabs a {
      padding: 7px 16px;
      border-radius: 20px;
      text-decoration: none;
      font-size: .81rem;
      font-weight: 600;
      color: #6b7280;
      border: 1px solid #e5e7eb;
      background: #fff;
      transition: .18s;
    }

    .hr-tabs a.active,
    .hr-tabs a:hover {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .hr-tabs a.warn-tab.active,
    .hr-tabs a.warn-tab:hover {
      background: #dc2626;
      border-color: #dc2626;
    }

    /* ── Class group header ── */
    .class-group-head {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 18px;
      background: #f8fafc;
      border-bottom: 1px solid #e5e7eb;
      cursor: pointer;
      user-select: none;
    }

    .class-group-head:hover {
      background: #f1f5f9;
    }

    .class-label {
      font-size: .82rem;
      font-weight: 700;
      color: #1f2937;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .class-badge {
      background: var(--primary);
      color: #fff;
      border-radius: 20px;
      padding: 2px 10px;
      font-size: .68rem;
      font-weight: 700;
    }

    .caret {
      transition: transform .2s;
      color: #9ca3af;
      font-size: .75rem;
    }

    .class-group.collapsed .caret {
      transform: rotate(-90deg);
    }

    .class-group.collapsed .class-body {
      display: none;
    }

    /* ── Health status badge ── */
    .hp-badge-ok {
      background: #f0fdf4;
      color: #16a34a;
      border-radius: 20px;
      padding: 2px 9px;
      font-size: .7rem;
      font-weight: 700;
    }

    .hp-badge-missing {
      background: #fef2f2;
      color: #ef4444;
      border-radius: 20px;
      padding: 2px 9px;
      font-size: .7rem;
      font-weight: 700;
    }

    /* ── Member cell ── */
    .member-avatar-sm {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: .85rem;
      color: #fff;
      flex-shrink: 0;
    }

    /* ── Section heading ── */
    .section-heading {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 28px 0 14px;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .8px;
      color: #9ca3af;
    }

    .section-heading::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e5e7eb;
    }

    /* ── Progress bar ── */
    .coverage-bar {
      height: 6px;
      background: #e5e7eb;
      border-radius: 3px;
      overflow: hidden;
      margin-top: 6px;
    }

    .coverage-fill {
      height: 100%;
      border-radius: 3px;
      background: var(--primary);
      transition: width .4s;
    }
  </style>
</head>

<body>
  <?php $active_page = 'health';
  $base_path = '../';
  include '../inc/sidebar-school.php'; ?>

  <div class="school-topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;">
        <i class="fas fa-heartbeat me-2 text-danger"></i>Health Records
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>

  <main class="school-content">

    <!-- ── Stats row ── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-tile">
          <div class="d-flex align-items-center gap-3">
            <div
              style="width:42px;height:42px;border-radius:10px;background:#eaf4fd;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--primary);">
              <i class="fas fa-users"></i>
            </div>
            <div>
              <div class="num"><?= $total ?></div>
              <div class="lbl">Total Members</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-tile">
          <div class="d-flex align-items-center gap-3">
            <div
              style="width:42px;height:42px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#16a34a;">
              <i class="fas fa-heartbeat"></i>
            </div>
            <div>
              <div class="num" style="color:#16a34a;"><?= $with_hp ?></div>
              <div class="lbl">With Health Profile</div>
            </div>
          </div>
          <?php if ($total > 0): ?>
            <div class="coverage-bar mt-3">
              <div class="coverage-fill" style="width:<?= round($with_hp / $total * 100) ?>%;background:#16a34a;"></div>
            </div>
            <div style="font-size:.68rem;color:#9ca3af;margin-top:3px;"><?= round($with_hp / $total * 100) ?>% coverage</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-tile">
          <div class="d-flex align-items-center gap-3">
            <div
              style="width:42px;height:42px;border-radius:10px;background:#fef2f2;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#ef4444;">
              <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
              <div class="num" style="color:#ef4444;"><?= $missing_hp ?></div>
              <div class="lbl">Missing Profile</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-tile">
          <div class="d-flex align-items-center gap-3">
            <div
              style="width:42px;height:42px;border-radius:10px;background:#eaf4fd;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--primary);">
              <i class="fas fa-user-graduate"></i>
            </div>
            <div>
              <div class="num"><?= count($students_by_class) ?></div>
              <div class="lbl">Classes</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Search + Tab filters ── -->
    <form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>"
        placeholder="Search name, roll number, email..." style="max-width:280px;">
      <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
      <?php if ($search || $filter !== 'all'): ?>
        <a href="records.php" class="btn btn-outline-secondary btn-sm">Clear</a>
      <?php endif; ?>
    </form>

    <div class="hr-tabs">
      <a href="?filter=all<?= $search ? '&q=' . urlencode($search) : '' ?>" class="<?= $filter === 'all' ? 'active' : '' ?>">
        <i class="fas fa-th-list me-1"></i>All (<?= $total ?>)
      </a>
      <a href="?filter=students<?= $search ? '&q=' . urlencode($search) : '' ?>"
        class="<?= $filter === 'students' ? 'active' : '' ?>">
        <i class="fas fa-user-graduate me-1"></i>Students (<?= $count_students ?>)
      </a>
      <a href="?filter=teachers<?= $search ? '&q=' . urlencode($search) : '' ?>"
        class="<?= $filter === 'teachers' ? 'active' : '' ?>">
        <i class="fas fa-chalkboard-teacher me-1"></i>Teachers (<?= $count_teachers ?>)
      </a>
      <a href="?filter=staff<?= $search ? '&q=' . urlencode($search) : '' ?>"
        class="<?= $filter === 'staff' ? 'active' : '' ?>">
        <i class="fas fa-user-tie me-1"></i>Staff (<?= $count_staff ?>)
      </a>
      <?php if ($missing_hp > 0): ?>
        <a href="?filter=missing<?= $search ? '&q=' . urlencode($search) : '' ?>"
          class="warn-tab <?= $filter === 'missing' ? 'active' : '' ?>">
          <i class="fas fa-exclamation-circle me-1"></i>Missing Profile (<?= $missing_hp ?>)
        </a>
      <?php endif; ?>
    </div>

    <?php if ($total === 0): ?>
      <!-- Empty state -->
      <div class="sec-card">
        <div class="text-center py-5">
          <div
            style="width:64px;height:64px;border-radius:16px;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="fas fa-heartbeat fa-xl" style="color:#fca5a5;"></i>
          </div>
          <div style="font-weight:700;font-size:.95rem;color:#374151;">No records found</div>
          <div style="font-size:.82rem;color:#9ca3af;margin-top:4px;">
            <?= $search ? 'Try a different search term.' : 'No members match the selected filter.' ?>
          </div>
          <a href="records.php" class="btn btn-sm btn-outline-primary mt-3">Clear Filters</a>
        </div>
      </div>

    <?php else: ?>

      <!-- ══════════════════════════════════════════════
       STUDENTS SECTION
  ══════════════════════════════════════════════ -->
      <?php if ($filter === 'all' || $filter === 'students' || $filter === 'missing'): ?>
        <?php if (!empty($students_by_class)): ?>

          <?php if ($filter === 'all'): ?>
            <div class="section-heading">
              <i class="fas fa-user-graduate" style="color:#0C74C5;"></i>
              Students &nbsp;<span style="font-size:.8rem;font-weight:600;color:#0C74C5;"><?= $count_students ?></span>
            </div>
          <?php endif; ?>

          <?php foreach ($students_by_class as $cls_key => $students): ?>
            <?php
            $cls_label = ($cls_key === '__unassigned__') ? 'Unassigned' : 'Class ' . htmlspecialchars($cls_key);
            $cls_total = count($students);
            $cls_with = count(array_filter($students, fn($s) => $s['hp_id']));
            $cls_pct = $cls_total > 0 ? round($cls_with / $cls_total * 100) : 0;
            ?>
            <div class="sec-card class-group" id="class-<?= htmlspecialchars($cls_key) ?>">
              <!-- Class header (clickable to collapse) -->
              <div class="class-group-head" onclick="toggleClass('class-<?= htmlspecialchars($cls_key) ?>')">
                <div
                  style="width:34px;height:34px;border-radius:9px;background:#eaf4fd;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#0C74C5;font-weight:700;flex-shrink:0;">
                  <?= ($cls_key === '__unassigned__') ? '?' : htmlspecialchars($cls_key) ?>
                </div>
                <div class="class-label" style="flex:1;">
                  <?= $cls_label ?>
                  <span class="class-badge"><?= $cls_total ?> student<?= $cls_total > 1 ? 's' : '' ?></span>
                  <!-- Coverage mini bar -->
                  <div class="d-none d-sm-flex align-items-center gap-2" style="margin-left:8px;">
                    <div style="width:80px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
                      <div
                        style="width:<?= $cls_pct ?>%;height:100%;border-radius:3px;background:<?= $cls_pct === 100 ? '#16a34a' : '#0C74C5' ?>;">
                      </div>
                    </div>
                    <span style="font-size:.68rem;color:#9ca3af;"><?= $cls_with ?>/<?= $cls_total ?> profiles</span>
                  </div>
                </div>
                <i class="fas fa-chevron-down caret"></i>
              </div>

              <!-- Class body -->
              <div class="class-body">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
                    <thead class="table-light">
                      <tr>
                        <th style="font-size:.7rem;padding-left:18px;">Student</th>
                        <th style="font-size:.7rem;">Section · Roll</th>
                        <th style="font-size:.7rem;">Health Profile</th>
                        <th style="font-size:.7rem;">Height / Weight</th>
                        <th style="font-size:.7rem;">BMI</th>
                        <th style="font-size:.7rem;">Allergies</th>
                        <th style="font-size:.7rem;">Vaccination</th>
                        <th style="font-size:.7rem;">Updated</th>
                        <th style="font-size:.7rem;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($students as $r): ?>
                        <?php $bmi = bmiInfo($r['height_cm'], $r['weight_kg']); ?>
                        <tr <?= !$r['hp_id'] ? 'style="background:#fffbfb;"' : '' ?>>
                          <td style="padding-left:18px;">
                            <div class="d-flex align-items-center gap-2">
                              <div class="member-avatar-sm" style="background:#0C74C5;">
                                <?= strtoupper(substr($r['name'], 0, 1)) ?>
                              </div>
                              <div>
                                <div class="fw-semibold"><?= htmlspecialchars($r['name']) ?></div>
                                <div style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($r['member_uid']) ?></div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <?php if ($r['section']): ?>
                              <span
                                style="background:#eaf4fd;color:#0C74C5;border-radius:6px;padding:1px 7px;font-size:.7rem;font-weight:700;">§<?= htmlspecialchars($r['section']) ?></span>
                            <?php endif; ?>
                            <?php if ($r['roll_number']): ?>
                              <span
                                style="color:#9ca3af;font-size:.75rem;margin-left:3px;">#<?= htmlspecialchars($r['roll_number']) ?></span>
                            <?php endif; ?>
                            <?php if (!$r['section'] && !$r['roll_number']): ?>
                              <span class="text-muted">—</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($r['hp_id']): ?>
                              <span class="hp-badge-ok"><i class="fas fa-check me-1"></i>Done</span>
                            <?php else: ?>
                              <span class="hp-badge-missing"><i class="fas fa-times me-1"></i>Missing</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?= $r['height_cm'] ? $r['height_cm'] . ' cm' : '<span class="text-muted">—</span>' ?>
                            <?= ($r['height_cm'] && $r['weight_kg']) ? ' / ' : '' ?>
                            <?= $r['weight_kg'] ? $r['weight_kg'] . ' kg' : '' ?>
                          </td>
                          <td>
                            <?php if ($bmi): ?>
                              <span style="color:<?= $bmi['color'] ?>;font-weight:700;"><?= $bmi['val'] ?></span>
                              <div style="font-size:.65rem;color:#9ca3af;"><?= $bmi['label'] ?></div>
                            <?php else: ?>
                              <span class="text-muted">—</span>
                            <?php endif; ?>
                          </td>
                          <td style="max-width:110px;">
                            <?php if ($r['known_allergies']): ?>
                              <span title="<?= htmlspecialchars($r['known_allergies']) ?>" style="cursor:help;">
                                <?= htmlspecialchars(substr($r['known_allergies'], 0, 25)) ?>            <?= strlen($r['known_allergies']) > 25 ? '…' : '' ?>
                              </span>
                            <?php else: ?>
                              <span class="text-muted" style="font-size:.75rem;">None recorded</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($r['vaccination_status']): ?>
                              <span style="color:#16a34a;font-size:.78rem;"><i class="fas fa-syringe me-1"></i>Yes</span>
                            <?php else: ?>
                              <span class="text-muted" style="font-size:.75rem;">—</span>
                            <?php endif; ?>
                          </td>
                          <td style="color:#9ca3af;font-size:.75rem;">
                            <?= $r['hp_updated'] ? date('d M Y', strtotime($r['hp_updated'])) : '<span style="color:#fca5a5;">Never</span>' ?>
                          </td>
                          <td>
                            <a href="../members/health-profile.php?id=<?= $r['id'] ?>"
                              class="btn btn-sm <?= $r['hp_id'] ? 'btn-outline-success' : 'btn-warning text-white' ?>"
                              style="font-size:.72rem;padding:3px 9px;white-space:nowrap;">
                              <i class="fas fa-<?= $r['hp_id'] ? 'edit' : 'plus' ?> me-1"></i><?= $r['hp_id'] ? 'Edit' : 'Add' ?>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; // end students check ?>
      <?php endif; // end students filter check ?>


      <!-- ══════════════════════════════════════════════
       TEACHERS SECTION
  ══════════════════════════════════════════════ -->
      <?php if (($filter === 'all' || $filter === 'teachers' || $filter === 'missing') && !empty($teachers)): ?>

        <?php if ($filter === 'all'): ?>
          <div class="section-heading">
            <i class="fas fa-chalkboard-teacher" style="color:#16a34a;"></i>
            Teachers &nbsp;<span style="font-size:.8rem;font-weight:600;color:#16a34a;"><?= $count_teachers ?></span>
          </div>
        <?php endif; ?>

        <div class="sec-card">
          <div class="sec-card-head">
            <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-chalkboard-teacher"></i></div>
            <div style="flex:1;">
              <h6>Teachers</h6>
              <p><?= count(array_filter($teachers, fn($t) => $t['hp_id'])) ?>/<?= $count_teachers ?> health profiles
                completed</p>
            </div>
            <div style="width:80px;">
              <?php $t_pct = $count_teachers > 0 ? round(count(array_filter($teachers, fn($t) => $t['hp_id'])) / $count_teachers * 100) : 0; ?>
              <div style="font-size:.68rem;color:#9ca3af;text-align:right;margin-bottom:3px;"><?= $t_pct ?>%</div>
              <div class="coverage-bar">
                <div class="coverage-fill" style="width:<?= $t_pct ?>%;background:#16a34a;"></div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
              <thead class="table-light">
                <tr>
                  <th style="font-size:.7rem;padding-left:18px;">#</th>
                  <th style="font-size:.7rem;">Teacher</th>
                  <th style="font-size:.7rem;">Designation</th>
                  <th style="font-size:.7rem;">Health Profile</th>
                  <th style="font-size:.7rem;">Height / Weight</th>
                  <th style="font-size:.7rem;">BMI</th>
                  <th style="font-size:.7rem;">Allergies</th>
                  <th style="font-size:.7rem;">Updated</th>
                  <th style="font-size:.7rem;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teachers as $i => $r): ?>
                  <?php $bmi = bmiInfo($r['height_cm'], $r['weight_kg']); ?>
                  <tr <?= !$r['hp_id'] ? 'style="background:#f9fff9;"' : '' ?>>
                    <td style="padding-left:18px;"><small class="text-muted"><?= $i + 1 ?></small></td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="member-avatar-sm" style="background:#16a34a;">
                          <?= strtoupper(substr($r['name'], 0, 1)) ?>
                        </div>
                        <div>
                          <div class="fw-semibold"><?= htmlspecialchars($r['name']) ?></div>
                          <div style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($r['member_uid']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td style="color:#6b7280;">
                      <?= $r['designation'] ? htmlspecialchars($r['designation']) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td>
                      <?php if ($r['hp_id']): ?>
                        <span class="hp-badge-ok"><i class="fas fa-check me-1"></i>Done</span>
                      <?php else: ?>
                        <span class="hp-badge-missing"><i class="fas fa-times me-1"></i>Missing</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= $r['height_cm'] ? $r['height_cm'] . ' cm' : '<span class="text-muted">—</span>' ?>
                      <?= ($r['height_cm'] && $r['weight_kg']) ? ' / ' : '' ?>
                      <?= $r['weight_kg'] ? $r['weight_kg'] . ' kg' : '' ?>
                    </td>
                    <td>
                      <?php if ($bmi): ?>
                        <span style="color:<?= $bmi['color'] ?>;font-weight:700;"><?= $bmi['val'] ?></span>
                        <div style="font-size:.65rem;color:#9ca3af;"><?= $bmi['label'] ?></div>
                      <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td style="max-width:110px;">
                      <?= $r['known_allergies']
                        ? '<span title="' . htmlspecialchars($r['known_allergies']) . '" style="cursor:help;">' . htmlspecialchars(substr($r['known_allergies'], 0, 25)) . (strlen($r['known_allergies']) > 25 ? '…' : '') . '</span>'
                        : '<span class="text-muted" style="font-size:.75rem;">None recorded</span>' ?>
                    </td>
                    <td style="color:#9ca3af;font-size:.75rem;">
                      <?= $r['hp_updated'] ? date('d M Y', strtotime($r['hp_updated'])) : '<span style="color:#fca5a5;">Never</span>' ?>
                    </td>
                    <td>
                      <a href="../members/health-profile.php?id=<?= $r['id'] ?>"
                        class="btn btn-sm <?= $r['hp_id'] ? 'btn-outline-success' : 'btn-warning text-white' ?>"
                        style="font-size:.72rem;padding:3px 9px;white-space:nowrap;">
                        <i class="fas fa-<?= $r['hp_id'] ? 'edit' : 'plus' ?> me-1"></i><?= $r['hp_id'] ? 'Edit' : 'Add' ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>


      <!-- ══════════════════════════════════════════════
       STAFF SECTION
  ══════════════════════════════════════════════ -->
      <?php if (($filter === 'all' || $filter === 'staff' || $filter === 'missing') && !empty($staff_list)): ?>

        <?php if ($filter === 'all'): ?>
          <div class="section-heading">
            <i class="fas fa-user-tie" style="color:#7c3aed;"></i>
            Staff &nbsp;<span style="font-size:.8rem;font-weight:600;color:#7c3aed;"><?= $count_staff ?></span>
          </div>
        <?php endif; ?>

        <div class="sec-card">
          <div class="sec-card-head">
            <div class="icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-user-tie"></i></div>
            <div style="flex:1;">
              <h6>Staff</h6>
              <p><?= count(array_filter($staff_list, fn($s) => $s['hp_id'])) ?>/<?= $count_staff ?> health profiles
                completed</p>
            </div>
            <div style="width:80px;">
              <?php $s_pct = $count_staff > 0 ? round(count(array_filter($staff_list, fn($s) => $s['hp_id'])) / $count_staff * 100) : 0; ?>
              <div style="font-size:.68rem;color:#9ca3af;text-align:right;margin-bottom:3px;"><?= $s_pct ?>%</div>
              <div class="coverage-bar">
                <div class="coverage-fill" style="width:<?= $s_pct ?>%;background:#7c3aed;"></div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
              <thead class="table-light">
                <tr>
                  <th style="font-size:.7rem;padding-left:18px;">#</th>
                  <th style="font-size:.7rem;">Staff Member</th>
                  <th style="font-size:.7rem;">Designation</th>
                  <th style="font-size:.7rem;">Health Profile</th>
                  <th style="font-size:.7rem;">Height / Weight</th>
                  <th style="font-size:.7rem;">BMI</th>
                  <th style="font-size:.7rem;">Allergies</th>
                  <th style="font-size:.7rem;">Updated</th>
                  <th style="font-size:.7rem;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($staff_list as $i => $r): ?>
                  <?php $bmi = bmiInfo($r['height_cm'], $r['weight_kg']); ?>
                  <tr <?= !$r['hp_id'] ? 'style="background:#fdf9ff;"' : '' ?>>
                    <td style="padding-left:18px;"><small class="text-muted"><?= $i + 1 ?></small></td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="member-avatar-sm" style="background:#7c3aed;">
                          <?= strtoupper(substr($r['name'], 0, 1)) ?>
                        </div>
                        <div>
                          <div class="fw-semibold"><?= htmlspecialchars($r['name']) ?></div>
                          <div style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($r['member_uid']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td style="color:#6b7280;">
                      <?= $r['designation'] ? htmlspecialchars($r['designation']) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td>
                      <?php if ($r['hp_id']): ?>
                        <span class="hp-badge-ok"><i class="fas fa-check me-1"></i>Done</span>
                      <?php else: ?>
                        <span class="hp-badge-missing"><i class="fas fa-times me-1"></i>Missing</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= $r['height_cm'] ? $r['height_cm'] . ' cm' : '<span class="text-muted">—</span>' ?>
                      <?= ($r['height_cm'] && $r['weight_kg']) ? ' / ' : '' ?>
                      <?= $r['weight_kg'] ? $r['weight_kg'] . ' kg' : '' ?>
                    </td>
                    <td>
                      <?php if ($bmi): ?>
                        <span style="color:<?= $bmi['color'] ?>;font-weight:700;"><?= $bmi['val'] ?></span>
                        <div style="font-size:.65rem;color:#9ca3af;"><?= $bmi['label'] ?></div>
                      <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td style="max-width:110px;">
                      <?= $r['known_allergies']
                        ? '<span title="' . htmlspecialchars($r['known_allergies']) . '" style="cursor:help;">' . htmlspecialchars(substr($r['known_allergies'], 0, 25)) . (strlen($r['known_allergies']) > 25 ? '…' : '') . '</span>'
                        : '<span class="text-muted" style="font-size:.75rem;">None recorded</span>' ?>
                    </td>
                    <td style="color:#9ca3af;font-size:.75rem;">
                      <?= $r['hp_updated'] ? date('d M Y', strtotime($r['hp_updated'])) : '<span style="color:#fca5a5;">Never</span>' ?>
                    </td>
                    <td>
                      <a href="../members/health-profile.php?id=<?= $r['id'] ?>"
                        class="btn btn-sm <?= $r['hp_id'] ? 'btn-outline-success' : 'btn-warning text-white' ?>"
                        style="font-size:.72rem;padding:3px 9px;white-space:nowrap;">
                        <i class="fas fa-<?= $r['hp_id'] ? 'edit' : 'plus' ?> me-1"></i><?= $r['hp_id'] ? 'Edit' : 'Add' ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; // end total > 0 ?>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function toggleClass(id) {
      document.getElementById(id).classList.toggle('collapsed');
    }
  </script>
</body>

</html>