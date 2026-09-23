<?php
include "functions.php"; // includes db-conn.php + enforces admin_jwt_guard()
require_once dirname(__DIR__) . '/lib/RazorpayRefund.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* school_health_memberships schema: see database/migration_school_membership_phase2.sql
 * One row per purchase/renewal. commission_status moves held -> payable once
 * commission_release_at has passed (computed here, not by a cron) -> paid_out
 * on manual admin action. Cancel & refund is only offered inside the window. */

$page_message = '';
$page_message_type = '';

/* ── Mark commission paid out ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid_out'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $id  = (int) ($_POST['membership_id'] ?? 0);
        $ref = trim($_POST['payout_ref'] ?? '');
        $s = $conn->prepare("UPDATE school_health_memberships
            SET commission_status='paid_out', payout_ref=?, paid_out_at=NOW()
            WHERE id=? AND commission_status='payable'");
        $s->bind_param('si', $ref, $id);
        $ok = $s->execute() && $s->affected_rows > 0;
        $page_message = $ok ? 'Commission marked as paid out.' : 'Could not mark paid out — it may no longer be payable.';
        $page_message_type = $ok ? 'success' : 'warning';
    }
}

/* ── Cancel & refund (admin-initiated, only inside the refund window) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_refund'])) {
    if (($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
        $page_message = 'Security check failed. Please try again.';
        $page_message_type = 'danger';
    } else {
        $id = (int) ($_POST['membership_id'] ?? 0);
        $s = $conn->prepare("SELECT * FROM school_health_memberships WHERE id=? LIMIT 1");
        $s->bind_param('i', $id);
        $s->execute();
        $m = $s->get_result()->fetch_assoc();

        if (!$m || $m['status'] !== 'active') {
            $page_message = 'Membership not found or not active.';
            $page_message_type = 'warning';
        } elseif (empty($m['refund_eligible_until']) || strtotime($m['refund_eligible_until']) < time()) {
            $page_message = 'The refund window for this membership has closed.';
            $page_message_type = 'warning';
        } elseif (empty($m['razorpay_payment_id']) || (float) $m['amount_paid'] <= 0) {
            $page_message = 'This membership has no recorded payment to refund.';
            $page_message_type = 'warning';
        } else {
            $amountPaise = (int) round((float) $m['amount_paid'] * 100);
            $refund = razorpay_refund_payment($m['razorpay_payment_id'], $amountPaise, ['membership_id' => $id]);
            if (!$refund['success']) {
                $page_message = $refund['message'] ?? 'Refund failed.';
                $page_message_type = 'danger';
            } else {
                // commission_status is left untouched: a refund is only reachable while the
                // refund window is still open, i.e. commission was still 'held' anyway.
                $u = $conn->prepare("UPDATE school_health_memberships SET
                    status='refunded', razorpay_refund_id=?, refunded_at=NOW(), refund_amount=?
                    WHERE id=?");
                $u->bind_param('sdi', $refund['refund_id'], $m['amount_paid'], $id);
                $u->execute();
                $page_message = 'Membership cancelled and refunded via Razorpay.';
                $page_message_type = 'success';
            }
        }
    }
}

/* ── Filters ── */
$school_filter = (int) ($_GET['school_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';

$where = [];
$params = []; $types = '';
if ($school_filter) { $where[] = 'shm.school_id = ?'; $params[] = $school_filter; $types .= 'i'; }
if ($status_filter && in_array($status_filter, ['pending_payment','active','expired','cancelled','refunded'], true)) {
    $where[] = 'shm.status = ?'; $params[] = $status_filter; $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT shm.*, sm.name AS student_name, sm.member_uid, s.school_name
        FROM school_health_memberships shm
        JOIN school_members sm ON sm.id = shm.member_id
        JOIN schools s ON s.id = shm.school_id
        $whereSql
        ORDER BY shm.created_at DESC
        LIMIT 300";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$memberships = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$schools = [];
$res = $conn->query("SELECT id, school_name FROM schools WHERE status='Active' ORDER BY school_name ASC");
if ($res) while ($r = $res->fetch_assoc()) $schools[] = $r;

/* Commission held -> payable is a display-time computation, not a stored transition. */
$now = time();
foreach ($memberships as &$m) {
    if ($m['commission_status'] === 'held' && !empty($m['commission_release_at']) && strtotime($m['commission_release_at']) <= $now) {
        $m['commission_status_display'] = 'payable';
    } else {
        $m['commission_status_display'] = $m['commission_status'];
    }
}
unset($m);

$pill = [
    'pending_payment' => 'pill-muted', 'active' => 'pill-success', 'expired' => 'pill-muted',
    'cancelled' => 'pill-danger', 'refunded' => 'pill-danger',
];
$cpill = ['held' => 'pill-warn', 'payable' => 'pill-info', 'paid_out' => 'pill-success'];
?>
<!DOCTYPE html>
<html lang="zxx">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>School Memberships | Admin Panel</title>
    <?php include "links.php"; ?>
</head>
<body class="crm_body_bg">
<?php include "header.php"; ?>

<section class="main_content dashboard_part large_header_bg">
    <div class="container-fluid g-0"><div class="row"><div class="col-lg-12 p-0"><?php include "top_nav.php"; ?></div></div></div>

    <div class="main_content_iner">
        <div class="container-fluid p-0 sm_padding_15px">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="white_card card_height_100 mb_30">
                        <div class="card-header bg-white border-0 py-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h3 class="mb-0 fw-bold">School Memberships</h3>
                                    <p class="text-muted mb-0 small">Paid 12-month student memberships and the school commission on each.</p>
                                </div>
                                <form method="GET" class="d-flex gap-2">
                                    <select name="school_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All schools</option>
                                        <?php foreach ($schools as $s): ?>
                                            <option value="<?= (int) $s['id'] ?>" <?= $school_filter === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['school_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All statuses</option>
                                        <?php foreach (['pending_payment','active','expired','cancelled','refunded'] as $st): ?>
                                            <option value="<?= $st ?>" <?= $status_filter === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        </div>
                        <div class="white_card_body">

                            <?php if ($page_message): ?>
                                <div class="alert alert-<?= htmlspecialchars($page_message_type) ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($page_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="QA_section"><div class="QA_table mb_30"><div class="table-responsive">
                                <table class="table table-hover tbl-admin tbl-cards">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>School</th>
                                            <th>Plan</th>
                                            <th>Paid</th>
                                            <th>Valid till</th>
                                            <th>Status</th>
                                            <th>Commission</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($memberships)): ?>
                                            <tr class="empty-row"><td colspan="8">
                                                <i class="fas fa-id-card fa-3x mb-3 d-block opacity-25"></i>
                                                No memberships match these filters.
                                            </td></tr>
                                        <?php else: foreach ($memberships as $m): ?>
                                            <tr>
                                                <td data-label="Student"><?= htmlspecialchars($m['student_name']) ?><div class="cell-sub"><?= htmlspecialchars($m['member_uid'] ?? '') ?></div></td>
                                                <td data-label="School"><?= htmlspecialchars($m['school_name']) ?></td>
                                                <td data-label="Plan"><?= htmlspecialchars($m['plan_name'] ?? '—') ?></td>
                                                <td data-label="Paid">&#8377;<?= number_format((float) ($m['amount_paid'] ?? 0)) ?><?php if ($m['paid_at']): ?><div class="cell-sub"><?= date('d M Y', strtotime($m['paid_at'])) ?></div><?php endif; ?></td>
                                                <td data-label="Valid till"><?= $m['end_date'] ? date('d M Y', strtotime($m['end_date'])) : '—' ?></td>
                                                <td data-label="Status"><span class="pill <?= $pill[$m['status']] ?? 'pill-muted' ?>"><?= ucfirst(str_replace('_',' ',$m['status'])) ?></span></td>
                                                <td data-label="Commission">
                                                    <span class="pill <?= $cpill[$m['commission_status_display']] ?? 'pill-muted' ?>"><?= ucfirst($m['commission_status_display']) ?></span>
                                                    <div class="cell-sub">&#8377;<?= number_format((float) ($m['commission_amount'] ?? 0)) ?> (<?= rtrim(rtrim((string)$m['commission_percent'],'0'),'.') ?>%)</div>
                                                </td>
                                                <td data-label="Action" class="text-end">
                                                    <?php if ($m['commission_status_display'] === 'payable'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success payout-btn"
                                                            data-id="<?= (int) $m['id'] ?>" data-student="<?= htmlspecialchars($m['student_name'], ENT_QUOTES) ?>">Mark Paid Out</button>
                                                    <?php endif; ?>
                                                    <?php if ($m['status'] === 'active' && !empty($m['refund_eligible_until']) && strtotime($m['refund_eligible_until']) >= time()): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this membership and refund via Razorpay?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                            <input type="hidden" name="membership_id" value="<?= (int) $m['id'] ?>">
                                                            <button type="submit" name="cancel_refund" value="1" class="btn btn-sm btn-outline-danger">Cancel &amp; Refund</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div></div></div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include "footer.php"; ?>
</section>

<div class="modal fade" id="payoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="mark_paid_out" value="1">
                <input type="hidden" name="membership_id" id="payout_membership_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Commission Paid Out</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Student: <strong id="payout_student_name"></strong></p>
                    <label class="form-label">Payout reference <span class="text-muted small">(bank transfer ID, cheque no., etc.)</span></label>
                    <input type="text" name="payout_ref" class="form-control" placeholder="e.g. NEFT/2026...">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Paid Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.payout-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('payout_membership_id').value = this.dataset.id;
        document.getElementById('payout_student_name').textContent = this.dataset.student;
        new bootstrap.Modal(document.getElementById('payoutModal')).show();
    });
});
</script>
</body>
</html>
