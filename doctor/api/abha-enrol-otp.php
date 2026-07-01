<?php
/**
 * ABHA Enrollment Step 1 — Send Aadhaar OTP to create a new ABHA (M1 flow).
 * POST { aadhaar: "12-digit aadhaar number" }
 * Returns { success, txnId, message }
 *
 * ABDM: POST /enrollment/request/otp
 *   scope=["abha-enrol"], loginHint=aadhaar, loginId=RSA(aadhaar), otpSystem=aadhaar
 */
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/config/abdm.php';
require_once dirname(dirname(__DIR__)) . '/lib/AbdmApi.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

if (!ABDM_CONFIGURED) {
    echo json_encode(['success'=>false,'error'=>'ABDM not configured on this server']); exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$aadhaar = preg_replace('/\D/', '', trim($body['aadhaar'] ?? ''));

if (strlen($aadhaar) !== 12) {
    echo json_encode(['success'=>false,'error'=>'Aadhaar must be exactly 12 digits']); exit;
}

try {
    $api = new AbdmApi();
    $res = $api->generateAadhaarOtp($aadhaar);

    if (!AbdmApi::wasSuccessful($res) || empty($res['txnId'])) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($res,'Failed to send OTP')]); exit;
    }

    echo json_encode([
        'success' => true,
        'txnId'   => $res['txnId'],
        'message' => $res['message'] ?? 'OTP sent to Aadhaar-linked mobile number',
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
