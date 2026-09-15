<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

$total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_subscriptions"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_subscriptions WHERE status='pending_approval'"))['c'];
$active  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_subscriptions WHERE status='active'"))['c'];
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM school_subscriptions WHERE status='rejected'"))['c'];

$filter = $_GET['status'] ?? 'pending_approval';
$search = trim($_GET['q'] ?? '');
$where  = ($filter !== 'all') ? "WHERE s.status = '" . mysqli_real_escape_string($conn, $filter) . "'" : 'WHERE 1=1';
if ($search !== '') {
    $q = mysqli_real_escape_string($conn, $search);
    $where .= " AND (sc.school_name LIKE '%$q%' OR sc.email LIKE '%$q%')";
}

$subs_result = mysqli_query($conn, "SELECT s.*, sc.school_name, sc.school_uid, sc.email AS school_email,
    p.name AS plan_name, p.max_students,
    (SELECT COUNT(*) FROM school_members sm WHERE sm.school_id=sc.id AND sm.type='Student') AS student_count,
    ref.school_name AS referring_school_name
    FROM school_subscriptions s
    JOIN schools sc ON sc.id = s.school_id
    JOIN school_plans p ON p.id = s.plan_id
    LEFT JOIN schools ref ON ref.id = sc.referred_by
    $where ORDER BY s.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin | School Subscriptions</title>
    <?php include "links.php"; ?>
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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">School Subscriptions</h4>
                        <small class="text-muted">Approve or reject school subscription requests &bull; <a href="school-subscription-plans.php">manage plans</a> &bull; <a href="school-referrals.php">referral earnings</a></small>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($pending > 0): ?>
                        <a href="school-subscriptions.php?status=pending_approval" class="btn btn-danger btn-sm">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= $pending ?> Pending
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-blue">
                            <i class="fas fa-layer-group big-icon"></i>
                            <div class="num"><?= $total ?></div>
                            <div class="lbl">Total Requests</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-warn">
                            <i class="fas fa-clock big-icon"></i>
                            <div class="num"><?= $pending ?></div>
                            <div class="lbl">Pending Approval</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-green">
                            <i class="fas fa-check-circle big-icon"></i>
                            <div class="num"><?= $active ?></div>
                            <div class="lbl">Active</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-stat-red">
                            <i class="fas fa-times-circle big-icon"></i>
                            <div class="num"><?= $rejected ?></div>
                            <div class="lbl">Rejected</div>
                        </div>
                    </div>
                </div>

                <div class="filter-card">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Search School</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold mb-1" style="font-size:.8rem;">Status</label>
                            <select class="form-control form-control-sm" name="status">
                                <?php foreach (['all' => 'All', 'pending_payment' => 'Pending Payment', 'pending_approval' => 'Pending Approval', 'active' => 'Active', 'rejected' => 'Rejected', 'expired' => 'Expired'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $filter === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>Filter</button>
                        </div>
                    </form>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title">
                                <h3 class="m-0">Subscription Requests <span class="badge bg-secondary ms-2"><?= mysqli_num_rows($subs_result) ?></span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr style="background:#eaf4fd;border:1px solid #b3d4f0;">
                                        <th style="font-size:.75rem;text-transform:uppercase;">#</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">School</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Plan</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Amount</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Student usage</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Referred by</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Status</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Requested</th>
                                        <th style="font-size:.75rem;text-transform:uppercase;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (mysqli_num_rows($subs_result) === 0): ?>
                                    <tr><td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-layer-group fa-3x mb-3 d-block opacity-25"></i>No subscription requests found.
                                    </td></tr>
                                <?php endif; ?>
                                <?php $i = 1; while ($s = mysqli_fetch_assoc($subs_result)): ?>
                                <tr>
                                    <td><small class="text-muted"><?= $i++ ?></small></td>
                                    <td>
                                        <div class="fw-semibold" style="font-size:.88rem;"><?= htmlspecialchars($s['school_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($s['school_uid']) ?> &bull; <?= htmlspecialchars($s['school_email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($s['plan_name']) ?></td>
                                    <td class="fw-semibold">&#8377;<?= number_format((float) $s['amount']) ?></td>
                                    <td>
                                        <?= (int) $s['student_count'] ?> / <?= $s['max_students'] === null ? '&infin;' : (int) $s['max_students'] ?>
                                        <?php if ($s['max_students'] !== null && (int) $s['student_count'] > (int) $s['max_students']): ?>
                                            <span class="badge bg-warning text-dark ms-1" title="Over plan's student count">Over limit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= $s['referring_school_name'] ? htmlspecialchars($s['referring_school_name']) : '&mdash;' ?></small></td>
                                    <td>
                                        <?php $colors = ['pending_payment' => '#6c757d', 'pending_approval' => '#ffc107', 'active' => '#2dc653', 'rejected' => '#ef233c', 'expired' => '#6c757d'];
                                        $labels = ['pending_payment' => 'Pending Payment', 'pending_approval' => 'Pending Approval', 'active' => 'Active', 'rejected' => 'Rejected', 'expired' => 'Expired'];
                                        $c = $colors[$s['status']] ?? '#6c757d'; ?>
                                        <span style="background:<?= $c ?>20; color:<?= $c ?>; border:1px solid <?= $c ?>40; border-radius:20px; padding:3px 10px; font-size:.72rem; font-weight:600;"><?= $labels[$s['status']] ?? $s['status'] ?></span>
                                    </td>
                                    <td><small class="text-muted"><?= date('d M Y', strtotime($s['created_at'])) ?></small></td>
                                    <td>
                                        <?php if ($s['status'] === 'pending_approval'): ?>
                                            <a href="school-subscription-approve.php?id=<?= $s['id'] ?>&action=approve" class="tbl-action-btn bg-success text-white" title="Approve" onclick="return confirm('Approve this subscription?')"><i class="fas fa-check"></i></a>
                                            <a href="school-subscription-approve.php?id=<?= $s['id'] ?>&action=reject" class="tbl-action-btn bg-danger text-white" title="Reject"><i class="fas fa-times"></i></a>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
</body>
</html>
