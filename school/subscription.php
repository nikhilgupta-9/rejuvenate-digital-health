<?php
include_once "../config/connect.php";
include_once "auth/auth.php";

// ── Current / most recent subscription ──────────────────────────────────
$current = $conn->prepare("SELECT s.*, p.name AS plan_name, p.tier, p.max_students, p.billing_cycle_days
    FROM school_subscriptions s
    JOIN school_plans p ON p.id = s.plan_id
    WHERE s.school_id = ?
    ORDER BY (s.status='active') DESC, s.created_at DESC
    LIMIT 1");
$current->bind_param('i', $school_id);
$current->execute();
$currentSub = $current->get_result()->fetch_assoc();

$activeSub = null;
if ($currentSub && $currentSub['status'] === 'active') {
    $activeSub = $currentSub;
}
$hasOpenRequest = $currentSub && in_array($currentSub['status'], ['pending_payment', 'pending_approval'], true);

// ── Student usage vs active plan's cap (soft warning only) ──────────────
$studentCount = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn, "SELECT COUNT(*) c FROM school_members WHERE school_id=$school_id AND type='Student'"
))['c'] ?? 0);

// ── Available plans ───────────────────────────────────────────────────
$plans = [];
$pr = $conn->query("SELECT * FROM school_plans WHERE is_active=1 ORDER BY sort_order ASC, price ASC, id ASC");
if ($pr) { while ($row = $pr->fetch_assoc()) $plans[] = $row; }

$statusLabels = [
    'pending_payment'  => ['Awaiting Payment', '#6c757d'],
    'pending_approval' => ['Pending Admin Approval', '#ffc107'],
    'active'           => ['Active', '#198754'],
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
    <title><?= htmlspecialchars($school_name) ?> | Subscription</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/school.css">
    <style>
        .plan-card { background:#fff; border-radius:14px; box-shadow:0 1px 8px rgba(0,0,0,.06); padding:24px; height:100%; display:flex; flex-direction:column; border:2px solid transparent; position:relative; }
        .plan-card.popular { border-color:var(--primary); }
        .plan-card .popular-badge { position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:var(--primary); color:#fff; font-size:.7rem; font-weight:700; padding:4px 14px; border-radius:20px; }
        .plan-card .plan-tier { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--primary); }
        .plan-card .plan-name { font-size:1.3rem; font-weight:800; color:#1f2937; margin:4px 0 2px; }
        .plan-card .plan-tagline { font-size:.8rem; color:#6b7280; margin-bottom:14px; }
        .plan-card .plan-price { font-size:2rem; font-weight:800; color:#1f2937; }
        .plan-card .plan-price small { font-size:.75rem; font-weight:500; color:#9ca3af; }
        .plan-card ul { list-style:none; padding:0; margin:16px 0; flex-grow:1; }
        .plan-card ul li { font-size:.85rem; color:#374151; padding:6px 0; display:flex; align-items:flex-start; gap:8px; }
        .plan-card ul li i { color:#198754; margin-top:3px; }
        .usage-bar-wrap { background:#f3f4f6; border-radius:8px; height:10px; overflow:hidden; margin-top:6px; }
        .usage-bar-fill { height:100%; border-radius:8px; background:var(--primary); transition:.3s; }
        .usage-bar-fill.over { background:#ef4444; }
    </style>
</head>
<body>
<?php $active_page = 'subscription'; $base_path = ''; include 'inc/sidebar-school.php'; ?>

<div class="school-topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="sidebar-toggler" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div style="font-size:1rem;font-weight:600;color:#1f2937;">
            <i class="fas fa-cubes me-2 text-primary"></i>Subscription
        </div>
    </div>
    <a href="referrals.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-share-alt me-1"></i><span class="d-none d-sm-inline">Refer a School</span></a>
</div>

<main class="school-content">

    <div id="alertBox"></div>

    <!-- Current status -->
    <div class="form-section mb-4">
        <div class="form-section-title"><i class="fas fa-info-circle me-2 text-primary"></i>Current Status</div>
        <?php if ($activeSub): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-bold" style="font-size:1.05rem;"><?= htmlspecialchars($activeSub['plan_name']) ?> <span class="badge" style="background:#19875420;color:#198754;">Active</span></div>
                    <div class="text-muted small">Valid until <?= $activeSub['expires_at'] ? date('d M Y', strtotime($activeSub['expires_at'])) : 'N/A' ?></div>
                </div>
                <div style="min-width:220px;">
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Students</span>
                        <span><?= $studentCount ?> / <?= $activeSub['max_students'] === null ? 'Unlimited' : (int) $activeSub['max_students'] ?></span>
                    </div>
                    <?php if ($activeSub['max_students'] !== null):
                        $pct = min(100, round(($studentCount / max(1, (int) $activeSub['max_students'])) * 100));
                        $over = $studentCount > (int) $activeSub['max_students'];
                    ?>
                    <div class="usage-bar-wrap"><div class="usage-bar-fill <?= $over ? 'over' : '' ?>" style="width:<?= $pct ?>%;"></div></div>
                    <?php if ($over): ?><div class="small text-danger mt-1"><i class="fas fa-exclamation-triangle me-1"></i>Over your plan's student count — consider upgrading. Adding members is not blocked.</div><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($hasOpenRequest): ?>
            <?php [$label, $color] = $statusLabels[$currentSub['status']]; ?>
            <div class="d-flex align-items-center gap-2">
                <span class="badge" style="background:<?= $color ?>20;color:<?= $color ?>;font-size:.8rem;"><?= $label ?></span>
                <span><?= htmlspecialchars($currentSub['plan_name']) ?> &mdash; &#8377;<?= number_format((float) $currentSub['amount']) ?></span>
            </div>
            <div class="text-muted small mt-1">
                <?= $currentSub['status'] === 'pending_payment'
                    ? 'Complete the payment below to submit this request for approval.'
                    : 'Your request has been submitted and is waiting for admin approval. You\'ll be notified by email.' ?>
            </div>
        <?php else: ?>
            <div class="text-muted">You don't have an active subscription yet. Choose a plan below to get started.</div>
        <?php endif; ?>
    </div>

    <!-- Plans -->
    <div class="form-section-title mb-3"><i class="fas fa-layer-group me-2 text-primary"></i>Available Plans</div>
    <div class="row g-4">
        <?php foreach ($plans as $p): $features = array_filter(array_map('trim', explode("\n", (string) $p['features']))); ?>
        <div class="col-md-4">
            <div class="plan-card <?= $p['is_popular'] ? 'popular' : '' ?>">
                <?php if ($p['is_popular']): ?><span class="popular-badge">Most Popular</span><?php endif; ?>
                <div class="plan-tier"><?= htmlspecialchars($p['tier'] ?: $p['name']) ?></div>
                <div class="plan-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="plan-tagline"><?= htmlspecialchars($p['tagline'] ?? '') ?></div>
                <div class="plan-price">&#8377;<?= number_format((float) $p['price']) ?> <small>/ <?= (int) $p['billing_cycle_days'] ?> days</small></div>
                <div class="small text-muted mt-1"><i class="fas fa-user-graduate me-1"></i><?= $p['max_students'] === null ? 'Unlimited students' : number_format((int) $p['max_students']) . ' students' ?></div>
                <ul>
                    <?php foreach ($features as $f): ?><li><i class="fas fa-check-circle"></i><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn-primary w-100 subscribe-btn"
                    data-plan-id="<?= (int) $p['id'] ?>" data-plan-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                    <?= $hasOpenRequest || ($activeSub && (int) $activeSub['plan_id'] === (int) $p['id']) ? 'disabled' : '' ?>>
                    <?php if ($activeSub && (int) $activeSub['plan_id'] === (int) $p['id']): ?>
                        <i class="fas fa-check me-1"></i>Current Plan
                    <?php elseif ($hasOpenRequest): ?>
                        Request Pending
                    <?php elseif ((float) $p['price'] <= 0): ?>
                        Request Free Plan
                    <?php else: ?>
                        Subscribe
                    <?php endif; ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showAlert(type, msg) {
    document.getElementById('alertBox').innerHTML =
        '<div class="alert alert-' + type + ' alert-dismissible fade show shadow-sm">' + msg +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('.subscribe-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (this.disabled) return;
        const planId = this.dataset.planId;
        const planName = this.dataset.planName;
        if (!confirm('Request the "' + planName + '" plan? Your request will need admin approval before it becomes active.')) return;

        this.disabled = true;
        const original = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Please wait…';

        const fd = new FormData();
        fd.append('plan_id', planId);
        fetch('create-subscription-order.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showAlert('danger', data.message || 'Could not start this request.');
                    this.disabled = false; this.innerHTML = original;
                    return;
                }
                if (data.free) {
                    showAlert('success', 'Your free plan request has been submitted and is awaiting admin approval.');
                    setTimeout(() => location.reload(), 1500);
                    return;
                }
                openRazorpay(data, this, original);
            })
            .catch(() => {
                showAlert('danger', 'Network error. Please try again.');
                this.disabled = false; this.innerHTML = original;
            });
    });
});

function openRazorpay(data, btn, originalHtml) {
    const opts = {
        key: data.key_id,
        order_id: data.order_id,
        amount: data.amount,
        currency: data.currency || 'INR',
        name: 'Rejuvenate Digital Health',
        description: (data.plan_name || 'School Subscription') + ' — <?= htmlspecialchars($school_name, ENT_QUOTES) ?>',
        theme: { color: '#0C74C5' },
        handler: function (resp) {
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming…';
            const fd = new FormData();
            fd.append('razorpay_order_id', resp.razorpay_order_id);
            fd.append('razorpay_payment_id', resp.razorpay_payment_id);
            fd.append('razorpay_signature', resp.razorpay_signature);
            fetch('verify-subscription-payment.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(v => {
                    if (v.success) {
                        showAlert('success', 'Payment received! Your request is now awaiting admin approval.');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', v.message || 'Payment could not be confirmed.');
                        btn.disabled = false; btn.innerHTML = originalHtml;
                    }
                })
                .catch(() => {
                    showAlert('danger', 'Network error while confirming payment.');
                    btn.disabled = false; btn.innerHTML = originalHtml;
                });
        },
        modal: {
            ondismiss: function () { btn.disabled = false; btn.innerHTML = originalHtml; }
        }
    };
    try {
        const rz = new Razorpay(opts);
        rz.on('payment.failed', function (r) {
            showAlert('danger', 'Payment failed: ' + (r.error && r.error.description ? r.error.description : 'please try again.'));
            btn.disabled = false; btn.innerHTML = originalHtml;
        });
        rz.open();
    } catch (e) {
        showAlert('danger', 'Could not open the payment window. Please try again.');
        btn.disabled = false; btn.innerHTML = originalHtml;
    }
}
</script>
</body>
</html>
