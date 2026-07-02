<?php
/**
 * ABDM Find Patient — Step 3: Verify OTP and obtain X-token.
 *
 * POST body (JSON): { "otp": "123456" }
 * Returns: { "success": true, "xToken": "..." }
 *    or:   { "success": false, "error": "..." }
 *
 * ABDM: POST https://abhasbx.abdm.gov.in/abha/api/v3/profile/login/verify
 *   Body: { "scope":["abha-login","mobile-verify"],
 *            "authData":{"authMethods":["otp"],
 *              "otp":{"txnId":"...","otpValue":"<RSA_encrypted_otp>"}} }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/abdm_rsa.php';
require_once __DIR__ . '/abdm_session.php';
require_once __DIR__ . '/abdm_get_cert.php';

/* ── Auth check ── */
if (empty($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ── Input ── */
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$otp   = preg_replace('/\D/', '', trim($input['otp'] ?? ''));
$txnId = $_SESSION['abdm_login_txnId'] ?? '';

if (!$otp || strlen($otp) < 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
    exit;
}
if (!$txnId) {
    echo json_encode(['success' => false, 'error' => 'Session expired. Please restart the search.']);
    exit;
}

try {
    $accessToken  = abdm_get_access_token();
    $publicKeyPem = abdm_get_public_key();

    /* RSA-OAEP encrypt OTP */
    $encryptedOtp = abdm_rsa_encrypt($otp, $publicKeyPem);

    $url     = 'https://abhasbx.abdm.gov.in/abha/api/v3/profile/login/verify';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'REQUEST-ID'    => abdm_uuid(),
        'TIMESTAMP'     => abdm_timestamp(),
        'X-CM-ID'       => ABDM_X_CM_ID_VALUE,
    ];
    $body = [
        'scope'    => ['abha-login', 'mobile-verify'],
        'authData' => [
            'authMethods' => ['otp'],
            'otp'         => [
                'txnId'    => $txnId,
                'otpValue' => $encryptedOtp,
            ],
        ],
    ];

    abdm_log('Verifying login OTP', ['txnId' => $txnId]);
    [$res, $http] = abdm_curl('POST', $url, $headers, $body, defined('ABDM_SSL_VERIFY') ? ABDM_SSL_VERIFY : true);

    if ($http < 200 || $http >= 300) {
        $err = abdm_extract_error($res, $http, 'OTP verification failed');
        abdm_log('Login OTP verify failed', ['http' => $http, 'response' => $res]);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    /* Extract X-token (various response shapes ABDM may return) */
    $xToken = $res['tokens']['token']   ?? ($res['token']  ?? ($res['xToken'] ?? ''));
    $newTxnId = $res['txnId']           ?? $txnId;

    if (!$xToken) {
        abdm_log('No X-token in login verify response', ['response' => $res]);
        echo json_encode(['success' => false, 'error' => 'OTP verified but no session token returned. Please retry.']);
        exit;
    }

    $_SESSION['abdm_patient_xtoken'] = $xToken;
    $_SESSION['abdm_login_txnId']    = $newTxnId;

    abdm_log('Login OTP verified — X-token obtained');
    echo json_encode([
        'success' => true,
        'xToken'  => $xToken,
        'txnId'   => $newTxnId,
    ]);

} catch (Exception $e) {
    abdm_log('abdm_verify_login_otp exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
