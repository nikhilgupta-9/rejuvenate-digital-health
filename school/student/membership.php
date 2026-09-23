<?php
/**
 * Student self-service: buy the 12-month school-health membership directly
 * (parent/student pays the platform — school never handles the payment,
 * see database/migration_school_membership_phase2.sql). Parent consent for
 * an actual checkup is a separate, later gate (doctor/inc/consent-helper.php)
 * — buying a membership doesn't itself authorize any clinical service.
 */
include_once "../../config/connect.php";
include_once "auth.php";
require_once __DIR__ . '/../../config/payment.php';
require_once __DIR__ . '/../../lib/SchoolPlanEligibility.php';
require_once __DIR__ . '/../../lib/RazorpayOrder.php';
require_once __DIR__ . '/../../lib/SchoolMembership.php';

$action = $_POST['action'] ?? '';

$stmt = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON sm.school_id=s.id WHERE sm.id=?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

/* ── AJAX: create order ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_order') {
    header('Content-Type: application/json');
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $ps = $conn->prepare("SELECT * FROM school_health_plans WHERE id=? AND is_active=1 LIMIT 1");
    $ps->bind_param('i', $planId);
    $ps->execute();
    $plan = $ps->get_result()->fetch_assoc();
    if (!$plan || !school_plan_eligible_for_member($plan, $student)) {
        echo json_encode(['success' => false, 'message' => 'This plan is not available for your age/class.']);
        exit;
    }
    if (school_active_membership($conn, $student_id)) {
        echo json_encode(['success' => false, 'message' => 'You already have an active membership.']);
        exit;
    }
    $price = (float) $plan['price'];
    if ($price <= 0) {
        // Free plan — activate immediately, no payment needed.
        $membershipId = school_create_membership($conn, $student_id, $student_school_id, $planId, $plan['name'], 0.0, null);
        echo json_encode(['success' => true, 'free' => true, 'membership_id' => $membershipId]);
        exit;
    }
    $order = razorpay_create_order(
        (int) round($price * 100),
        'shm_' . $student_id . '_' . time(),
        ['member_id' => $student_id, 'plan_id' => $planId, 'purpose' => 'school_membership']
    );
    if (!$order['success']) {
        echo json_encode(['success' => false, 'message' => $order['message']]);
        exit;
    }
    echo json_encode([
        'success'  => true,
        'key_id'   => RAZORPAY_KEY_ID,
        'order_id' => $order['order_id'],
        'amount'   => $order['amount'],
        'plan_id'  => $planId,
    ]);
    exit;
}

/* ── AJAX: verify payment ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'verify_payment') {
    header('Content-Type: application/json');
    $planId      = (int) ($_POST['plan_id'] ?? 0);
    $rpOrderId   = trim($_POST['razorpay_order_id'] ?? '');
    $rpPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
    $rpSignature = trim($_POST['razorpay_signature'] ?? '');

    if (!$rpOrderId || !$rpPaymentId || !$rpSignature || !razorpay_verify_signature($rpOrderId, $rpPaymentId, $rpSignature)) {
        echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted it will be refunded automatically.']);
        exit;
    }
    $ps = $conn->prepare("SELECT * FROM school_health_plans WHERE id=? LIMIT 1");
    $ps->bind_param('i', $planId);
    $ps->execute();
    $plan = $ps->get_result()->fetch_assoc();
    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'Plan not found.']);
        exit;
    }
    $membershipId = school_create_membership($conn, $student_id, $student_school_id, $planId, $plan['name'], (float) $plan['price'], $rpPaymentId);
    echo json_encode(['success' => (bool) $membershipId, 'membership_id' => $membershipId]);
    exit;
}

/* ── Page render ── */
$activeMembership = school_active_membership($conn, $student_id);

$plans = [];
if (!$activeMembership) {
    $res = $conn->query("SELECT * FROM school_health_plans WHERE is_active=1 ORDER BY sort_order ASC, price ASC");
    if ($res) {
        while ($p = $res->fetch_assoc()) {
            if (school_plan_eligible_for_member($p, $student)) $plans[] = $p;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Membership | <?= htmlspecialchars($student_school) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <style>
    :root { --primary: #0C74C5; --accent: #02c9b8; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }
    .s-topnav {
      background: var(--primary); color: #fff; padding: 0 16px; height: 58px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 10px rgba(12,116,197,.3);
    }
    .s-topnav .brand { font-size: .9rem; font-weight: 700; }
    .s-topnav .sub   { font-size: .68rem; opacity: .75; }
    .s-body { max-width: 700px; margin: 0 auto; padding: 18px 14px 90px; }

    .active-card {
      background: linear-gradient(135deg, var(--primary), #0a5da0);
      color: #fff; border-radius: 16px; padding: 22px 20px; text-align: center;
    }
    .active-card .big { font-size: 1.6rem; font-weight: 800; }

    .plan-card {
      background: #fff; border-radius: 14px; padding: 18px; margin-bottom: 14px;
      border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .plan-card .tier { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--primary); }
    .plan-card .name { font-size: 1.05rem; font-weight: 800; margin: 2px 0 4px; }
    .plan-card .price { font-size: 1.4rem; font-weight: 800; color: #111827; }
    .plan-card .price small { font-size: .62rem; font-weight: 500; color: #6b7280; }
    .plan-card ul { list-style: none; padding: 0; margin: 12px 0; font-size: .82rem; color: #374151; }
    .plan-card ul li { padding: 4px 0; }
    .plan-card ul li i { color: #16a34a; margin-right: 6px; }
    .empty-note { text-align: center; padding: 40px 20px; color: #6b7280; }

    .s-bottomnav {
      position: fixed; bottom: 0; left: 0; right: 0;
      background: #fff; border-top: 1px solid #e5e7eb; display: flex; z-index: 99;
      box-shadow: 0 -2px 10px rgba(0,0,0,.06);
    }
    .s-bottomnav a {
      flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 9px 4px; text-decoration: none; color: #9ca3af; font-size: .58rem; font-weight: 600; gap: 3px;
    }
    .s-bottomnav a i { font-size: 1.05rem; }
    .s-bottomnav a.active { color: var(--primary); }
    .s-bottomnav a.active i { color: var(--primary); }
  </style>
</head>
<body>

<nav class="s-topnav">
  <div>
    <div class="brand"><i class="fas fa-id-card me-2" style="color:var(--accent);"></i>Health Membership</div>
    <div class="sub"><?= htmlspecialchars($student_school) ?></div>
  </div>
  <a href="dashboard.php" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:none;font-size:.76rem;">
    <i class="fas fa-arrow-left me-1"></i><span class="d-none d-sm-inline">Dashboard</span>
  </a>
</nav>

<div class="s-body">

<?php if ($activeMembership): ?>
    <div class="active-card mb-3">
        <i class="fas fa-check-circle fa-2x mb-2"></i>
        <div class="big"><?= htmlspecialchars($activeMembership['plan_name'] ?: 'Active Membership') ?></div>
        <div style="opacity:.85;font-size:.85rem;">Valid till <?= date('d M Y', strtotime($activeMembership['end_date'])) ?></div>
    </div>
    <div class="alert alert-light border small text-center">
        Your membership is active. Book a doctor consultation from <a href="book-appointment.php">Book Appointment</a> —
        your parent will confirm consent and payment before it's booked.
    </div>
<?php elseif (empty($plans)): ?>
    <div class="empty-note">
        <i class="fas fa-id-card fa-3x mb-3 d-block opacity-25"></i>
        No membership plan is currently available for your age/class. Please check with your school.
    </div>
<?php else: ?>
    <p class="text-muted small mb-3">Choose a plan for your 12-month school health membership. Payment goes directly to the platform — your school is not involved in this payment.</p>
    <div id="plansList">
        <?php foreach ($plans as $p): ?>
            <div class="plan-card">
                <?php if ($p['tier']): ?><div class="tier"><?= htmlspecialchars($p['tier']) ?></div><?php endif; ?>
                <div class="name"><?= htmlspecialchars($p['name']) ?></div>
                <?php if ($p['tagline']): ?><div class="text-muted small mb-2"><?= htmlspecialchars($p['tagline']) ?></div><?php endif; ?>
                <div class="price">&#8377;<?= number_format((float) $p['price']) ?> <small><?= htmlspecialchars($p['billing_label']) ?></small></div>
                <?php if ($p['features']): ?>
                    <ul>
                        <?php foreach (array_filter(array_map('trim', explode("\n", $p['features']))) as $f): ?>
                            <li><i class="fas fa-check"></i><?= htmlspecialchars($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn btn-primary w-100 buy-btn" data-plan-id="<?= (int) $p['id'] ?>" data-plan-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                    <i class="fas fa-lock me-1"></i> Pay &amp; Activate
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

<nav class="s-bottomnav">
  <a href="dashboard.php"><i class="fas fa-home"></i>Home</a>
  <a href="health.php"><i class="fas fa-heartbeat"></i>Health</a>
  <a href="records.php"><i class="fas fa-file-medical"></i>Records</a>
  <a href="abha.php"><i class="fas fa-id-card"></i>ABHA</a>
  <a href="book-appointment.php"><i class="fas fa-stethoscope"></i>Book</a>
  <a href="profile.php"><i class="fas fa-user-circle"></i>Profile</a>
</nav>

<script>
document.querySelectorAll('.buy-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const planId = this.dataset.planId;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Please wait…';

        try {
            const res = await fetch('membership.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=create_order&plan_id=' + encodeURIComponent(planId)
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Could not start payment.');
                this.disabled = false; this.innerHTML = '<i class="fas fa-lock me-1"></i> Pay &amp; Activate';
                return;
            }
            if (data.free) {
                alert('Membership activated!');
                window.location.reload();
                return;
            }

            const rzp = new Razorpay({
                key: data.key_id,
                order_id: data.order_id,
                amount: data.amount,
                currency: 'INR',
                name: 'School Health Membership',
                theme: { color: '#0C74C5' },
                handler: async function (response) {
                    const vres = await fetch('membership.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'verify_payment',
                            plan_id: planId,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature
                        })
                    });
                    const vdata = await vres.json();
                    if (vdata.success) {
                        alert('Membership activated!');
                        window.location.reload();
                    } else {
                        alert('Payment succeeded but activation failed. Please contact support with your payment ID.');
                    }
                },
                modal: {
                    ondismiss: () => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-lock me-1"></i> Pay &amp; Activate';
                    }
                }
            });
            rzp.open();
        } catch (e) {
            alert('Something went wrong. Please try again.');
            this.disabled = false; this.innerHTML = '<i class="fas fa-lock me-1"></i> Pay &amp; Activate';
        }
    });
});
</script>
</body>
</html>
