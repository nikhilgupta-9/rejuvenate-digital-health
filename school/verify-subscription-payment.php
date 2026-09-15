<?php
/**
 * POST { razorpay_order_id, razorpay_payment_id, razorpay_signature } ->
 * verifies the Razorpay signature and marks the matching pending
 * school_subscriptions row 'pending_approval'. Unlike the doctor flow,
 * this does NOT activate the subscription or credit referral commission —
 * that only happens once an admin approves it on
 * admin/school-subscription-approve.php (see that file for the commission
 * logic), since every school plan needs admin sign-off.
 */
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../lib/AuditLogger.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['school_logged_in']) || $_SESSION['school_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}
$school_id = (int) ($_SESSION['school_id'] ?? 0);

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
        (new AuditLogger($conn))->logValidationFailure('razorpay_signature', 'Signature mismatch on school subscription payment', $school_id, 'school');
    } catch (Throwable $e) {
    }
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. If money was deducted, it will be refunded automatically — please contact us.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM school_subscriptions WHERE razorpay_order_id = ? AND school_id = ? AND status = 'pending_payment' LIMIT 1");
$stmt->bind_param('si', $rpOrderId, $school_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();

if (!$sub) {
    echo json_encode(['success' => false, 'message' => 'Could not find a matching pending payment.']);
    exit;
}

$upd = $conn->prepare("UPDATE school_subscriptions SET
    razorpay_payment_id = ?, razorpay_signature = ?, status = 'pending_approval'
    WHERE id = ?");
$upd->bind_param('ssi', $rpPaymentId, $rpSignature, $sub['id']);
$upd->execute();

echo json_encode(['success' => true]);
