<?php
include_once "../config/connect.php";
include_once "auth/auth.php";

$uidRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT school_uid FROM schools WHERE id=$school_id"));
$school_uid = $uidRow['school_uid'] ?? '';
$referral_link = BASE_URL . 'school-register.php?ref=' . urlencode($school_uid);

// ── Referred schools + their latest subscription status ─────────────────
$referred = [];
$rr = $conn->prepare("SELECT s.id, s.school_name, s.school_uid, s.status AS school_status, s.created_at,
    (SELECT sub.status FROM school_subscriptions sub WHERE sub.school_id = s.id ORDER BY sub.created_at DESC LIMIT 1) AS latest_sub_status
    FROM schools s WHERE s.referred_by = ? ORDER BY s.created_at DESC");
$rr->bind_param('i', $school_id);
$rr->execute();
$res = $rr->get_result();
while ($row = $res->fetch_assoc()) $referred[] = $row;

// ── Earnings summary + ledger ────────────────────────────────────────────
$sumStmt = $conn->prepare("SELECT
    COALESCE(SUM(CASE WHEN payout_status='unpaid' THEN commission_amount ELSE 0 END),0) AS unpaid_total,
    COALESCE(SUM(CASE WHEN payout_status='paid' THEN commission_amount ELSE 0 END),0) AS paid_total,
    COUNT(*) AS total_count
    FROM school_referral_earnings WHERE referring_school_id = ?");
$sumStmt->bind_param('i', $school_id);
$sumStmt->execute();
$summary = $sumStmt->get_result()->fetch_assoc();

$earnings = [];
$er = $conn->prepare("SELECT e.*, ds.school_name AS referred_school_name
    FROM school_referral_earnings e
    JOIN schools ds ON ds.id = e.referred_school_id
    WHERE e.referring_school_id = ?
    ORDER BY e.created_at DESC");
$er->bind_param('i', $school_id);
$er->execute();
$res2 = $er->get_result();
while ($row = $res2->fetch_assoc()) $earnings[] = $row;

$subStatusLabels = [
    'pending_payment'  => ['Awaiting Payment', '#6c757d'],
    'pending_approval' => ['Pending Approval', '#ffc107'],
    'active'           => ['Subscribed', '#198754'],
    'rejected'         => ['Rejected', '#dc3545'],
    'expired'          => ['Expired', '#6c757d'],
];
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($school_name) ?> | Refer a School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/school.css">
</head>
<body>
<?php $active_page = 'referrals'; $base_path = ''; include 'inc/sidebar-school.php'; ?>

<div class="school-topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div style="font-size:1rem;font-weight:600;color:#1f2937;">
            <i class="fas fa-share-alt me-2 text-primary"></i>Refer a School
        </div>
    </div>
    <a href="subscription.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-cubes me-1"></i><span class="d-none d-sm-inline">Subscription</span></a>
</div>

<main class="school-content">

    <div class="row g-4 mb-2">
        <div class="col-lg-7">
            <div class="form-section h-100">
                <div class="form-section-title"><i class="fas fa-link me-2 text-primary"></i>Your Referral Link</div>
                <p class="text-muted small mb-2">Share this link with other schools. When a school you refer subscribes to a
                    <strong>paid</strong> plan and it's approved, you earn <strong>25%</strong> of that first payment.</p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" id="referralLinkInput" value="<?= htmlspecialchars($referral_link) ?>" readonly>
                    <button type="button" class="btn btn-outline-primary" id="btnCopyReferral"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <div class="small text-muted">Commission is credited once per referred school (their first paid subscription only) and paid out manually by admin.</div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="row g-3 h-100">
                <div class="col-6">
                    <div class="stat-card card-orange">
                        <i class="fas fa-hourglass-half bg-icon"></i>
                        <div class="num">&#8377;<?= number_format($summary['unpaid_total'], 2) ?></div>
                        <div class="lbl">Unpaid Earnings</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card card-green">
                        <i class="fas fa-check-double bg-icon"></i>
                        <div class="num">&#8377;<?= number_format($summary['paid_total'], 2) ?></div>
                        <div class="lbl">Paid Out</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Referred schools -->
    <div class="form-section mb-4">
        <div class="form-section-title"><i class="fas fa-school me-2 text-primary"></i>Schools You've Referred</div>
        <?php if (empty($referred)): ?>
            <div class="text-muted small">No schools have signed up through your referral link yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>School</th><th>Registered</th><th>Subscription</th></tr></thead>
                    <tbody>
                        <?php foreach ($referred as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['school_name']) ?><div class="text-muted small"><?= htmlspecialchars($r['school_uid']) ?></div></td>
                            <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <?php if ($r['latest_sub_status'] && isset($subStatusLabels[$r['latest_sub_status']])): [$label, $color] = $subStatusLabels[$r['latest_sub_status']]; ?>
                                    <span class="badge" style="background:<?= $color ?>20;color:<?= $color ?>;"><?= $label ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">No subscription yet</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Earnings ledger -->
    <div class="form-section">
        <div class="form-section-title"><i class="fas fa-hand-holding-usd me-2 text-primary"></i>Earnings History</div>
        <?php if (empty($earnings)): ?>
            <div class="text-muted small">No commission earned yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Referred School</th><th>Subscription</th><th>Commission</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($earnings as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['referred_school_name']) ?></td>
                            <td>&#8377;<?= number_format($e['subscription_amount'], 2) ?></td>
                            <td class="fw-semibold">&#8377;<?= number_format($e['commission_amount'], 2) ?></td>
                            <td><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                            <td>
                                <?php if ($e['payout_status'] === 'paid'): ?>
                                    <span class="badge" style="background:#19875420;color:#198754;">Paid</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#ffc10720;color:#997404;">Unpaid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('btnCopyReferral').addEventListener('click', function () {
    const input = document.getElementById('referralLinkInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('btnCopyReferral');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied';
        setTimeout(() => { btn.innerHTML = original; }, 1800);
    });
});
</script>
</body>
</html>
