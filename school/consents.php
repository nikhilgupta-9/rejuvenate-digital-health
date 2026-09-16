<?php
/**
 * School panel — parent consent submissions for this school.
 *
 * Consents submitted through a student's personalized "Send Consent Link"
 * (members/view.php → send-consent.php, lib/ConsentToken.php) already carry
 * member_id and never need attention here. Consents submitted through the
 * old generic school-wide link (school/parent-consent.php with no ?ctoken=)
 * don't know which student they're for — member_id stays NULL until someone
 * who knows the students matches them by hand. That's what this page is for.
 */
include_once "../config/connect.php";
include_once "auth/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['consent_id'], $_POST['member_id']) && $_POST['action'] === 'link') {
    $consent_id = (int) $_POST['consent_id'];
    $member_id  = (int) $_POST['member_id'];

    $chk = $conn->prepare("SELECT id FROM school_members WHERE id = ? AND school_id = ? LIMIT 1");
    $chk->bind_param('ii', $member_id, $school_id);
    $chk->execute();

    if (!$chk->get_result()->fetch_assoc()) {
        $msg = "That student wasn't found in your school.";
        $msg_type = 'danger';
    } else {
        $upd = $conn->prepare("UPDATE parent_consent_forms
            SET member_id = ?, linked_at = NOW()
            WHERE id = ? AND school_id = ? AND member_id IS NULL");
        $upd->bind_param('iii', $member_id, $consent_id, $school_id);
        $upd->execute();
        $msg = $upd->affected_rows ? "Consent linked to the student." : "Could not link — it may already be linked.";
        $msg_type = $upd->affected_rows ? 'success' : 'warning';
    }
}

$view   = $_GET['view'] ?? 'unlinked';
$search = trim($_GET['q'] ?? '');
$where  = "WHERE c.school_id = $school_id";
if ($view === 'unlinked') $where .= " AND c.member_id IS NULL";
elseif ($view === 'linked') $where .= " AND c.member_id IS NOT NULL";
if ($search !== '') {
    $q = mysqli_real_escape_string($conn, $search);
    $where .= " AND (c.student_name LIKE '%$q%' OR c.parent_name LIKE '%$q%' OR c.parent_mobile LIKE '%$q%')";
}

$consents = mysqli_query($conn, "SELECT c.*, m.member_uid FROM parent_consent_forms c
    LEFT JOIN school_members m ON m.id = c.member_id
    $where ORDER BY c.submitted_at DESC LIMIT 300");

$unlinked_count = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM parent_consent_forms WHERE school_id = $school_id AND member_id IS NULL"))['c'];

/* Search box for the "link to student" search-picker, scoped to search_for row */
$linking_id = (int) ($_GET['link_row'] ?? 0);
$link_row   = null;
$candidates = [];
if ($linking_id) {
    $ls = $conn->prepare("SELECT * FROM parent_consent_forms WHERE id = ? AND school_id = ? AND member_id IS NULL LIMIT 1");
    $ls->bind_param('ii', $linking_id, $school_id);
    $ls->execute();
    $link_row = $ls->get_result()->fetch_assoc() ?: null;
    if ($link_row) {
        $lq = trim($_GET['lq'] ?? $link_row['student_name']);
        $lqe = mysqli_real_escape_string($conn, $lq);
        $cr = mysqli_query($conn, "SELECT id, member_uid, name, class, section, roll_number, dob
            FROM school_members WHERE school_id = $school_id AND type = 'Student'
              AND (name LIKE '%$lqe%' OR roll_number LIKE '%$lqe%')
            ORDER BY name ASC LIMIT 20");
        if ($cr) $candidates = mysqli_fetch_all($cr, MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | Parent Consents</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="assets/school.css">
</head>
<body>
<?php $active_page='consents'; $base_path=''; include 'inc/sidebar-school.php'; ?>

<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <span style="font-size:.95rem;font-weight:600;color:#1f2937;"><i class="fas fa-file-signature me-2" style="color:var(--primary)"></i>Parent Consents</span>
  </div>
  <a href="auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
</div>

<main class="school-content">

  <?php if (isset($msg)): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
      <i class="fas fa-<?= $msg_type==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i><?= htmlspecialchars($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($unlinked_count > 0 && $view !== 'unlinked'): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div><i class="fas fa-link-slash me-2"></i><strong><?= $unlinked_count ?></strong> consent<?= $unlinked_count === 1 ? '' : 's' ?> from the old generic link need<?= $unlinked_count === 1 ? 's' : '' ?> to be linked to a student.</div>
      <a href="?view=unlinked" class="btn btn-warning btn-sm">Review now</a>
    </div>
  <?php endif; ?>

  <?php if ($linking_id && $link_row): ?>
  <!-- ── Link picker for one consent ── -->
  <div class="card border-0 shadow-sm rounded-3 mb-4" style="border-left:4px solid #ea580c !important;">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3 pb-2">
      <h6 class="fw-bold mb-0"><i class="fas fa-link text-warning me-2"></i>Link consent to a student</h6>
      <a href="consents.php?view=<?= htmlspecialchars($view) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Close</a>
    </div>
    <div class="card-body">
      <div class="row g-2 mb-3" style="font-size:.85rem;">
        <div class="col-md-3"><strong>Submitted name:</strong> <?= htmlspecialchars($link_row['student_name']) ?></div>
        <div class="col-md-3"><strong>DOB:</strong> <?= $link_row['student_dob'] && $link_row['student_dob'] !== '0000-00-00' ? date('d M Y', strtotime($link_row['student_dob'])) : '—' ?></div>
        <div class="col-md-2"><strong>Class:</strong> <?= htmlspecialchars(trim(($link_row['student_class'] ?? '') . ' ' . ($link_row['student_section'] ?? '')) ?: '—') ?></div>
        <div class="col-md-2"><strong>Roll:</strong> <?= htmlspecialchars($link_row['student_roll_no'] ?: '—') ?></div>
        <div class="col-md-2"><strong>Parent:</strong> <?= htmlspecialchars($link_row['parent_name']) ?></div>
      </div>
      <form method="GET" class="d-flex gap-2 mb-3">
        <input type="hidden" name="link_row" value="<?= $linking_id ?>">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
        <input type="text" class="form-control form-control-sm" name="lq" value="<?= htmlspecialchars($_GET['lq'] ?? $link_row['student_name']) ?>" placeholder="Search name or roll number..." style="max-width:320px;">
        <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
      </form>
      <?php if (!$candidates): ?>
        <div class="text-muted py-3"><i class="fas fa-user-slash me-1"></i>No matching student found in Members.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Class / Section</th><th>Roll No.</th><th>DOB</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($candidates as $cd): ?>
              <tr>
                <td><div class="fw-semibold" style="font-size:.84rem;"><?= htmlspecialchars($cd['name']) ?></div><small class="text-muted"><?= htmlspecialchars($cd['member_uid']) ?></small></td>
                <td style="font-size:.82rem;"><?= htmlspecialchars(trim(($cd['class'] ?? '') . ' ' . ($cd['section'] ?? '')) ?: '—') ?></td>
                <td style="font-size:.82rem;"><?= htmlspecialchars($cd['roll_number'] ?: '—') ?></td>
                <td style="font-size:.82rem;"><?= $cd['dob'] ? date('d M Y', strtotime($cd['dob'])) : '—' ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Link this consent to ' + <?= json_encode($cd['name']) ?> + '?')">
                    <input type="hidden" name="action" value="link">
                    <input type="hidden" name="consent_id" value="<?= $linking_id ?>">
                    <input type="hidden" name="member_id" value="<?= $cd['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-link me-1"></i>Link</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tab filters -->
  <div class="tab-filter d-flex gap-2 mb-4 flex-wrap">
    <a href="?view=unlinked" class="<?= $view==='unlinked'?'active':'' ?>"><i class="fas fa-link-slash me-1"></i>Needs linking<?= $unlinked_count ? ' ('.$unlinked_count.')' : '' ?></a>
    <a href="?view=linked" class="<?= $view==='linked'?'active':'' ?>"><i class="fas fa-link me-1"></i>Linked</a>
    <a href="?view=all" class="<?= $view==='all'?'active':'' ?>"><i class="fas fa-list me-1"></i>All</a>
  </div>

  <form method="GET" class="d-flex gap-2 mb-4">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
    <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student, parent, mobile..." style="max-width:320px;">
    <button class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
    <?php if ($search): ?><a href="?view=<?= urlencode($view) ?>" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
  </form>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-3">
      <h6 class="fw-bold mb-0"><i class="fas fa-list text-primary me-2"></i>Consents</h6>
      <span class="badge bg-secondary"><?= mysqli_num_rows($consents) ?> records</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="font-size:.73rem;">Student</th>
              <th style="font-size:.73rem;">Parent / Guardian</th>
              <th style="font-size:.73rem;">Submitted</th>
              <th style="font-size:.73rem;">Status</th>
              <th style="font-size:.73rem;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (mysqli_num_rows($consents) === 0): ?>
            <tr><td colspan="5" class="text-center py-5 text-muted">
              <i class="fas fa-file-signature fa-3x d-block mb-2 opacity-25"></i>No consents found.
            </td></tr>
          <?php endif; ?>
          <?php while ($c = mysqli_fetch_assoc($consents)): ?>
            <tr>
              <td>
                <div class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($c['student_name']) ?></div>
                <?php if ($c['member_uid']): ?>
                  <small class="text-muted"><?= htmlspecialchars($c['member_uid']) ?></small>
                <?php else: ?>
                  <a href="?view=<?= htmlspecialchars($view) ?>&link_row=<?= $c['id'] ?>" class="text-danger" style="font-size:.78rem;font-weight:600;"><i class="fas fa-link-slash me-1"></i>Not linked — link now</a>
                <?php endif; ?>
              </td>
              <td style="font-size:.82rem;"><?= htmlspecialchars($c['parent_name']) ?><br><small class="text-muted"><?= htmlspecialchars($c['parent_mobile']) ?></small></td>
              <td style="font-size:.78rem;color:#9ca3af;"><?= date('d M Y', strtotime($c['submitted_at'])) ?></td>
              <td>
                <span class="badge bg-<?= ['pending'=>'warning text-dark','reviewed'=>'success','archived'=>'secondary'][$c['status']] ?? 'secondary' ?>" style="font-size:.72rem;"><?= ucfirst($c['status']) ?></span>
                <?php if (!empty($c['revoked'])): ?>
                  <div class="mt-1"><span class="badge bg-danger" style="font-size:.68rem;"><i class="fas fa-ban me-1"></i>Revoked</span></div>
                <?php elseif (!empty($c['expires_at']) && $c['expires_at'] < date('Y-m-d')): ?>
                  <div class="mt-1"><span class="badge bg-danger" style="font-size:.68rem;"><i class="fas fa-hourglass-end me-1"></i>Expired</span></div>
                <?php elseif (!empty($c['academic_year'])): ?>
                  <div class="mt-1"><small class="text-muted" style="font-size:.68rem;">AY <?= htmlspecialchars($c['academic_year']) ?></small></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$c['member_uid']): ?>
                  <a href="?view=<?= htmlspecialchars($view) ?>&link_row=<?= $c['id'] ?>" class="btn btn-sm btn-warning" style="font-size:.72rem;padding:3px 8px;"><i class="fas fa-link me-1"></i>Link</a>
                <?php else: ?>
                  <span class="text-muted" style="font-size:.75rem;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>
</body>
</html>
