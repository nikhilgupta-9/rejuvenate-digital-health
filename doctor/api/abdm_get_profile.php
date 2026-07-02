<?php
/**
 * ABDM Find Patient — Step 4: Fetch profile using X-token.
 *
 * GET (no body — reads X-token from session)
 * Returns: { "success": true, "profile": { ABHANumber, name, gender, dob, mobile,
 *             email, abhaAddress, photo, verificationStatus } }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: GET https://abhasbx.abdm.gov.in/abha/api/v3/profile/account
 *   Headers: X-token: Bearer {xToken}, REQUEST-ID, TIMESTAMP
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';

/* ── Auth check ── */
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

/* ── Read X-token from session ── */
$xToken = $_SESSION['abdm_patient_xtoken'] ?? '';
if (!$xToken) {
    echo json_encode(['success' => false, 'error' => 'No patient session token. Please complete OTP verification first.']);
    exit;
}

try {
    $accessToken = abdm_get_access_token();

    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/account';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'X-token'       => 'Bearer ' . $xToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];

    abdm_log('Fetching patient ABHA profile');
    [$res, $http] = abdm_curl('GET', $url, $headers, null, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'Failed to fetch ABHA profile');
        abdm_log('Profile fetch failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    /* Normalise profile fields (ABDM may use different key names) */
    $profile = [
        'ABHANumber'         => $res['ABHANumber']         ?? ($res['abhaNumber']         ?? ''),
        'name'               => $res['name']               ?? ($res['fullName']            ?? ''),
        'gender'             => $res['gender']             ?? '',
        'dob'                => $res['dob']                ?? ($res['birthdate']           ?? ''),
        'mobile'             => preg_replace('/\D/', '', $res['mobile'] ?? ''),
        'email'              => $res['email']              ?? '',
        'abhaAddress'        => $res['preferredAbhaAddress'] ?? ($res['abhaAddress']       ?? ($res['phrAddress'] ?? '')),
        'photo'              => $res['profilePhoto']       ?? ($res['photo']               ?? ''),
        'verificationStatus' => $res['verificationStatus'] ?? ($res['kycVerified'] ? 'VERIFIED' : 'UNVERIFIED'),
        'stateCode'          => $res['stateCode']          ?? '',
        'districtName'       => $res['districtName']       ?? '',
        'address'            => $res['address']            ?? '',
    ];

    abdm_log('Profile fetched', ['ABHANumber' => $profile['ABHANumber'], 'name' => $profile['name']]);

    /* Cache profile in session so save endpoint can read it */
    $_SESSION['abdm_fetched_profile'] = $profile;

    echo json_encode(['success' => true, 'profile' => $profile]);

} catch (Exception $e) {
    abdm_log('abdm_get_profile exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
