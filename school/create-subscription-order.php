<?php
/**
 * POST -> creates a Razorpay order for this school's chosen subscription
 * plan, or (price 0 / free plan) records the request directly. Reads the
 * plan price from school_plans, never trusts the client for the amount.
 *
 * Unlike doctor/create-subscription-order.php, a school subscription is
 * NEVER activated here — every request (free or paid) lands as
 * 'pending_approval' and waits for an admin decision on
 * admin/school-subscriptions.php.
 */
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';

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

$requestedPlanId = (int) ($_POST['plan_id'] ?? 0);
if (!$requestedPlanId) {
    echo json_encode(['success' => false, 'message' => 'Please choose a plan.']);
    exit;
}

// A school can't have two requests in flight at once for the same or a
// different plan — keeps the approval queue and student-count math simple.
$openStmt = $conn->prepare("SELECT id FROM school_subscriptions WHERE school_id = ? AND status IN ('pending_payment','pending_approval') LIMIT 1");
$openStmt->bind_param('i', $school_id);
$openStmt->execute();
if ($openStmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'You already have a subscription request awaiting approval.']);
    exit;
}

$ps = $conn->prepare("SELECT id, name, price, billing_cycle_days FROM school_plans WHERE id = ? AND is_active = 1 LIMIT 1");
$ps->bind_param('i', $requestedPlanId);
$ps->execute();
$plan = $ps->get_result()->fetch_assoc();
if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'That plan is not available.']);
    exit;
}

// Free plan — no Razorpay order needed, go straight to pending_approval.
if ((float) $plan['price'] <= 0) {
    $ins = $conn->prepare("INSERT INTO school_subscriptions (school_id, plan_id, amount, status) VALUES (?, ?, 0, 'pending_approval')");
    $ins->bind_param('ii', $school_id, $plan['id']);
    $ins->execute();

    echo json_encode(['success' => true, 'free' => true]);
    exit;
}

if (!RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
    error_log('[Razorpay] RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET not configured in .env');
    echo json_encode(['success' => false, 'message' => 'Online payment is temporarily unavailable. Please try again later.']);
    exit;
}

$amountPaise = (int) round(((float) $plan['price']) * 100);
$receipt = 'schsub_' . $school_id . '_' . time() . '_' . bin2hex(random_bytes(3));

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'amount'   => $amountPaise,
        'currency' => 'INR',
        'receipt'  => $receipt,
        'notes'    => [
            'school_id' => $school_id,
            'plan_id'   => $plan['id'],
            'purpose'   => 'school_subscription',
        ],
    ]),
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    error_log('[Razorpay] School subscription order creation failed: ' . $curlErr . ' | HTTP ' . $httpCode . ' | ' . $response);
    echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
    exit;
}

$order = json_decode($response, true);
if (empty($order['id'])) {
    error_log('[Razorpay] Unexpected school subscription order response: ' . $response);
    echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
    exit;
}

// Track the pending attempt so verify-subscription-payment.php can look up
// which plan/amount this order_id was for, without trusting the client.
$ins = $conn->prepare("INSERT INTO school_subscriptions (school_id, plan_id, amount, razorpay_order_id, status) VALUES (?, ?, ?, ?, 'pending_payment')");
$ins->bind_param('iids', $school_id, $plan['id'], $plan['price'], $order['id']);
$ins->execute();

echo json_encode([
    'success'   => true,
    'key_id'    => RAZORPAY_KEY_ID,
    'order_id'  => $order['id'],
    'amount'    => $amountPaise,
    'currency'  => 'INR',
    'plan_name' => $plan['name'],
]);
