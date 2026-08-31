<?php
/**
 * Doctor panel — verify the OTP the patient read back. Returns a single-use
 * token that create-patient-submit.php consumes.
 * POST { mobile, otp }
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

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$mobile = preg_replace('/\D/', '', $in['mobile'] ?? '');
$otp    = preg_replace('/\D/', '', $in['otp'] ?? '');

echo json_encode(otp_verify('patient', $mobile, $otp));
