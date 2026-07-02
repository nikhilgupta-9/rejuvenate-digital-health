<?php
/**
 * ABDM Enrollment Step 1 — Send OTP to Aadhaar-linked mobile.
 *
 * POST body (JSON): { "aadhaar": "123456789012", "mobile": "9876543210" }
 * Returns:          { "success": true, "txnId": "...", "message": "..." }
 *            or:    { "success": false, "error": "..." }
 *
 * ABDM: POST https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/request/otp
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';
require_once __DIR__ . '/abdm_get_cert.php';

/* ── Auth check ── */
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated. Please log in again.']);
    exit;
}

/* ── Accept only POST ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Parse input ── */
$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$aadhaar = preg_replace('/\D/', '', trim($input['aadhaar'] ?? ''));
$mobile  = preg_replace('/\D/', '', trim($input['mobile']  ?? ''));

/* ── Validate Aadhaar ── */
$aadhaarCheck = abdm_validate_aadhaar($aadhaar);
if ($aadhaarCheck !== true) {
    echo json_encode(['success' => false, 'error' => $aadhaarCheck]);
    exit;
}

/* ── Validate mobile (optional at step 1 — can be entered at step 2) ── */
if ($mobile && strlen($mobile) !== 10) {
    echo json_encode(['success' => false, 'error' => 'Mobile must be 10 digits']);
    exit;
}

try {
    /* ── Get access token + public key ── */
    $accessToken  = abdm_get_access_token();
    $publicKeyPem = abdm_get_public_key();

    /* ── RSA-encrypt Aadhaar (OAEP) ── */
    $encryptedAadhaar = abdm_rsa_encrypt($aadhaar, $publicKeyPem);

    /* ── Call ABDM ── */
    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/request/otp';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];
    $body = [
        'txnId'     => '',
        'scope'     => ['abha-enrol'],
        'loginHint' => 'aadhaar',
        'loginId'   => $encryptedAadhaar,
        'otpSystem' => 'aadhaar',
    ];

    abdm_log('Sending Aadhaar OTP request', ['doctor_id' => $_SESSION['doctor_id']]);
    [$res, $http] = abdm_curl('POST', $url, $headers, $body, true);

    /* ── Handle response ── */
    if ($http < 200 || $http >= 300 || empty($res['txnId'])) {
        $err = abdm_extract_error($res, $http, 'Failed to send OTP');
        abdm_log('Send OTP failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    /* ── Store session state ── */
    $_SESSION['abdm_txnId']  = $res['txnId'];
    $_SESSION['abdm_mobile'] = $mobile; // pre-fill for step 2

    abdm_log('OTP sent successfully', ['txnId' => $res['txnId']]);
    echo json_encode([
        'success' => true,
        'txnId'   => $res['txnId'],
        'message' => $res['message'] ?? 'OTP sent to Aadhaar-linked mobile number',
    ]);

} catch (Exception $e) {
    abdm_log('abdm_send_otp exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
