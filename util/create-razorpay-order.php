<?php
/**
 * POST doctor_id -> creates a Razorpay order for that doctor's consultation
 * fee and returns what the browser needs to open Checkout.
 *
 * The fee is read from the DB, never trusted from the client — this is the
 * one place that decides how much money changes hands.
 *
 * Response shapes:
 *   {"success":true,"payment_required":false}                         doctor has no fee set
 *   {"success":true,"payment_required":true,"key_id":..,"order_id":..,
 *    "amount":.. (paise),"currency":"INR","doctor_name":..}
 *   {"success":false,"message":".."}
 */
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../config/payment.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$doctorId = (int) ($_POST['doctor_id'] ?? 0);
if (!$doctorId) {
    echo json_encode(['success' => false, 'message' => 'doctor_id is required']);
    exit;
}

$stmt = $conn->prepare("SELECT name, consultation_fee FROM doctors WHERE id = ? AND status = 'Active' LIMIT 1");
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit;
}

$fee = (float) ($doctor['consultation_fee'] ?? 0);

// No fee configured for this doctor — nothing to charge, booking proceeds
// straight to appointment-handler.php without a payment step.
if ($fee <= 0) {
    echo json_encode(['success' => true, 'payment_required' => false]);
    exit;
}

if (!RAZORPAY_KEY_ID || !RAZORPAY_KEY_SECRET) {
    error_log('[Razorpay] RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET not configured in .env');
    echo json_encode(['success' => false, 'message' => 'Online payment is temporarily unavailable. Please try again later or contact us.']);
    exit;
}

$amountPaise = (int) round($fee * 100);
$receipt = 'apt_' . $doctorId . '_' . time() . '_' . bin2hex(random_bytes(3));

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
            'doctor_id'   => $doctorId,
            'doctor_name' => $doctor['name'],
        ],
    ]),
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 200) {
    error_log('[Razorpay] Order creation failed: ' . $curlErr . ' | HTTP ' . $httpCode . ' | ' . $response);
    echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
    exit;
}

$order = json_decode($response, true);
if (empty($order['id'])) {
    error_log('[Razorpay] Unexpected order response: ' . $response);
    echo json_encode(['success' => false, 'message' => 'Could not start the payment. Please try again.']);
    exit;
}

echo json_encode([
    'success'          => true,
    'payment_required' => true,
    'key_id'           => RAZORPAY_KEY_ID,
    'order_id'         => $order['id'],
    'amount'           => $amountPaise,
    'currency'         => 'INR',
    'doctor_name'      => $doctor['name'],
]);
