<?php
/**
 * Admin panel — verify the OTP the patient read back. Returns a single-use
 * token consumed by admin/add-customer.php on submit. POST { mobile, otp }
 */
require_once dirname(__DIR__) . '/util/otp-service.php';
require_once __DIR__ . '/auth/guard.php';

header('Content-Type: application/json');

$payload = admin_jwt_guard(true);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$mobile = preg_replace('/\D/', '', $in['mobile'] ?? '');
$otp    = preg_replace('/\D/', '', $in['otp'] ?? '');

echo json_encode(otp_verify('patient', $mobile, $otp));
