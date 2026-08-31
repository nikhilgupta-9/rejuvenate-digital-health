<?php
/**
 * POST { razorpay_order_id, razorpay_payment_id, razorpay_signature } ->
 * verifies the Razorpay signature, marks the matching pending
 * doctor_subscriptions row 'paid', extends the doctor's membership expiry,
 * and — if this doctor signed up via another doctor's referral link —
 * credits that referring doctor 10% of the subscription amount into
 * doctor_referral_earnings (a running balance; payout itself is handled
 * manually/offline by admin, not automated here).
 */
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/../lib/AuditLogger.php';

header('Content-Type: application/json');

const REFERRAL_COMMISSION_RATE = 0.10;

$jwt_doctor = doctor_jwt_guard(true);
if (!$jwt_doctor) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}
$doctor_id = (int) $jwt_doctor['sub'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$rpOrderId   = trim($_POST['razorpay_order_id'] ?? '');
$rpPaymentId = trim($_POST['razorpay_payment_id'] ?? '');
$rpSignature = trim($_POST['razorpay_signature'] ?? '');

if (!$rpOrderId || !$rpPaymentId || !$rpSignature) {
    echo json_encode(['success' => false, 'message' => 'Payment was not completed. Please try again.']);
    exit;
}

if (!RAZORPAY_KEY_SECRET) {
    echo json_encode(['success' => false, 'message' => 'Online payment is temporarily unavailable. Please try again later.']);
    exit;
}

$expectedSignature = hash_hmac('sha256', $rpOrderId . '|' . $rpPaymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expectedSignature, $rpSignature)) {
    try {
        (new AuditLogger($conn))->logValidationFailure('razorpay_signature', 'Signature mismatch on doctor subscription payment', $doctor_id, 'doctor');
    } catch (Throwable $e) {
    }
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted, it will be refunded automatically — please contact us.']);
    exit;
}

// The pending row created in create-subscription-order.php — this is what
// ties the verified order_id back to a specific doctor/plan/amount, so
// nothing here is trusted from the POST body except the signature inputs.
$stmt = $conn->prepare("SELECT * FROM doctor_subscriptions WHERE razorpay_order_id = ? AND doctor_id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param('si', $rpOrderId, $doctor_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();

if (!$sub) {
    echo json_encode(['success' => false, 'message' => 'Could not find a matching pending payment.']);
    exit;
}

$plan = $conn->query("SELECT billing_cycle_days FROM doctor_plans WHERE id = " . (int) $sub['plan_id'])->fetch_assoc();
$cycleDays = (int) ($plan['billing_cycle_days'] ?? 30);

// Extend from the doctor's current active expiry if membership hasn't
// lapsed yet, otherwise start fresh from now.
$doc = $conn->prepare("SELECT MAX(expires_at) AS current_expiry FROM doctor_subscriptions WHERE doctor_id = ? AND status = 'paid'");
$doc->bind_param('i', $doctor_id);
$doc->execute();
$currentExpiry = $doc->get_result()->fetch_assoc()['current_expiry'] ?? null;

$startsAt = ($currentExpiry && strtotime($currentExpiry) > time()) ? $currentExpiry : date('Y-m-d H:i:s');
$expiresAt = date('Y-m-d H:i:s', strtotime($startsAt . " +{$cycleDays} days"));

$upd = $conn->prepare("UPDATE doctor_subscriptions SET
    razorpay_payment_id = ?, razorpay_signature = ?, status = 'paid',
    starts_at = ?, expires_at = ?
    WHERE id = ?");
$upd->bind_param('ssssi', $rpPaymentId, $rpSignature, $startsAt, $expiresAt, $sub['id']);
$upd->execute();

// Referral commission — only once per subscription payment (guarded by
// the UNIQUE key on doctor_subscription_id, in case this ever runs twice).
$refStmt = $conn->prepare("SELECT referred_by FROM doctors WHERE id = ? LIMIT 1");
$refStmt->bind_param('i', $doctor_id);
$refStmt->execute();
$referredBy = $refStmt->get_result()->fetch_assoc()['referred_by'] ?? null;

if ($referredBy) {
    $commission = round(((float) $sub['amount']) * REFERRAL_COMMISSION_RATE, 2);
    $earnIns = $conn->prepare("INSERT IGNORE INTO doctor_referral_earnings
        (referring_doctor_id, referred_doctor_id, doctor_subscription_id, subscription_amount, commission_amount)
        VALUES (?, ?, ?, ?, ?)");
    $earnIns->bind_param('iiidd', $referredBy, $doctor_id, $sub['id'], $sub['amount'], $commission);
    $earnIns->execute();
}

echo json_encode([
    'success'    => true,
    'expires_at' => $expiresAt,
]);
