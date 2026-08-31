<?php
/**
 * Doctor panel — send a WhatsApp + email OTP to a patient the doctor is adding
 * manually. The patient reads the code back and the doctor types it in.
 * POST { mobile, email?, name? }
 */
require_once __DIR__ . '/../auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/util/otp-service.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$doctor_id = (int)($payload['doctor_id'] ?? $payload['sub'] ?? 0);

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$mobile = preg_replace('/\D/', '', $in['mobile'] ?? '');
$email  = trim($in['email'] ?? '');
$name   = trim($in['name'] ?? '');

// Already a portal account? The doctor should link it via "Search by Mobile" —
// no OTP needed, create-patient-submit will attach it.
if (registration_mobile_exists('patient', $mobile)) {
    echo json_encode([
        'success'           => false,
        'already_registered' => true,
        'error'             => 'This patient already has an account — they will be linked to your panel without OTP.',
    ]);
    exit;
}

echo json_encode(otp_send('patient', $mobile, $email ?: null, $name, $doctor_id));
