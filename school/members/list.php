<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";

// ── Handle approve / reject of self-registered members ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['member_id'])) {
    $mid    = (int)$_POST['member_id'];
    $action = $_POST['action'];
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE school_members SET status='Active' WHERE id=? AND school_id=? AND status='Pending'");
        $stmt->bind_param('ii', $mid, $school_id); $stmt->execute();
        $msg = "Member approved successfully."; $msg_type = 'success';
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("DELETE FROM school_members WHERE id=? AND school_id=? AND status='Pending'");
        $stmt->bind_param('ii', $mid, $school_id); $stmt->execute();
        $msg = "Registration request rejected and removed."; $msg_type = 'warning';
    }
}

// ── Pending self-registrations ──────────────────────────────────────────
$pending_members = mysqli_query($conn, "SELECT * FROM school_members WHERE school_id=$school_id AND status='Pending' ORDER BY created_at DESC");
$pending_count   = mysqli_num_rows($pending_members);

// ── Active/Inactive member list ─────────────────────────────────────────
$type   = $_GET['type'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$where  = "WHERE school_id=$school_id AND status != 'Pending'";
if ($type !== 'all') $where .= " AND type='" . mysqli_real_escape_string($conn, $type) . "'";
if ($search) $where .= " AND (name LIKE '%" . mysqli_real_escape_string($conn,$search) . "%' OR email LIKE '%" . mysqli_real_escape_string($conn,$search) . "%' OR roll_number LIKE '%" . mysqli_real_escape_string($conn,$search) . "%')";

$members = mysqli_query($conn, "SELECT * FROM school_members $where ORDER BY type, name ASC");

$counts = [];
foreach (['all','Student','Teacher','Staff'] as $t) {
    $w = $t==='all' ? "WHERE school_id=$school_id AND status!='Pending'" : "WHERE school_id=$school_id AND status!='Pending' AND type='$t'";
    $counts[$t] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_members $w"))['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Members</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
</head>
<body>
<?php $active_page='members'; $base_path='../'; include '../inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <span style="font-size:.95rem;font-weight:600;color:#1f2937;"><i class="fas fa-users me-2" style="color:var(--primary)"></i>Members</span>
  </div>
  <div class="d-flex align-items-center gap-2">
    <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">Add Member</span></a>
    <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <?php if (isset($msg)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
      <i class="fas fa-<?= $msg_type==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($pending_count > 0): ?>
  <!-- ── Pending Self-Registrations ── -->
  <div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left:4px solid #ea580c !important;">
    <div class="card-header bg-white border-0 d-flex align-items-center gap-2 pt-3 pb-2">
      <i class="fas fa-user-clock text-warning"></i>
      <h6 class="fw-bold mb-0">Pending Approvals <span class="badge bg-warning text-dark ms-1"><?= $pending_count ?></span></h6>
      <small class="text-muted ms-2">Self-registered members awaiting your approval</small>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="font-size:.72rem;">Name</th>
              <th style="font-size:.72rem;">Type</th>
              <th style="font-size:.72rem;">Email</th>
              <th style="font-size:.72rem;">Class / Designation</th>
              <th style="font-size:.72rem;">Registered</th>
              <th style="font-size:.72rem;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($pm = mysqli_fetch_assoc($pending_members)): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div style="width:32px;height:32px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#92400e;flex-shrink:0;">
                    <?= strtoupper(substr($pm['name'],0,1)) ?>
                  </div>
                  <div>
                    <div class="fw-semibold" style="font-size:.84rem;"><?= htmlspecialchars($pm['name']) ?></div>
                    <?php if ($pm['phone']): ?><small class="text-muted"><?= htmlspecialchars($pm['phone']) ?></small><?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <?php $tc = ['Student'=>'primary','Teacher'=>'success','Staff'=>'secondary'][$pm['type']] ?? 'secondary'; ?>
                <span class="badge bg-<?= $tc ?>" style="font-size:.72rem;"><?= $pm['type'] ?></span>
              </td>
              <td style="font-size:.82rem;"><?= htmlspecialchars($pm['email'] ?? '—') ?></td>
              <td style="font-size:.82rem;">
                <?= $pm['type']==='Student'
                    ? htmlspecialchars(($pm['class'] ? 'Class '.$pm['class'] : '') . ($pm['roll_number'] ? ' · Roll: '.$pm['roll_number'] : ''))
                    : htmlspecialchars($pm['designation'] ?? '—') ?>
              </td>
              <td style="font-size:.78rem;color:#9ca3af;"><?= date('d M Y', strtotime($pm['created_at'])) ?></td>
              <td>
                <div class="d-flex gap-1">
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this member?')">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="member_id" value="<?= $pm['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success" style="font-size:.72rem;padding:3px 8px;">
                      <i class="fas fa-check me-1"></i>Approve
                    </button>
                  </form>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Reject and remove this registration?')">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="member_id" value="<?= $pm['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:.72rem;padding:3px 8px;">
                      <i class="fas fa-times me-1"></i>Reject
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tab filters -->
  <div class="tab-filter d-flex gap-2 mb-4 flex-wrap">
    <a href="list.php" class="<?= $type==='all'?'active':'' ?>"><i class="fas fa-users me-1"></i>All (<?= $counts['all'] ?>)</a>
    <a href="list.php?type=Student" class="<?= $type==='Student'?'active':'' ?>"><i class="fas fa-user-graduate me-1"></i>Students (<?= $counts['Student'] ?>)</a>
    <a href="list.php?type=Teacher" class="<?= $type==='Teacher'?'active':'' ?>"><i class="fas fa-chalkboard-teacher me-1"></i>Teachers (<?= $counts['Teacher'] ?>)</a>
    <a href="list.php?type=Staff"   class="<?= $type==='Staff'?'active':'' ?>"><i class="fas fa-user-tie me-1"></i>Staff (<?= $counts['Staff'] ?>)</a>
  </div>

  <!-- Search -->
  <form method="GET" class="d-flex gap-2 mb-4">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, roll number..." style="max-width:320px;">
    <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
    <?php if ($search): ?><a href="list.php?type=<?= urlencode($type) ?>" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
  </form>

  <!-- Table -->
  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
      <h6 class="fw-bold mb-0"><i class="fas fa-list text-primary me-2"></i><?= $type==='all'?'All Members':$type.'s' ?></h6>
      <span class="badge bg-secondary"><?= mysqli_num_rows($members) ?> records</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="font-size:.73rem;">#</th>
              <th style="font-size:.73rem;">Member</th>
              <th style="font-size:.73rem;">Type</th>
              <th style="font-size:.73rem;">Class / Designation</th>
              <th style="font-size:.73rem;">Contact</th>
              <th style="font-size:.73rem;">ABHA</th>
              <th style="font-size:.73rem;">Login</th>
              <th style="font-size:.73rem;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (mysqli_num_rows($members)===0): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">
              <i class="fas fa-user-slash fa-3x d-block mb-2 opacity-25"></i>
              No members found. <a href="add.php">Add first member →</a>
            </td></tr>
          <?php endif; ?>
          <?php $i=1; while ($m = mysqli_fetch_assoc($members)): ?>
          <tr>
            <td><small class="text-muted"><?= $i++ ?></small></td>
            <td>
              <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($m['name']) ?></div>
              <small class="text-muted"><?= htmlspecialchars($m['member_uid']) ?></small>
            </td>
            <td><span class="member-type-badge badge-<?= strtolower($m['type']) ?>"><?= $m['type'] ?></span></td>
            <td style="font-size:.82rem;">
              <?= htmlspecialchars($m['type']==='Student' ? (($m['class']??'').' '.($m['roll_number']??"Roll: ".$m['roll_number']??'—')) : ($m['designation']??'—')) ?>
            </td>
            <td style="font-size:.8rem;">
              <?= $m['email']?htmlspecialchars($m['email']):'<span class="text-muted">—</span>' ?>
              <?php if ($m['phone']): ?><br><small><?= htmlspecialchars($m['phone']) ?></small><?php endif; ?>
            </td>
            <td>
              <?php if ($m['abha_linked']): ?>
                <span style="color:#2e7d32;font-size:.78rem;"><i class="fas fa-check-circle"></i> Linked</span>
              <?php else: ?>
                <a href="../health/abha.php?member_id=<?= $m['id'] ?>" style="font-size:.75rem;color:#f77f00;"><i class="fas fa-link me-1"></i>Link ABHA</a>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($m['password']): ?>
                <span style="color:#2e7d32;font-size:.75rem;"><i class="fas fa-key"></i> Set</span>
              <?php else: ?>
                <a href="set-credentials.php?id=<?= $m['id'] ?>" style="font-size:.75rem;color:#6b7280;"><i class="fas fa-key me-1"></i>Set</a>
              <?php endif; ?>
            </td>
            <td>
              <a href="view.php?id=<?= $m['id'] ?>" class="action-btn bg-primary text-white" title="View"><i class="fas fa-eye"></i></a>
              <a href="edit.php?id=<?= $m['id'] ?>" class="action-btn bg-warning text-white" title="Edit"><i class="fas fa-edit"></i></a>
              <a href="health-profile.php?id=<?= $m['id'] ?>" class="action-btn bg-success text-white" title="Health Profile"><i class="fas fa-heartbeat"></i></a>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
