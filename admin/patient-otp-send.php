<?php
/**
 * Admin panel — send a WhatsApp + email OTP to a patient the super admin is
 * adding from admin/add-customer.php. POST { mobile, email?, name? }
 */
require_once dirname(__DIR__) . '/util/otp-service.php';   // pulls config/connect.php ($conn, BASE_URL, JWT_SECRET)
require_once __DIR__ . '/auth/guard.php';

header('Content-Type: application/json');

$payload = admin_jwt_guard(true);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$admin_id = (int)($payload['sub'] ?? 0);

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$mobile = preg_replace('/\D/', '', $in['mobile'] ?? '');
$email  = trim($in['email'] ?? '');
$name   = trim($in['name'] ?? '');

if (registration_mobile_exists('patient', $mobile)) {
    echo json_encode([
        'success'            => false,
        'already_registered' => true,
        'error'              => 'This mobile number is already registered.',
    ]);
    exit;
}

echo json_encode(otp_send('patient', $mobile, $email ?: null, $name, $admin_id));
