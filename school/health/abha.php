<?php
include_once "../../config/connect.php";
include_once "../auth/auth.php";
require_once __DIR__ . "/../../lib/Abha.php";

/** True when this member belongs to the signed-in school (ABHA writes are school-scoped). */
function _member_in_school(mysqli $conn, int $memberId, int $schoolId): bool
{
    $c = $conn->prepare("SELECT 1 FROM school_members WHERE id=? AND school_id=? LIMIT 1");
    $c->bind_param('ii', $memberId, $schoolId);
    $c->execute();
    return (bool) $c->get_result()->fetch_row();
}

$tab     = $_GET['tab'] ?? 'overview';
$filter  = $_GET['filter'] ?? 'all';        // all | Student | Teacher | Staff
$success = $error = '';

/* ─── POST handlers ───────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Link / Update ABHA manually */
    if ($action === 'link_abha') {
        $mid       = (int)$_POST['mid'];
        $abha_id   = trim($_POST['abha_id']   ?? '');
        $abha_addr = trim($_POST['abha_address'] ?? '');
        $verified  = isset($_POST['mark_verified']) ? 1 : 0;

        $raw = preg_replace('/\D/', '', $abha_id);
        if (strlen($raw) !== 14) {
            $error = "Invalid ABHA number. Must be 14 digits (XX-XXXX-XXXX-XXXX).";
        } else {
            $abha_fmt = substr($raw,0,2).'-'.substr($raw,2,4).'-'.substr($raw,6,4).'-'.substr($raw,10,4);
            // append @abdm if address given without it
            if ($abha_addr && strpos($abha_addr,'@') === false) $abha_addr .= '@abdm';

            if (!_member_in_school($conn, $mid, (int) $school_id)) {
                $error = "Member not found in your school.";
            } else {
                Abha::save($conn, 'school_member', $mid, [
                    'abha_number'  => $abha_fmt,
                    'abha_address' => $abha_addr,
                    'linked'       => 1,
                    'verified'     => $verified,
                    'source'       => 'school',
                ]);
                $success = "ABHA linked successfully for member.";
            }
        }
    }

    /* Unlink ABHA */
    if ($action === 'unlink_abha') {
        $mid = (int)$_POST['mid'];
        if (!_member_in_school($conn, $mid, (int) $school_id)) {
            $error = "Member not found in your school.";
        } else {
            Abha::unlink($conn, 'school_member', $mid);
            $success = "ABHA unlinked.";
        }
    }

    /* Approve link request from student/teacher */
    if ($action === 'approve_request') {
        $req_id = (int)$_POST['req_id'];
        $rq = $conn->prepare("SELECT * FROM abha_link_requests WHERE id=? AND school_id=? AND status='Pending'");
        $rq->bind_param('ii', $req_id, $school_id);
        $rq->execute();
        $req = $rq->get_result()->fetch_assoc();
        if ($req) {
            if (_member_in_school($conn, (int) $req['member_id'], (int) $school_id)) {
                Abha::save($conn, 'school_member', (int) $req['member_id'], [
                    'abha_number'  => $req['abha_id'],
                    'abha_address' => $req['abha_address'],
                    'linked'       => 1,
                    'source'       => 'school',
                ]);
            }
            $done = $conn->prepare("UPDATE abha_link_requests SET status='Approved', reviewed_at=NOW(), reviewed_by=? WHERE id=?");
            $done->bind_param('ii', $school_user_id, $req_id);
            $done->execute();
            $success = "ABHA link request approved.";
        } else $error = "Request not found or already processed.";
    }

    /* Reject link request */
    if ($action === 'reject_request') {
        $req_id = (int)$_POST['req_id'];
        $notes  = trim($_POST['notes'] ?? 'Rejected by admin');
        $done = $conn->prepare("UPDATE abha_link_requests SET status='Rejected', reviewed_at=NOW(), reviewed_by=?, notes=? WHERE id=? AND school_id=? AND status='Pending'");
        $done->bind_param('isii', $school_user_id, $notes, $req_id, $school_id);
        if ($done->execute()) $success = "Request rejected.";
    }
}

/* ─── Stats ──────────────────────────────────────────────────────── */
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
      COUNT(*) as total,
      SUM(abha_linked=1) as linked,
      SUM(abha_linked=0 OR abha_linked IS NULL) as unlinked,
      SUM(abha_verified=1) as verified,
      SUM(type='Student') as students,
      SUM(type='Teacher') as teachers,
      SUM(type='Staff')   as staff,
      SUM(type='Student' AND abha_linked=1) as s_linked,
      SUM(type='Teacher' AND abha_linked=1) as t_linked,
      SUM(type='Staff'   AND abha_linked=1) as st_linked
    FROM school_members WHERE school_id=$school_id AND status='Active'
"));

/* Pending requests */
$pending_req = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM abha_link_requests WHERE school_id=$school_id AND status='Pending'"))['c'];

/* ─── Members list ───────────────────────────────────────────────── */
$where_type = ($filter !== 'all') ? "AND sm.type='" . mysqli_real_escape_string($conn, $filter) . "'" : '';
$where_tab  = ($tab === 'linked')   ? "AND sm.abha_linked=1"
            : (($tab === 'unlinked') ? "AND (sm.abha_linked=0 OR sm.abha_linked IS NULL)"
            : '');
$members = mysqli_query($conn, "
    SELECT sm.id, sm.name, sm.type, sm.member_uid, sm.class, sm.employee_id,
           sm.abha_id, sm.abha_address, sm.abha_linked, sm.abha_linked_at, sm.abha_verified,
           sm.profile_pic
    FROM school_members sm
    WHERE sm.school_id=$school_id AND sm.status='Active' $where_type $where_tab
    ORDER BY sm.abha_linked DESC, sm.name ASC");

/* Pending requests list */
$req_list = mysqli_query($conn, "
    SELECT alr.*, sm.name as member_name, sm.type as member_type, sm.member_uid
    FROM abha_link_requests alr
    JOIN school_members sm ON alr.member_id=sm.id
    WHERE alr.school_id=$school_id AND alr.status='Pending'
    ORDER BY alr.requested_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($school_name) ?> | ABHA Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/school.css">
  <style>
    :root { --ab-green:#00875a; --primary:#0C74C5; }

    .abha-brand {
      background: #00875a;
      border-radius: 14px;
      color: #fff;
      padding: 22px 24px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 18px;
    }
    .abha-brand .ab-logo {
      width: 56px; height: 56px; background: rgba(255,255,255,.18);
      border-radius: 14px; display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; flex-shrink: 0;
    }
    .abha-brand h5 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .abha-brand p  { margin: 4px 0 0; font-size: .78rem; opacity: .85; line-height: 1.4; }

    .abha-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
    .abha-tab  { padding:6px 16px; border-radius:20px; border:1.5px solid #e5e7eb; background:#fff; font-size:.8rem; font-weight:600; color:#374151; cursor:pointer; text-decoration:none; transition:.15s; }
    .abha-tab:hover, .abha-tab.active { background:#0C74C5; color:#fff; border-color:#0C74C5; text-decoration:none; }
    .tab-badge { background:#ea580c; color:#fff; border-radius:10px; padding:1px 6px; font-size:.6rem; font-weight:700; margin-left:4px; }

    .filter-chip { padding:4px 12px; border-radius:16px; border:1.5px solid #e5e7eb; background:#fff; font-size:.75rem; font-weight:600; color:#374151; cursor:pointer; text-decoration:none; white-space:nowrap; }
    .filter-chip:hover, .filter-chip.active { background:#0C74C5; color:#fff; border-color:#0C74C5; text-decoration:none; }

    .abha-table th { font-size:.71rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.03em; background:#f9fafb; border-bottom:1px solid #e5e7eb; padding:10px 12px; }
    .abha-table td { font-size:.83rem; vertical-align:middle; padding:10px 12px; }
    .abha-table tbody tr:hover td { background:#f0f7ff; }

    .badge-linked   { background:#d1fae5; color:#065f46; border-radius:6px; padding:3px 9px; font-size:.7rem; font-weight:700; white-space:nowrap; }
    .badge-unlinked { background:#fef3c7; color:#92400e; border-radius:6px; padding:3px 9px; font-size:.7rem; font-weight:700; white-space:nowrap; }
    .badge-verified { background:#dbeafe; color:#1e40af; border-radius:6px; padding:3px 9px; font-size:.7rem; font-weight:700; white-space:nowrap; }

    .type-chip    { border-radius:5px; padding:2px 9px; font-size:.68rem; font-weight:700; }
    .chip-student { background:#e0f2fe; color:#0277bd; }
    .chip-teacher { background:#e8f5e9; color:#2e7d32; }
    .chip-staff   { background:#f3e5f5; color:#6a1b9a; }

    .mem-av { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }

    .mini-bar { height:6px; border-radius:3px; background:#e5e7eb; overflow:hidden; margin-top:5px; }
    .mini-bar-fill { height:100%; border-radius:3px; }

    .req-card { border:1.5px solid #fde68a; background:#fffbeb; border-radius:10px; padding:14px 16px; margin-bottom:10px; }
    .req-abha { font-family:monospace; font-size:.88rem; font-weight:700; color:#00875a; }

    .abha-card-preview {
      background: #00875a;
      border-radius: 12px; color: #fff; padding: 18px 20px; margin-bottom: 16px;
    }
    .abha-card-preview .ac-num { font-size:1.05rem; letter-spacing:.08em; font-weight:700; font-family:monospace; }
    .abha-card-preview .ac-lbl { font-size:.62rem; opacity:.75; text-transform:uppercase; letter-spacing:.08em; }
  </style>
</head>
<body>
<?php $active_page='abha'; $base_path='../'; include '../inc/sidebar-school.php'; ?>

<!-- Topbar -->
<div class="school-topbar">
  <div class="d-flex align-items-center gap-2">
    <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div>
      <div style="font-size:1rem;font-weight:600;color:#1f2937;"><i class="fas fa-id-card me-2" style="color:#00875a;"></i>ABHA Management</div>
      <div style="font-size:.72rem;color:#6b7280;">Ayushman Bharat Digital Health Account</div>
    </div>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <?php if ($pending_req > 0): ?>
      <a href="abha.php?tab=requests" class="btn btn-warning btn-sm position-relative">
        <i class="fas fa-bell me-1"></i>Requests
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;"><?= $pending_req ?></span>
      </a>
    <?php endif; ?>
    <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-sign-out-alt"></i></a>
  </div>
</div>

<main class="school-content">

  <?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- ABHA Brand Header -->
  <div class="abha-brand">
    <div class="ab-logo"><i class="fas fa-heartbeat"></i></div>
    <div>
      <h5>ABHA — Ayushman Bharat Health Account</h5>
      <p>India's 14-digit digital health identity. Link ABHA for your members to connect them with India's national health records system (ABDM). Students and teachers can also request linking from their own portal.</p>
    </div>
    <a href="https://healthid.ndhm.gov.in/" target="_blank" class="btn btn-sm ms-auto flex-shrink-0"
       style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);white-space:nowrap;">
      <i class="fas fa-external-link-alt me-1"></i>ABDM Portal
    </a>
  </div>

  <!-- Stats row -->
  <?php
    $link_pct = $stats['total'] > 0 ? round(($stats['linked']/$stats['total'])*100) : 0;
    $ver_pct  = $stats['linked'] > 0 ? round(($stats['verified']/$stats['linked'])*100) : 0;
    $r = 30; $c = 2*M_PI*$r;
    $fill_l = $c * $link_pct / 100;
  ?>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
            <svg width="80" height="80" viewBox="0 0 80 80" style="transform:rotate(-90deg);">
              <circle cx="40" cy="40" r="<?= $r ?>" fill="none" stroke="#e5e7eb" stroke-width="8"/>
              <circle cx="40" cy="40" r="<?= $r ?>" fill="none" stroke="#00875a" stroke-width="8"
                stroke-dasharray="<?= round($fill_l,1) ?> <?= round($c - $fill_l,1) ?>" stroke-linecap="round"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
              <span style="font-size:.9rem;font-weight:700;"><?= $link_pct ?>%</span>
              <small style="font-size:.58rem;color:#6b7280;">Linked</small>
            </div>
          </div>
          <div>
            <div style="font-size:1.5rem;font-weight:700;color:#111;">
              <?= $stats['linked'] ?><span style="color:#6b7280;font-size:.85rem;font-weight:400;"> / <?= $stats['total'] ?></span>
            </div>
            <div style="font-size:.75rem;color:#6b7280;">Members with ABHA linked</div>
            <div class="mini-bar"><div class="mini-bar-fill" style="width:<?= $link_pct ?>%;background:#00875a;"></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-body p-3">
          <div class="row g-2">
            <div class="col-4 text-center">
              <div style="font-size:1.4rem;font-weight:700;color:#1e40af;"><?= $stats['verified'] ?></div>
              <div style="font-size:.68rem;color:#6b7280;">Verified</div>
            </div>
            <div class="col-4 text-center border-start border-end">
              <div style="font-size:1.4rem;font-weight:700;color:#d97706;"><?= $stats['unlinked'] ?></div>
              <div style="font-size:.68rem;color:#6b7280;">Not Linked</div>
            </div>
            <div class="col-4 text-center">
              <div style="font-size:1.4rem;font-weight:700;color:#dc2626;"><?= $pending_req ?></div>
              <div style="font-size:.68rem;color:#6b7280;">Pending</div>
            </div>
          </div>
          <hr class="my-2">
          <div style="font-size:.7rem;color:#9ca3af;"><i class="fas fa-info-circle me-1"></i>Verified = ABHA confirmed against original ID credentials</div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-body p-3">
          <div style="font-size:.73rem;font-weight:700;color:#374151;margin-bottom:10px;">Coverage by Member Type</div>
          <?php
            $types = [
              ['Students', (int)$stats['students'], (int)$stats['s_linked'],  '#0277bd'],
              ['Teachers', (int)$stats['teachers'], (int)$stats['t_linked'],  '#2e7d32'],
              ['Staff',    (int)$stats['staff'],    (int)$stats['st_linked'], '#7c3aed'],
            ];
            foreach ($types as [$lbl, $tot, $lnk, $col]):
              $p = $tot > 0 ? round($lnk/$tot*100) : 0;
          ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between" style="font-size:.74rem;">
              <span style="font-weight:600;color:#374151;"><?= $lbl ?></span>
              <span style="color:#6b7280;"><?= $lnk ?>/<?= $tot ?> (<?= $p ?>%)</span>
            </div>
            <div class="mini-bar"><div class="mini-bar-fill" style="width:<?= $p ?>%;background:<?= $col ?>;"></div></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="abha-tabs">
    <a href="abha.php?tab=overview&filter=<?= $filter ?>" class="abha-tab <?= $tab==='overview'?'active':'' ?>">
      <i class="fas fa-list me-1"></i>All Members
    </a>
    <a href="abha.php?tab=linked&filter=<?= $filter ?>"   class="abha-tab <?= $tab==='linked'?'active':'' ?>">
      <i class="fas fa-check-circle me-1"></i>Linked
    </a>
    <a href="abha.php?tab=unlinked&filter=<?= $filter ?>" class="abha-tab <?= $tab==='unlinked'?'active':'' ?>">
      <i class="fas fa-exclamation-circle me-1"></i>Not Linked
    </a>
    <a href="abha.php?tab=requests&filter=<?= $filter ?>" class="abha-tab <?= $tab==='requests'?'active':'' ?>">
      <i class="fas fa-inbox me-1"></i>Link Requests
      <?php if ($pending_req > 0): ?><span class="tab-badge"><?= $pending_req ?></span><?php endif; ?>
    </a>
  </div>

  <?php if ($tab === 'requests'): ?>
  <!-- ═══ REQUESTS TAB ═══ -->
  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 pt-3">
      <h6 class="fw-bold mb-0"><i class="fas fa-inbox text-warning me-2"></i>Pending ABHA Link Requests</h6>
      <small class="text-muted">Submitted by members from their own portal — review and approve or reject each.</small>
    </div>
    <div class="card-body">
      <?php if (mysqli_num_rows($req_list) === 0): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>
          <strong>All clear!</strong><br>No pending ABHA link requests.
        </div>
      <?php else: ?>
        <?php while ($req = mysqli_fetch_assoc($req_list)): ?>
        <div class="req-card">
          <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <span class="type-chip chip-<?= strtolower($req['member_type']) ?>"><?= $req['member_type'] ?></span>
                <strong style="font-size:.9rem;"><?= htmlspecialchars($req['member_name']) ?></strong>
                <span style="font-size:.73rem;color:#6b7280;"><?= htmlspecialchars($req['member_uid']) ?></span>
              </div>
              <div class="mb-1">
                <span style="font-size:.72rem;color:#6b7280;">ABHA Number:</span>
                <span class="req-abha ms-1"><?= htmlspecialchars($req['abha_id'] ?: '—') ?></span>
              </div>
              <?php if ($req['abha_address']): ?>
              <div class="mb-1">
                <span style="font-size:.72rem;color:#6b7280;">ABHA Address:</span>
                <span style="font-size:.83rem;font-weight:600;" class="ms-1"><?= htmlspecialchars($req['abha_address']) ?></span>
              </div>
              <?php endif; ?>
              <div style="font-size:.7rem;color:#9ca3af;">
                <i class="fas fa-clock me-1"></i>Requested: <?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?>
              </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0 flex-wrap">
              <form method="POST" class="d-inline">
                <input type="hidden" name="action"  value="approve_request">
                <input type="hidden" name="req_id"  value="<?= $req['id'] ?>">
                <button type="submit" class="btn btn-sm btn-success">
                  <i class="fas fa-check me-1"></i>Approve
                </button>
              </form>
              <button type="button" class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal" data-bs-target="#rejectModal"
                data-reqid="<?= $req['id'] ?>" data-name="<?= htmlspecialchars($req['member_name']) ?>">
                <i class="fas fa-times me-1"></i>Reject
              </button>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- ═══ MEMBERS TABLE ═══ -->

  <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
    <a href="abha.php?tab=<?= $tab ?>&filter=all"     class="filter-chip <?= $filter==='all'?'active':'' ?>">All Types</a>
    <a href="abha.php?tab=<?= $tab ?>&filter=Student" class="filter-chip <?= $filter==='Student'?'active':'' ?>">
      <i class="fas fa-user-graduate me-1"></i>Students
    </a>
    <a href="abha.php?tab=<?= $tab ?>&filter=Teacher" class="filter-chip <?= $filter==='Teacher'?'active':'' ?>">
      <i class="fas fa-chalkboard-teacher me-1"></i>Teachers
    </a>
    <a href="abha.php?tab=<?= $tab ?>&filter=Staff"   class="filter-chip <?= $filter==='Staff'?'active':'' ?>">
      <i class="fas fa-user-tie me-1"></i>Staff
    </a>
    <div class="ms-auto">
      <input type="text" id="memberSearch" class="form-control form-control-sm"
        placeholder="Search name or UID..." style="width:210px;">
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table abha-table mb-0" id="membersTable">
          <thead>
            <tr>
              <th class="ps-3" style="width:40px;">#</th>
              <th>Member</th>
              <th style="width:90px;">Type</th>
              <th>ABHA Number</th>
              <th>ABHA Address</th>
              <th style="width:110px;">Status</th>
              <th class="pe-3 text-end" style="width:130px;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php $i=1; while ($m = mysqli_fetch_assoc($members)): ?>
          <?php
            $tc = ['Student'=>'student','Teacher'=>'teacher','Staff'=>'staff'][$m['type']] ?? 'student';
            $av_color = ['Student'=>'#0277bd','Teacher'=>'#2e7d32','Staff'=>'#7c3aed'][$m['type']] ?? '#0C74C5';
            $sub = $m['type']==='Student' ? ($m['class'] ? 'Class '.$m['class'] : '') : ($m['employee_id'] ?: '');
          ?>
          <tr>
            <td class="ps-3"><small class="text-muted"><?= $i++ ?></small></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if ($m['profile_pic']): ?>
                  <img src="<?= BASE_URL.$m['profile_pic'] ?>" class="mem-av" style="object-fit:cover;" alt="">
                <?php else: ?>
                  <div class="mem-av" style="background:<?= $av_color ?>"><?= strtoupper(substr($m['name'],0,1)) ?></div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($m['name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($m['member_uid']) ?><?= $sub ? ' · '.$sub : '' ?></small>
                </div>
              </div>
            </td>
            <td><span class="type-chip chip-<?= $tc ?>"><?= $m['type'] ?></span></td>
            <td>
              <?php if ($m['abha_id']): ?>
                <span style="font-family:monospace;font-size:.83rem;font-weight:700;color:#00875a;"><?= htmlspecialchars($m['abha_id']) ?></span>
                <?php if ($m['abha_linked_at']): ?>
                  <br><small class="text-muted" style="font-size:.67rem;">Linked <?= date('d M Y', strtotime($m['abha_linked_at'])) ?></small>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:.8rem;">— not set —</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem;">
              <?= $m['abha_address'] ? htmlspecialchars($m['abha_address']) : '<span class="text-muted">—</span>' ?>
            </td>
            <td>
              <?php if ($m['abha_linked']): ?>
                <?php if ($m['abha_verified']): ?>
                  <span class="badge-verified"><i class="fas fa-shield-alt me-1"></i>Verified</span>
                <?php else: ?>
                  <span class="badge-linked"><i class="fas fa-link me-1"></i>Linked</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge-unlinked"><i class="fas fa-unlink me-1"></i>Not Linked</span>
              <?php endif; ?>
            </td>
            <td class="pe-3">
              <div class="d-flex justify-content-end gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary"
                  style="font-size:.71rem;padding:3px 8px;"
                  onclick="openLinkModal(<?= $m['id'] ?>,'<?= addslashes(htmlspecialchars($m['name'])) ?>','<?= htmlspecialchars($m['abha_id'] ?? '') ?>','<?= htmlspecialchars(str_replace('@abdm','',$m['abha_address'] ?? '')) ?>',<?= (int)$m['abha_verified'] ?>)">
                  <i class="fas fa-<?= $m['abha_linked'] ? 'pen' : 'link' ?> me-1"></i><?= $m['abha_linked'] ? 'Update' : 'Link' ?>
                </button>
                <?php if ($m['abha_linked']): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Remove ABHA link for <?= addslashes($m['name']) ?>?')">
                  <input type="hidden" name="action" value="unlink_abha">
                  <input type="hidden" name="mid"    value="<?= $m['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:.71rem;padding:3px 8px;" title="Unlink ABHA">
                    <i class="fas fa-unlink"></i>
                  </button>
                </form>
                <?php endif; ?>
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

</main>

<!-- ═══ Link ABHA Modal ═══ -->
<div class="modal fade" id="linkAbhaModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0" style="background:#00875a;color:#fff;">
        <h6 class="modal-title fw-bold"><i class="fas fa-id-card me-2"></i>Link ABHA — <span id="m_name"></span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="link_abha">
        <input type="hidden" name="mid"    id="m_id">
        <div class="modal-body p-4">
          <!-- Preview card -->
          <div class="abha-card-preview">
            <div class="ac-lbl mb-1">Ayushman Bharat Health Account</div>
            <div class="ac-num" id="prev_abha_num">XX-XXXX-XXXX-XXXX</div>
            <div style="font-size:.75rem;margin-top:6px;opacity:.8;" id="prev_abha_addr">address@abdm</div>
            <div style="font-size:.68rem;margin-top:12px;opacity:.65;"><i class="fas fa-heartbeat me-1"></i>National Digital Health Mission · India</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">ABHA Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="abha_id" id="m_abha_id"
              placeholder="XX-XXXX-XXXX-XXXX" maxlength="19"
              oninput="fmtAbha(this)" required>
            <small class="text-muted">14-digit health ID — formatted automatically as XX-XXXX-XXXX-XXXX</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ABHA Address <small class="text-muted fw-normal">(optional)</small></label>
            <div class="input-group">
              <input type="text" class="form-control" name="abha_address" id="m_abha_addr"
                placeholder="yourname" oninput="fmtAddr(this)">
              <span class="input-group-text">@abdm</span>
            </div>
            <small class="text-muted">PHR address e.g. john.doe@abdm</small>
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="mark_verified" id="markVerified">
            <label class="form-check-label fw-normal" for="markVerified" style="font-size:.83rem;">
              <i class="fas fa-shield-alt text-primary me-1"></i>Mark as <strong>Verified</strong> — ABHA confirmed against original credentials
            </label>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm" style="background:#00875a;color:#fff;">
            <i class="fas fa-link me-1"></i>Save ABHA
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══ Reject Modal ═══ -->
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Reject Request</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="reject_request">
        <input type="hidden" name="req_id" id="rej_req_id">
        <div class="modal-body">
          <p class="text-muted" style="font-size:.83rem;">Rejecting ABHA request from <strong id="rej_name"></strong>.</p>
          <textarea class="form-control" name="notes" rows="2" placeholder="Reason (optional)"></textarea>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">Confirm Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openLinkModal(id, name, abhaId, abhaAddr, verified) {
  document.getElementById('m_id').value   = id;
  document.getElementById('m_name').textContent = name;
  document.getElementById('m_abha_id').value    = abhaId || '';
  document.getElementById('m_abha_addr').value  = abhaAddr || '';
  document.getElementById('markVerified').checked = !!verified;
  document.getElementById('prev_abha_num').textContent = abhaId || 'XX-XXXX-XXXX-XXXX';
  document.getElementById('prev_abha_addr').textContent = abhaAddr ? abhaAddr+'@abdm' : 'address@abdm';
  new bootstrap.Modal(document.getElementById('linkAbhaModal')).show();
}

function fmtAbha(el) {
  let v = el.value.replace(/\D/g,'').substring(0,14);
  let out = v.length > 0 ? v.substring(0,2) : '';
  if (v.length > 2)  out += '-' + v.substring(2,6);
  if (v.length > 6)  out += '-' + v.substring(6,10);
  if (v.length > 10) out += '-' + v.substring(10,14);
  el.value = out;
  document.getElementById('prev_abha_num').textContent = out || 'XX-XXXX-XXXX-XXXX';
}

function fmtAddr(el) {
  const addr = el.value.replace('@abdm','').trim();
  document.getElementById('prev_abha_addr').textContent = addr ? addr+'@abdm' : 'address@abdm';
}

document.getElementById('rejectModal').addEventListener('show.bs.modal', function(e) {
  const btn = e.relatedTarget;
  document.getElementById('rej_req_id').value = btn.dataset.reqid;
  document.getElementById('rej_name').textContent = btn.dataset.name;
});

const searchEl = document.getElementById('memberSearch');
if (searchEl) {
  searchEl.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#membersTable tbody tr').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}
</script>
</body>
</html>
