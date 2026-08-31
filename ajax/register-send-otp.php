<?php
/**
 * POST { role, mobile, email, name } — send a WhatsApp + email OTP for a
 * self-service registration flow (patient / doctor / student / teacher / school_admin).
 * Returns { success, channels, debug_otp? } or { success:false, error }.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../util/otp-service.php';

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$role   = trim($in['role'] ?? '');
$mobile = preg_replace('/\D/', '', $in['mobile'] ?? '');
$email  = trim($in['email'] ?? '');
$name   = trim($in['name'] ?? '');

if (!otp_valid_role($role)) {
    echo json_encode(['success' => false, 'error' => 'Invalid registration type.']);
    exit;
}

if (registration_mobile_exists($role, $mobile)) {
    $where = $role === 'doctor' ? 'a doctor account' : 'an account';
    echo json_encode(['success' => false, 'error' => "This mobile number is already linked to {$where}. Please log in instead."]);
    exit;
}

echo json_encode(otp_send($role, $mobile, $email ?: null, $name));
