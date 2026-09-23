<?php
/**
 * Parent-facing landing page for a student-initiated OPD booking request
 * (school/student/book-appointment.php). Public — no login, reached only
 * via a signed, expiring link (lib/BookingApprovalToken.php).
 *
 * A student's own booking is never auto-confirmed: the parent must (1)
 * confirm consent for the checkup (reusing the Phase 1 gate,
 * doctor/inc/consent-helper.php — a lightweight point-of-care-style capture
 * here, not the full plan/health-data form in school/parent-consent.php)
 * and (2) pay the doctor's consultation fee (no membership discount — that
 * calculation is Phase 3) before the request becomes a real appointment.
 * See database/migration_school_membership_phase2.sql.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../util/function.php';       // insert_appointment(), find_or_create_patient_user(), get_favicon()
require_once __DIR__ . '/../util/mail_config.php';    // Mailer
require_once __DIR__ . '/../lib/BookingApprovalToken.php';
require_once __DIR__ . '/../lib/RazorpayOrder.php';
require_once __DIR__ . '/../doctor/inc/consent-helper.php'; // consent_item_labels(), student_has_consent()

header_remove('X-Powered-By');

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$holdId = $token !== '' ? booking_verify_token($token) : null;

$error = null;
$hold = null;
$member = null;
$doctor = null;

if (!$holdId) {
    $error = 'This link is invalid or has expired. Please ask your child to send a fresh booking request.';
} else {
    $hs = $conn->prepare("SELECT * FROM school_booking_holds WHERE id = ? LIMIT 1");
    $hs->bind_param('i', $holdId);
    $hs->execute();
    $hold = $hs->get_result()->fetch_assoc();

    if (!$hold) {
        $error = 'This booking request could not be found.';
    } else {
        if ($hold['status'] === 'awaiting_parent' && strtotime($hold['expires_at']) < time()) {
            $conn->query("UPDATE school_booking_holds SET status='expired' WHERE id=" . (int) $hold['id']);
            $hold['status'] = 'expired';
        }
        $ms = $conn->prepare("SELECT sm.*, s.school_name FROM school_members sm JOIN schools s ON s.id = sm.school_id WHERE sm.id = ? LIMIT 1");
        $ms->bind_param('i', $hold['member_id']);
        $ms->execute();
        $member = $ms->get_result()->fetch_assoc();

        $ds = $conn->prepare("SELECT * FROM doctors WHERE id = ? LIMIT 1");
        $ds->bind_param('i', $hold['doctor_id']);
        $ds->execute();
        $doctor = $ds->get_result()->fetch_assoc();

        if (!$member || !$doctor) {
            $error = 'This booking request is no longer valid.';
        }
    }
}

$consentLabels = consent_item_labels();
$hasConsent = ($hold && $member) ? student_has_consent($conn, (int) $member['id']) : false;

/* ── Submit consent (point-of-care style, captured by the parent themself) ── */
$consent_error = '';
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_consent') {
    $parent_name   = trim($_POST['parent_name'] ?? '');
    $relation      = in_array($_POST['relation'] ?? '', ['Father', 'Mother', 'Guardian', 'Other']) ? $_POST['relation'] : 'Father';
    $parent_mobile = preg_replace('/\D/', '', trim($_POST['parent_mobile'] ?? ''));
    $parent_email  = trim($_POST['parent_email'] ?? '');
    $consent_given = isset($_POST['i_agree']) ? 1 : 0;

    if (!$parent_name || strlen($parent_mobile) < 10 || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        $consent_error = 'Please fill your name, a valid 10-digit mobile number and a valid email.';
    } elseif (!$consent_given) {
        $consent_error = 'Please agree to the declaration to continue.';
    } else {
        $items = [];
        foreach (array_keys($consentLabels) as $k) $items[$k] = isset($_POST['consent'][$k]);

        $y = (int) date('Y'); $aprStart = (int) date('n') >= 4 ? $y : $y - 1;
        $academicYear = $aprStart . '-' . ($aprStart + 1);
        $expiresAt    = ($aprStart + 1) . '-03-31';

        $ins = $conn->prepare("INSERT INTO parent_consent_forms
            (token, school_id, member_id, parent_name, relation, parent_mobile, parent_email,
             student_name, student_dob, student_class, consent_items, consent_given,
             declaration_text, academic_year, expires_at, ip_address, user_agent, status, source, verified_via)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'reviewed','parent','token')");
        $t = bin2hex(random_bytes(16));
        $decl = "I, {$parent_name} ({$relation}), give consent for the health checkup of {$member['name']}, requested via the platform booking flow.";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $itemsJson = json_encode($items);
        $ins->bind_param(
            'siissssssssisssss',
            $t, $hold['school_id'], $hold['member_id'], $parent_name, $relation, $parent_mobile, $parent_email,
            $member['name'], $member['dob'], $member['class'], $itemsJson, $consent_given,
            $decl, $academicYear, $expiresAt, $ip, $ua
        );
        $ins->execute();

        $hasConsent = true;
        // Keyed by hold id so two different children's requests, opened in the
        // same browser session one after another, never mix up parent details.
        $_SESSION['pba_' . $holdId . '_name']   = $parent_name;
        $_SESSION['pba_' . $holdId . '_email']  = $parent_email;
        $_SESSION['pba_' . $holdId . '_mobile'] = $parent_mobile;
    }
}

/* ── Create Razorpay order ── */
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_order') {
    header('Content-Type: application/json');
    if (!$hasConsent || $hold['status'] !== 'awaiting_parent') {
        echo json_encode(['success' => false, 'message' => 'This request is not ready for payment.']);
        exit;
    }
    $fee = (float) ($doctor['consultation_fee'] ?? 0);
    if ($fee <= 0) {
        echo json_encode(['success' => true, 'payment_required' => false]);
        exit;
    }
    $order = razorpay_create_order((int) round($fee * 100), 'sbh_' . $hold['id'] . '_' . time(), ['hold_id' => $hold['id'], 'doctor_id' => $doctor['id']]);
    if (!$order['success']) {
        echo json_encode(['success' => false, 'message' => $order['message']]);
        exit;
    }
    echo json_encode(['success' => true, 'payment_required' => true, 'key_id' => RAZORPAY_KEY_ID, 'order_id' => $order['order_id'], 'amount' => $order['amount'], 'doctor_name' => $doctor['name']]);
    exit;
}

/* ── Verify payment, create the real appointment, resolve the hold ── */
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_booking') {
    header('Content-Type: application/json');
    if (!$hasConsent || $hold['status'] !== 'awaiting_parent') {
        echo json_encode(['success' => false, 'message' => 'This request is no longer valid.']);
        exit;
    }

    $fee = (float) ($doctor['consultation_fee'] ?? 0);
    $paymentStatus = 'not_required';
    $paymentAmount = null;
    $rpOrderId = $rpPaymentId = $rpSignature = null;

    if ($fee > 0) {
        $rpOrderId   = trim($_POST['razorpay_order_id'] ?? '');
        $rpPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
        $rpSignature = trim($_POST['razorpay_signature'] ?? '');
        if (!$rpOrderId || !$rpPaymentId || !$rpSignature || !razorpay_verify_signature($rpOrderId, $rpPaymentId, $rpSignature)) {
            echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted it will be refunded automatically.']);
            exit;
        }
        $paymentStatus = 'paid';
        $paymentAmount = $fee;
    }

    $parentName   = $_SESSION['pba_' . $holdId . '_name']  ?? $member['name'];
    $parentEmail  = $_SESSION['pba_' . $holdId . '_email'] ?? '';
    $parentMobile = $_SESSION['pba_' . $holdId . '_mobile'] ?? preg_replace('/\D/', '', (string) ($member['parent_mobile'] ?? ''));

    if (!$parentEmail || !$parentMobile) {
        echo json_encode(['success' => false, 'message' => 'Missing parent contact details — please refresh and confirm consent again.']);
        exit;
    }

    $account = find_or_create_patient_user($conn, $parentName, $parentEmail, $parentMobile);

    $appointmentId = insert_appointment([
        'name'   => $parentName,
        'email'  => $parentEmail,
        'phone'  => $parentMobile,
        'user_id' => $account['user_id'],
        'doctor_id' => $doctor['id'],
        'date' => $hold['appointment_date'],
        'time' => $hold['appointment_time'],
        'department' => $hold['department'],
        'notes' => $hold['notes'],
        'visit_person' => 'other',
        'visited_person_name' => $member['name'],
        'school_member_id' => $hold['member_id'],
        'booking_source' => 'school_student_self',
        'consent_given' => 1,
        'payment_status' => $paymentStatus,
        'payment_amount' => $paymentAmount,
        'razorpay_order_id' => $rpOrderId,
        'razorpay_payment_id' => $rpPaymentId,
        'razorpay_signature' => $rpSignature,
    ]);

    if (!$appointmentId) {
        echo json_encode(['success' => false, 'message' => 'Could not create the appointment. Please try again or contact support.']);
        exit;
    }

    $upd = $conn->prepare("UPDATE school_booking_holds SET status='approved', resolved_at=NOW(), resulting_appointment_id=? WHERE id=?");
    $upd->bind_param('ii', $appointmentId, $hold['id']);
    $upd->execute();

    unset($_SESSION['pba_' . $holdId . '_name'], $_SESSION['pba_' . $holdId . '_email'], $_SESSION['pba_' . $holdId . '_mobile']);

    echo json_encode(['success' => true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php if (!function_exists('get_favicon')) { require_once __DIR__ . '/../util/function.php'; } ?>
<head>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . get_favicon() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Confirm Booking | Rejuvenate Digital Health</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7fb; margin: 0; }
        .pb-wrap { max-width: 560px; margin: 0 auto; padding: 24px 16px 60px; }
        .pb-header { text-align: center; margin-bottom: 20px; }
        .pb-header .brand { font-size: 1rem; font-weight: 800; color: #0C74C5; }
        .pb-card { background: #fff; border-radius: 16px; padding: 22px; box-shadow: 0 2px 12px rgba(0,0,0,.06); margin-bottom: 16px; }
        .summary-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: .88rem; border-bottom: 1px solid #f1f5f9; }
        .summary-row:last-child { border-bottom: none; }
        .step-pill { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 700; color: #0C74C5; background: #eff6ff; padding: 4px 10px; border-radius: 20px; margin-bottom: 12px; }
        .consent-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; font-size: .8rem; margin: 10px 0; }
    </style>
</head>
<body>
<div class="pb-wrap">
    <div class="pb-header">
        <div class="brand"><i class="fas fa-heartbeat me-2"></i>Rejuvenate Digital Health</div>
    </div>

    <?php if ($error): ?>
        <div class="pb-card text-center">
            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
            <p class="mb-0"><?= htmlspecialchars($error) ?></p>
        </div>

    <?php elseif ($hold['status'] === 'approved'): ?>
        <div class="pb-card text-center">
            <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
            <h5 class="fw-bold">Appointment Confirmed</h5>
            <p class="text-muted mb-0">This booking has already been approved and confirmed.</p>
        </div>

    <?php elseif ($hold['status'] === 'expired'): ?>
        <div class="pb-card text-center">
            <i class="fas fa-hourglass-end fa-2x text-muted mb-3"></i>
            <h5 class="fw-bold">Request Expired</h5>
            <p class="text-muted mb-0">This booking request has expired. Please ask your child to send a fresh request.</p>
        </div>

    <?php else: ?>
        <div class="pb-card">
            <div class="step-pill"><i class="fas fa-info-circle"></i> Booking request from <?= htmlspecialchars($member['name']) ?></div>
            <div class="summary-row"><span>Student</span><strong><?= htmlspecialchars($member['name']) ?></strong></div>
            <div class="summary-row"><span>School</span><strong><?= htmlspecialchars($member['school_name']) ?></strong></div>
            <div class="summary-row"><span>Doctor</span><strong>Dr. <?= htmlspecialchars($doctor['name']) ?></strong></div>
            <div class="summary-row"><span>When</span><strong><?= date('d M Y', strtotime($hold['appointment_date'])) ?> at <?= date('h:i A', strtotime($hold['appointment_time'])) ?></strong></div>
            <div class="summary-row"><span>Consultation fee</span><strong><?= ((float) $doctor['consultation_fee']) > 0 ? '₹' . number_format((float) $doctor['consultation_fee']) : 'Free' ?></strong></div>
        </div>

        <?php if (!$hasConsent): ?>
            <div class="pb-card">
                <h6 class="fw-bold mb-1"><i class="fas fa-file-signature me-1" style="color:#0C74C5;"></i> Parent/Guardian Consent</h6>
                <p class="text-muted small mb-3">Required before this checkup can be booked.</p>
                <?php if ($consent_error): ?><div class="alert alert-danger small"><?= htmlspecialchars($consent_error) ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="submit_consent">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small">Your name</label><input type="text" name="parent_name" class="form-control form-control-sm" required></div>
                        <div class="col-6">
                            <label class="form-label small">Relation</label>
                            <select name="relation" class="form-select form-select-sm">
                                <option>Father</option><option>Mother</option><option>Guardian</option><option>Other</option>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label small">Mobile</label><input type="text" name="parent_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($member['parent_mobile'] ?? '') ?>" required></div>
                        <div class="col-6"><label class="form-label small">Email</label><input type="email" name="parent_email" class="form-control form-control-sm" required></div>
                    </div>
                    <label class="form-label small fw-semibold">I consent to the following for this visit:</label>
                    <div class="consent-grid mb-2">
                        <?php foreach ($consentLabels as $k => $lbl): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="consent[<?= $k ?>]" value="1" checked id="c_<?= $k ?>">
                                <label class="form-check-label" for="c_<?= $k ?>"><?= htmlspecialchars($lbl) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="i_agree" value="1" id="i_agree" required>
                        <label class="form-check-label small" for="i_agree">I have read and understood the declaration and give informed consent for this checkup.</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-1"></i> Confirm Consent</button>
                </form>
            </div>
        <?php else: ?>
            <div class="pb-card text-center">
                <h6 class="fw-bold mb-2">Complete Payment to Confirm</h6>
                <p class="text-muted small">Consent confirmed. Pay the consultation fee to finalise this booking.</p>
                <button type="button" id="payBtn" class="btn btn-primary w-100"><i class="fas fa-lock me-1"></i> Pay &amp; Confirm Booking</button>
                <div id="payMsg" class="small text-danger mt-2"></div>
            </div>
            <script>
            document.getElementById('payBtn').addEventListener('click', async function () {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Please wait…';
                const msg = document.getElementById('payMsg');
                try {
                    const oRes = await fetch('parent-booking-approval.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=create_order&token=<?= urlencode($token) ?>'
                    });
                    const oData = await oRes.json();
                    if (!oData.success) { msg.textContent = oData.message || 'Could not start payment.'; this.disabled = false; this.innerHTML = 'Pay &amp; Confirm Booking'; return; }

                    if (!oData.payment_required) {
                        const cRes = await fetch('parent-booking-approval.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=confirm_booking&token=<?= urlencode($token) ?>'
                        });
                        const cData = await cRes.json();
                        if (cData.success) { window.location.reload(); } else { msg.textContent = cData.message; this.disabled = false; this.innerHTML = 'Pay &amp; Confirm Booking'; }
                        return;
                    }

                    const rzp = new Razorpay({
                        key: oData.key_id, order_id: oData.order_id, amount: oData.amount, currency: 'INR',
                        name: 'Rejuvenate Digital Health', description: 'Consultation with Dr. ' + oData.doctor_name,
                        theme: { color: '#0C74C5' },
                        handler: async function (response) {
                            const cRes = await fetch('parent-booking-approval.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    action: 'confirm_booking', token: '<?= addslashes($token) ?>',
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_signature: response.razorpay_signature
                                })
                            });
                            const cData = await cRes.json();
                            if (cData.success) { window.location.reload(); } else { msg.textContent = cData.message; }
                        },
                        modal: { ondismiss: () => { document.getElementById('payBtn').disabled = false; document.getElementById('payBtn').innerHTML = 'Pay &amp; Confirm Booking'; } }
                    });
                    rzp.open();
                } catch (e) {
                    msg.textContent = 'Something went wrong. Please try again.';
                    this.disabled = false; this.innerHTML = 'Pay &amp; Confirm Booking';
                }
            });
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
