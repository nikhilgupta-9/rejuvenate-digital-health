<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/Abha.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../config/abdm.php';
admin_jwt_guard();

$portal = $_GET['portal'] ?? 'all';   // all | patients | school
$tab    = $_GET['tab']    ?? 'list';  // list | linked | unlinked | requests
$type   = $_GET['type']   ?? 'all';   // all | Student | Teacher | Staff (school portal)
$success = $error = '';

/* ─── POST handlers ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Link ABHA to a patient (user) */
    if ($action === 'link_user_abha') {
        $uid      = (int)$_POST['uid'];
        $abha_raw = preg_replace('/\D/', '', trim($_POST['abha_id'] ?? ''));
        $abha_addr= trim($_POST['abha_address'] ?? '');
        $verified = isset($_POST['mark_verified']) ? 1 : 0;
        if (strlen($abha_raw) !== 14) { $error = "Invalid ABHA number — must be 14 digits."; }
        else {
            $fmt = substr($abha_raw,0,2).'-'.substr($abha_raw,2,4).'-'.substr($abha_raw,6,4).'-'.substr($abha_raw,10,4);
            if ($abha_addr && strpos($abha_addr,'@') === false) $abha_addr .= '@abdm';
            try {
                Abha::save($conn, 'patient', $uid, [
                    'abha_number'  => $fmt,
                    'abha_address' => $abha_addr,
                    'linked'       => 1,
                    'verified'     => $verified,
                    'source'       => 'admin',
                ]);
                $success = "ABHA linked to patient.";
            } catch (Throwable $e) {
                error_log('[admin/abha-management link] ' . $e->getMessage());
                $error = "Could not link ABHA. Please try again.";
            }
        }
    }

    /* Unlink ABHA from patient */
    if ($action === 'unlink_user_abha') {
        $uid = (int)$_POST['uid'];
        Abha::unlink($conn, 'patient', $uid);
        $success = "ABHA unlinked from patient.";
    }

    /* Approve patient ABHA request */
    if ($action === 'approve_user_req') {
        $req_id = (int)$_POST['req_id'];
        $rq = $conn->prepare("SELECT * FROM user_abha_requests WHERE id=? AND status='Pending'");
        $rq->bind_param('i', $req_id); $rq->execute();
        $req = $rq->get_result()->fetch_assoc();
        if ($req) {
            Abha::save($conn, 'patient', (int) $req['user_id'], [
                'abha_number'  => $req['abha_id'],
                'abha_address' => $req['abha_address'],
                'linked'       => 1,
                'source'       => 'admin',
            ]);
            $done = $conn->prepare("UPDATE user_abha_requests SET status='Approved', reviewed_at=NOW(), reviewed_by=? WHERE id=?");
            $done->bind_param('ii', $_SESSION['admin_id'], $req_id); $done->execute();
            $success = "Patient ABHA request approved.";
        } else $error = "Request not found or already processed.";
    }

    /* Reject patient ABHA request */
    if ($action === 'reject_user_req') {
        $req_id = (int)$_POST['req_id'];
        $notes  = trim($_POST['notes'] ?? 'Rejected by admin');
        $done = $conn->prepare("UPDATE user_abha_requests SET status='Rejected', reviewed_at=NOW(), reviewed_by=?, notes=? WHERE id=? AND status='Pending'");
        $done->bind_param('isi', $_SESSION['admin_id'], $notes, $req_id); $done->execute();
        $success = "Request rejected.";
    }
}

/* ─── Stats ─────────────────────────────────────────────────────── */
$us = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total, SUM(abha_linked=1) as linked, SUM(abha_verified=1) as verified
    FROM users WHERE status='Active'"));
$sm = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total, SUM(abha_linked=1) as linked, SUM(abha_verified=1) as verified
    FROM school_members WHERE status='Active'"));
$user_req_pend  = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM user_abha_requests WHERE status='Pending'"))['c'];
$school_req_pend= (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) as c FROM abha_link_requests  WHERE status='Pending'"))['c'];
$total_req_pend = $user_req_pend + $school_req_pend;

/* ─── Fetch data by portal & tab ────────────────────────────────── */
$users_list = $school_list = null;
$user_reqs = $school_reqs = null;

if ($tab === 'requests') {
    if ($portal !== 'school') {
        $user_reqs = mysqli_query($conn,"
            SELECT uar.*, u.name, u.email, u.mobile
            FROM user_abha_requests uar
            JOIN users u ON uar.user_id=u.id
            WHERE uar.status='Pending' ORDER BY uar.requested_at DESC");
    }
    if ($portal !== 'patients') {
        $school_reqs = mysqli_query($conn,"
            SELECT alr.*, sm.name as member_name, sm.type as member_type, sm.member_uid, s.school_name
            FROM abha_link_requests alr
            JOIN school_members sm ON alr.member_id=sm.id
            JOIN schools s ON alr.school_id=s.id
            WHERE alr.status='Pending' ORDER BY alr.requested_at DESC");
    }
} else {
    if ($portal !== 'school') {
        $where_u = ($tab === 'linked')   ? "AND abha_linked=1"
                 : (($tab === 'unlinked') ? "AND (abha_linked=0 OR abha_linked IS NULL)"
                 : '');
        $users_list = mysqli_query($conn,"
            SELECT id,name,email,mobile,profile_pic,blood_group,gender,
                   abha_id,abha_address,abha_linked,abha_linked_at,abha_verified
            FROM users WHERE status='Active' $where_u
            ORDER BY abha_linked DESC, name ASC LIMIT 200");
    }
    if ($portal !== 'patients') {
        $where_t = ($type !== 'all') ? "AND sm.type='".mysqli_real_escape_string($conn,$type)."'" : '';
        $where_m = ($tab === 'linked')   ? "AND sm.abha_linked=1"
                 : (($tab === 'unlinked') ? "AND (sm.abha_linked=0 OR sm.abha_linked IS NULL)"
                 : '');
        $school_list = mysqli_query($conn,"
            SELECT sm.id,sm.name,sm.type,sm.member_uid,sm.class,sm.employee_id,
                   sm.abha_id,sm.abha_address,sm.abha_linked,sm.abha_linked_at,sm.abha_verified,
                   sm.profile_pic, s.school_name
            FROM school_members sm
            JOIN schools s ON sm.school_id=s.id
            WHERE sm.status='Active' $where_t $where_m
            ORDER BY sm.abha_linked DESC, sm.name ASC LIMIT 300");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin | ABHA Management</title>
    <?php include "links.php"; ?>
    <style>
        :root { --ab:#00875a; }
        .abha-hero {
            background: #00875a;
            border-radius: 14px;
            color: #fff;
            padding: 22px 26px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .abha-hero .icon-box {
            width:56px;height:56px;background:rgba(255,255,255,.18);border-radius:14px;
            display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;
        }
        .abha-hero h5 { margin:0;font-size:1.05rem;font-weight:700; }
        .abha-hero p  { margin:4px 0 0;font-size:.78rem;opacity:.85; }

        /* Stat card */
        .ab-stat { background:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.07); }
        .ab-stat .num { font-size:1.6rem;font-weight:700;line-height:1; }
        .ab-stat .lbl { font-size:.72rem;color:#6b7280;margin-top:3px; }
        .ab-bar { height:5px;border-radius:3px;background:#e5e7eb;overflow:hidden;margin-top:8px; }
        .ab-bar-fill { height:100%;border-radius:3px; }

        /* Portal tabs */
        .p-tabs { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px; }
        .p-tab  { padding:6px 16px;border-radius:20px;border:1.5px solid #e5e7eb;background:#fff;font-size:.8rem;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap; }
        .p-tab:hover,.p-tab.active { background:#0C74C5;color:#fff;border-color:#0C74C5;text-decoration:none; }
        .p-tab.ab-active { background:#00875a;color:#fff;border-color:#00875a; }
        .tab-cnt { background:#ea580c;color:#fff;border-radius:10px;padding:1px 6px;font-size:.6rem;font-weight:700;margin-left:4px; }

        /* Filters */
        .fchip { padding:4px 12px;border-radius:16px;border:1.5px solid #e5e7eb;background:#fff;font-size:.74rem;font-weight:600;color:#374151;text-decoration:none;white-space:nowrap; }
        .fchip:hover,.fchip.active { background:#0C74C5;color:#fff;border-color:#0C74C5;text-decoration:none; }

        /* Table */
        .ab-table th { font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;background:#f9fafb;border-bottom:1px solid #e5e7eb;padding:9px 12px; }
        .ab-table td { font-size:.82rem;vertical-align:middle;padding:9px 12px;border-bottom:1px solid #f3f4f6; }
        .ab-table tbody tr:hover td { background:#f0f7ff; }

        /* Badges */
        .b-linked   { background:#d1fae5;color:#065f46;border-radius:5px;padding:2px 8px;font-size:.7rem;font-weight:700; }
        .b-unlinked { background:#fef3c7;color:#92400e;border-radius:5px;padding:2px 8px;font-size:.7rem;font-weight:700; }
        .b-verified { background:#dbeafe;color:#1e40af;border-radius:5px;padding:2px 8px;font-size:.7rem;font-weight:700; }
        .chip-s { background:#e0f2fe;color:#0277bd; }
        .chip-t { background:#e8f5e9;color:#2e7d32; }
        .chip-st{ background:#f3e5f5;color:#6a1b9a; }
        .t-chip { border-radius:5px;padding:1px 8px;font-size:.68rem;font-weight:700; }

        .mem-av { width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden; }

        /* Request card */
        .req-row { background:#fffbeb;border:1.5px solid #fde68a;border-radius:10px;padding:12px 16px;margin-bottom:8px; }
        .req-abha { font-family:monospace;font-size:.9rem;font-weight:700;color:#00875a; }

        /* ABHA preview (modal) */
        .abha-prev { background:#00875a;border-radius:10px;color:#fff;padding:14px 18px;margin-bottom:14px; }
        .abha-prev .pnum { font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:.08em; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include "header.php"; ?>
    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div>
        </div>
        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">

                <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error):   ?><div class="alert alert-danger  alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <!-- Hero -->
                <div class="abha-hero">
                    <div class="icon-box"><i class="fas fa-heartbeat"></i></div>
                    <div>
                        <h5>ABHA — Ayushman Bharat Health Account</h5>
                        <p>Central management hub for all ABHA digital health IDs across patients, school students, teachers and staff. Approve self-service requests, link IDs manually, and monitor coverage.</p>
                    </div>
                    <div class="d-flex gap-2 ms-auto flex-shrink-0 flex-wrap">
                        <button type="button" class="btn btn-sm btn-light fw-bold text-dark shadow-sm" onclick="openFindAbhaModal()">
                            <i class="fas fa-search me-1 text-primary"></i>Find ABHA Number
                        </button>
                        <a href="https://healthid.ndhm.gov.in/" target="_blank" class="btn btn-sm"
                           style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);">
                            <i class="fas fa-external-link-alt me-1"></i>ABDM Portal
                        </a>
                    </div>
                </div>

                <!-- Stats row -->
                <?php
                  $tot_all     = (int)$us['total'] + (int)$sm['total'];
                  $lnk_all     = (int)$us['linked'] + (int)$sm['linked'];
                  $ver_all     = (int)$us['verified'] + (int)$sm['verified'];
                  $pct_all     = $tot_all > 0 ? round($lnk_all/$tot_all*100) : 0;
                  $pct_user    = $us['total'] > 0 ? round($us['linked']/$us['total']*100) : 0;
                  $pct_school  = $sm['total'] > 0 ? round($sm['linked']/$sm['total']*100) : 0;
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="ab-stat">
                            <div class="num" style="color:#00875a;"><?= $lnk_all ?><span style="font-size:.8rem;color:#9ca3af;font-weight:400;"> / <?= $tot_all ?></span></div>
                            <div class="lbl">Total ABHA Linked (All)</div>
                            <div class="ab-bar"><div class="ab-bar-fill" style="width:<?= $pct_all ?>%;background:#00875a;"></div></div>
                            <div style="font-size:.7rem;color:#9ca3af;margin-top:4px;"><?= $pct_all ?>% overall coverage</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="ab-stat">
                            <div class="num" style="color:#0C74C5;"><?= (int)$us['linked'] ?><span style="font-size:.8rem;color:#9ca3af;font-weight:400;"> / <?= (int)$us['total'] ?></span></div>
                            <div class="lbl">Patient ABHA Linked</div>
                            <div class="ab-bar"><div class="ab-bar-fill" style="width:<?= $pct_user ?>%;background:#0C74C5;"></div></div>
                            <div style="font-size:.7rem;color:#9ca3af;margin-top:4px;"><?= $pct_user ?>% · <?= (int)$us['verified'] ?> verified</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="ab-stat">
                            <div class="num" style="color:#7c3aed;"><?= (int)$sm['linked'] ?><span style="font-size:.8rem;color:#9ca3af;font-weight:400;"> / <?= (int)$sm['total'] ?></span></div>
                            <div class="lbl">School Member ABHA Linked</div>
                            <div class="ab-bar"><div class="ab-bar-fill" style="width:<?= $pct_school ?>%;background:#7c3aed;"></div></div>
                            <div style="font-size:.7rem;color:#9ca3af;margin-top:4px;"><?= $pct_school ?>% · <?= (int)$sm['verified'] ?> verified</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="ab-stat">
                            <div class="num" style="color:<?= $total_req_pend > 0 ? '#dc2626' : '#6b7280' ?>;"><?= $total_req_pend ?></div>
                            <div class="lbl">Pending Link Requests</div>
                            <div class="ab-bar"><div class="ab-bar-fill" style="width:<?= min(100,$total_req_pend*10) ?>%;background:#dc2626;"></div></div>
                            <div style="font-size:.7rem;color:#9ca3af;margin-top:4px;"><?= $user_req_pend ?> patients · <?= $school_req_pend ?> school members</div>
                        </div>
                    </div>
                </div>

                <!-- Portal selector + tab nav -->
                <div class="p-tabs mb-2">
                    <a href="abha-management.php?portal=all&tab=<?= $tab ?>" class="p-tab <?= $portal==='all'?'ab-active':'' ?>"><i class="fas fa-globe me-1"></i>All Portals</a>
                    <a href="abha-management.php?portal=patients&tab=<?= $tab ?>" class="p-tab <?= $portal==='patients'?'active':'' ?>"><i class="fas fa-users me-1"></i>Patients</a>
                    <a href="abha-management.php?portal=school&tab=<?= $tab ?>" class="p-tab <?= $portal==='school'?'active':'' ?>"><i class="fas fa-school me-1"></i>School Members</a>
                </div>
                <div class="p-tabs">
                    <a href="abha-management.php?portal=<?= $portal ?>&tab=list&type=<?= $type ?>" class="p-tab <?= $tab==='list'?'active':'' ?>"><i class="fas fa-list me-1"></i>All</a>
                    <a href="abha-management.php?portal=<?= $portal ?>&tab=linked&type=<?= $type ?>" class="p-tab <?= $tab==='linked'?'active':'' ?>"><i class="fas fa-check-circle me-1"></i>Linked</a>
                    <a href="abha-management.php?portal=<?= $portal ?>&tab=unlinked&type=<?= $type ?>" class="p-tab <?= $tab==='unlinked'?'active':'' ?>"><i class="fas fa-exclamation-circle me-1"></i>Not Linked</a>
                    <a href="abha-management.php?portal=<?= $portal ?>&tab=requests" class="p-tab <?= $tab==='requests'?'active':'' ?>">
                        <i class="fas fa-inbox me-1"></i>Link Requests
                        <?php if ($total_req_pend > 0): ?><span class="tab-cnt"><?= $total_req_pend ?></span><?php endif; ?>
                    </a>
                    <?php if ($portal === 'school' || $portal === 'all'): ?>
                    <div class="ms-auto d-flex gap-2">
                        <a href="abha-management.php?portal=<?= $portal ?>&tab=<?= $tab ?>&type=all"     class="fchip <?= $type==='all'?'active':'' ?>">All Types</a>
                        <a href="abha-management.php?portal=<?= $portal ?>&tab=<?= $tab ?>&type=Student" class="fchip <?= $type==='Student'?'active':'' ?>">Students</a>
                        <a href="abha-management.php?portal=<?= $portal ?>&tab=<?= $tab ?>&type=Teacher" class="fchip <?= $type==='Teacher'?'active':'' ?>">Teachers</a>
                        <a href="abha-management.php?portal=<?= $portal ?>&tab=<?= $tab ?>&type=Staff"   class="fchip <?= $type==='Staff'?'active':'' ?>">Staff</a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($tab === 'requests'): ?>
                <!-- ═══ REQUESTS ═══ -->
                <div class="card border-0 shadow-sm" style="border-radius:12px;">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="fw-bold mb-0"><i class="fas fa-inbox text-warning me-2"></i>Pending ABHA Link Requests</h6>
                        <small class="text-muted">Members and patients submitted these from their own portals</small>
                    </div>
                    <div class="card-body">
                        <?php if ($portal !== 'school' && $user_reqs && mysqli_num_rows($user_reqs) > 0): ?>
                        <div class="fw-semibold mb-2" style="font-size:.8rem;color:#0C74C5;"><i class="fas fa-users me-2"></i>Patient Requests (<?= mysqli_num_rows($user_reqs) ?>)</div>
                        <?php while ($req = mysqli_fetch_assoc($user_reqs)): ?>
                        <div class="req-row">
                            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="badge bg-info text-dark" style="font-size:.68rem;">Patient</span>
                                        <strong><?= htmlspecialchars($req['name']) ?></strong>
                                        <span style="font-size:.72rem;color:#6b7280;"><?= htmlspecialchars($req['email']) ?> · <?= $req['mobile'] ?></span>
                                    </div>
                                    <div><span style="font-size:.72rem;color:#6b7280;">ABHA:</span> <span class="req-abha ms-1"><?= htmlspecialchars($req['abha_id'] ?: '—') ?></span></div>
                                    <?php if ($req['abha_address']): ?><div style="font-size:.78rem;"><?= htmlspecialchars($req['abha_address']) ?></div><?php endif; ?>
                                    <div style="font-size:.7rem;color:#9ca3af;"><i class="fas fa-clock me-1"></i><?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?></div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <form method="POST"><input type="hidden" name="action" value="approve_user_req"><input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Approve</button></form>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectUModal" data-reqid="<?= $req['id'] ?>" data-name="<?= htmlspecialchars($req['name']) ?>"><i class="fas fa-times me-1"></i>Reject</button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($portal !== 'patients' && $school_reqs && mysqli_num_rows($school_reqs) > 0): ?>
                        <div class="fw-semibold mb-2 mt-3" style="font-size:.8rem;color:#7c3aed;"><i class="fas fa-school me-2"></i>School Member Requests (<?= mysqli_num_rows($school_reqs) ?>)</div>
                        <?php while ($req = mysqli_fetch_assoc($school_reqs)): ?>
                        <div class="req-row" style="border-color:#ddd6fe;background:#faf5ff;">
                            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <span class="t-chip chip-<?= strtolower($req['member_type']) ?>"><?= $req['member_type'] ?></span>
                                        <strong><?= htmlspecialchars($req['member_name']) ?></strong>
                                        <span style="font-size:.72rem;color:#6b7280;"><?= htmlspecialchars($req['member_uid']) ?> · <?= htmlspecialchars($req['school_name']) ?></span>
                                    </div>
                                    <div><span style="font-size:.72rem;color:#6b7280;">ABHA:</span> <span class="req-abha ms-1"><?= htmlspecialchars($req['abha_id'] ?: '—') ?></span></div>
                                    <?php if ($req['abha_address']): ?><div style="font-size:.78rem;"><?= htmlspecialchars($req['abha_address']) ?></div><?php endif; ?>
                                    <div style="font-size:.7rem;color:#9ca3af;"><i class="fas fa-clock me-1"></i><?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?></div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <!-- School requests handled per-school; link to school ABHA mgmt -->
                                    <a href="<?= BASE_URL ?>school/health/abha.php?tab=requests" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i>Review in School Portal</a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ((!$user_reqs || mysqli_num_rows($user_reqs) === 0) && (!$school_reqs || mysqli_num_rows($school_reqs) === 0)): ?>
                        <div class="text-center py-5 text-muted"><i class="fas fa-check-circle fa-3x text-success mb-3"></i><br><strong>No pending requests</strong></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- ═══ TABLE(S) ═══ -->
                <div style="margin-bottom:10px;">
                    <input type="text" id="abhaSearch" class="form-control form-control-sm" placeholder="Search name, email, UID, ABHA..." style="max-width:280px;">
                </div>

                <?php if ($users_list && ($portal === 'all' || $portal === 'patients')): ?>
                <!-- Patient table -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
                    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="fas fa-users text-primary me-2"></i>Patients — ABHA Status</h6>
                        <span style="font-size:.75rem;color:#6b7280;"><?= mysqli_num_rows($users_list) ?> records</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table ab-table mb-0" id="patientAbhaTable">
                                <thead><tr>
                                    <th class="ps-3">#</th><th>Patient</th><th>Contact</th>
                                    <th>ABHA Number</th><th>ABHA Address</th><th>Status</th>
                                    <th class="pe-3 text-end">Actions</th>
                                </tr></thead>
                                <tbody>
                                <?php $i=1; while ($u = mysqli_fetch_assoc($users_list)): ?>
                                <tr>
                                    <td class="ps-3"><small class="text-muted"><?= $i++ ?></small></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($u['profile_pic']): ?>
                                            <img src="<?= BASE_URL ?>assets/img/<?= htmlspecialchars($u['profile_pic']) ?>" class="mem-av" style="object-fit:cover;" alt="">
                                            <?php else: ?>
                                            <div class="mem-av" style="background:#0C74C5;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size:.8rem;"><?= htmlspecialchars($u['mobile'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($u['abha_id']): ?>
                                        <span style="font-family:monospace;font-size:.83rem;font-weight:700;color:#00875a;"><?= htmlspecialchars($u['abha_id']) ?></span>
                                        <?php if ($u['abha_linked_at']): ?><br><small class="text-muted" style="font-size:.66rem;"><?= date('d M Y', strtotime($u['abha_linked_at'])) ?></small><?php endif; ?>
                                        <?php else: ?><span class="text-muted" style="font-size:.8rem;">—</span><?php endif; ?>
                                    </td>
                                    <td style="font-size:.8rem;"><?= $u['abha_address'] ? htmlspecialchars($u['abha_address']) : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <?php if ($u['abha_linked']): ?>
                                            <?php if ($u['abha_verified']): ?><span class="b-verified"><i class="fas fa-shield-alt me-1"></i>Verified</span>
                                            <?php else: ?><span class="b-linked"><i class="fas fa-link me-1"></i>Linked</span><?php endif; ?>
                                        <?php else: ?><span class="b-unlinked"><i class="fas fa-unlink me-1"></i>Not Linked</span><?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.71rem;padding:3px 8px;"
                                              onclick="openUserModal(<?= $u['id'] ?>,'<?= addslashes($u['name']) ?>','<?= $u['abha_id'] ?>','<?= str_replace('@abdm','',$u['abha_address'] ?? '') ?>',<?= (int)$u['abha_verified'] ?>)">
                                              <i class="fas fa-<?= $u['abha_linked']?'pen':'link' ?> me-1"></i><?= $u['abha_linked']?'Update':'Link' ?>
                                            </button>
                                            <?php if ($u['abha_linked']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove ABHA for <?= addslashes($u['name']) ?>?')">
                                                <input type="hidden" name="action" value="unlink_user_abha">
                                                <input type="hidden" name="uid"    value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:.71rem;padding:3px 8px;"><i class="fas fa-unlink"></i></button>
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

                <?php if ($school_list && ($portal === 'all' || $portal === 'school')): ?>
                <!-- School members table -->
                <div class="card border-0 shadow-sm" style="border-radius:12px;">
                    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="fas fa-school me-2" style="color:#7c3aed;"></i>School Members — ABHA Status</h6>
                        <a href="<?= BASE_URL ?>school/health/abha.php" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size:.73rem;">Open School ABHA →</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table ab-table mb-0" id="schoolAbhaTable">
                                <thead><tr>
                                    <th class="ps-3">#</th><th>Member</th><th>School</th><th>Type</th>
                                    <th>ABHA Number</th><th>Status</th>
                                </tr></thead>
                                <tbody>
                                <?php $i=1; while ($m = mysqli_fetch_assoc($school_list)): ?>
                                <?php $tc=['Student'=>'s','Teacher'=>'t','Staff'=>'st'][$m['type']]??'s'; ?>
                                <tr>
                                    <td class="ps-3"><small class="text-muted"><?= $i++ ?></small></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="mem-av" style="background:<?= ['Student'=>'#0277bd','Teacher'=>'#2e7d32','Staff'=>'#7c3aed'][$m['type']]??'#555' ?>"><?= strtoupper(substr($m['name'],0,1)) ?></div>
                                            <div>
                                                <div style="font-weight:600;font-size:.84rem;"><?= htmlspecialchars($m['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($m['member_uid']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size:.78rem;"><?= htmlspecialchars($m['school_name']) ?></td>
                                    <td><span class="t-chip chip-<?= $tc ?>"><?= $m['type'] ?></span></td>
                                    <td>
                                        <?php if ($m['abha_id']): ?>
                                        <span style="font-family:monospace;font-size:.83rem;font-weight:700;color:#00875a;"><?= htmlspecialchars($m['abha_id']) ?></span>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['abha_linked']): ?>
                                            <?php if ($m['abha_verified']): ?><span class="b-verified"><i class="fas fa-shield-alt me-1"></i>Verified</span>
                                            <?php else: ?><span class="b-linked"><i class="fas fa-link me-1"></i>Linked</span><?php endif; ?>
                                        <?php else: ?><span class="b-unlinked"><i class="fas fa-unlink me-1"></i>Not Linked</span><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </section>
</div>

<!-- Link ABHA Modal (patients) -->
<div class="modal fade" id="linkUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0" style="background:#00875a;color:#fff;">
        <h6 class="modal-title fw-bold"><i class="fas fa-id-card me-2"></i>Link ABHA — <span id="mu_name"></span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="link_user_abha">
        <input type="hidden" name="uid" id="mu_id">
        <div class="modal-body p-4">
          <div class="abha-prev">
            <div style="font-size:.6rem;opacity:.7;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;">Ayushman Bharat Health Account</div>
            <div class="pnum" id="mu_prev_num">XX-XXXX-XXXX-XXXX</div>
            <div style="font-size:.75rem;opacity:.8;margin-top:3px;" id="mu_prev_addr">address@abdm</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ABHA Number <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="abha_id" id="mu_abha" placeholder="XX-XXXX-XXXX-XXXX" maxlength="19" oninput="fmtAbha(this,'mu_prev_num')" required>
            <small class="text-muted">14-digit health ID, auto-formatted</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ABHA Address <small class="text-muted fw-normal">(optional)</small></label>
            <div class="input-group">
              <input type="text" class="form-control" name="abha_address" id="mu_addr" placeholder="yourname" oninput="fmtAddr(this,'mu_prev_addr')">
              <span class="input-group-text">@abdm</span>
              <button class="btn btn-outline-primary" type="button" id="btnAdminVerifyAbha" onclick="verifyAbhaLive()" title="Verify on ABDM Registry">
                <i class="fas fa-check-circle me-1"></i>Verify
              </button>
            </div>
            <div id="mu_verify_status" class="mt-1" style="font-size:.78rem;"></div>
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" name="mark_verified" id="mu_verified">
            <label class="form-check-label" for="mu_verified" style="font-size:.83rem;">
              <i class="fas fa-shield-alt text-primary me-1"></i>Mark as Verified
            </label>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm" style="background:#00875a;color:#fff;"><i class="fas fa-link me-1"></i>Save ABHA</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject patient request modal -->
<div class="modal fade" id="rejectUModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-header border-0"><h6 class="modal-title text-danger fw-bold"><i class="fas fa-times-circle me-2"></i>Reject Request</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST"><input type="hidden" name="action" value="reject_user_req"><input type="hidden" name="req_id" id="ru_id">
      <div class="modal-body"><p class="text-muted" style="font-size:.83rem;">Rejecting request from <strong id="ru_name"></strong>.</p><textarea class="form-control" name="notes" rows="2" placeholder="Reason (optional)"></textarea></div>
      <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-danger">Reject</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Find ABHA Modal (Admin Tool) -->
<div class="modal fade" id="findAbhaModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0" style="background:#0C74C5;color:#fff;">
        <h6 class="modal-title fw-bold"><i class="fas fa-search me-2"></i>Find ABHA Number (ABDM Registry)</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div id="admFindAlert" style="display:none;" class="alert mb-3"></div>

        <!-- Step 1: Input -->
        <div id="admFindStep1">
          <p class="text-muted" style="font-size:.82rem;margin-bottom:14px;">
            Search the national ABDM database to discover registered ABHA accounts by Mobile or Aadhaar OTP.
          </p>
          <div class="d-flex gap-2 mb-3">
            <button type="button" class="btn flex-fill text-start p-2" id="btnAdmFindMobile"
              onclick="admSwitchMethod('mobile')"
              style="border:2px solid #0C74C5;background:#f0f9ff;border-radius:8px;">
              <div class="fw-semibold" style="font-size:.82rem;color:#0369a1;"><i class="fas fa-mobile-alt me-1"></i>Mobile OTP</div>
              <div style="font-size:.7rem;color:#6b7280;">Finds all ABHAs on mobile</div>
            </button>
            <button type="button" class="btn flex-fill text-start p-2" id="btnAdmFindAadhaar"
              onclick="admSwitchMethod('aadhaar')"
              style="border:2px solid #e5e7eb;background:#f9fafb;border-radius:8px;">
              <div class="fw-semibold" style="font-size:.82rem;color:#374151;"><i class="fas fa-fingerprint me-1"></i>Aadhaar OTP</div>
              <div style="font-size:.7rem;color:#6b7280;">UIDAI OTP verification</div>
            </button>
          </div>

          <div id="admFindFormMobile">
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.84rem;">Mobile Number <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control" id="adm_find_mobile" placeholder="10-digit mobile number" maxlength="10" inputmode="numeric">
              </div>
            </div>
          </div>

          <div id="admFindFormAadhaar" style="display:none;">
            <div class="mb-3">
              <label class="form-label fw-semibold" style="font-size:.84rem;">Aadhaar Number <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="adm_find_aadhaar" placeholder="12-digit Aadhaar number" maxlength="12" inputmode="numeric">
            </div>
            <div class="form-check mb-3" style="font-size:.78rem;">
              <input class="form-check-input" type="checkbox" id="adm_find_consent" checked>
              <label class="form-check-label text-muted" for="adm_find_consent">
                Patient / User has consented to OTP authentication via UIDAI.
              </label>
            </div>
          </div>

          <button class="btn w-100 fw-semibold" id="btnAdmSendOtp" style="background:#0C74C5;color:#fff;" onclick="admReqOtp()">
            <i class="fas fa-paper-plane me-1"></i>Send OTP
          </button>
        </div>

        <!-- Step 2: OTP -->
        <div id="admFindStep2" style="display:none;">
          <p id="admFindOtpMsg" style="font-size:.82rem;color:#374151;margin-bottom:12px;"></p>
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.84rem;">Enter 6-digit OTP</label>
            <input type="text" class="form-control text-center fw-bold" id="adm_find_otp"
              placeholder="• • • • • •" maxlength="6" inputmode="numeric" style="letter-spacing:6px;font-size:1.3rem;">
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="admResetFind()"><i class="fas fa-arrow-left me-1"></i>Back</button>
            <button class="btn flex-fill fw-semibold btn-sm" id="btnAdmVerifyOtp" style="background:#0C74C5;color:#fff;" onclick="admVerifyOtp()">
              <i class="fas fa-search me-1"></i>Verify & Discover ABHA
            </button>
          </div>
        </div>

        <!-- Step 3: Results -->
        <div id="admFindStep3" style="display:none;">
          <div class="alert alert-success py-2 px-3 mb-3 d-flex align-items-center" style="font-size:.82rem;">
            <i class="fas fa-check-circle me-2 fa-lg text-success"></i>
            <div>Found <strong id="admAccountsCount">0</strong> registered ABHA account(s).</div>
          </div>
          <div id="admResultsList" style="max-height:300px;overflow-y:auto;"></div>
          <div class="mt-3 pt-2 border-top text-end">
            <button class="btn btn-sm btn-outline-secondary" onclick="admResetFind()">
              <i class="fas fa-redo me-1"></i>Search Another
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include "footer.php"; ?>
<script>
function openUserModal(id, name, abhaId, abhaAddr, verified) {
    document.getElementById('mu_id').value = id;
    document.getElementById('mu_name').textContent = name;
    document.getElementById('mu_abha').value = abhaId || '';
    document.getElementById('mu_addr').value = abhaAddr || '';
    document.getElementById('mu_verified').checked = !!verified;
    document.getElementById('mu_prev_num').textContent = abhaId || 'XX-XXXX-XXXX-XXXX';
    document.getElementById('mu_prev_addr').textContent = abhaAddr ? abhaAddr+'@abdm' : 'address@abdm';
    const st = document.getElementById('mu_verify_status');
    if (st) st.innerHTML = '';
    new bootstrap.Modal(document.getElementById('linkUserModal')).show();
}

async function verifyAbhaLive() {
    const addr = document.getElementById('mu_addr').value.trim();
    const abhaNum = document.getElementById('mu_abha').value.trim();
    const query = addr || abhaNum;
    const statusDiv = document.getElementById('mu_verify_status');
    const btn = document.getElementById('btnAdminVerifyAbha');

    if (!query) {
        statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Please enter an ABHA Address or ABHA Number first</span>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying...';
    statusDiv.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Checking ABDM Registry...</span>';

    try {
        const resp = await fetch('<?= BASE_URL ?>ajax/abdm-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'verify_abha',
                abha_address: addr ? (addr.includes('@') ? addr : addr + '@abdm') : '',
                abha_id: abhaNum,
                _csrf: '<?= Security::csrfToken() ?>'
            })
        });
        const data = await resp.json();
        if (data.success) {
            statusDiv.innerHTML = `<span class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Verified Active in ABDM! ${data.name ? '('+data.name+')' : ''} [${data.status || 'ACTIVE'}]</span>`;
            document.getElementById('mu_verified').checked = true;
            if (data.healthId && !document.getElementById('mu_abha').value) {
                document.getElementById('mu_abha').value = data.healthId;
                fmtAbha(document.getElementById('mu_abha'), 'mu_prev_num');
            }
        } else {
            statusDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>${data.message || 'Not found in ABDM registry'}</span>`;
        }
    } catch (e) {
        statusDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Verification request failed</span>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Verify';
    }
}
function fmtAbha(el, prevId) {
    let v = el.value.replace(/\D/g,'').substring(0,14);
    let out = v.length > 0 ? v.substring(0,2) : '';
    if (v.length > 2)  out += '-' + v.substring(2,6);
    if (v.length > 6)  out += '-' + v.substring(6,10);
    if (v.length > 10) out += '-' + v.substring(10,14);
    el.value = out;
    document.getElementById(prevId).textContent = out || 'XX-XXXX-XXXX-XXXX';
}
function fmtAddr(el, prevId) {
    const addr = el.value.replace('@abdm','').trim();
    document.getElementById(prevId).textContent = addr ? addr+'@abdm' : 'address@abdm';
}
document.getElementById('rejectUModal').addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('ru_id').value = b.dataset.reqid;
    document.getElementById('ru_name').textContent = b.dataset.name;
});
// Search
const searchEl = document.getElementById('abhaSearch');
if (searchEl) {
    searchEl.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        ['patientAbhaTable','schoolAbhaTable'].forEach(tid => {
            const tbl = document.getElementById(tid);
            if (!tbl) return;
            tbl.querySelectorAll('tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });
}

/* ── Admin Find ABHA Tool ── */
let admFindMethod = 'mobile';
let admFindTxnId = '';
let findAbhaModalInstance = null;

function openFindAbhaModal() {
    admResetFind();
    if (!findAbhaModalInstance) {
        findAbhaModalInstance = new bootstrap.Modal(document.getElementById('findAbhaModal'));
    }
    findAbhaModalInstance.show();
}

function admAlert(msg, type = 'danger') {
    const el = document.getElementById('admFindAlert');
    el.className = 'alert alert-' + type + ' mb-3';
    el.innerHTML = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 6000);
}

function admSwitchMethod(method) {
    admFindMethod = method;
    document.getElementById('admFindFormMobile').style.display = method === 'mobile' ? 'block' : 'none';
    document.getElementById('admFindFormAadhaar').style.display = method === 'aadhaar' ? 'block' : 'none';
    const btnM = document.getElementById('btnAdmFindMobile');
    const btnA = document.getElementById('btnAdmFindAadhaar');
    if (btnM) {
        btnM.style.border = method === 'mobile' ? '2px solid #0C74C5' : '2px solid #e5e7eb';
        btnM.style.background = method === 'mobile' ? '#f0f9ff' : '#f9fafb';
    }
    if (btnA) {
        btnA.style.border = method === 'aadhaar' ? '2px solid #00875a' : '2px solid #e5e7eb';
        btnA.style.background = method === 'aadhaar' ? '#f0fdf4' : '#f9fafb';
    }
}

function admResetFind() {
    admFindTxnId = '';
    document.getElementById('admFindStep1').style.display = 'block';
    document.getElementById('admFindStep2').style.display = 'none';
    document.getElementById('admFindStep3').style.display = 'none';
    document.getElementById('admFindAlert').style.display = 'none';
    document.getElementById('adm_find_otp').value = '';
    admSwitchMethod(admFindMethod);
}

async function admReqOtp() {
    let val = '';
    let consent = false;
    if (admFindMethod === 'mobile') {
        val = document.getElementById('adm_find_mobile').value.replace(/\D/g, '');
        if (val.length !== 10) { admAlert('Enter a valid 10-digit mobile number'); return; }
    } else {
        val = document.getElementById('adm_find_aadhaar').value.replace(/\D/g, '');
        if (val.length !== 12) { admAlert('Enter a valid 12-digit Aadhaar number'); return; }
        consent = document.getElementById('adm_find_consent')?.checked;
        if (!consent) { admAlert('Consent is required to authenticate via Aadhaar OTP'); return; }
    }

    const btn = document.getElementById('btnAdmSendOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending OTP...';

    try {
        const resp = await fetch('<?= BASE_URL ?>ajax/abdm-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'find_abha_request_otp',
                auth_type: admFindMethod,
                auth_value: val,
                consent: consent ? 1 : 0,
                _csrf: '<?= Security::csrfToken() ?>'
            })
        });
        const data = await resp.json();
        if (data.success) {
            admFindTxnId = data.txnId || '';
            document.getElementById('admFindOtpMsg').textContent = data.message || 'OTP sent successfully.';
            document.getElementById('admFindStep1').style.display = 'none';
            document.getElementById('admFindStep2').style.display = 'block';
            document.getElementById('adm_find_otp').focus();
        } else {
            admAlert(data.message || 'Failed to send OTP.');
        }
    } catch(e) {
        admAlert('Network error while requesting OTP.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send OTP';
    }
}

async function admVerifyOtp() {
    const otp = document.getElementById('adm_find_otp').value.replace(/\D/g, '');
    if (otp.length !== 6) { admAlert('Please enter 6-digit OTP'); return; }

    const btn = document.getElementById('btnAdmVerifyOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verifying...';

    try {
        const resp = await fetch('<?= BASE_URL ?>ajax/abdm-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'find_abha_verify_otp',
                otp: otp,
                txnId: admFindTxnId,
                _csrf: '<?= Security::csrfToken() ?>'
            })
        });
        const data = await resp.json();
        if (data.success && data.accounts && data.accounts.length > 0) {
            renderAdmResults(data.accounts);
            document.getElementById('admFindStep2').style.display = 'none';
            document.getElementById('admFindStep3').style.display = 'block';
        } else {
            admAlert(data.message || 'No registered ABHA found for this detail.');
        }
    } catch(e) {
        admAlert('Network error while verifying OTP.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search me-1"></i>Verify & Discover ABHA';
    }
}

function renderAdmResults(accounts) {
    const c = document.getElementById('admResultsList');
    document.getElementById('admAccountsCount').textContent = accounts.length;
    let html = '';
    accounts.forEach(a => {
        const num = a.abha_number || '—';
        const addr = a.abha_address || '—';
        const name = a.name || 'ABHA Holder';
        const meta = [a.gender, a.yearOfBirth ? 'YOB: ' + a.yearOfBirth : '', a.status].filter(Boolean).join(' · ');
        html += `
            <div class="card mb-2 p-3 border shadow-sm" style="border-radius:10px;background:#f9fafb;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold" style="font-size:.9rem;"><i class="fas fa-user-circle text-primary me-1"></i>${escapeHtmlAdm(name)}</div>
                        <div class="mt-1" style="font-family:monospace;font-weight:700;color:#00875a;font-size:1.05rem;">
                            ${escapeHtmlAdm(num)}
                            <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-muted" title="Copy ABHA Number" onclick="copyAdmText('${escapeHtmlAdm(num)}', this)">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                        ${addr !== '—' ? `<div style="font-size:.8rem;color:#0C74C5;font-family:monospace;">${escapeHtmlAdm(addr)}</div>` : ''}
                        <div style="font-size:.72rem;color:#6b7280;margin-top:2px;">${escapeHtmlAdm(meta)}</div>
                    </div>
                </div>
            </div>
        `;
    });
    c.innerHTML = html;
}

function copyAdmText(text, btn) {
    if (!navigator.clipboard) {
        const t = document.createElement('textarea');
        t.value = text;
        document.body.appendChild(t);
        t.select();
        document.execCommand('copy');
        document.body.removeChild(t);
    } else {
        navigator.clipboard.writeText(text);
    }
    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => btn.innerHTML = orig, 1800);
    }
}

function escapeHtmlAdm(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
}
</script>
</body>
</html>
