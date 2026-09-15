<?php
require_once __DIR__ . '/db-conn.php';
require_once __DIR__ . '/auth/guard.php';
admin_jwt_guard();

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = $error_message = '';

// Mark an earning as paid (bank transfer / UPI done offline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $error_message = 'Security check failed. Please try again.';
    } else {
        $earning_id = (int) ($_POST['earning_id'] ?? 0);
        $reference  = trim($_POST['payout_reference'] ?? '');
        $admin_id   = $_SESSION['admin_id'] ?? 1;

        $stmt = $conn->prepare("UPDATE school_referral_earnings
            SET payout_status='paid', paid_at=NOW(), paid_by=?, payout_reference=?
            WHERE id=? AND payout_status='unpaid'");
        $stmt->bind_param('isi', $admin_id, $reference, $earning_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_message = "Earning #$earning_id marked as paid.";
        } else {
            $error_message = "Could not mark that earning (already paid, or not found).";
        }
    }
}

$status_filter = $_GET['status'] ?? 'unpaid';
$where = '';
if ($status_filter === 'unpaid') {
    $where = "WHERE e.payout_status = 'unpaid'";
} elseif ($status_filter === 'paid') {
    $where = "WHERE e.payout_status = 'paid'";
}

$totals = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN payout_status='unpaid' THEN commission_amount ELSE 0 END),0) AS unpaid_total,
        COALESCE(SUM(CASE WHEN payout_status='paid' THEN commission_amount ELSE 0 END),0) AS paid_total,
        COALESCE(SUM(CASE WHEN payout_status='unpaid' THEN 1 ELSE 0 END),0) AS unpaid_count
    FROM school_referral_earnings
")->fetch_assoc();

$sql = "
    SELECT e.*,
           rs.school_name AS referring_school_name, rs.school_uid AS referring_school_uid,
           ds.school_name AS referred_school_name, ds.school_uid AS referred_school_uid
    FROM school_referral_earnings e
    JOIN schools rs ON rs.id = e.referring_school_id
    JOIN schools ds ON ds.id = e.referred_school_id
    $where
    ORDER BY e.created_at DESC
";
$result = $conn->query($sql);
$earnings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">

<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>School Referral Earnings | Admin Panel</title>
    <?php include "links.php"; ?>
</head>

<body class="crm_body_bg">
    <?php include "header.php"; ?>

    <section class="main_content dashboard_part">
        <div class="container-fluid g-0">
            <div class="row">
                <div class="col-lg-12 p-0">
                    <?php include "top_nav.php"; ?>
                </div>
            </div>
        </div>

        <div class="main_content_iner">
            <div class="container-fluid p-0 sm_padding_15px">
                <div class="list-page-head">
                    <div class="page-heading">
                        <h4 class="mb-0 fw-bold">School Referral Earnings</h4>
                        <small class="text-muted">25% commission on a referred school's first approved paid subscription &bull; <a href="school-subscriptions.php">back to subscriptions</a></small>
                    </div>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-hourglass-half big-icon"></i><div class="num">&#8377;<?= number_format($totals['unpaid_total'], 2) ?></div><div class="lbl"><?= (int) $totals['unpaid_count'] ?> Unpaid Commission(s)</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-double big-icon"></i><div class="num">&#8377;<?= number_format($totals['paid_total'], 2) ?></div><div class="lbl">Total Paid Out</div></div></div>
                </div>

                <div class="filter-card">
                    <div class="filter-buttons" style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="school-referrals.php?status=unpaid" class="filter-btn <?= $status_filter === 'unpaid' ? 'active' : '' ?>">Unpaid</a>
                        <a href="school-referrals.php?status=paid" class="filter-btn <?= $status_filter === 'paid' ? 'active' : '' ?>">Paid</a>
                        <a href="school-referrals.php?status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
                    </div>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title"><h3 class="m-0">Referral Earnings <span class="badge bg-secondary ms-2"><?= count($earnings) ?></span></h3></div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards align-middle">
                                <thead>
                                    <tr>
                                        <th>Referring School</th>
                                        <th>Referred School</th>
                                        <th>Subscription Amount</th>
                                        <th>Commission (25%)</th>
                                        <th>Earned</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($earnings)): ?>
                                        <tr class="empty-row"><td colspan="7"><i class="fas fa-hand-holding-usd fa-3x mb-3 d-block opacity-25"></i>No referral earnings found.</td></tr>
                                    <?php else: foreach ($earnings as $e): ?>
                                        <tr>
                                            <td data-label="Referring School">
                                                <div class="cell-title"><?= htmlspecialchars($e['referring_school_name']) ?></div>
                                                <div class="cell-sub"><?= htmlspecialchars($e['referring_school_uid']) ?></div>
                                            </td>
                                            <td data-label="Referred School">
                                                <div class="cell-title"><?= htmlspecialchars($e['referred_school_name']) ?></div>
                                                <div class="cell-sub"><?= htmlspecialchars($e['referred_school_uid']) ?></div>
                                            </td>
                                            <td data-label="Subscription Amount">&#8377;<?= number_format($e['subscription_amount'], 2) ?></td>
                                            <td data-label="Commission" class="fw-semibold">&#8377;<?= number_format($e['commission_amount'], 2) ?></td>
                                            <td data-label="Earned"><span class="cell-sub"><?= date('d M Y', strtotime($e['created_at'])) ?></span></td>
                                            <td data-label="Status">
                                                <?php if ($e['payout_status'] === 'paid'): ?>
                                                    <span class="pill pill-success">Paid</span>
                                                    <?php if ($e['payout_reference']): ?><div class="cell-sub mt-1"><?= htmlspecialchars($e['payout_reference']) ?></div><?php endif; ?>
                                                <?php else: ?>
                                                    <span class="pill pill-warn">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Action">
                                                <?php if ($e['payout_status'] === 'unpaid'): ?>
                                                    <form method="POST" class="d-flex" style="gap:4px;max-width:180px;margin-left:auto;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="earning_id" value="<?= (int) $e['id'] ?>">
                                                        <input type="text" name="payout_reference" class="form-control form-control-sm" placeholder="UTR / ref">
                                                        <button type="submit" name="mark_paid" value="1" class="btn btn-sm btn-success"
                                                                onclick="return confirm('Mark this commission as paid out?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include "footer.php"; ?>
    </section>
</body>
</html>
