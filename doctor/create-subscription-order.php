<?php
/**
 * POST -> creates a Razorpay order for this doctor's membership plan
 * (manual renewal — no Razorpay auto-recurring subscription). Reads the
 * plan price from doctor_plans, never trusts the client for the amount.
 */
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/auth/guard.php';

header('Content-Type: application/json');

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

$plan = $conn->query("SELECT id, name, price, billing_cycle_days FROM doctor_plans WHERE is_active = 1 ORDER BY id ASC LIMIT 1")->fetch_assoc();
if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'No membership plan is currently available.']);
    exit;
}

if (!RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
    error_log('[Razorpay] RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET not configured in .env');
    echo json_encode(['success' => false, 'message' => 'Online payment is temporarily unavailable. Please try again later.']);
    exit;
}

$amountPaise = (int) round(((float) $plan['price']) * 100);
$receipt = 'docsub_' . $doctor_id . '_' . time() . '_' . bin2hex(random_bytes(3));

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
            'doctor_id' => $doctor_id,
            'plan_id'   => $plan['id'],
            'purpose'   => 'doctor_subscription',
        ],
    ]),
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    error_log('[Razorpay] Subscription order creation failed: ' . $curlErr . ' | HTTP ' . $httpCode . ' | ' . $response);
    echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
    exit;
}

$order = json_decode($response, true);
if (empty($order['id'])) {
    error_log('[Razorpay] Unexpected subscription order response: ' . $response);
    echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
    exit;
}

// Track the pending attempt so verify-subscription-payment.php can look up
// which plan/amount this order_id was for, without trusting the client.
$ins = $conn->prepare("INSERT INTO doctor_subscriptions (doctor_id, plan_id, amount, razorpay_order_id, status) VALUES (?, ?, ?, ?, 'pending')");
$ins->bind_param('iids', $doctor_id, $plan['id'], $plan['price'], $order['id']);
$ins->execute();

echo json_encode([
    'success'   => true,
    'key_id'    => RAZORPAY_KEY_ID,
    'order_id'  => $order['id'],
    'amount'    => $amountPaise,
    'currency'  => 'INR',
    'plan_name' => $plan['name'],
]);
