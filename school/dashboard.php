<?php
include_once "../config/connect.php";
include_once "auth/auth.php";

$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE school_id=$school_id AND type='Student'"))['c'];
$total_teachers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE school_id=$school_id AND type='Teacher'"))['c'];
$total_staff    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE school_id=$school_id AND type='Staff'"))['c'];
$abha_linked    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members WHERE school_id=$school_id AND abha_linked=1"))['c'];
$health_filled  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT member_id) as c FROM member_health_profiles WHERE school_id=$school_id"))['c'];
$total_members  = $total_students + $total_teachers + $total_staff;
$school_info    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM schools WHERE id=$school_id"));
$recent         = mysqli_query($conn, "SELECT * FROM school_members WHERE school_id=$school_id ORDER BY created_at DESC LIMIT 6");

$base_path   = '';   // relative prefix from school/ folder
$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="assets/school.css">
  <style>
    .section-title {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 1.1px; color: #6b7280;
      border-left: 3px solid var(--primary);
      padding-left: 10px; margin: 24px 0 14px 0;
    }
    .qcard {
      border-radius: 10px; border: 1px solid #e5e7eb; padding: 16px;
      text-align: center; text-decoration: none; color: #374151;
      display: block; transition: .2s; background: #fff;
      height: 100%;
    }
    .qcard:hover { border-color: var(--primary); background: #eaf4fd; color: var(--primary); transform: translateY(-2px); }
    .qcard .qi {
      width: 44px; height: 44px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; margin: 0 auto 10px;
    }
    .qcard h6 { font-size: .82rem; font-weight: 600; margin: 0; }
    .qcard p  { font-size: .7rem; color: #9ca3af; margin: 3px 0 0; }
    .info-alert {
      background: #fff8e1; border: 1px solid #ffd54f;
      border-radius: 8px; padding: 12px 16px; font-size: .83rem; color: #7c5400;
    }
  </style>
</head>
<body>

<?php include 'inc/sidebar-school.php'; ?>

<!-- Top Bar -->
<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:.95rem;font-weight:600;color:#1f2937;">Dashboard</div>
      <div class="d-none d-sm-block" style="font-size:.72rem;color:#9ca3af;"><?= date('l, d M Y') ?></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <div class="d-none d-md-flex flex-column text-end">
      <span style="font-weight:600;font-size:.82rem;"><?= htmlspecialchars($school_user_name) ?></span>
      <span style="font-size:.7rem;color:#9ca3af;"><?= htmlspecialchars($school_name) ?></span>
    </div>
    <div class="avatar-circle" style="width:34px;height:34px;font-size:.85rem;"><?= strtoupper(substr($school_user_name,0,1)) ?></div>
    <a href="auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <?php if (!$school_info || $school_info['status'] !== 'Active'): ?>
  <div class="info-alert mb-4">
    <i class="fas fa-info-circle me-2"></i>
    Your school account is <strong><?= $school_info['status'] ?? 'Pending' ?></strong>. Some features may be restricted until admin approval.
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <p class="section-title">Overview</p>
  <div class="row g-3">
    <div class="col-6 col-sm-4 col-xl-2">
      <div class="stat-card card-primary">
        <i class="fas fa-user-graduate bg-icon"></i>
        <div class="num"><?= $total_students ?></div>
        <div class="lbl">Students</div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
      <div class="stat-card card-green">
        <i class="fas fa-chalkboard-teacher bg-icon"></i>
        <div class="num"><?= $total_teachers ?></div>
        <div class="lbl">Teachers</div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
      <div class="stat-card card-orange">
        <i class="fas fa-user-tie bg-icon"></i>
        <div class="num"><?= $total_staff ?></div>
        <div class="lbl">Staff</div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
      <div class="stat-card card-accent">
        <i class="fas fa-users bg-icon"></i>
        <div class="num"><?= $total_members ?></div>
        <div class="lbl">Total Members</div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
      <div class="stat-card card-purple">
        <i class="fas fa-heartbeat bg-icon"></i>
        <div class="num"><?= $health_filled ?></div>
        <div class="lbl">Health Profiles</div>
      </div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
      <div class="stat-card card-teal2">
        <i class="fas fa-id-card bg-icon"></i>
        <div class="num"><?= $abha_linked ?></div>
        <div class="lbl">ABHA Linked</div>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <p class="section-title">Quick Actions</p>
  <div class="row g-3">
    <?php
    $actions = [
      ['members/add.php','bg-primary-theme text-white','fas fa-user-graduate','Add Student',''],
      ['members/add.php','bg-success text-white','fas fa-chalkboard-teacher','Add Teacher',''],
      ['members/add.php','bg-accent-theme text-white','fas fa-user-tie','Add Staff',''],
      ['members/list.php','bg-primary-theme text-white','fas fa-list','All Members',''],
      ['health/records.php','bg-danger text-white','fas fa-heartbeat','Health Records',''],
      ['health/abha.php','bg-secondary text-white','fas fa-id-card','ABHA IDs',''],
    ];
    foreach ($actions as [$href,$cls,$icon,$title,$sub]):
    ?>
    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
      <a href="<?= $href ?>" class="qcard">
        <div class="qi <?= $cls ?>"><i class="<?= $icon ?>"></i></div>
        <h6><?= $title ?></h6>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent Members + School Info -->
  <div class="row g-3 mt-1">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
          <h6 class="fw-bold mb-0"><i class="fas fa-users me-2" style="color:var(--primary)"></i>Recent Members</h6>
          <a href="members/list.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="font-size:.73rem;">Name</th>
                  <th style="font-size:.73rem;">Type</th>
                  <th class="d-none d-sm-table-cell" style="font-size:.73rem;">Class / Role</th>
                  <th style="font-size:.73rem;">ABHA</th>
                  <th class="d-none d-md-table-cell" style="font-size:.73rem;">Added</th>
                </tr>
              </thead>
              <tbody>
              <?php if (mysqli_num_rows($recent)===0): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No members yet. <a href="members/add.php">Add first member →</a></td></tr>
              <?php endif; ?>
              <?php while ($m = mysqli_fetch_assoc($recent)): ?>
                <tr>
                  <td>
                    <div class="fw-semibold" style="font-size:.84rem;"><?= htmlspecialchars($m['name']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($m['member_uid']) ?></small>
                  </td>
                  <td><span class="member-type-badge badge-<?= strtolower($m['type']) ?>"><?= $m['type'] ?></span></td>
                  <td class="d-none d-sm-table-cell" style="font-size:.82rem;"><?= htmlspecialchars($m['type']==='Student'?($m['class']??'—'):($m['designation']??'—')) ?></td>
                  <td>
                    <?php if ($m['abha_linked']): ?>
                      <span style="color:#2e7d32;font-size:.75rem;"><i class="fas fa-check-circle"></i></span>
                    <?php else: ?>
                      <span style="color:#bbb;font-size:.75rem;">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="d-none d-md-table-cell"><small class="text-muted"><?= date('d M Y', strtotime($m['created_at'])) ?></small></td>
                </tr>
              <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-3 pb-2">
          <h6 class="fw-bold mb-0"><i class="fas fa-school me-2" style="color:var(--primary)"></i>School Info</h6>
        </div>
        <div class="card-body">
          <?php if ($school_info): ?>
          <?php
          $sc_colors = ['Active'=>'bg-success','Pending'=>'bg-warning text-dark','Inactive'=>'bg-secondary','Rejected'=>'bg-danger'];
          $sc_cls = $sc_colors[$school_info['status']] ?? 'bg-secondary';
          ?>
          <div class="mb-2">
            <div style="font-size:.68rem;text-transform:uppercase;color:#9ca3af;font-weight:700;">School</div>
            <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($school_info['school_name']) ?></div>
          </div>
          <div class="mb-2">
            <div style="font-size:.68rem;text-transform:uppercase;color:#9ca3af;font-weight:700;">Principal</div>
            <div style="font-size:.85rem;"><?= htmlspecialchars($school_info['principal_name'] ?? '—') ?></div>
          </div>
          <div class="mb-2">
            <div style="font-size:.68rem;text-transform:uppercase;color:#9ca3af;font-weight:700;">Board / Type</div>
            <div style="font-size:.85rem;"><?= ($school_info['board']??'—') ?> &bull; <?= ($school_info['school_type']??'—') ?></div>
          </div>
          <div class="mb-2">
            <div style="font-size:.68rem;text-transform:uppercase;color:#9ca3af;font-weight:700;">Location</div>
            <div style="font-size:.85rem;"><?= htmlspecialchars(($school_info['city']??'').' '.($school_info['state']??'')) ?></div>
          </div>
          <div>
            <span class="badge <?= $sc_cls ?>"><?= $school_info['status'] ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
