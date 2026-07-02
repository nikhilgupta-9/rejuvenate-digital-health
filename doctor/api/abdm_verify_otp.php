<?php
/**
 * ABDM Enrollment Step 2 — Verify Aadhaar OTP and create ABHA.
 *
 * POST body (JSON): { "otp": "123456", "mobile": "9876543210" }
 * Returns: { "success": true, "token": "...", "txnId": "...", "isNew": true,
 *             "ABHAProfile": { ... } }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: POST https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/byAadhaar
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Parse input ── */
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$otp    = preg_replace('/\D/', '', trim($input['otp']    ?? ''));
$mobile = preg_replace('/\D/', '', trim($input['mobile'] ?? $_SESSION['abdm_mobile'] ?? ''));
$txnId  = $_SESSION['abdm_txnId'] ?? '';

/* ── Validate ── */
if (!$otp || strlen($otp) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
    exit;
}
if (!$txnId) {
    echo json_encode(['success' => false, 'error' => 'Session expired. Please resend OTP.']);
    exit;
}
if (!$mobile || strlen($mobile) !== 10) {
    echo json_encode(['success' => false, 'error' => 'Valid 10-digit mobile number is required']);
    exit;
}

try {
    /* ── Get access token + public key ── */
    $accessToken  = abdm_get_access_token();
    $publicKeyPem = abdm_get_public_key();

    /* ── RSA-encrypt OTP (OAEP) ── */
    $encryptedOtp = abdm_rsa_encrypt($otp, $publicKeyPem);

    /* ── Call ABDM ── */
    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/byAadhaar';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];
    $body = [
        'authData' => [
            'authMethods' => ['otp'],
            'otp'         => [
                'txnId'    => $txnId,
                'otpValue' => $encryptedOtp,
                'mobile'   => $mobile, // plain text — ABDM stores this as communication mobile
            ],
        ],
        'consent' => [
            'code'    => 'abha-enrollment',
            'version' => '1.4',
        ],
    ];

    abdm_log('Verifying Aadhaar OTP', ['txnId' => $txnId, 'doctor_id' => $_SESSION['doctor_id']]);
    [$res, $http] = abdm_curl('POST', $url, $headers, $body, true);

    /* ── Handle response ── */
    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'OTP verification failed');
        abdm_log('Verify OTP failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    /* ── Extract token and profile ── */
    $xToken     = $res['tokens']['token']        ?? ($res['token'] ?? '');
    $newTxnId   = $res['txnId']                  ?? $txnId;
    $isNew      = (bool)($res['isNew']           ?? true);
    $abhaProfile= $res['ABHAProfile']            ?? ($res['abhaProfile'] ?? []);

    if (!$xToken) {
        abdm_log('No X-token in enrolByAadhaar response', ['response' => $res]);
        echo json_encode(['success' => false, 'error' => 'ABHA created but no session token returned. Please retry.']);
        exit;
    }

    /* ── Update session ── */
    $_SESSION['abdm_x_token'] = $xToken;
    $_SESSION['abdm_txnId']   = $newTxnId;

    abdm_log('OTP verified — ABHA ' . ($isNew ? 'created' : 'already exists'), [
        'isNew'       => $isNew,
        'txnId'       => $newTxnId,
        'abhaNumber'  => $abhaProfile['ABHANumber'] ?? '—',
    ]);

    echo json_encode([
        'success'     => true,
        'token'       => $xToken,
        'txnId'       => $newTxnId,
        'isNew'       => $isNew,
        'message'     => $res['message'] ?? ($isNew ? 'ABHA created successfully' : 'ABHA already exists'),
        'ABHAProfile' => $abhaProfile,
    ]);

} catch (Exception $e) {
    abdm_log('abdm_verify_otp exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
