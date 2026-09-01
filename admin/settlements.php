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
        .filter-buttons  { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .settle-stat { border-radius: 12px; padding: 18px 20px; color: #fff; }
        .settle-stat .num { font-size: 1.5rem; font-weight: 700; }
        .settle-stat .lbl { font-size: .78rem; opacity: .9; }
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
                <div class="row justify-content-center">
                    <div class="col-12">
                        <div class="white_card card_height_100 mb_30 p-4">
                            <div class="white_card_header">
                                <div class="page-header mb-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h2 class="mb-0">Doctor Payment Settlements</h2>
                                    </div>
                                    <p class="text-muted mb-0" style="font-size:.85rem;">
                                        Every completed, paid appointment creates a settlement due T+2 days later. Platform keeps a 10% commission.
                                    </p>
                                </div>
                            </div>

                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($success_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($error_message) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-4">
                                    <div class="settle-stat" style="background:#e07e18;">
                                        <div class="num">₹<?= number_format($totals['pending_total'], 2) ?></div>
                                        <div class="lbl"><?= (int) $totals['pending_count'] ?> Pending Settlement(s)</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="settle-stat" style="background:#198754;">
                                        <div class="num">₹<?= number_format($totals['settled_total'], 2) ?></div>
                                        <div class="lbl">Total Settled</div>
                                    </div>
                                </div>
                            </div>

                            <div class="filter-buttons">
                                <a href="settlements.php?status=pending" class="btn btn-sm <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>">Pending</a>
                                <a href="settlements.php?status=settled" class="btn btn-sm <?= $status_filter === 'settled' ? 'btn-primary' : 'btn-outline-primary' ?>">Settled</a>
                                <a href="settlements.php?status=all" class="btn btn-sm <?= $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
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
                                            <tr><td colspan="9" class="text-center text-muted py-4">No settlements found.</td></tr>
                                        <?php else: foreach ($settlements as $s): ?>
                                            <tr>
                                                <td>
                                                    <a href="doctor-edit.php?id=<?= (int) $s['doctor_id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($s['doctor_name']) ?>
                                                    </a>
                                                    <br><small class="text-muted"><?= htmlspecialchars($s['doctor_uid']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($s['patient_user_name'] ?: $s['patient_name'] ?: '—') ?></td>
                                                <td>₹<?= number_format($s['gross_amount'], 2) ?></td>
                                                <td class="text-muted">₹<?= number_format($s['commission_amount'], 2) ?> (<?= rtrim(rtrim(number_format($s['commission_rate'], 2), '0'), '.') ?>%)</td>
                                                <td class="fw-semibold">₹<?= number_format($s['settlement_amount'], 2) ?></td>
                                                <td class="bank-mini">
                                                    <?php if ($s['account_number']): ?>
                                                        <?= htmlspecialchars($s['account_holder_name']) ?><br>
                                                        <?= htmlspecialchars($s['bank_name']) ?> · <?= htmlspecialchars($s['ifsc_code']) ?><br>
                                                        A/C: <?= htmlspecialchars($s['account_number']) ?>
                                                        <?php if ($s['bank_verified']): ?> <span class="badge bg-success" style="font-size:9px;">Verified</span><?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-danger">No bank details on file</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d M Y', strtotime($s['due_date'])) ?></td>
                                                <td>
                                                    <?php if ($s['status'] === 'settled'): ?>
                                                        <span class="badge bg-success">Settled</span>
                                                        <?php if ($s['settlement_reference']): ?><br><small class="text-muted"><?= htmlspecialchars($s['settlement_reference']) ?></small><?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($s['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-flex" style="gap:4px;">
                                                            <input type="hidden" name="settlement_id" value="<?= (int) $s['id'] ?>">
                                                            <input type="text" name="settlement_reference" class="form-control form-control-sm" placeholder="UTR / ref" style="width:100px;">
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
        </div>

        <?php include "footer.php"; ?>
    </section>
</body>

</html>
