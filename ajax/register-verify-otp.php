<?php
/**
 * POST { role, mobile, otp } — verify a registration OTP.
 * On success returns { success:true, token } — the caller puts `token` in a
 * hidden field and the final registration handler passes it to
 * otp_consume_token().
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../util/otp-service.php';

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$role   = trim($in['role'] ?? '');
$mobile = preg_replace('/\D/', '', $in['mobile'] ?? '');
$otp    = preg_replace('/\D/', '', $in['otp'] ?? '');

if (!otp_valid_role($role)) {
    echo json_encode(['success' => false, 'error' => 'Invalid registration type.']);
    exit;
}

echo json_encode(otp_verify($role, $mobile, $otp));
