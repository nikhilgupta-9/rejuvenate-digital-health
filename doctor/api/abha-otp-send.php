<?php
/**
 * Send OTP to verify an existing ABHA holder.
 * POST { abha_input, type: "number"|"address"|"mobile"|"aadhaar" }
 * Returns { success, txnId, message }
 *
 * ABDM: POST /profile/login/request/otp
 *   loginHint: abha-number | abha-address | mobile | aadhaar
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

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$abha_input = trim($body['abha_input'] ?? '');
$type       = trim($body['type']       ?? 'number');

if (!$abha_input) { echo json_encode(['success'=>false,'error'=>'Input required']); exit; }

try {
    $api = new AbdmApi();

    switch ($type) {
        case 'address':
            $loginId   = strpos($abha_input,'@') !== false ? $abha_input : $abha_input.'@abdm';
            $loginHint = 'abha-address';
            break;

        case 'mobile':
            $digits = preg_replace('/\D/','',$abha_input);
            if (strlen($digits) !== 10) {
                echo json_encode(['success'=>false,'error'=>'Enter a valid 10-digit mobile number']); exit;
            }
            $loginId   = $digits;
            $loginHint = 'mobile';
            break;

        case 'aadhaar':
            $digits = preg_replace('/\D/','',$abha_input);
            if (strlen($digits) !== 12) {
                echo json_encode(['success'=>false,'error'=>'Aadhaar must be 12 digits']); exit;
            }
            $loginId   = $digits;
            $loginHint = 'aadhaar';
            break;

        default: // 'number'
            $digits = preg_replace('/\D/','',$abha_input);
            if (strlen($digits) !== 14) {
                echo json_encode(['success'=>false,'error'=>'ABHA number must be 14 digits']); exit;
            }
            $loginId   = AbdmApi::formatAbhaNumber($digits);
            $loginHint = 'abha-number';
            break;
    }

    $res = $api->initAuth($loginId, $loginHint, 'abdm', ['abha-login']);

    if (!AbdmApi::wasSuccessful($res) || empty($res['txnId'])) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($res,'Failed to send OTP')]); exit;
    }

    echo json_encode([
        'success' => true,
        'txnId'   => $res['txnId'],
        'message' => $res['message'] ?? 'OTP sent to patient\'s ABHA-registered mobile',
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
