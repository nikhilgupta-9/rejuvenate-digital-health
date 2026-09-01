<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once "db-conn.php";
include_once "functions.php";

$success_message = $error_message = '';

// Mark a settlement as settled (bank transfer done offline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_settled'])) {
    $settlement_id = intval($_POST['settlement_id']);
    $reference     = trim($_POST['settlement_reference'] ?? '');
    $admin_id      = $_SESSION['admin_id'] ?? null;

    $stmt = $conn->prepare("UPDATE appointment_settlements
        SET status = 'settled', settled_at = NOW(), settled_by = ?, settlement_reference = ?
        WHERE id = ? AND status = 'pending'");
    $stmt->bind_param('isi', $admin_id, $reference, $settlement_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success_message = "Settlement #$settlement_id marked as settled.";
    } else {
        $error_message = "Could not mark that settlement (already settled, or not found).";
    }
}

$status_filter = $_GET['status'] ?? 'pending';
$where = '';
if ($status_filter === 'pending') {
    $where = "WHERE s.status = 'pending'";
} elseif ($status_filter === 'settled') {
    $where = "WHERE s.status = 'settled'";
}

$sql = "
    SELECT s.*, d.name AS doctor_name, d.doctor_uid,
           a.appointment_date, a.patient_name, u.name AS patient_user_name,
           ba.account_holder_name, ba.account_number, ba.ifsc_code, ba.bank_name, ba.upi_id, ba.is_verified AS bank_verified
    FROM appointment_settlements s
    JOIN doctors d ON d.id = s.doctor_id
    JOIN appointments a ON a.id = s.appointment_id
    LEFT JOIN users u ON u.id = a.user_id
    LEFT JOIN doctor_bank_accounts ba ON ba.doctor_id = s.doctor_id
    $where
    ORDER BY s.due_date ASC, s.created_at ASC
";
$result = $conn->query($sql);
$settlements = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$totals = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN status='pending' THEN settlement_amount ELSE 0 END),0) AS pending_total,
        COALESCE(SUM(CASE WHEN status='settled' THEN settlement_amount ELSE 0 END),0) AS settled_total,
        COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END),0) AS pending_count
    FROM appointment_settlements
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Doctor Payment Settlements | Admin Panel</title>
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <?php include "links.php"; ?>
    <style>
        .filter-buttons { display:flex; gap:8px; flex-wrap:wrap; }
        .bank-mini { font-size: .74rem; color: #6b7280; line-height: 1.5; }
    </style>
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
                        <h4 class="mb-0 fw-bold">Doctor Payment Settlements</h4>
                        <small class="text-muted">Every completed, paid appointment creates a settlement due T+2 days later. Platform keeps a 10% commission.</small>
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
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-warn"><i class="fas fa-hourglass-half big-icon"></i><div class="num">₹<?= number_format($totals['pending_total'], 2) ?></div><div class="lbl"><?= (int) $totals['pending_count'] ?> Pending Settlement(s)</div></div></div>
                    <div class="col-6 col-lg-3"><div class="stat-box bg-stat-green"><i class="fas fa-check-double big-icon"></i><div class="num">₹<?= number_format($totals['settled_total'], 2) ?></div><div class="lbl">Total Settled</div></div></div>
                </div>

                <div class="filter-card">
                    <div class="filter-buttons">
                        <a href="settlements.php?status=pending" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">Pending</a>
                        <a href="settlements.php?status=settled" class="filter-btn <?= $status_filter === 'settled' ? 'active' : '' ?>">Settled</a>
                        <a href="settlements.php?status=all" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
                    </div>
                </div>

                <div class="white_card card_height_100 mb_30">
                    <div class="white_card_header">
                        <div class="box_header d-flex justify-content-between align-items-center">
                            <div class="main-title"><h3 class="m-0">Settlements <span class="badge bg-secondary ms-2"><?= count($settlements) ?></span></h3></div>
                        </div>
                    </div>
                    <div class="white_card_body">
                        <div class="table-responsive">
                            <table class="table table-hover tbl-admin tbl-cards align-middle">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Patient</th>
                                        <th>Gross</th>
                                        <th>Commission</th>
                                        <th>Net Payout</th>
                                        <th>Bank / UPI</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($settlements)): ?>
                                        <tr class="empty-row"><td colspan="9"><i class="fas fa-wallet fa-3x mb-3 d-block opacity-25"></i>No settlements found.</td></tr>
                                    <?php else: foreach ($settlements as $s): ?>
                                        <tr>
                                            <td data-label="Doctor">
                                                <a href="doctor-edit.php?id=<?= (int) $s['doctor_id'] ?>" class="text-decoration-none">
                                                    <div class="cell-title"><?= htmlspecialchars($s['doctor_name']) ?></div>
                                                </a>
                                                <div class="cell-sub"><?= htmlspecialchars($s['doctor_uid']) ?></div>
                                            </td>
                                            <td data-label="Patient"><?= htmlspecialchars($s['patient_user_name'] ?: $s['patient_name'] ?: '—') ?></td>
                                            <td data-label="Gross">₹<?= number_format($s['gross_amount'], 2) ?></td>
                                            <td data-label="Commission" class="text-muted">₹<?= number_format($s['commission_amount'], 2) ?> (<?= rtrim(rtrim(number_format($s['commission_rate'], 2), '0'), '.') ?>%)</td>
                                            <td data-label="Net Payout" class="fw-semibold">₹<?= number_format($s['settlement_amount'], 2) ?></td>
                                            <td data-label="Bank / UPI" class="bank-mini">
                                                <?php if ($s['account_number']): ?>
                                                    <?= htmlspecialchars($s['account_holder_name']) ?><br>
                                                    <?= htmlspecialchars($s['bank_name']) ?> · <?= htmlspecialchars($s['ifsc_code']) ?><br>
                                                    A/C: <?= htmlspecialchars($s['account_number']) ?>
                                                    <?php if ($s['bank_verified']): ?> <span class="pill pill-success">Verified</span><?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-danger">No bank details on file</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Due"><span class="cell-sub"><?= date('d M Y', strtotime($s['due_date'])) ?></span></td>
                                            <td data-label="Status">
                                                <?php if ($s['status'] === 'settled'): ?>
                                                    <span class="pill pill-success">Settled</span>
                                                    <?php if ($s['settlement_reference']): ?><div class="cell-sub mt-1"><?= htmlspecialchars($s['settlement_reference']) ?></div><?php endif; ?>
                                                <?php else: ?>
                                                    <span class="pill pill-warn">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Action">
                                                <?php if ($s['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-flex" style="gap:4px;max-width:180px;margin-left:auto;">
                                                        <input type="hidden" name="settlement_id" value="<?= (int) $s['id'] ?>">
                                                        <input type="text" name="settlement_reference" class="form-control form-control-sm" placeholder="UTR / ref">
                                                        <button type="submit" name="mark_settled" value="1" class="btn btn-sm btn-success"
                                                                onclick="return confirm('Mark this settlement as paid?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    —
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
</body>

</html>
