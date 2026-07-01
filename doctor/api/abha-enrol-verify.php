<?php
/**
 * ABHA Enrollment Step 2 — Verify Aadhaar OTP and create ABHA.
 * POST { txnId, otp, mobile? }
 * Returns { success, txnId, xToken, profile, suggestions, is_new_abha }
 *
 * ABDM: POST /enrollment/enrol/byAadhaar
 * On success: ABHAProfile + tokens.token (X-token for address selection)
 * Then: GET /enrollment/enrol/suggestion?txnId= → suggested ABHA addresses
 */
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/config/connect.php';
require_once dirname(dirname(__DIR__)) . '/config/abdm.php';
require_once dirname(dirname(__DIR__)) . '/lib/AbdmApi.php';

header('Content-Type: application/json');

$payload = doctor_jwt_guard(true);
if (!$payload) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

if (!ABDM_CONFIGURED) {
    echo json_encode(['success'=>false,'error'=>'ABDM not configured']); exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$txnId  = trim($body['txnId'] ?? '');
$otp    = trim($body['otp']   ?? '');
$mobile = preg_replace('/\D/', '', trim($body['mobile'] ?? ''));

if (!$txnId || !$otp) {
    echo json_encode(['success'=>false,'error'=>'txnId and OTP are required']); exit;
}

try {
    $api = new AbdmApi();

    // Verify OTP → enrol ABHA
    $enrolRes = $api->enrolByAadhaar($otp, $txnId, $mobile ?: '');

    if (!AbdmApi::wasSuccessful($enrolRes)) {
        echo json_encode(['success'=>false,'error'=>AbdmApi::extractError($enrolRes,'OTP verification failed')]); exit;
    }

    $abhaProfile = $enrolRes['ABHAProfile'] ?? $enrolRes['abhaProfile'] ?? [];
    $xToken      = $enrolRes['tokens']['token']   ?? ($enrolRes['token'] ?? '');
    $refreshTok  = $enrolRes['tokens']['refreshToken'] ?? '';
    $isNew       = (bool)($enrolRes['isNew'] ?? true);
    $newTxnId    = $enrolRes['txnId'] ?? $txnId;

    // Normalise profile
    $abha_number  = AbdmApi::formatAbhaNumber($abhaProfile['ABHANumber'] ?? '');
    $abha_address = $abhaProfile['preferredAbhaAddress'] ?? ($abhaProfile['phrAddress'] ?? '');
    $first_name   = $abhaProfile['firstName']  ?? '';
    $middle_name  = $abhaProfile['middleName'] ?? '';
    $last_name    = $abhaProfile['lastName']   ?? '';
    $full_name    = trim($first_name.' '.$middle_name.' '.$last_name);
    $gender       = $abhaProfile['gender']     ?? '';
    if ($gender === 'M') $gender = 'Male';
    elseif ($gender === 'F') $gender = 'Female';
    $dob_raw = $abhaProfile['dob'] ?? ($abhaProfile['birthdate'] ?? '');
    $mob_raw = preg_replace('/\D/','',$abhaProfile['mobile'] ?? '');
    $email   = $abhaProfile['email'] ?? '';

    $profile = [
        'abha_number'  => $abha_number,
        'abha_address' => $abha_address,
        'name'         => $full_name,
        'first_name'   => $first_name,
        'middle_name'  => $middle_name,
        'last_name'    => $last_name,
        'gender'       => $gender,
        'dob'          => $dob_raw,
        'mobile'       => $mob_raw,
        'email'        => $email,
        'photo'        => $abhaProfile['profilePhoto'] ?? ($abhaProfile['photo'] ?? ''),
    ];

    // Fetch ABHA address suggestions if this is a brand new ABHA (isNew = true)
    $suggestions = [];
    if ($isNew && $xToken && $newTxnId) {
        try {
            $suggestRes  = $api->getEnrollmentAbhaAddressSuggestions($newTxnId, $xToken);
            $suggestions = $suggestRes['abhaAddressList'] ?? ($suggestRes['phrAddressList'] ?? []);
        } catch (Exception $e) {
            // suggestions are optional — don't fail
        }
    }

    echo json_encode([
        'success'     => true,
        'txnId'       => $newTxnId,
        'xToken'      => $xToken,
        'is_new_abha' => $isNew,
        'profile'     => $profile,
        'suggestions' => $suggestions,
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
